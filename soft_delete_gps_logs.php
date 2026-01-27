<?php
// soft_delete_gps_logs.php

header('Content-Type: application/json');

$response = ["status" => "error", "message" => ""];

// 1. Get the device_id from the URL
if (!isset($_GET['device_id'])) {
    $response['message'] = "Device ID not provided.";
    echo json_encode($response);
    exit;
}

$deviceID = $_GET['device_id'];

// 2. Database connection (replace with your actual connection details)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trackingv2";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Prepare and execute the UPDATE statement
    // This SQL query updates the 'is_deleted' flag to 1 for the specified device.
    $stmt = $conn->prepare("UPDATE gps_logs SET is_deleted = 1 WHERE device_id = :device_id");
    $stmt->bindParam(':device_id', $deviceID);
    $stmt->execute();

    // 4. Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        $response['status'] = "success";
        $response['message'] = "Trail successfully cleared (soft deleted).";
    } else {
        $response['message'] = "No trail found for this device or trail already soft deleted.";
    }

} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
}

$conn = null;
echo json_encode($response);

?>