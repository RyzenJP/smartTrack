<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $vehicleId = $input['vehicle_id'] ?? null;
    $message = $input['message'] ?? '';
    $servicesRequired = $input['services_required'] ?? '';
    $notificationType = $input['notification_type'] ?? 'maintenance_alert';
    $priority = $input['priority'] ?? 'medium';
    
    if (!$vehicleId) {
        throw new Exception('Vehicle ID is required');
    }
    
    // Get driver assigned to this vehicle
    $stmt = $conn->prepare("
        SELECT va.driver_id, va.driver_name, va.phone_number, va.vehicle_id
        FROM vehicle_assignments va
        WHERE va.vehicle_id = ? AND va.status = 'active'
    ");
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    
    if (!$driver) {
        throw new Exception('No active driver assigned to vehicle ID: ' . $vehicleId);
    }
    
    // Debug: Log the driver information
    error_log("Found driver for vehicle $vehicleId: " . json_encode($driver));
    
    // Get vehicle information
    $stmt = $conn->prepare("
        SELECT article, plate_number FROM fleet_vehicles WHERE id = ?
    ");
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    
    $vehicleName = $vehicle ? $vehicle['article'] : 'Unknown Vehicle';
    $plateNumber = $vehicle ? $vehicle['plate_number'] : 'Unknown Plate';
    
    // Create notification in database
    $stmt = $conn->prepare("
        INSERT INTO driver_messages (driver_id, message_text, sent_at)
        VALUES (?, ?, NOW())
    ");
    
    $fullMessage = "🔧 MAINTENANCE ALERT\n\n";
    $fullMessage .= "Vehicle: " . $vehicleName . " (" . $plateNumber . ")\n";
    $fullMessage .= "Services Required: " . $servicesRequired . "\n";
    $fullMessage .= "Message: " . $message . "\n\n";
    $fullMessage .= "Please check your dashboard for more details.";
    
    $stmt->bind_param('is', 
        $driver['driver_id'], 
        $fullMessage
    );
    
    if ($stmt->execute()) {
        $notificationId = $conn->insert_id;
        
        // Debug: Log successful notification creation
        error_log("Notification created successfully: ID $notificationId for driver {$driver['driver_id']} ({$driver['driver_name']})");
        
        // Also create an alert record for tracking
        $stmt = $conn->prepare("
            INSERT INTO alerts (title, description, priority, resolved, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        
        $alertTitle = "Maintenance Alert - Vehicle ID: " . $vehicleId;
        $alertDescription = "Driver " . $driver['driver_name'] . " notified about maintenance: " . $servicesRequired;
        
        $stmt->bind_param('sss', $alertTitle, $alertDescription, $priority);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully',
            'data' => [
                'notification_id' => $notificationId,
                'driver_id' => $driver['driver_id'],
                'driver_name' => $driver['driver_name'],
                'vehicle_id' => $vehicleId,
                'message' => $fullMessage
            ]
        ]);
    } else {
        error_log("Failed to create notification: " . $conn->error);
        throw new Exception('Failed to create notification: ' . $conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
