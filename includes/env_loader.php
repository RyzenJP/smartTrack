<?php
/**
 * Environment Variable Loader
 * Simple .env file parser for PHP
 * 
 * Usage:
 * require_once 'includes/env_loader.php';
 * loadEnv(__DIR__ . '/../.env');
 */

function loadEnv($envFile) {
    if (!file_exists($envFile)) {
        // If .env doesn't exist, try .env.example
        $exampleFile = str_replace('.env', '.env.example', $envFile);
        if (file_exists($exampleFile)) {
            error_log("Warning: .env file not found. Using .env.example as template. Please create .env file.");
            return false;
        }
        error_log("Warning: .env file not found at: " . $envFile);
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Only set if not already defined
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
    
    return true;
}

