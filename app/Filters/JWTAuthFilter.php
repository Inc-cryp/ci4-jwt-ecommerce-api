<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\JWTHandler;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $jwt = new JWTHandler();
        $token = $jwt->getTokenFromHeader();
        
        if (!$token) {
            return service('response')
                ->setJSON([
                    'status' => false,
                    'message' => 'Token not provided'
                ])
                ->setStatusCode(401);
        }
        
        $validated = $jwt->validateToken($token);
        
        if (!$validated['success']) {
            return service('response')
                ->setJSON([
                    'status' => false,
                    'message' => 'Invalid or expired token',
                    'error' => $validated['message']
                ])
                ->setStatusCode(401);
        }
        
        // Store user data in request for later use
        $request->user = $validated['data'];
        
        return $request;
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}