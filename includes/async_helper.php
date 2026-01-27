<?php
/**
 * Async Operations Helper
 * Provides utilities for optimizing blocking operations
 */

class AsyncHelper {
    /**
     * Execute non-blocking operations
     * For PHP, we optimize by ensuring operations don't block unnecessarily
     */
    
    /**
     * Optimize database queries to prevent blocking
     * @param PDO $pdo Database connection
     */
    public static function optimizeDatabaseConnection($pdo) {
        // Set connection timeout to prevent long blocking
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        // Disable persistent connections to prevent connection leaks
        $pdo->setAttribute(PDO::ATTR_PERSISTENT, false);
        
        // Set fetch mode for better performance
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Enable query caching if available
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }
    
    /**
     * Process data in chunks to prevent memory issues
     * @param array $data Data to process
     * @param callable $callback Processing callback
     * @param int $chunkSize Chunk size (default: 100)
     */
    public static function processInChunks($data, $callback, $chunkSize = 100) {
        $chunks = array_chunk($data, $chunkSize);
        
        foreach ($chunks as $chunk) {
            $callback($chunk);
            
            // Free memory after each chunk
            unset($chunk);
            
            // Force garbage collection if needed
            if (memory_get_usage(true) > 50 * 1024 * 1024) { // 50MB
                gc_collect_cycles();
            }
        }
    }
    
    /**
     * Optimize file operations to prevent blocking
     * @param string $filePath File path
     * @param callable $operation File operation callback
     * @return mixed Operation result
     */
    public static function optimizeFileOperation($filePath, $operation) {
        // Use file locking to prevent blocking
        $fp = fopen($filePath, 'r+');
        
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            try {
                $result = $operation($fp);
                flock($fp, LOCK_UN);
                return $result;
            } finally {
                fclose($fp);
            }
        } else {
            throw new Exception("File is locked by another process");
        }
    }
}

