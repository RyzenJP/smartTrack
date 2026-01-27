<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

session_start();

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    error_log("Unauthorized access - Session: " . json_encode($_SESSION));
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized',
        'debug' => [
            'session_data' => $_SESSION,
            'has_user_id' => isset($_SESSION['user_id']),
            'has_role' => isset($_SESSION['role']),
            'role_value' => $_SESSION['role'] ?? 'not_set'
        ]
    ]);
    exit;
}

try {
    $sessionUserId = $_SESSION['user_id'];
    $sessionUsername = $_SESSION['username'] ?? '';
    
    // Debug: Log the session information
    error_log("Getting notifications for session user ID: $sessionUserId, username: $sessionUsername");
    error_log("Session data: " . json_encode($_SESSION));
    
    // Get the driver_id from vehicle_assignments by matching username
    $driverIdsToCheck = [];
    
    // First, try to find by exact username match
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
    
    // If still no matches, try by user_id (in case they match)
    if (empty($driverIdsToCheck)) {
        $stmt = $conn->prepare("
            SELECT DISTINCT driver_id 
            FROM vehicle_assignments 
            WHERE driver_id = ? AND status = 'active'
        ");
        $stmt->bind_param('i', $sessionUserId);
        $stmt->execute();
        $idMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($idMatches as $match) {
            $driverIdsToCheck[] = $match['driver_id'];
        }
    }
    
    // Always add the session user_id as a fallback
    $driverIdsToCheck[] = $sessionUserId;
    $driverIdsToCheck = array_unique($driverIdsToCheck);
    
    error_log("Driver IDs to check: " . json_encode($driverIdsToCheck));
    
    // Collect all notifications for all possible driver_ids
    $allNotifications = [];
    
    foreach ($driverIdsToCheck as $driverId) {
        $stmt = $conn->prepare("
            SELECT id, message_text, sent_at
            FROM driver_messages 
            WHERE driver_id = ? 
            AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sent_at DESC
        ");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $allNotifications = array_merge($allNotifications, $notifications);
    }
    
    // Remove duplicates and sort by date
    $uniqueNotifications = [];
    $seenIds = [];
    foreach ($allNotifications as $notification) {
        if (!in_array($notification['id'], $seenIds)) {
            $uniqueNotifications[] = $notification;
            $seenIds[] = $notification['id'];
        }
    }
    
    // Sort by sent_at descending
    usort($uniqueNotifications, function($a, $b) {
        return strtotime($b['sent_at']) - strtotime($a['sent_at']);
    });
    
    // Limit to 10 most recent
    $notifications = array_slice($uniqueNotifications, 0, 10);
    
    // Debug: Log the notifications found
    error_log("Found " . count($notifications) . " notifications for driver $sessionUserId");
    error_log("Checked driver IDs: " . json_encode($driverIdsToCheck));
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'debug' => [
            'session_user_id' => $sessionUserId,
            'session_username' => $sessionUsername,
            'checked_driver_ids' => $driverIdsToCheck,
            'count' => count($notifications)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
