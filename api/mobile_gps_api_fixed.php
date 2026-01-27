<?php
header('Content-Type: application/json');

// Secure CORS configuration
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true); // Allow credentials for authenticated requests

// Include database connection
require_once '../db_connection.php';

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log detailed error server-side only
    error_log("Mobile GPS API (Fixed) - Database connection failed: " . $e->getMessage());
    error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
    
    // Show generic error to user (no sensitive info)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error. Please contact the administrator.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Function to validate API key
function validateApiKey($apiKey) {
    // You can implement your own API key validation logic here
    // For now, we'll use a simple validation
    return !empty($apiKey) && strlen($apiKey) >= 10;
}

// Function to log GPS data
function logGpsData($pdo, $data) {
    try {
        $sql = "INSERT INTO gps_logs (
            device_id, 
            latitude, 
            longitude
        ) VALUES (?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['device_id'],
            $data['latitude'],
            $data['longitude']
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("GPS logging error: " . $e->getMessage());
        error_log("GPS logging SQL: " . $sql);
        error_log("GPS logging data: " . json_encode($data));
        return false;
    }
}

// Function to update device status using your existing gps_devices table structure
function updateDeviceStatus($pdo, $deviceId, $deviceName, $latitude, $longitude, $speed, $batteryLevel = null, $vehicleId = null) {
    try {
        // Check if device exists
        $checkSql = "SELECT id FROM gps_devices WHERE device_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$deviceId]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing device using your table structure
            $updateSql = "UPDATE gps_devices SET 
                vehicle_id = ?, 
                status = 'active',
                last_update = NOW(),
                battery_level = ?,
                lat = ?, 
                lng = ?, 
                speed = ?
                WHERE device_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $vehicleId, // vehicle_id from request
                $batteryLevel,
                $latitude,
                $longitude,
                $speed,
                $deviceId
            ]);
        } else {
            // Insert new device using your table structure
            $insertSql = "INSERT INTO gps_devices (
                device_id, 
                vehicle_id, 
                status, 
                last_update, 
                battery_level, 
                lat, 
                lng, 
                speed
            ) VALUES (?, ?, 'active', NOW(), ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $deviceId,
                $vehicleId, // vehicle_id from request
                $batteryLevel,
                $latitude,
                $longitude,
                $speed
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Device status update error: " . $e->getMessage());
        return false;
    }
}

// Main API logic
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $requiredFields = ['device_id', 'device_name', 'latitude', 'longitude', 'api_key'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate API key
    if (!validateApiKey($input['api_key'])) {
        throw new Exception('Invalid API key');
    }

    // Validate coordinates
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid coordinates');
    }

    // Prepare data for database
    $gpsData = [
        'device_id' => $input['device_id'],
        'device_name' => $input['device_name'],
        'vehicle_id' => $input['vehicle_id'] ?? null,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'speed' => floatval($input['speed'] ?? 0),
        'battery_level' => isset($input['battery_level']) ? intval($input['battery_level']) : null,
        'status' => $input['status'] ?? 'active',
        'timestamp' => $input['timestamp'] ?? date('Y-m-d H:i:s')
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Log GPS data
        $logResult = logGpsData($pdo, $gpsData);
        
        // Update device status
        $statusResult = updateDeviceStatus(
            $pdo, 
            $gpsData['device_id'], 
            $gpsData['device_name'], 
            $gpsData['latitude'], 
            $gpsData['longitude'], 
            $gpsData['speed'],
            $gpsData['battery_level'],
            $gpsData['vehicle_id']
        );

        if (!$logResult || !$statusResult) {
            error_log("GPS Save Failed - Log: " . ($logResult ? 'SUCCESS' : 'FAILED') . ", Status: " . ($statusResult ? 'SUCCESS' : 'FAILED'));
            throw new Exception('Failed to save GPS data - Log: ' . ($logResult ? 'OK' : 'FAILED') . ', Status: ' . ($statusResult ? 'OK' : 'FAILED'));
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'GPS data received successfully',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'device_id' => $gpsData['device_id'],
                'coordinates' => [
                    'latitude' => $gpsData['latitude'],
                    'longitude' => $gpsData['longitude']
                ],
                'speed' => $gpsData['speed']
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
