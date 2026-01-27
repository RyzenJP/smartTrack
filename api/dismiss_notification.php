<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$notificationId = $_POST['notification_id'] ?? null;
$sessionUserId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit();
}

// Find the correct driver_id (same logic as working simple dashboard)
$driverIdsToCheck = [];

// Find driver_id by username
if (!empty($username)) {
    $stmt = $conn->prepare("SELECT DISTINCT driver_id FROM vehicle_assignments WHERE driver_name = ? AND status = 'active'");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($matches as $match) {
        $driverIdsToCheck[] = $match['driver_id'];
    }
}

// Always add session user_id
$driverIdsToCheck[] = $sessionUserId;
$driverIdsToCheck = array_unique($driverIdsToCheck);

// Try to delete the notification
$deleted = false;
foreach ($driverIdsToCheck as $driverId) {
    $stmt = $conn->prepare("DELETE FROM driver_messages WHERE id = ? AND driver_id = ?");
    $stmt->bind_param('ii', $notificationId, $driverId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $deleted = true;
        break;
    }
}

if ($deleted) {
    echo json_encode(['success' => true, 'message' => 'Notification dismissed successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to dismiss notification']);
}
exit();
?>
