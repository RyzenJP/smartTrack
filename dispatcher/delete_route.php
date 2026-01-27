<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    try {
        $route_id = $_POST['route_id'];
        
        // Soft delete - mark as deleted instead of hard delete
        $stmt = $conn->prepare("UPDATE routes SET is_deleted = 1, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $route_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'Route deleted successfully!'
            ];
        } else {
            throw new Exception("Failed to delete route: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Error: ' . $e->getMessage()
        ];
    }
}

header("Location: active-routes.php");
exit();
?>