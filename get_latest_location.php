<?php
header("Content-Type: application/json");

require_once __DIR__ . '/db_connection.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

if (!isset($_GET['device_id']) || empty($_GET['device_id'])) {
    echo json_encode(["error" => "Missing device_id"]);
    exit;
}

// Use prepared statement for security (device_id comes from user input)
$device_id = $_GET['device_id'] ?? '';
$stmt = $conn->prepare("SELECT lat, lng, speed, last_update FROM gps_devices WHERE device_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        "lat" => floatval($row['lat']),
        "lng" => floatval($row['lng']),
        "speed" => isset($row['speed']) ? floatval($row['speed']) : 0,
        "last_update" => $row['last_update'] ?? null
    ]);
} else {
    echo json_encode(["error" => "No data found"]);
}
$stmt->close();

$conn->close();
?>
