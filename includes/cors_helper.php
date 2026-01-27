<?php
/**
 * CORS Helper - Secure CORS Configuration
 * 
 * 🔒 SECURITY: Restricts CORS to specific allowed origins instead of wildcard (*)
 * 
 * Usage:
 *   require_once __DIR__ . '/includes/cors_helper.php';
 *   setCORSHeaders();
 */

/**
 * Get allowed origins from environment or use default
 * 
 * @return array Array of allowed origin domains
 */
function getAllowedOrigins() {
    // Get from environment variable (comma-separated list)
    $allowedOriginsEnv = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : '';
    
    if (!empty($allowedOriginsEnv)) {
        return array_map('trim', explode(',', $allowedOriginsEnv));
    }
    
    // Default allowed origins (production)
    $defaultOrigins = [
        'https://smarttrack.bccbsis.com',
        'https://www.smarttrack.bccbsis.com',
        // Add mobile app origins if needed
        // 'https://your-mobile-app-domain.com'
    ];
    
    // In development, allow localhost
    $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
    if ($environment === 'development' || $environment === 'local') {
        $defaultOrigins[] = 'http://localhost';
        $defaultOrigins[] = 'http://localhost:8080';
        $defaultOrigins[] = 'http://127.0.0.1';
        $defaultOrigins[] = 'http://127.0.0.1:8080';
    }
    
    return $defaultOrigins;
}

/**
 * Set secure CORS headers
 * 
 * @param bool $allowCredentials Whether to allow credentials (cookies, auth headers)
 */
function setCORSHeaders($allowCredentials = false) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = getAllowedOrigins();
    
    // Check if origin is in allowed list
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else if (!empty($origin)) {
        // Origin not allowed - don't set CORS header (or set to null)
        // This prevents the request from accessing the resource
        header("Access-Control-Allow-Origin: null");
        return;
    } else {
        // No origin header (same-origin request) - allow it
        // Don't set CORS header for same-origin requests
        return;
    }
    
    // Set other CORS headers
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    
    if ($allowCredentials) {
        header('Access-Control-Allow-Credentials: true');
    }
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Legacy function for backward compatibility
 * Sets CORS with wildcard (INSECURE - use setCORSHeaders() instead)
 * 
 * @deprecated Use setCORSHeaders() instead
 */
function setCORSHeadersLegacy() {
    // Log warning about insecure CORS usage
    error_log("WARNING: Using insecure CORS wildcard (*) in " . $_SERVER['PHP_SELF']);
    
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}
?>

