<?php
/**
 * PHPUnit Bootstrap File
 * Sets up testing environment
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define testing environment
define('TESTING_MODE', true);
define('TEST_ENVIRONMENT', 'testing');

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load database configuration for testing
if (file_exists(__DIR__ . '/../db_connection.php')) {
    // Override with test database settings
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'test_smarttrack');
    define('DB_USER', getenv('DB_USER') ?: 'test_user');
    define('DB_PASS', getenv('DB_PASS') ?: 'test_password');
}

// Load required classes
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/cache_helper.php';
require_once __DIR__ . '/../includes/performance_helper.php';

// Set up test database
function setupTestDatabase()
{
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        // Create test database if it doesn't exist
        $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $conn->select_db(DB_NAME);
        
        return $conn;
    } catch (Exception $e) {
        echo "Failed to setup test database: " . $e->getMessage() . "\n";
        return null;
    }
}

// Clean up test database after tests
function cleanupTestDatabase()
{
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $conn->query("DROP DATABASE IF EXISTS " . DB_NAME);
        $conn->close();
    } catch (Exception $e) {
        echo "Failed to cleanup test database: " . $e->getMessage() . "\n";
    }
}

// Register shutdown function to clean up
register_shutdown_function('cleanupTestDatabase');

echo "PHPUnit Bootstrap loaded. Test environment ready.\n";

