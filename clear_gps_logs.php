<?php
// clear_gps_logs.php
header("Content-Type: application/json");

$host = "localhost";
$db = "trackingv2";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

if (!isset($_GET['device_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing device_id"]);
    exit;
}

// Use prepared statement for security - real_escape_string is not enough
$device_id = trim($_GET['device_id']);
$delete_stmt = $conn->prepare("DELETE FROM gps_logs WHERE device_id = ?");
$delete_stmt->bind_param("s", $device_id);
$delete = $delete_stmt->execute();
$delete_stmt->close();

if ($delete) {
    echo json_encode(["status" => "success", "message" => "Trail logs deleted"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete"]);
}

$conn->close();
?>
