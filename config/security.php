<?php
// Security Configuration
class Security {
    private static $instance = null;
    private $conn = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Database connection is lazy - only connect when needed
        // This allows Security class to be used even when DB constants aren't defined yet
    }
    
    // Lazy database connection - only connect when needed
    private function getConnection() {
        if ($this->conn === null) {
            // Check if database constants are defined
            if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
                // Database constants not defined yet - return null
                // This allows Security class to work for non-DB operations
                return null;
            }
            
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->conn->connect_error) {
                // Log detailed error server-side only
                error_log("Security class DB connection failed: " . $this->conn->connect_error);
                error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
                $this->logSecurityEvent('DB_CONNECTION_ERROR', $this->conn->connect_error);
                
                // Show generic error to user (no sensitive info)
                http_response_code(500);
                die("Database connection error. Please contact the administrator.");
            }
            $this->conn->set_charset("utf8mb4");
        }
        return $this->conn;
    }
    
    // SQL Injection Protection
    public function prepare($sql) {
        $conn = $this->getConnection();
        if ($conn === null) {
            throw new Exception("Database connection not available");
        }
        return $conn->prepare($sql);
    }
    
    public function query($sql) {
        $conn = $this->getConnection();
        if ($conn === null) {
            throw new Exception("Database connection not available");
        }
        return $conn->query($sql);
    }
    
    // Input Sanitization
    public function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function sanitizeInt($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public function sanitizeEmail($input) {
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }
    
    // Unified sanitization method with type parameter
    public function sanitizeInput($input, $type = 'string') {
        if ($input === null || $input === '') {
            return '';
        }
        
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
            case 'double':
                return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Comprehensive Input Validation
    public function validateInput($input, $type = 'string', $options = []) {
        if ($input === null || $input === '') {
            return isset($options['required']) && $options['required'] ? false : true;
        }
        
        $minLength = $options['min_length'] ?? null;
        $maxLength = $options['max_length'] ?? null;
        $pattern = $options['pattern'] ?? null;
        
        switch ($type) {
            case 'int':
            case 'integer':
                $value = filter_var($input, FILTER_VALIDATE_INT);
                if ($value === false) return false;
                if (isset($options['min']) && $value < $options['min']) return false;
                if (isset($options['max']) && $value > $options['max']) return false;
                return true;
                
            case 'float':
            case 'double':
                $value = filter_var($input, FILTER_VALIDATE_FLOAT);
                if ($value === false) return false;
                if (isset($options['min']) && $value < $options['min']) return false;
                if (isset($options['max']) && $value > $options['max']) return false;
                return true;
                
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
                
            case 'username':
                $pattern = $pattern ?? '/^[A-Za-z0-9_]{3,30}$/';
                return preg_match($pattern, $input) === 1;
                
            case 'phone':
                $pattern = $pattern ?? '/^09\d{9}$/';
                return preg_match($pattern, $input) === 1;
                
            case 'password':
                $minLength = $minLength ?? 8;
                return strlen($input) >= $minLength;
                
            case 'string':
            default:
                if ($minLength !== null && strlen($input) < $minLength) return false;
                if ($maxLength !== null && strlen($input) > $maxLength) return false;
                if ($pattern !== null && preg_match($pattern, $input) !== 1) return false;
                return true;
        }
    }
    
    // Get and sanitize GET parameter
    public function getGet($key, $type = 'string', $default = '') {
        $value = $_GET[$key] ?? $default;
        return $this->sanitizeInput($value, $type);
    }
    
    // Get and sanitize POST parameter
    public function getPost($key, $type = 'string', $default = '') {
        $value = $_POST[$key] ?? $default;
        return $this->sanitizeInput($value, $type);
    }
    
    // Get and sanitize REQUEST parameter
    public function getRequest($key, $type = 'string', $default = '') {
        $value = $_REQUEST[$key] ?? $default;
        return $this->sanitizeInput($value, $type);
    }
    
    // Validate and sanitize array of inputs
    public function sanitizeArray($inputs, $rules = []) {
        $sanitized = [];
        foreach ($inputs as $key => $value) {
            $type = $rules[$key]['type'] ?? 'string';
            $sanitized[$key] = $this->sanitizeInput($value, $type);
        }
        return $sanitized;
    }
    
    // XSS Protection - Enhanced
    public function escapeOutput($output) {
        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // SQL Injection Protection - Table/Column name validation
    public function validateTableName($tableName) {
        // Whitelist approach - only allow alphanumeric and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $tableName) === 1;
    }
    
    public function validateColumnName($columnName) {
        // Whitelist approach - only allow alphanumeric and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $columnName) === 1;
    }
    
    // CSRF Protection
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Rate Limiting
    public function checkRateLimit($action, $limit = 10, $window = 300) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = $action . '_' . $ip;
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = ['count' => 0, 'reset' => $now + $window];
        }
        
        if ($now > $_SESSION['rate_limit'][$key]['reset']) {
            $_SESSION['rate_limit'][$key] = ['count' => 0, 'reset' => $now + $window];
        }
        
        if ($_SESSION['rate_limit'][$key]['count'] >= $limit) {
            return false;
        }
        
        $_SESSION['rate_limit'][$key]['count']++;
        return true;
    }
    
    // HTTPS Enforcement (Production Only)
    public function enforceHTTPS() {
        // Only enforce in production environment
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        
        if ($environment === 'production') {
            // Check if HTTPS is not being used
            $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            
            if (!$isHTTPS) {
                // Redirect to HTTPS
                $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: $url", true, 301);
                exit();
            }
        }
    }
    
    // Security Headers
    public function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net cdnjs.cloudflare.com;');
        
        // HSTS Header (Strict-Transport-Security) - Production Only
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        if ($environment === 'production') {
            // HSTS: max-age=31536000 (1 year), includeSubDomains, preload
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    // Session Security
    // Note: Session ini settings must be set BEFORE session_start()
    // This method only regenerates the session ID for security
    public function secureSession() {
        // Only regenerate ID if session is active and headers haven't been sent
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
    }
    
    // Log Security Events
    public function logSecurityEvent($event, $details = '') {
        $log = date('Y-m-d H:i:s') . " - " . $event . " - " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - " . $details . "\n";
        $logFile = __DIR__ . '/../security.log';
        @file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
    }
}
?>
