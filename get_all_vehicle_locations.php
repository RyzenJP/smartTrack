<?php
// Clean version without any potential issues
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
require_once 'db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple query to get GPS devices with coordinates
    $query = "
        SELECT 
            g.device_id,
            g.vehicle_id,
            g.lat as latitude,
            g.lng as longitude,
            g.speed,
            g.last_update,
            f.article as vehicle_name,
            f.plate_number
        FROM gps_devices g
        LEFT JOIN fleet_vehicles f ON g.vehicle_id = f.id
        WHERE g.status = 'active' 
        AND g.lat IS NOT NULL 
        AND g.lng IS NOT NULL
        ORDER BY g.device_id
    ";
    
    $stmt = $pdo->query($query);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the data
    $processedVehicles = [];
    foreach ($vehicles as $vehicle) {
        if ($vehicle['latitude'] && $vehicle['longitude']) {
            $processedVehicles[] = [
                'vehicle_id' => $vehicle['vehicle_id'],
                'vehicle_name' => $vehicle['vehicle_name'] ?: 'Unassigned Device',
                'plate_number' => $vehicle['plate_number'] ?: 'N/A',
                'device_id' => $vehicle['device_id'],
                'driver_name' => 'Unassigned',
                'driver_phone' => 'N/A',
                'latitude' => floatval($vehicle['latitude']),
                'longitude' => floatval($vehicle['longitude']),
                'speed' => floatval($vehicle['speed'] ?? 0),
                'timestamp' => $vehicle['last_update'],
                'last_update' => $vehicle['last_update']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'vehicles' => $processedVehicles,
        'count' => count($processedVehicles),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
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
