<?php
header("Content-Type: application/json");
require_once 'db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No data received');
    }
    
    $deviceId = $input['device_id'] ?? null;
    $speed = $input['speed'] ?? 0;
    $lat = $input['lat'] ?? null;
    $lng = $input['lng'] ?? null;
    $timestamp = $input['timestamp'] ?? null;
    $time = $input['time'] ?? null;
    
    if (!$deviceId) {
        throw new Exception('Device ID is required');
    }
    
    // Update GPS data with speed
    $query = "
        UPDATE gps_devices 
        SET 
            lat = ?,
            lng = ?,
            speed = ?,
            last_update = NOW()
        WHERE device_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$lat, $lng, $speed, $deviceId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Speed data updated successfully',
            'device_id' => $deviceId,
            'speed' => $speed,
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // If no rows updated, insert new record
        $insertQuery = "
            INSERT INTO gps_devices (device_id, lat, lng, speed, last_update, status)
            VALUES (?, ?, ?, ?, NOW(), 'active')
        ";
        
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([$deviceId, $lat, $lng, $speed]);
        
        echo json_encode([
            'success' => true,
            'message' => 'New speed data inserted',
            'device_id' => $deviceId,
            'speed' => $speed,
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
