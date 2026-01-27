<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../sms api.php';

// Only allow authenticated drivers
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $driverId = intval($input['driver_id']);
    $driverName = $conn->real_escape_string($input['driver_name']);
    $routeId = intval($input['route_id']);
    $vehicleUnit = $conn->real_escape_string($input['vehicle_unit']);
    $currentLat = floatval($input['current_lat']);
    $currentLng = floatval($input['current_lng']);
    $completionLat = floatval($input['completion_lat']);
    $completionLng = floatval($input['completion_lng']);
    $distanceMoved = intval($input['distance_moved']);
    $timestamp = $conn->real_escape_string($input['timestamp']);
    
    // Get dispatcher assigned to this route (from vehicle_reservations if exists, or default dispatcher) - use prepared statement
    $dispatcher_stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.phone
                        FROM user_table u
                        WHERE u.role = 'dispatcher' AND u.status = 'Active'
                        LIMIT 1");
    $dispatcher_stmt->execute();
    $dispatcherResult = $dispatcher_stmt->get_result();
    
    if (!$dispatcherResult || $dispatcherResult->num_rows === 0) {
        $dispatcher_stmt->close();
        throw new Exception('No active dispatcher found');
    }
    
    $dispatcher = $dispatcherResult->fetch_assoc();
    $dispatcher_stmt->close();
    $dispatcherId = $dispatcher['user_id'];
    $dispatcherName = $dispatcher['full_name'];
    $dispatcherPhone = $dispatcher['phone'];
    
    // Create system alert in alerts table
    $alertTitle = "Unauthorized Vehicle Movement - " . $vehicleUnit;
    $alertDescription = "Driver: {$driverName} (ID: {$driverId})\n";
    $alertDescription .= "Vehicle: {$vehicleUnit}\n";
    $alertDescription .= "Route ID: {$routeId}\n";
    $alertDescription .= "Alert: Vehicle moved {$distanceMoved}m after trip completion\n";
    $alertDescription .= "Current Location: {$currentLat}, {$currentLng}\n";
    $alertDescription .= "Completion Location: {$completionLat}, {$completionLng}\n";
    $alertDescription .= "Time: {$timestamp}";
    
    $insertAlert = $conn->prepare("INSERT INTO alerts (title, description, priority, resolved, created_at) 
                                    VALUES (?, ?, 'high', 0, NOW())");
    $insertAlert->bind_param("ss", $alertTitle, $alertDescription);
    
    if (!$insertAlert->execute()) {
        throw new Exception('Failed to create system alert');
    }
    
    $alertId = $conn->insert_id;
    
    // Send SMS to dispatcher if phone number is available
    $smsSent = false;
    if (!empty($dispatcherPhone)) {
        $smsMessage = "ALERT: Vehicle {$vehicleUnit} moved {$distanceMoved}m after trip completion. Driver: {$driverName}. Check system for details.";
        
        // Use existing SMS API function from sms api.php
        $smsResult = sendSMS($smsMessage, $dispatcherPhone);
        
        if ($smsResult['success']) {
            $smsSent = true;
            error_log("SMS sent to dispatcher {$dispatcherName} at {$dispatcherPhone}");
        } else {
            error_log("SMS failed to send: " . $smsResult['error']);
        }
    }
    
    // Log the alert event
    error_log("Post-trip movement alert created: Alert ID {$alertId}, Driver: {$driverName}, Vehicle: {$vehicleUnit}, Distance: {$distanceMoved}m");
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispatcher alerted successfully',
        'alert_id' => $alertId,
        'sms_sent' => $smsSent,
        'dispatcher' => $dispatcherName
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

