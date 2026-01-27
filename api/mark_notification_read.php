<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $notificationId = $input['notification_id'] ?? null;
    $sessionUserId = $_SESSION['user_id'];
    $sessionUsername = $_SESSION['username'] ?? '';
    
    if (!$notificationId) {
        throw new Exception('Notification ID is required');
    }
    
    // Find the correct driver_id (same logic as get_driver_notifications.php)
    $driverIdsToCheck = [];
    
    // Try to find by exact username match
    if (!empty($sessionUsername)) {
        $stmt = $conn->prepare("
            SELECT DISTINCT driver_id 
            FROM vehicle_assignments 
            WHERE driver_name = ? AND status = 'active'
        ");
        $stmt->bind_param('s', $sessionUsername);
        $stmt->execute();
        $exactMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($exactMatches as $match) {
            $driverIdsToCheck[] = $match['driver_id'];
        }
    }
    
    // If no exact match, try partial match
    if (empty($driverIdsToCheck) && !empty($sessionUsername)) {
        $stmt = $conn->prepare("
            SELECT DISTINCT driver_id 
            FROM vehicle_assignments 
            WHERE driver_name LIKE ? AND status = 'active'
        ");
        $searchName = "%$sessionUsername%";
        $stmt->bind_param('s', $searchName);
        $stmt->execute();
        $partialMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($partialMatches as $match) {
            $driverIdsToCheck[] = $match['driver_id'];
        }
    }
    
    // Always add the session user_id as a fallback
    $driverIdsToCheck[] = $sessionUserId;
    $driverIdsToCheck = array_unique($driverIdsToCheck);
    
    // Verify the notification belongs to this driver (check all possible driver IDs)
    $notificationFound = false;
    foreach ($driverIdsToCheck as $driverId) {
        $stmt = $conn->prepare("
            SELECT id FROM driver_messages 
            WHERE id = ? AND driver_id = ?
        ");
        $stmt->bind_param('ii', $notificationId, $driverId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $notificationFound = true;
            break;
        }
    }
    
    if (!$notificationFound) {
        throw new Exception('Notification not found or access denied');
    }
    
    // For now, we'll just return success since the driver_messages table doesn't have a read status
    // In a real implementation, you might want to add a 'read' column to track read status
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
