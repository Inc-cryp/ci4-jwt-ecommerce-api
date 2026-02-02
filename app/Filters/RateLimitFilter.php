<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\RedisCache;

class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if rate limiting is enabled
        if (getenv('ratelimit.enabled') !== 'true') {
            return $request;
        }
        
        $redis = new RedisCache();
        
        if (!$redis->isConnected()) {
            // If Redis is not available, allow request
            return $request;
        }
        
        // Get client identifier (IP address)
        $clientIp = $request->getIPAddress();
        $route = $request->getUri()->getPath();
        
        // Create unique key for this client and route
        $key = "ratelimit:{$clientIp}:{$route}";
        
        // Get rate limit settings
        $maxRequests = (int) getenv('ratelimit.requests') ?: 60;
        $period = (int) getenv('ratelimit.period') ?: 60; // in seconds
        
        // Get current request count
        $currentCount = $redis->get($key);
        
        if ($currentCount === null) {
            // First request, set counter
            $redis->set($key, 1, $period);
            $remaining = $maxRequests - 1;
        } else {
            // Increment counter
            $currentCount = (int) $currentCount;
            
            if ($currentCount >= $maxRequests) {
                // Rate limit exceeded
                $ttl = $redis->ttl($key);
                
                return service('response')
                    ->setJSON([
                        'status' => false,
                        'message' => 'Rate limit exceeded. Please try again later.',
                        'retry_after' => $ttl
                    ])
                    ->setStatusCode(429)
                    ->setHeader('X-RateLimit-Limit', $maxRequests)
                    ->setHeader('X-RateLimit-Remaining', 0)
                    ->setHeader('X-RateLimit-Reset', time() + $ttl)
                    ->setHeader('Retry-After', $ttl);
            }
            
            $redis->increment($key);
            $remaining = $maxRequests - $currentCount - 1;
        }
        
        // Add rate limit headers to request
        $request->rateLimit = [
            'limit' => $maxRequests,
            'remaining' => $remaining,
            'reset' => time() + $redis->ttl($key)
        ];
        
        return $request;
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add rate limit headers to response
        if (isset($request->rateLimit)) {
            $response->setHeader('X-RateLimit-Limit', $request->rateLimit['limit']);
            $response->setHeader('X-RateLimit-Remaining', $request->rateLimit['remaining']);
            $response->setHeader('X-RateLimit-Reset', $request->rateLimit['reset']);
        }
        
        return $response;
    }
}