<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connection.php';

// Only allow authenticated dispatchers
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $alertId = intval($input['alert_id']);
    
    if ($alertId <= 0) {
        throw new Exception('Invalid alert ID');
    }
    
    // Update alert to resolved
    $stmt = $conn->prepare("UPDATE alerts SET resolved = 1, resolved_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $alertId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Alert resolved successfully'
        ]);
    } else {
        throw new Exception('Failed to resolve alert');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

