<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'get_fuel_overview':
            // Get fuel overview data
            $overviewQuery = "
                SELECT 
                    COUNT(*) as total_vehicles,
                    SUM(CASE WHEN (current_fuel_level_liters / fuel_tank_capacity_liters) < 0.25 THEN 1 ELSE 0 END) as low_fuel_vehicles,
                    AVG(fuel_efficiency_km_per_l) as avg_efficiency,
                    SUM(total_fuel_consumed_liters * fuel_cost_per_liter) as monthly_cost
                FROM fleet_vehicles 
                WHERE article NOT LIKE 'Synthetic%' 
                AND plate_number NOT LIKE 'SYN-%'
            ";
            
            $stmt = $pdo->query($overviewQuery);
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get vehicle fuel levels
            $vehiclesQuery = "
                SELECT 
                    v.id,
                    v.article,
                    v.plate_number,
                    v.current_fuel_level_liters,
                    v.fuel_tank_capacity_liters,
                    v.fuel_efficiency_km_per_l,
                    v.last_refuel_date
                FROM fleet_vehicles v
                WHERE v.article NOT LIKE 'Synthetic%' 
                AND v.plate_number NOT LIKE 'SYN-%'
                ORDER BY v.article
            ";
            
            $stmt = $pdo->query($vehiclesQuery);
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get consumption data for chart
            $consumptionQuery = "
                SELECT 
                    v.article as vehicle,
                    COALESCE(SUM(fcl.fuel_consumed_liters), 0) as consumption
                FROM fleet_vehicles v
                LEFT JOIN fuel_consumption_logs fcl ON v.id = fcl.vehicle_id 
                    AND fcl.log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                WHERE v.article NOT LIKE 'Synthetic%' 
                AND v.plate_number NOT LIKE 'SYN-%'
                GROUP BY v.id, v.article
                ORDER BY consumption DESC
                LIMIT 10
            ";
            
            $stmt = $pdo->query($consumptionQuery);
            $consumption = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'overview' => $overview,
                'vehicles' => $vehicles,
                'consumption' => $consumption
            ]);
            break;

        case 'get_refuel_history':
            $filter = $_GET['filter'] ?? 'month';
            
            $dateFilter = "";
            switch($filter) {
                case 'today':
                    $dateFilter = "AND DATE(frl.refuel_date) = CURDATE()";
                    break;
                case 'week':
                    $dateFilter = "AND frl.refuel_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                default:
                    $dateFilter = "AND frl.refuel_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
            }
            
            $historyQuery = "
                SELECT 
                    frl.*,
                    v.article as vehicle_name,
                    v.plate_number
                FROM fuel_refueling_logs frl
                LEFT JOIN fleet_vehicles v ON frl.vehicle_id = v.id
                WHERE 1=1 $dateFilter
                ORDER BY frl.refuel_date DESC
                LIMIT 50
            ";
            
            $stmt = $pdo->query($historyQuery);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        case 'record_refuel':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['vehicle_id']) || empty($data['fuel_amount_liters']) || empty($data['fuel_cost_per_liter'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }
            
            $vehicleId = $data['vehicle_id'];
            $fuelAmount = floatval($data['fuel_amount_liters']);
            $costPerLiter = floatval($data['fuel_cost_per_liter']);
            $totalCost = $fuelAmount * $costPerLiter;
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Insert refuel record
                $refuelQuery = "
                    INSERT INTO fuel_refueling_logs 
                    (vehicle_id, fuel_amount_liters, fuel_cost_per_liter, total_cost, 
                     fuel_station, refuel_type, odometer_reading, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = $pdo->prepare($refuelQuery);
                $stmt->execute([
                    $vehicleId,
                    $fuelAmount,
                    $costPerLiter,
                    $totalCost,
                    $data['fuel_station'] ?? null,
                    $data['refuel_type'] ?? 'full_tank',
                    $data['odometer_reading'] ?? null,
                    $data['notes'] ?? null,
                    $_SESSION['user_id'] ?? null
                ]);
                
                // Update vehicle fuel level
                $updateQuery = "
                    UPDATE fleet_vehicles 
                    SET 
                        current_fuel_level_liters = LEAST(fuel_tank_capacity_liters, current_fuel_level_liters + ?),
                        last_refuel_date = NOW(),
                        last_refuel_amount_liters = ?,
                        fuel_cost_per_liter = ?,
                        total_fuel_consumed_liters = GREATEST(0, total_fuel_consumed_liters - ?)
                    WHERE id = ?
                ";
                
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([$fuelAmount, $fuelAmount, $costPerLiter, $fuelAmount, $vehicleId]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Refuel recorded successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;

        case 'get_vehicle_fuel_data':
            $vehicleId = $_GET['vehicle_id'] ?? '';
            
            if (empty($vehicleId)) {
                echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
                break;
            }
            
            $vehicleQuery = "
                SELECT 
                    v.*,
                    frl.refuel_date as last_refuel_date,
                    frl.fuel_amount_liters as last_refuel_amount
                FROM fleet_vehicles v
                LEFT JOIN fuel_refueling_logs frl ON v.id = frl.vehicle_id
                WHERE v.id = ?
                ORDER BY frl.refuel_date DESC
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($vehicleQuery);
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'vehicle' => $vehicle
            ]);
            break;

        case 'get_vehicle_distance':
            $vehicleId = $_GET['vehicle_id'] ?? '';
            
            if (empty($vehicleId)) {
                echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
                break;
            }
            
            // Get total distance traveled from GPS logs for this vehicle
            $distanceQuery = "
                SELECT 
                    COALESCE(SUM(COUNT(*) * 0.1), 0) as total_distance_km
                FROM gps_logs g
                INNER JOIN gps_devices gd ON g.device_id = gd.device_id
                INNER JOIN fleet_vehicles v ON gd.vehicle_id = v.id
                WHERE v.id = ?
                AND g.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND g.is_deleted = 0
                GROUP BY v.id
            ";
            
            $stmt = $pdo->prepare($distanceQuery);
            $stmt->execute([$vehicleId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $distance = $result ? floatval($result['total_distance_km']) : 0;
            
            echo json_encode([
                'success' => true,
                'distance' => $distance,
                'period' => 'Last 30 days'
            ]);
            break;

        case 'update_fuel_from_gps':
            // First, let's check what GPS data we have
            $checkQuery = "
                SELECT 
                    gd.vehicle_id,
                    v.article,
                    v.plate_number,
                    COUNT(g.id) as gps_points,
                    COUNT(g.id) * 0.1 as distance_km,
                    MAX(g.timestamp) as latest_gps
                FROM gps_logs g
                INNER JOIN gps_devices gd ON g.device_id = gd.device_id
                INNER JOIN fleet_vehicles v ON gd.vehicle_id = v.id
                WHERE g.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND g.is_deleted = 0
                AND v.article NOT LIKE 'Synthetic%'
                AND v.plate_number NOT LIKE 'SYN-%'
                GROUP BY gd.vehicle_id
                HAVING gps_points > 0
            ";
            
            $stmt = $pdo->query($checkQuery);
            $gpsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($gpsData)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No GPS data found in the last 7 days',
                    'vehicles_updated' => 0
                ]);
                break;
            }
            
            $vehiclesUpdated = 0;
            $totalFuelConsumed = 0;
            
            foreach ($gpsData as $data) {
                $vehicleId = $data['vehicle_id'];
                $distance = floatval($data['distance_km']);
                $gpsPoints = intval($data['gps_points']);
                
                // Get vehicle fuel consumption rate
                $vehicleQuery = "SELECT fuel_consumption_l_per_km, current_fuel_level_liters FROM fleet_vehicles WHERE id = ?";
                $stmt = $pdo->prepare($vehicleQuery);
                $stmt->execute([$vehicleId]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle) continue;
                
                $fuelConsumption = floatval($vehicle['fuel_consumption_l_per_km']);
                $fuelConsumed = $distance * $fuelConsumption;
                $currentFuel = floatval($vehicle['current_fuel_level_liters']);
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
                
                // Log daily consumption
                $logQuery = "
                    INSERT INTO fuel_consumption_logs 
                    (vehicle_id, log_date, distance_traveled_km, fuel_consumed_liters, fuel_efficiency_km_per_l, gps_points_count)
                    VALUES (?, CURDATE(), ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        distance_traveled_km = distance_traveled_km + VALUES(distance_traveled_km),
                        fuel_consumed_liters = fuel_consumed_liters + VALUES(fuel_consumed_liters),
                        gps_points_count = gps_points_count + VALUES(gps_points_count),
                        fuel_efficiency_km_per_l = (distance_traveled_km + VALUES(distance_traveled_km)) / (fuel_consumed_liters + VALUES(fuel_consumed_liters)),
                        updated_at = CURRENT_TIMESTAMP
                ";
                
                $efficiency = $fuelConsumption > 0 ? (1 / $fuelConsumption) : 0;
                $stmt = $pdo->prepare($logQuery);
                $stmt->execute([$vehicleId, $distance, $fuelConsumed, $efficiency, $gpsPoints]);
                
                $vehiclesUpdated++;
                $totalFuelConsumed += $fuelConsumed;
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fuel consumption updated from GPS data. {$vehiclesUpdated} vehicles updated, {$totalFuelConsumed}L total consumed",
                'vehicles_updated' => $vehiclesUpdated,
                'total_fuel_consumed' => $totalFuelConsumed
            ]);
            break;

        case 'update_fuel_consumption':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['vehicle_id']) || empty($data['distance_km'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }
            
            $vehicleId = $data['vehicle_id'];
            $distance = floatval($data['distance_km']);
            
            // Get vehicle fuel consumption rate
            $vehicleQuery = "SELECT fuel_consumption_l_per_km FROM fleet_vehicles WHERE id = ?";
            $stmt = $pdo->prepare($vehicleQuery);
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
                break;
            }
            
            $fuelConsumed = $distance * floatval($vehicle['fuel_consumption_l_per_km']);
            
            // Update vehicle fuel level
            $updateQuery = "
                UPDATE fleet_vehicles 
                SET 
                    current_fuel_level_liters = GREATEST(0, current_fuel_level_liters - ?),
                    total_fuel_consumed_liters = total_fuel_consumed_liters + ?
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$fuelConsumed, $fuelConsumed, $vehicleId]);
            
            // Log daily consumption
            $logQuery = "
                INSERT INTO fuel_consumption_logs 
                (vehicle_id, log_date, distance_traveled_km, fuel_consumed_liters, fuel_efficiency_km_per_l)
                VALUES (?, CURDATE(), ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    distance_traveled_km = distance_traveled_km + VALUES(distance_traveled_km),
                    fuel_consumed_liters = fuel_consumed_liters + VALUES(fuel_consumed_liters),
                    fuel_efficiency_km_per_l = (distance_traveled_km + VALUES(distance_traveled_km)) / (fuel_consumed_liters + VALUES(fuel_consumed_liters)),
                    updated_at = CURRENT_TIMESTAMP
            ";
            
            $efficiency = $distance / $fuelConsumed;
            $stmt = $pdo->prepare($logQuery);
            $stmt->execute([$vehicleId, $distance, $fuelConsumed, $efficiency]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Fuel consumption updated',
                'fuel_consumed' => $fuelConsumed
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
