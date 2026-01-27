<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as dispatcher
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../sms api.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $driverId = $input['driver_id'] ?? null;
    $message = $input['message'] ?? null;
    
    if (!$driverId || !$message) {
        throw new Exception('Driver ID and message are required');
    }
    
    // Validate message length
    if (strlen($message) > 160) {
        throw new Exception('Message is too long (max 160 characters)');
    }
    
    // Get driver's phone number from database
    $stmt = $conn->prepare("SELECT full_name, phone FROM user_table WHERE user_id = ? AND role = 'Driver'");
    $stmt->bind_param("s", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Driver not found');
    }
    
    $driver = $result->fetch_assoc();
    $phoneNumber = $driver['phone'];
    $driverName = $driver['full_name'];
    
    if (!$phoneNumber) {
        throw new Exception('Driver does not have a phone number registered');
    }
    
    // Format the message with dispatcher info
    $formattedMessage = "Smart Track Dispatch: {$message}";
    
    // Send SMS using the SMS API
    $smsResult = sendSMS($formattedMessage, $phoneNumber);
    
    if ($smsResult['success']) {
        // Log the SMS in database (optional)
        $logStmt = $conn->prepare("INSERT INTO sms_logs (driver_id, driver_name, message, sent_by, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $dispatcherId = $_SESSION['user_id'] ?? 'dispatcher';
        $logStmt->bind_param("ssss", $driverId, $driverName, $message, $dispatcherId);
        $logStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'SMS sent successfully to ' . $driverName,
            'driver_name' => $driverName,
            'phone_number' => $phoneNumber
        ]);
    } else {
        throw new Exception('SMS sending failed: ' . $smsResult['error']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
