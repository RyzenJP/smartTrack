<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: ../login.php");
    exit();
}

$vehicleId = $_POST['vehicle_id'] ?? null;
$message = $_POST['message'] ?? '';
$servicesText = $_POST['services_text'] ?? '';

if (!$vehicleId) {
    header("Location: predictive_maintenance.php?error=no_vehicle_id");
    exit();
}

// Get driver assigned to this vehicle
$stmt = $conn->prepare("
    SELECT va.driver_id, va.driver_name, va.phone_number, fv.article, fv.plate_number
    FROM vehicle_assignments va
    LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id
    WHERE va.vehicle_id = ? AND va.status = 'active'
");
$stmt->bind_param('i', $vehicleId);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();

if (!$driver) {
    header("Location: predictive_maintenance.php?error=no_driver_found");
    exit();
}

// Create the notification message
$fullMessage = "🔧 MAINTENANCE ALERT\n\n";
$fullMessage .= "Vehicle: " . $driver['article'] . " (" . $driver['plate_number'] . ")\n";
$fullMessage .= "Services Required: " . $servicesText . "\n";
$fullMessage .= "Message: " . $message . "\n\n";
$fullMessage .= "Please check your dashboard for more details.";

// Insert notification into driver_messages
$stmt = $conn->prepare("
    INSERT INTO driver_messages (driver_id, message_text, sent_at)
    VALUES (?, ?, NOW())
");
$stmt->bind_param('is', $driver['driver_id'], $fullMessage);

if ($stmt->execute()) {
    header("Location: predictive_maintenance.php?success=single_sent&driver=" . urlencode($driver['driver_name']));
} else {
    header("Location: predictive_maintenance.php?error=send_failed&driver=" . urlencode($driver['driver_name']));
}
exit();
?>
