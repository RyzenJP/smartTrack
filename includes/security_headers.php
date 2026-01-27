<?php
// Security Headers and Session Configuration
try {
    require_once __DIR__ . '/../config/security.php';
    
    // Get security instance first to configure session settings
    $security = Security::getInstance();
    
    // Configure session settings BEFORE starting session
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        session_start();
    }

    // Set security headers (only if headers haven't been sent)
    if (!headers_sent()) {
        $security->enforceHTTPS(); // Enforce HTTPS in production
        $security->setSecurityHeaders(); // Includes HSTS header
        $security->secureSession(); // This will only regenerate ID now
    }

    // CSRF Token for forms (only define if not already defined)
    if (!defined('CSRF_TOKEN')) {
        define('CSRF_TOKEN', $security->generateCSRFToken());
    }

    // Rate limiting check
    if (!$security->checkRateLimit('page_access', 30, 300)) {
        http_response_code(429);
        die('Too many requests. Please try again later.');
    }
} catch (Exception $e) {
    // Log error but don't expose it to user
    error_log("Security headers error: " . $e->getMessage());
    // Continue execution - don't break the page
    if (!defined('CSRF_TOKEN')) {
        // Fallback CSRF token if Security class fails
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        define('CSRF_TOKEN', $_SESSION['csrf_token']);
    }
}
?>
