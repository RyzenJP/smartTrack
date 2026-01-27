<?php
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['driver_id'])) {  // Added missing closing parenthesis
    echo json_encode(['success' => false, 'message' => 'Driver ID required']);
    exit;
}

$driverId = (int)$_GET['driver_id'];

// Get current status of the driver
$currentStatusQuery = "SELECT current_status_id FROM user_table WHERE user_id = ?";
$stmt = $conn->prepare($currentStatusQuery);
$stmt->bind_param('i', $driverId);
$stmt->execute();
$result = $stmt->get_result();
$currentStatus = $result->fetch_assoc();

// Get all possible status options - use prepared statement for consistency
$status_stmt = $conn->prepare("SELECT * FROM driver_status ORDER BY status_priority");
$status_stmt->execute();
$statusResult = $status_stmt->get_result();

$options = [];
while ($status = $statusResult->fetch_assoc()) {
    $options[] = [
        'status_id' => $status['status_id'],
        'status_name' => $status['status_name'],
        'status_color' => $status['status_color'],
        'description' => $status['description'],
        'is_current' => ($status['status_id'] == $currentStatus['current_status_id']),
        'requires_notes' => ($status['requires_notes'] == 1)
    ];
}

echo json_encode(['success' => true, 'options' => $options]);
$status_stmt->close();
$stmt->close();
?>