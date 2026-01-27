<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'update_fuel_on_trip_completion':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['vehicle_id']) || empty($data['trip_distance_km'])) {
                echo json_encode(['success' => false, 'message' => 'Vehicle ID and trip distance required']);
                break;
            }
            
            $vehicleId = intval($data['vehicle_id']);
            $tripDistance = floatval($data['trip_distance_km']);
            $tripNotes = $data['trip_notes'] ?? 'Trip completion fuel consumption';
            
            // Get vehicle fuel consumption rate
            $vehicleQuery = "SELECT fuel_consumption_l_per_km, current_fuel_level_liters, article, plate_number FROM fleet_vehicles WHERE id = ?";
            $stmt = $pdo->prepare($vehicleQuery);
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
                break;
            }
            
            $fuelConsumptionRate = floatval($vehicle['fuel_consumption_l_per_km']);
            $currentFuel = floatval($vehicle['current_fuel_level_liters']);
            $fuelConsumed = $tripDistance * $fuelConsumptionRate;
            $newFuelLevel = max(0, $currentFuel - $fuelConsumed);
            
            // Update vehicle fuel level
            $updateQuery = "
                UPDATE fleet_vehicles 
                SET 
                    current_fuel_level_liters = ?,
                    total_fuel_consumed_liters = total_fuel_consumed_liters + ?,
                    last_updated = NOW()
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$newFuelLevel, $fuelConsumed, $vehicleId]);
            
            // Log the trip fuel consumption
            $logQuery = "
                INSERT INTO fuel_consumption_logs 
                (vehicle_id, log_date, distance_traveled_km, fuel_consumed_liters, fuel_efficiency_km_per_l, gps_points_count)
                VALUES (?, CURDATE(), ?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE
                    distance_traveled_km = distance_traveled_km + VALUES(distance_traveled_km),
                    fuel_consumed_liters = fuel_consumed_liters + VALUES(fuel_consumed_liters),
                    fuel_efficiency_km_per_l = (distance_traveled_km + VALUES(distance_traveled_km)) / (fuel_consumed_liters + VALUES(fuel_consumed_liters)),
                    updated_at = CURRENT_TIMESTAMP
            ";
            
            $efficiency = $fuelConsumptionRate > 0 ? (1 / $fuelConsumptionRate) : 0;
            $stmt = $pdo->prepare($logQuery);
            $stmt->execute([$vehicleId, $tripDistance, $fuelConsumed, $efficiency]);
            
            // Create a refuel log entry for trip completion
            $refuelLogQuery = "
                INSERT INTO fuel_refueling_logs 
                (vehicle_id, refuel_date, fuel_amount_liters, fuel_cost_per_liter, total_cost, 
                 odometer_reading, refuel_type, fuel_station, notes)
                VALUES (?, NOW(), 0, 0, 0, ?, 'partial', 'Trip Completion', ?)
            ";
            
            $stmt = $pdo->prepare($refuelLogQuery);
            $stmt->execute([$vehicleId, $tripDistance, $tripNotes]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Fuel consumption updated for trip completion',
                'vehicle' => $vehicle['article'] . ' (' . $vehicle['plate_number'] . ')',
                'trip_distance' => $tripDistance,
                'fuel_consumed' => $fuelConsumed,
                'old_fuel_level' => $currentFuel,
                'new_fuel_level' => $newFuelLevel,
                'fuel_efficiency' => $efficiency
            ]);
            break;
            
        case 'get_trip_distance':
            $vehicleId = $_GET['vehicle_id'] ?? '';
            $startTime = $_GET['start_time'] ?? '';
            $endTime = $_GET['end_time'] ?? '';
            
            if (empty($vehicleId) || empty($startTime) || empty($endTime)) {
                echo json_encode(['success' => false, 'message' => 'Vehicle ID, start time, and end time required']);
                break;
            }
            
            // Calculate distance from GPS logs during trip period
            $distanceQuery = "
                SELECT 
                    COUNT(g.id) as gps_points,
                    COUNT(g.id) * 0.1 as distance_km,
                    MIN(g.timestamp) as first_gps,
                    MAX(g.timestamp) as last_gps
                FROM gps_logs g
                INNER JOIN gps_devices gd ON g.device_id = gd.device_id
                WHERE gd.vehicle_id = ?
                AND g.timestamp >= ?
                AND g.timestamp <= ?
                AND g.is_deleted = 0
            ";
            
            $stmt = $pdo->prepare($distanceQuery);
            $stmt->execute([$vehicleId, $startTime, $endTime]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $distance = $result ? floatval($result['distance_km']) : 0;
            $gpsPoints = $result ? intval($result['gps_points']) : 0;
            
            echo json_encode([
                'success' => true,
                'distance_km' => $distance,
                'gps_points' => $gpsPoints,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'first_gps' => $result['first_gps'] ?? null,
                'last_gps' => $result['last_gps'] ?? null
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
