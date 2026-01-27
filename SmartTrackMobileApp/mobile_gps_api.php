<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../config/db_connection.php';

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
            imei, 
            vehicle_id, 
            status, 
            last_update, 
            battery_level, 
            lat, 
            lng, 
            speed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['device_id'],
            $data['imei'] ?? $data['device_id'], // Use device_id as IMEI if not provided
            $data['vehicle_id'],
            $data['status'] ?? 'active',
            $data['timestamp'],
            $data['battery_level'] ?? null,
            $data['latitude'],
            $data['longitude'],
            $data['speed']
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("GPS logging error: " . $e->getMessage());
        return false;
    }
}

// Function to update device status using your existing gps_devices table structure
function updateDeviceStatus($pdo, $deviceId, $deviceName, $latitude, $longitude, $speed, $batteryLevel = null) {
    try {
        // Check if device exists
        $checkSql = "SELECT id FROM gps_devices WHERE device_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$deviceId]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing device using your table structure
            $updateSql = "UPDATE gps_devices SET 
                imei = ?, 
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
                $deviceId, // Use device_id as IMEI for mobile devices
                null, // vehicle_id - can be set later
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
                imei, 
                vehicle_id, 
                status, 
                last_update, 
                battery_level, 
                lat, 
                lng, 
                speed
            ) VALUES (?, ?, ?, 'active', NOW(), ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $deviceId,
                $deviceId, // Use device_id as IMEI for mobile devices
                null, // vehicle_id - can be set later
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
            $gpsData['battery_level']
        );

        if (!$logResult || !$statusResult) {
            throw new Exception('Failed to save GPS data');
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
