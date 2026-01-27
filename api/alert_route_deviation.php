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
    $routeStartLat = floatval($input['route_start_lat']);
    $routeStartLng = floatval($input['route_start_lng']);
    $routeEndLat = floatval($input['route_end_lat']);
    $routeEndLng = floatval($input['route_end_lng']);
    $timestamp = $conn->real_escape_string($input['timestamp']);
    
    // Get dispatcher (first active dispatcher or assigned to this route) - use prepared statement
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
    $alertTitle = "Route Deviation Alert - " . $vehicleUnit;
    $alertDescription = "Driver: {$driverName} (ID: {$driverId})\n";
    $alertDescription .= "Vehicle: {$vehicleUnit}\n";
    $alertDescription .= "Route ID: {$routeId}\n";
    $alertDescription .= "Alert: Vehicle deviated from designated route (>200m)\n";
    $alertDescription .= "Current Location: {$currentLat}, {$currentLng}\n";
    $alertDescription .= "Designated Route: ({$routeStartLat}, {$routeStartLng}) to ({$routeEndLat}, {$routeEndLng})\n";
    $alertDescription .= "Time: {$timestamp}\n";
    $alertDescription .= "Action: Driver notified to return to route";
    
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
        $smsMessage = "ALERT: Vehicle {$vehicleUnit} deviated from route. Driver: {$driverName}. Location: {$currentLat},{$currentLng}. Check system alerts.";
        
        // Use existing SMS API function from sms api.php
        $smsResult = sendSMS($smsMessage, $dispatcherPhone);
        
        if ($smsResult['success']) {
            $smsSent = true;
            error_log("Route deviation SMS sent to dispatcher {$dispatcherName} at {$dispatcherPhone}");
        } else {
            error_log("Route deviation SMS failed: " . $smsResult['error']);
        }
    }
    
    // Log the alert event
    error_log("Route deviation alert created: Alert ID {$alertId}, Driver: {$driverName}, Vehicle: {$vehicleUnit}, Route: {$routeId}");
    
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

