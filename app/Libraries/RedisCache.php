<?php

namespace App\Libraries;

use Predis\Client;

class RedisCache
{
    private $redis;
    
    public function __construct()
    {
        try {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => getenv('redis.host') ?? '127.0.0.1',
                'port'   => getenv('redis.port') ?: 6379,
                'password' => (getenv('redis.password') !== false && getenv('redis.password') !== '') ? getenv('redis.password') : null,
                'database' => getenv('redis.database') ?: 0,
            ]);
        } catch (\Exception $e) {
            error_log('Redis Connection Error: ' . $e->getMessage());
            $this->redis = null;
        }
    }
    
    /**
     * Check if Redis is connected
     */
    public function isConnected()
    {
        return $this->redis !== null;
    }
    
    /**
     * Get value from cache
     */
    public function get($key)
    {
        if (!$this->isConnected()) {
            return null;
        }
        
        try {
            if ($this->redis === null) {
                return null;
            }
            $value = $this->redis->get($key);
            if ($value === null) {
                // Cache miss
                return null;
            }
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Redis Get Error: JSON decode failed for key "' . $key . '": ' . json_last_error_msg());
                return null;
            }
            return $decoded;
        } catch (\Exception $e) {
            error_log('Redis Get Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = 3600)
    {
        if (!$this->isConnected() || $this->redis === null) {
            return false;
        }
        
        try {
            $jsonValue = json_encode($value);
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $jsonValue);
            } else {
                return $this->redis->set($key, $jsonValue);
            }
        } catch (\Exception $e) {
            error_log('Redis Set Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete key from cache
     */
    public function delete($key)
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            if ($this->redis === null) {
                return false;
            }
            return $this->redis->del([$key]);
        } catch (\Exception $e) {
            error_log('Redis Delete Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if key exists
     */
    public function exists($key)
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            if ($this->redis === null) {
                return false;
            }
            return (bool) $this->redis->exists($key);
        } catch (\Exception $e) {
            error_log('Redis Exists Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment value
     * Note: If the key does not exist, it will be set to 0 before incrementing.
     * If the key contains a value of the wrong type (not an integer), this will fail.
     */
    public function increment($key, $amount = 1)
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            if ($this->redis === null) {
                return false;
            }
            // Check if key exists and is an integer
            $current = $this->redis->get($key);
            if ($current !== null && !is_numeric($current)) {
                error_log('Redis Increment Error: Value for key "' . $key . '" is not an integer.');
                return false;
            }
            return $this->redis->incrby($key, $amount);
        } catch (\Exception $e) {
            error_log('Redis Increment Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set expiration time
     */
    public function expire($key, $ttl)
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            if ($this->redis === null) {
                return false;
            }
            return $this->redis->expire($key, $ttl);
        } catch (\Exception $e) {
            log_message('error', 'Redis Expire Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get time to live (TTL) for a key.
     *
     * @param string $key
     * @return int Returns TTL in seconds, -2 if the key does not exist, -1 if the key exists but has no associated expire.
     */
    public function ttl($key)
    {
        if (!$this->isConnected()) {
            return -1;
        }
        try {
            if ($this->redis === null) {
                return -1;
            }
            // Returns TTL in seconds, -2 if the key does not exist, -1 if the key exists but has no associated expire.
            return $this->redis->ttl($key);
        } catch (\Exception $e) {
            error_log('Redis TTL Error: ' . $e->getMessage());
            log_message('error', 'Redis TTL Error: ' . $e->getMessage());
            return -1;
        }
    }
    
    /**
     * Flush all cache
     */
    public function flush()
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            if ($this->redis === null) {
                return false;
            }
            return $this->redis->flushdb();
        } catch (\Exception $e) {
            log_message('error', 'Redis Flush Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get keys by pattern
     *
     * WARNING: This command may be slow on large datasets and is not recommended for production use.
     *
     * @param string $pattern
     * @return array
     */
    public function keys($pattern = '*')
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            if ($this->redis === null) {
                return [];
            }
            return $this->redis->keys($pattern);
        } catch (\Exception $e) {
            log_message('error', 'Redis Keys Error: ' . $e->getMessage());
            return [];
        }
    }
}