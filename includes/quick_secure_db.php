<?php
// Quick Secure Database Wrapper
class QuickSecureDB {
    private $conn;
    
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            // Log detailed error server-side only
            error_log("QuickSecureDB connection failed: " . $this->conn->connect_error);
            error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
            
            // Show generic error to user (no sensitive info)
            http_response_code(500);
            die("Database connection error. Please contact the administrator.");
        }
        $this->conn->set_charset("utf8mb4");
    }
    
    // Secure query with prepared statements
    public function secureQuery($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        
        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    // Sanitize input
    public function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
    }
    
    public function close() {
        $this->conn->close();
    }
}
?>