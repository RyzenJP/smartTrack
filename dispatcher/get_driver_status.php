<?php
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

// This endpoint would return the same data as the main query in driver-status.php
// In a real implementation, you might want to return JSON for AJAX updates

$driversQuery = "
    SELECT 
        u.user_id, 
        u.full_name, 
        u.phone_number,
        u.status AS driver_status,
        ds.status_name AS current_status,
        ds.status_color,
        v.article AS vehicle_name,
        v.plate_number,
        v.id AS vehicle_id,
        lh.location_name,
        lh.recorded_at
    FROM 
        user_table u
    LEFT JOIN 
        driver_status ds ON u.current_status_id = ds.status_id
    LEFT JOIN 
        vehicle_assignments va ON u.user_id = va.driver_id AND va.status = 'active'
    LEFT JOIN 
        fleet_vehicles v ON va.vehicle_id = v.id
    LEFT JOIN 
        (SELECT 
            vehicle_id, 
            location_name, 
            recorded_at 
         FROM 
            fleet_location_history 
         WHERE 
            recorded_at = (SELECT MAX(recorded_at) FROM fleet_location_history WHERE vehicle_id = fleet_location_history.vehicle_id)
        ) lh ON v.id = lh.vehicle_id
    WHERE 
        u.role = 'Driver' 
        AND u.status = 'Active'
    ORDER BY 
        ds.status_priority, u.full_name
";

// Use prepared statement for consistency (static query but best practice)
$drivers_stmt = $conn->prepare($driversQuery);
$drivers_stmt->execute();
$drivers = $drivers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$drivers_stmt->close();

echo json_encode(['success' => true, 'drivers' => $drivers]);
?>