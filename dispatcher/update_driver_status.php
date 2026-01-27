<?php
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($data['driver_id'], $data['status_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$driverId = (int)$data['driver_id'];
$statusId = (int)$data['status_id'];
$notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : null;

// Update driver status
$updateQuery = "UPDATE user_table SET current_status_id = ? WHERE user_id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param('ii', $statusId, $driverId);
$updateSuccess = $stmt->execute();

if ($updateSuccess) {
    // Log the status change
    $logQuery = "INSERT INTO driver_status_log (driver_id, status_id, changed_by, notes) 
                 VALUES (?, ?, ?, ?)";
    $changedBy = $_SESSION['user_id'] ?? 0; // Assuming you have user_id in session
    $stmt = $conn->prepare($logQuery);
    $stmt->bind_param('iiis', $driverId, $statusId, $changedBy, $notes);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>