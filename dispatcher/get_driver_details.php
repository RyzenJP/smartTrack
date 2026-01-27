<?php
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Driver ID required']);
    exit;
}

$driverId = (int)$_GET['driver_id'];

// Get basic driver info
$driverQuery = "
    SELECT 
        u.*,
        ds.status_name AS current_status,
        ds.status_color,
        dsln.notes AS status_notes
    FROM 
        user_table u
    LEFT JOIN 
        driver_status ds ON u.current_status_id = ds.status_id
    LEFT JOIN 
        (SELECT driver_id, notes FROM driver_status_log 
         WHERE driver_id = ? ORDER BY change_date DESC LIMIT 1) dsln ON u.user_id = dsln.driver_id
    WHERE 
        u.user_id = ?
";
$stmt = $conn->prepare($driverQuery);
$stmt->bind_param('ii', $driverId, $driverId);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    echo json_encode(['success' => false, 'message' => 'Driver not found']);
    exit;
}

// Get assigned vehicle info
$vehicleQuery = "
    SELECT 
        v.*,
        flh.location_name AS last_location,
        flh.recorded_at AS last_update
    FROM 
        vehicle_assignments va
    JOIN 
        fleet_vehicles v ON va.vehicle_id = v.id
    LEFT JOIN 
        (SELECT vehicle_id, location_name, recorded_at 
         FROM fleet_location_history 
         WHERE vehicle_id = (SELECT vehicle_id FROM vehicle_assignments 
                             WHERE driver_id = ? AND status = 'active' LIMIT 1)
         ORDER BY recorded_at DESC LIMIT 1) flh ON v.id = flh.vehicle_id
    WHERE 
        va.driver_id = ? 
        AND va.status = 'active'
    LIMIT 1
";
$stmt = $conn->prepare($vehicleQuery);
$stmt->bind_param('ii', $driverId, $driverId);
$stmt->execute();
$result = $stmt->get_result();
$assignedVehicle = $result->fetch_assoc();

// Get recent activity log
$activityQuery = "
    SELECT 
        activity_type,
        details,
        timestamp
    FROM 
        driver_activity_log
    WHERE 
        driver_id = ?
    ORDER BY 
        timestamp DESC
    LIMIT 10
";
$stmt = $conn->prepare($activityQuery);
$stmt->bind_param('i', $driverId);
$stmt->execute();
$result = $stmt->get_result();
$activityLog = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'driver' => $driver,
    'assigned_vehicle' => $assignedVehicle,
    'activity_log' => $activityLog
]);
?>