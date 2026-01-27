<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$driver_id = $data['driver_id'];
$lat = $data['lat'];
$lng = $data['lng'];
$distance = $data['distance'];

$stmt = $conn->prepare("INSERT INTO route_alerts (driver_id, lat, lng, distance, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iddi", $driver_id, $lat, $lng, $distance);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Alert recorded']);
