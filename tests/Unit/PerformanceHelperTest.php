<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Performance Helper Unit Tests
 * Tests for the PerformanceHelper class
 */
class PerformanceHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static properties
        \PerformanceHelper::cleanup();
    }

    protected function tearDown(): void
    {
        \PerformanceHelper::cleanup();
    }

    public function testStartPerformanceMonitoring()
    {
        \PerformanceHelper::start();
        $time = \PerformanceHelper::getExecutionTime();
        
        $this->assertGreaterThanOrEqual(0, $time);
        $this->assertLessThan(1, $time); // Should be very fast
    }

    public function testGetExecutionTime()
    {
        \PerformanceHelper::start();
        usleep(100000); // Sleep for 0.1 seconds
        $time = \PerformanceHelper::getExecutionTime();
        
        $this->assertGreaterThanOrEqual(0.09, $time);
        $this->assertLessThan(0.2, $time);
    }

    public function testGetExecutionTimeWithoutStart()
    {
        // Should return 0 if start() wasn't called
        $time = \PerformanceHelper::getExecutionTime();
        $this->assertEquals(0, $time);
    }

    public function testGetMemoryUsage()
    {
        \PerformanceHelper::start();
        $memory = \PerformanceHelper::getMemoryUsage();
        
        $this->assertIsArray($memory);
        $this->assertArrayHasKey('current', $memory);
        $this->assertArrayHasKey('peak', $memory);
        $this->assertArrayHasKey('start', $memory);
        $this->assertArrayHasKey('used', $memory);
        
        $this->assertGreaterThan(0, $memory['current']);
        $this->assertGreaterThan(0, $memory['peak']);
        $this->assertGreaterThanOrEqual(0, $memory['used']);
    }

    public function testLogQuery()
    {
        \PerformanceHelper::logQuery('SELECT * FROM users', 0.05);
        \PerformanceHelper::logQuery('SELECT * FROM vehicles', 0.03);
        
        $summary = \PerformanceHelper::getQuerySummary();
        
        $this->assertCount(2, $summary);
        $this->assertEquals('SELECT * FROM users', $summary[0]['query']);
        $this->assertEquals(0.05, $summary[0]['time']);
    }

    public function testGetQuerySummary()
    {
        \PerformanceHelper::logQuery('SELECT 1', 0.01);
        $summary = \PerformanceHelper::getQuerySummary();
        
        $this->assertIsArray($summary);
        $this->assertCount(1, $summary);
        $this->assertArrayHasKey('query', $summary[0]);
        $this->assertArrayHasKey('time', $summary[0]);
        $this->assertArrayHasKey('memory', $summary[0]);
    }

    public function testCleanup()
    {
        \PerformanceHelper::logQuery('SELECT 1', 0.01);
        \PerformanceHelper::cleanup();
        
        $summary = \PerformanceHelper::getQuerySummary();
        $this->assertEmpty($summary);
    }

    public function testCheckMemoryLeaks()
    {
        \PerformanceHelper::start();
        $warnings = \PerformanceHelper::checkMemoryLeaks();
        
        $this->assertIsArray($warnings);
        // Should not have warnings for normal usage
        // (warnings only appear if memory > 50MB or queries > 100)
    }

    public function testCheckMemoryLeaksWithManyQueries()
    {
        \PerformanceHelper::start();
        
        // Log many queries to trigger warning
        for ($i = 0; $i < 101; $i++) {
            \PerformanceHelper::logQuery("SELECT $i", 0.001);
        }
        
        $warnings = \PerformanceHelper::checkMemoryLeaks();
        
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Large number of queries', $warnings[0]);
    }
}

