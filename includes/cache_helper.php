<?php
/**
 * Simple File-Based Cache Helper
 * Provides basic caching functionality for frequently accessed data
 */

class CacheHelper {
    private $cacheDir;
    private $defaultTTL; // Time to live in seconds (default: 5 minutes)
    
    public function __construct($cacheDir = 'cache', $defaultTTL = 300) {
        $this->cacheDir = __DIR__ . '/../' . $cacheDir;
        $this->defaultTTL = $defaultTTL;
        
        // Create cache directory if it doesn't exist
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached data
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = @unserialize(file_get_contents($file));
        
        if ($data === false) {
            return null;
        }
        
        // Check if cache is expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'] ?? null;
    }
    
    /**
     * Set cached data
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int|null $ttl Time to live in seconds (null = use default)
     * @return bool Success
     */
    public function set($key, $value, $ttl = null) {
        $file = $this->getCacheFile($key);
        $ttl = $ttl ?? $this->defaultTTL;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    /**
     * Delete cached data
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Clear all cache
     * @return int Number of files deleted
     */
    public function clear() {
        $count = 0;
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Get cache file path
     * @param string $key Cache key
     * @return string File path
     */
    private function getCacheFile($key) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . md5($safeKey) . '.cache';
    }
    
    /**
     * Check if cache exists and is valid
     * @param string $key Cache key
     * @return bool
     */
    public function exists($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = @unserialize(file_get_contents($file));
        
        if ($data === false) {
            return false;
        }
        
        // Check if expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }
}

