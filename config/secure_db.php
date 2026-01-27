<?php
// Secure Database Connection with SQL Injection Protection
require_once 'security.php';

class SecureDB {
    private $conn;
    private $security;
    
    public function __construct() {
        $this->security = Security::getInstance();
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            // Log detailed error server-side only
            error_log("SecureDB connection failed: " . $this->conn->connect_error);
            error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
            $this->security->logSecurityEvent('DB_CONNECTION_ERROR', $this->conn->connect_error);
            
            // Show generic error to user (no sensitive info)
            http_response_code(500);
            die("Database connection error. Please contact the administrator.");
        }
        
        $this->conn->set_charset("utf8mb4");
    }
    
    // Secure Query with Prepared Statements
    public function secureQuery($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->security->logSecurityEvent('SQL_PREPARE_ERROR', $this->conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Default to string type
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            $this->security->logSecurityEvent('SQL_EXECUTE_ERROR', $stmt->error);
            return false;
        }
        
        return $stmt;
    }
    
    // Secure Select
    public function select($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        if (!$stmt) return false;
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }
    
    // Secure Insert
    public function insert($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        if (!$stmt) return false;
        
        $insertId = $this->conn->insert_id;
        $stmt->close();
        return $insertId;
    }
    
    // Secure Update
    public function update($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        if (!$stmt) return false;
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
    
    // Secure Delete
    public function delete($sql, $params = []) {
        return $this->update($sql, $params);
    }
    
    // Get single row
    public function selectOne($sql, $params = []) {
        $data = $this->select($sql, $params);
        return $data ? $data[0] : null;
    }
    
    // Count rows
    public function count($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        if (!$stmt) return 0;
        
        $result = $stmt->get_result();
        $count = $result->num_rows;
        $stmt->close();
        return $count;
    }
    
    // Transaction support
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollback() {
        return $this->conn->rollback();
    }
    
    public function close() {
        $this->conn->close();
    }
}

// Global instance
$secureDB = new SecureDB();
?>
