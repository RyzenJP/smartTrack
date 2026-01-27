<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_route') {
    try {
        // Get form data
        $name = $_POST['name'];
        $start_lat = $_POST['start_lat'];
        $start_lng = $_POST['start_lng'];
        $end_lat = $_POST['end_lat'];
        $end_lng = $_POST['end_lng'];
        $distance = $_POST['distance'];
        $duration = $_POST['duration'];
        $driver_id = $_POST['driver_id'];
        $unit = $_POST['unit'];
        $route_type = $_POST['route_type'] ?? 'round_trip'; // Default to round trip
        $return_time = !empty($_POST['return_time']) ? $_POST['return_time'] : null;
        
        // Check for duplicate route (same driver, same name, or same coordinates within 1 hour)
        // Allow multiple routes with different names or after 1 hour
        $checkStmt = $conn->prepare("
            SELECT id FROM routes 
            WHERE driver_id = ? 
            AND (
                (name = ? AND (is_deleted = 0 OR is_deleted IS NULL))
                OR 
                (start_lat = ? AND start_lng = ? AND end_lat = ? AND end_lng = ? 
                 AND (is_deleted = 0 OR is_deleted IS NULL)
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR))
            )
        ");
        $checkStmt->bind_param("isdddd", $driver_id, $name, $start_lat, $start_lng, $end_lat, $end_lng);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = [
                'type' => 'warning',
                'text' => 'A route with the same name already exists for this driver, or a similar route was created recently. Please use a different route name or wait at least 1 hour.'
            ];
        } else {
            // Insert into route table
            $stmt = $conn->prepare("
                INSERT INTO routes (
                    name, start_lat, start_lng, end_lat, end_lng, 
                    distance, duration, driver_id, unit, route_type, return_time, status, is_deleted, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
            ");
            
            $stmt->bind_param(
                "sddddssssss",
                $name, $start_lat, $start_lng, $end_lat, $end_lng,
                $distance, $duration, $driver_id, $unit, $route_type, $return_time
            );
            
            if ($stmt->execute()) {
                $routeTypeText = $route_type === 'round_trip' ? 'Round trip route' : 'Route';
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => $routeTypeText . ' saved successfully!'
                ];
            } else {
                throw new Exception("Failed to save route: " . $conn->error);
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Error: ' . $e->getMessage()
        ];
    }
}

header("Location: active-routes.php");
exit();
?>