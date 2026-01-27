<?php
// Include database connection
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

try {

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_fleet':
            // Use caching for frequently accessed fleet data
            require_once __DIR__ . '/../includes/cache_helper.php';
            $cache = new CacheHelper('cache', 300); // Cache for 5 minutes
            $cacheKey = 'fleet_vehicles_list';
            
            $vehicles = $cache->get($cacheKey);
            if ($vehicles === null) {
                // Cache miss - fetch from database
                $stmt = $conn->prepare("
                    SELECT * FROM fleet_vehicles 
                    WHERE article NOT LIKE '%Synthetic%' 
                    AND plate_number NOT LIKE 'SYN-%'
                    ORDER BY last_updated DESC
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                $vehicles = [];
                while ($row = $result->fetch_assoc()) {
                    $vehicles[] = $row;
                }
                $stmt->close();
                $cache->set($cacheKey, $vehicles, 300); // Cache for 5 minutes
            }
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;

        case 'get_fleet_filtered':
            // Get fleet vehicles excluding synthetic vehicles - use prepared statement for consistency
            $stmt = $conn->prepare("
                SELECT id, article, plate_number, status FROM fleet_vehicles 
                WHERE article NOT LIKE '%Synthetic%' 
                AND plate_number NOT LIKE '%SYN%'
                AND status = 'active'
                ORDER BY article ASC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $vehicles = [];
            while ($row = $result->fetch_assoc()) {
                $vehicles[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;

        case 'add_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Ensure numeric fields are properly cast
            $fuel_consumption = isset($data['fuel_consumption_l_per_km']) && $data['fuel_consumption_l_per_km'] !== '' 
                ? floatval($data['fuel_consumption_l_per_km']) : 0.1;
            $tank_capacity = isset($data['fuel_tank_capacity_liters']) && $data['fuel_tank_capacity_liters'] !== '' 
                ? floatval($data['fuel_tank_capacity_liters']) : 50.0;
            $current_fuel = isset($data['current_fuel_level_liters']) && $data['current_fuel_level_liters'] !== '' 
                ? floatval($data['current_fuel_level_liters']) : 50.0;
            $fuel_cost = isset($data['fuel_cost_per_liter']) && $data['fuel_cost_per_liter'] !== '' 
                ? floatval($data['fuel_cost_per_liter']) : 0.0;
            
            // Calculate fuel efficiency if not provided
            $fuel_efficiency = isset($data['fuel_efficiency_km_per_l']) && $data['fuel_efficiency_km_per_l'] !== '' 
                ? floatval($data['fuel_efficiency_km_per_l']) 
                : ($fuel_consumption > 0 ? (1 / $fuel_consumption) : 10.0);
            
            $stmt = $conn->prepare("
                INSERT INTO fleet_vehicles 
                (article, unit, plate_number, status, fuel_consumption_l_per_km, fuel_tank_capacity_liters, 
                 current_fuel_level_liters, fuel_efficiency_km_per_l, fuel_cost_per_liter, last_updated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssssddddd", 
                $data['article'],
                $data['unit'],
                $data['plate_number'],
                $data['status'],
                $fuel_consumption,
                $tank_capacity,
                $current_fuel,
                $fuel_efficiency,
                $fuel_cost
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to add vehicle: ' . $stmt->error);
            }
            
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Vehicle added successfully']);
            break;

        case 'update_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['id'])) {
                throw new Exception('Vehicle ID is required');
            }
            
            // Ensure numeric fields are properly cast
            $fuel_consumption = isset($data['fuel_consumption_l_per_km']) && $data['fuel_consumption_l_per_km'] !== '' 
                ? floatval($data['fuel_consumption_l_per_km']) : 0.1;
            $tank_capacity = isset($data['fuel_tank_capacity_liters']) && $data['fuel_tank_capacity_liters'] !== '' 
                ? floatval($data['fuel_tank_capacity_liters']) : 50.0;
            $current_fuel = isset($data['current_fuel_level_liters']) && $data['current_fuel_level_liters'] !== '' 
                ? floatval($data['current_fuel_level_liters']) : 50.0;
            $fuel_cost = isset($data['fuel_cost_per_liter']) && $data['fuel_cost_per_liter'] !== '' 
                ? floatval($data['fuel_cost_per_liter']) : 0.0;
            
            // Calculate fuel efficiency if not provided
            $fuel_efficiency = isset($data['fuel_efficiency_km_per_l']) && $data['fuel_efficiency_km_per_l'] !== '' 
                ? floatval($data['fuel_efficiency_km_per_l']) 
                : ($fuel_consumption > 0 ? (1 / $fuel_consumption) : 10.0);
            
            $stmt = $conn->prepare("
                UPDATE fleet_vehicles 
                SET article=?, unit=?, plate_number=?, status=?, 
                    fuel_consumption_l_per_km=?, fuel_tank_capacity_liters=?, 
                    current_fuel_level_liters=?, fuel_efficiency_km_per_l=?, fuel_cost_per_liter=?,
                    last_updated=NOW()
                WHERE id=?
            ");
            $stmt->bind_param("ssssdddddi", 
                $data['article'],
                $data['unit'],
                $data['plate_number'],
                $data['status'],
                $fuel_consumption,
                $tank_capacity,
                $current_fuel,
                $fuel_efficiency,
                $fuel_cost,
                $data['id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update vehicle: ' . $stmt->error);
            }
            
            $stmt->close();

            if (!empty($data['status_changed'])) {
                $stmt2 = $conn->prepare("INSERT INTO fleet_status_history (vehicle_id, status, notes, created_at) VALUES (?, ?, ?, NOW())");
                $notes = !empty($data['notes']) ? $data['notes'] : 'Status updated';
                $stmt2->bind_param("iss", $data['id'], $data['status'], $notes);
                $stmt2->execute();
                $stmt2->close();
            }

            echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
            break;

        case 'delete_vehicle':
            // Sanitize and validate input
            require_once __DIR__ . '/../config/security.php';
            $security = Security::getInstance();
            $id = (int)$security->sanitizeInput($_GET['id'] ?? '', 'int');
            if ($id <= 0) {
                throw new Exception('Invalid vehicle ID');
            }

            $stmt = $conn->prepare("DELETE FROM fleet_vehicles WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Clear cache on deletion
            require_once __DIR__ . '/../includes/cache_helper.php';
            $cache = new CacheHelper('cache', 300);
            $cache->delete('fleet_vehicles_list');
            
            echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
    // Cleanup to prevent memory leaks
    $conn->close();
    
    // Clear cache on data modifications
    if (in_array($action, ['add_vehicle', 'update_vehicle'])) {
        require_once __DIR__ . '/../includes/cache_helper.php';
        $cache = new CacheHelper('cache', 300);
        $cache->delete('fleet_vehicles_list');
    }

} catch (Exception $e) {
    // Cleanup on error
    if (isset($conn)) {
        $conn->close();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
