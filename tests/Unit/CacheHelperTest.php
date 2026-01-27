<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Cache Helper Unit Tests
 * Tests for the CacheHelper class
 */
class CacheHelperTest extends TestCase
{
    private $cache;
    private $testCacheDir = 'tests/cache_test';

    protected function setUp(): void
    {
        $this->cache = new \CacheHelper($this->testCacheDir, 60);
        
        // Ensure test cache directory exists
        if (!file_exists($this->testCacheDir)) {
            mkdir($this->testCacheDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test cache directory
        $this->cache->clear();
        if (file_exists($this->testCacheDir)) {
            rmdir($this->testCacheDir);
        }
    }

    public function testCacheSetAndGet()
    {
        $key = 'test_key';
        $value = ['data' => 'test_value'];
        
        $this->cache->set($key, $value);
        $retrieved = $this->cache->get($key);
        
        $this->assertEquals($value, $retrieved);
    }

    public function testCacheGetNonExistent()
    {
        $retrieved = $this->cache->get('non_existent_key');
        $this->assertNull($retrieved);
    }

    public function testCacheDelete()
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->exists($key));
        
        $this->cache->delete($key);
        $this->assertFalse($this->cache->exists($key));
    }

    public function testCacheClear()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $cleared = $this->cache->clear();
        $this->assertGreaterThanOrEqual(2, $cleared);
        
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testCacheExpiration()
    {
        $key = 'expiring_key';
        $value = 'expiring_value';
        
        // Set with 1 second TTL
        $this->cache->set($key, $value, 1);
        $this->assertEquals($value, $this->cache->get($key));
        
        // Wait for expiration
        sleep(2);
        $this->assertNull($this->cache->get($key));
    }

    public function testCacheExists()
    {
        $key = 'test_key';
        
        $this->assertFalse($this->cache->exists($key));
        
        $this->cache->set($key, 'test_value');
        $this->assertTrue($this->cache->exists($key));
    }

    public function testCacheWithComplexData()
    {
        $key = 'complex_data';
        $value = [
            'string' => 'test',
            'number' => 123,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value']
        ];
        
        $this->cache->set($key, $value);
        $retrieved = $this->cache->get($key);
        
        $this->assertEquals($value, $retrieved);
    }
}

