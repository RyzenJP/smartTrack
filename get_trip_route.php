<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../db_connection.php';

if (!isset($_GET['trip_id'])) {
    echo json_encode([]);
    exit;
}

$trip_id = intval($_GET['trip_id']);

$sql = "SELECT lat, lng FROM gps_logs WHERE trip_id = ? ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();

$points = [];
while ($row = $result->fetch_assoc()) {
    $points[] = [
        "lat" => floatval($row['lat']),
        "lng" => floatval($row['lng'])
    ];
}

echo json_encode($points);
