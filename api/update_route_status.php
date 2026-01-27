<?php
session_start();
require_once __DIR__ . '/../config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['route_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$routeId = intval($input['route_id']);
$status = $input['status'];
$completedAt = isset($input['completed_at']) ? $input['completed_at'] : date('Y-m-d H:i:s');

try {
    // Update route status
    $stmt = $conn->prepare("UPDATE routes SET status = ?, completed_at = ? WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ssii", $status, $completedAt, $routeId, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // If route is completed, write a trip log entry
            if (strtolower($status) === 'completed') {
                // Fetch route details for logging
                $routeInfoStmt = $conn->prepare("SELECT id, driver_id, start_lat, start_lng, end_lat, end_lng, created_at, vehicle_id FROM routes WHERE id = ? LIMIT 1");
                $routeInfoStmt->bind_param("i", $routeId);
                if ($routeInfoStmt->execute()) {
                    $routeResult = $routeInfoStmt->get_result();
                    if ($routeRow = $routeResult->fetch_assoc()) {
                        // Insert into trip_logs (idempotent on route_id if unique/PK)
                        $insertLogStmt = $conn->prepare("INSERT INTO trip_logs (route_id, driver_id, start_lat, start_lng, end_lat, end_lng, started_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)");
                        $insertLogStmt->bind_param(
                            "iiddddss",
                            $routeRow['id'],              // i
                            $routeRow['driver_id'],       // i
                            $routeRow['start_lat'],       // d
                            $routeRow['start_lng'],       // d
                            $routeRow['end_lat'],         // d
                            $routeRow['end_lng'],         // d
                            $routeRow['created_at'],      // s
                            $completedAt                  // s
                        );
                        // Execute but do not fail the whole request if logging fails
                        $insertLogStmt->execute();
                        $insertLogStmt->close();
                        
                        // Update fuel consumption for completed trip
                        if ($routeRow['vehicle_id']) {
                            $startTime = $routeRow['created_at'];
                            $endTime = $completedAt;
                            
                            // Calculate trip distance from GPS data
                            $distanceQuery = "
                                SELECT COUNT(g.id) * 0.1 as distance_km
                                FROM gps_logs g
                                INNER JOIN gps_devices gd ON g.device_id = gd.device_id
                                WHERE gd.vehicle_id = ?
                                AND g.timestamp >= ?
                                AND g.timestamp <= ?
                                AND g.is_deleted = 0
                            ";
                            
                            $distanceStmt = $conn->prepare($distanceQuery);
                            $distanceStmt->bind_param("iss", $routeRow['vehicle_id'], $startTime, $endTime);
                            $distanceStmt->execute();
                            $distanceResult = $distanceStmt->get_result()->fetch_assoc();
                            $tripDistance = $distanceResult ? floatval($distanceResult['distance_km']) : 0;
                            
                            if ($tripDistance > 0) {
                                // Get vehicle fuel consumption rate
                                $vehicleQuery = "SELECT fuel_consumption_l_per_km, current_fuel_level_liters FROM fleet_vehicles WHERE id = ?";
                                $vehicleStmt = $conn->prepare($vehicleQuery);
                                $vehicleStmt->bind_param("i", $routeRow['vehicle_id']);
                                $vehicleStmt->execute();
                                $vehicle = $vehicleStmt->get_result()->fetch_assoc();
                                
                                if ($vehicle) {
                                    $fuelConsumptionRate = floatval($vehicle['fuel_consumption_l_per_km']);
                                    $currentFuel = floatval($vehicle['current_fuel_level_liters']);
                                    $fuelConsumed = $tripDistance * $fuelConsumptionRate;
                                    $newFuelLevel = max(0, $currentFuel - $fuelConsumed);
                                    
                                    // Update vehicle fuel level
                                    $updateFuelStmt = $conn->prepare("
                                        UPDATE fleet_vehicles 
                                        SET 
                                            current_fuel_level_liters = ?,
                                            total_fuel_consumed_liters = total_fuel_consumed_liters + ?,
                                            last_updated = NOW()
                                        WHERE id = ?
                                    ");
                                    $updateFuelStmt->bind_param("ddi", $newFuelLevel, $fuelConsumed, $routeRow['vehicle_id']);
                                    $updateFuelStmt->execute();
                                    $updateFuelStmt->close();
                                    
                                    // Log daily consumption
                                    $logStmt = $conn->prepare("
                                        INSERT INTO fuel_consumption_logs 
                                        (vehicle_id, log_date, distance_traveled_km, fuel_consumed_liters, fuel_efficiency_km_per_l, gps_points_count)
                                        VALUES (?, CURDATE(), ?, ?, ?, 0)
                                        ON DUPLICATE KEY UPDATE
                                            distance_traveled_km = distance_traveled_km + VALUES(distance_traveled_km),
                                            fuel_consumed_liters = fuel_consumed_liters + VALUES(fuel_consumed_liters),
                                            fuel_efficiency_km_per_l = (distance_traveled_km + VALUES(distance_traveled_km)) / (fuel_consumed_liters + VALUES(fuel_consumed_liters)),
                                            updated_at = CURRENT_TIMESTAMP
                                    ");
                                    
                                    $efficiency = $fuelConsumptionRate > 0 ? (1 / $fuelConsumptionRate) : 0;
                                    $logStmt->bind_param("iddd", $routeRow['vehicle_id'], $tripDistance, $fuelConsumed, $efficiency);
                                    $logStmt->execute();
                                    $logStmt->close();
                                }
                            }
                        }
                    }
                }
                $routeInfoStmt->close();
            }

            // Also update any related vehicle reservations if they exist
            $updateReservationStmt = $conn->prepare("
                UPDATE vehicle_reservations 
                SET status = 'completed', completed_at = ? 
                WHERE assigned_dispatcher_id = (
                    SELECT driver_id FROM routes WHERE id = ?
                ) AND status = 'active'
            ");
            $updateReservationStmt->bind_param("si", $completedAt, $routeId);
            $updateReservationStmt->execute();
            $updateReservationStmt->close();
            
            // Make vehicle and driver available again by updating vehicle assignments
            $makeAvailableStmt = $conn->prepare("
                UPDATE vehicle_assignments 
                SET status = 'available', 
                    last_trip_completed_at = NOW(),
                    updated_at = NOW()
                WHERE driver_id = ? 
                AND status = 'active'
            ");
            $makeAvailableStmt->bind_param("i", $_SESSION['user_id']);
            $makeAvailableStmt->execute();
            $makeAvailableStmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Route status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Route not found or no changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
