<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connection.php';

try {
    // Get active routes with driver and vehicle information
    $query = "
        SELECT 
            r.id,
            r.start_lat,
            r.start_lng,
            r.end_lat,
            r.end_lng,
            r.unit,
            r.driver_id,
            d.full_name AS driver_name,
            v.plate_number,
            gd.lat AS current_lat,
            gd.lng AS current_lng
        FROM routes r
        LEFT JOIN user_table d ON r.driver_id = d.user_id
        LEFT JOIN fleet_vehicles v ON r.unit = v.unit
        LEFT JOIN gps_devices gd ON v.id = gd.vehicle_id
        WHERE r.status = 'active' 
        AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
        ORDER BY r.id DESC
    ";
    
    // Use prepared statement for consistency (static query but best practice)
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    $stmt->close();
    
    $routes = [];
    while ($row = $result->fetch_assoc()) {
        $routes[] = [
            'id' => (int)$row['id'],
            'start_lat' => (float)$row['start_lat'],
            'start_lng' => (float)$row['start_lng'],
            'end_lat' => (float)$row['end_lat'],
            'end_lng' => (float)$row['end_lng'],
            'unit' => $row['unit'],
            'driver_id' => (int)$row['driver_id'],
            'driver_name' => $row['driver_name'],
            'plate_number' => $row['plate_number'],
            'current_lat' => $row['current_lat'] ? (float)$row['current_lat'] : null,
            'current_lng' => $row['current_lng'] ? (float)$row['current_lng'] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'routes' => $routes,
        'count' => count($routes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'routes' => []
    ]);
}
?>
