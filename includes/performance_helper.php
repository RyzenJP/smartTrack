<?php
/**
 * Performance Helper
 * Provides performance monitoring and optimization utilities
 */

class PerformanceHelper {
    private static $startTime;
    private static $memoryStart;
    private static $queries = [];
    
    /**
     * Start performance monitoring
     */
    public static function start() {
        self::$startTime = microtime(true);
        self::$memoryStart = memory_get_usage(true);
    }
    
    /**
     * Get execution time
     * @return float Execution time in seconds
     */
    public static function getExecutionTime() {
        if (self::$startTime === null) {
            return 0;
        }
        return microtime(true) - self::$startTime;
    }
    
    /**
     * Get memory usage
     * @return array Memory usage information
     */
    public static function getMemoryUsage() {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'start' => self::$memoryStart ?? 0,
            'used' => memory_get_usage(true) - (self::$memoryStart ?? 0)
        ];
    }
    
    /**
     * Log query execution time
     * @param string $query Query description
     * @param float $time Execution time
     */
    public static function logQuery($query, $time) {
        self::$queries[] = [
            'query' => $query,
            'time' => $time,
            'memory' => memory_get_usage(true)
        ];
    }
    
    /**
     * Get query performance summary
     * @return array Query performance data
     */
    public static function getQuerySummary() {
        return self::$queries;
    }
    
    /**
     * Clean up resources to prevent memory leaks
     */
    public static function cleanup() {
        // Clear query log
        self::$queries = [];
        
        // Force garbage collection if memory usage is high
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = self::parseMemoryLimit($memoryLimit);
        
        if ($memoryLimitBytes > 0 && $memoryUsage > ($memoryLimitBytes * 0.8)) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Parse memory limit string to bytes
     * @param string $limit Memory limit string (e.g., "128M")
     * @return int Bytes
     */
    private static function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int)$limit;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Check for potential memory leaks
     * @return array Memory leak warnings
     */
    public static function checkMemoryLeaks() {
        $warnings = [];
        $memory = self::getMemoryUsage();
        
        // Warn if memory usage is high
        if ($memory['used'] > 50 * 1024 * 1024) { // 50MB
            $warnings[] = "High memory usage detected: " . round($memory['used'] / 1024 / 1024, 2) . "MB";
        }
        
        // Warn if too many queries
        if (count(self::$queries) > 100) {
            $warnings[] = "Large number of queries detected: " . count(self::$queries);
        }
        
        return $warnings;
    }
}

