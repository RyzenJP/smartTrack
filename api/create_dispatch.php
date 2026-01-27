<?php
require_once __DIR__ . '/../../db_connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['vehicle_id']) || empty($data['driver_id']) || empty($data['route_details'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO dispatches (vehicle_id, driver_id, route_details, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iisi", $data['vehicle_id'], $data['driver_id'], $data['route_details'], $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>