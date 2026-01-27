<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../db_connection.php';

    // Updated query to fetch GPS data from gps_devices table with proper joins
    $query = "SELECT 
                gd.device_id,
                gd.lat,
                gd.lng,
                gd.speed,
                gd.status,
                gd.last_update,
                gd.battery_level,
                fv.id as vehicle_id,
                fv.article,
                fv.plate_number,
                fv.status as vehicle_status,
                u.full_name as driver_name,
                u.user_id as driver_id
              FROM gps_devices gd
              LEFT JOIN fleet_vehicles fv ON gd.vehicle_id = fv.id
              LEFT JOIN vehicle_assignments va ON fv.id = va.vehicle_id AND va.status = 'active'
              LEFT JOIN user_table u ON va.driver_id = u.user_id
              WHERE gd.status = 'active' 
              AND gd.lat IS NOT NULL 
              AND gd.lng IS NOT NULL
              ORDER BY gd.last_update DESC";

    // Use prepared statement for consistency (static query but best practice)
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicles = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format the data to match what the navigation page expects
            $vehicles[] = [
                'id' => $row['vehicle_id'],
                'device_id' => $row['device_id'],
                'article' => $row['article'] ?: 'Mobile Device',
                'plate_number' => $row['plate_number'] ?: 'N/A',
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'speed' => $row['speed'] ?: 0,
                'status' => $row['vehicle_status'] ?: 'active',
                'last_update' => $row['last_update'],
                'driver_name' => $row['driver_name'] ?: 'Unassigned',
                'driver_full_name' => $row['driver_name'] ?: 'Unassigned',
                'battery_level' => $row['battery_level']
            ];
        }
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $vehicles]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>