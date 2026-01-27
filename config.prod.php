<?php
/**
 * Production Configuration
 * Loads configuration from .env file for security
 * 
 * ⚠️ IMPORTANT: This file should NOT be committed to version control
 * Make sure .env and config.prod.php are in .gitignore
 * 
 * 🔒 SECURITY: This file requires .env file - no hardcoded credentials allowed
 */

// Load environment variables from .env file
require_once __DIR__ . '/includes/env_loader.php';
$envLoaded = loadEnv(__DIR__ . '/.env');

// CRITICAL: Require .env file - fail if not present (no hardcoded fallbacks)
if (!$envLoaded) {
    // Log critical error server-side
    error_log("CRITICAL SECURITY ERROR: .env file not found at: " . __DIR__ . '/.env');
    error_log("Application cannot start without .env file. Please create .env file from .env.example");
    
    // Show generic error to user (no sensitive information)
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    die("Configuration error. Please contact the administrator.");
}

// Validate required environment variables are set
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$missingVars = [];

foreach ($requiredVars as $var) {
    if (!defined($var) || empty(constant($var))) {
        $missingVars[] = $var;
    }
}

// Fail if required variables are missing
if (!empty($missingVars)) {
    error_log("CRITICAL: Required environment variables not set: " . implode(', ', $missingVars));
    error_log("Please check your .env file and ensure all required variables are defined.");
    
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    die("Configuration error. Please contact the administrator.");
}

// Set default values for optional variables (only if not already defined)
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'production');
if (!defined('BASE_URL')) define('BASE_URL', 'https://smarttrack.bccbsis.com/trackingv2/trackingv2/');
if (!defined('PYTHON_ML_SERVER_URL')) define('PYTHON_ML_SERVER_URL', 'https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com');
if (!defined('DEBUG')) define('DEBUG', false);
if (!defined('SHOW_ERRORS')) define('SHOW_ERRORS', false);
?>
