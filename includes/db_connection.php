<?php
/**
 * Database Connection (Includes)
 * Secure error handling - does not expose sensitive information
 */

// Load environment configuration (path-safe)
require_once __DIR__ . '/../config.php';

// Load environment variables if available
if (file_exists(__DIR__ . '/env_loader.php')) {
    require_once __DIR__ . '/env_loader.php';
    loadEnv(__DIR__ . '/../.env');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // Log detailed error server-side only
    error_log("Includes DB connection failed: " . $conn->connect_error);
    error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
    
    // Show generic error to user (no sensitive info)
    http_response_code(500);
    die("Database connection error. Please contact the administrator.");
}

// Ensure utf8mb4
if (function_exists('mysqli_set_charset')) {
    mysqli_set_charset($conn, 'utf8mb4');
}
// Use prepared statement for consistency (SET command but best practice)
$charset_stmt = $conn->prepare("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
if ($charset_stmt) {
    $charset_stmt->execute();
    $charset_stmt->close();
}
?>