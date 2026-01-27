<?php
header("Content-Type: application/json");

require_once __DIR__ . '/db_connection.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

if (!isset($_GET['device_id'])) {
    echo json_encode(["error" => "Missing device_id"]);
    exit;
}

// Use prepared statement for security (device_id comes from user input)
$device_id = $_GET['device_id'] ?? '';
$stmt = $conn->prepare("SELECT latitude AS lat, longitude AS lng FROM gps_logs WHERE device_id = ? ORDER BY timestamp DESC LIMIT 50");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();

$trail = [];
while ($row = $result->fetch_assoc()) {
    $trail[] = [
        "lat" => floatval($row['lat']),
        "lng" => floatval($row['lng'])
    ];
}

echo json_encode($trail);
$conn->close();
?>
