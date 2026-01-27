<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Debug: Log session data
error_log("Send All Notifications - Session data: " . json_encode($_SESSION));

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    error_log("Send All Notifications - Unauthorized access. Session: " . json_encode($_SESSION));
    header("Location: ../login.php");
    exit();
}

// Get all active vehicle assignments
$stmt = $conn->prepare("
    SELECT va.*, fv.article, fv.plate_number, fv.current_mileage
    FROM vehicle_assignments va
    LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id
    WHERE va.status = 'active'
");
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$notificationsSent = 0;
$errors = [];

foreach ($assignments as $assignment) {
    $vehicleId = $assignment['vehicle_id'];
    $driverId = $assignment['driver_id'];
    $driverName = $assignment['driver_name'];
    $vehicleName = $assignment['article'];
    $plateNumber = $assignment['plate_number'];
    
    // Create maintenance notification message
    $message = "🔧 MAINTENANCE ALERT\n\n";
    $message .= "Vehicle: $vehicleName ($plateNumber)\n";
    $message .= "Services Required: CHANGE OIL\n";
    $message .= "Message: Vehicle $plateNumber needs CHANGE OIL maintenance\n\n";
    $message .= "Please check your dashboard for more details.";
    
    // Insert notification into driver_messages
    $stmt = $conn->prepare("
        INSERT INTO driver_messages (driver_id, message_text, sent_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param('is', $driverId, $message);
    
    if ($stmt->execute()) {
        $notificationsSent++;
    } else {
        $errors[] = "Failed to send notification to $driverName: " . $conn->error;
    }
}

// Redirect back with results
$redirectUrl = "predictive_maintenance.php?";
if ($notificationsSent > 0) {
    $redirectUrl .= "success=sent&count=$notificationsSent";
}
if (!empty($errors)) {
    $redirectUrl .= "&errors=" . urlencode(implode('; ', $errors));
}

header("Location: $redirectUrl");
exit();
?>
