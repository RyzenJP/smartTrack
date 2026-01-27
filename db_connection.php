<?php
/**
 * Database Connection (Local Development)
 * Secure error handling - does not expose sensitive information
 */

// Load environment variables if available
if (file_exists(__DIR__ . '/includes/env_loader.php')) {
    require_once __DIR__ . '/includes/env_loader.php';
    loadEnv(__DIR__ . '/.env');
}

// Fallback to default local development values
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'trackingv2');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection with secure error handling
if ($conn->connect_error) {
    // Log detailed error server-side only
    error_log("Database connection failed: " . $conn->connect_error);
    error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
    
    // Show generic error to user (no sensitive info)
    http_response_code(500);
    die("Database connection error. Please contact the administrator.");
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>
