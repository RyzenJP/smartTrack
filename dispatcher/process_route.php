<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db_connection.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // Add New Route
            $driver_id = $_POST['driver_id'];
            $vehicle_id = $_POST['vehicle_id'];
            $start_lat = $_POST['start_lat'];
            $start_lng = $_POST['start_lng'];
            $end_lat = $_POST['end_lat'];
            $end_lng = $_POST['end_lng'];
            $estimated_arrival = $_POST['estimated_arrival'];
            
            // Assuming default status is 'scheduled' for new routes
            $status = 'scheduled';

            $stmt = $conn->prepare("INSERT INTO routes (driver_id, vehicle_id, start_lat, start_lng, end_lat, end_lng, status, estimated_arrival) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $driver_id, $vehicle_id, $start_lat, $start_lng, $end_lat, $end_lng, $status, $estimated_arrival);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Route added successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error adding route: ' . $conn->error];
            }
            $stmt->close();
            break;
            
        case 'edit':
            // Edit Existing Route
            $route_id = $_POST['route_id'];
            $driver_id = $_POST['driver_id'];
            $vehicle_id = $_POST['vehicle_id'];
            $start_lat = $_POST['start_lat'];
            $start_lng = $_POST['start_lng'];
            $end_lat = $_POST['end_lat'];
            $end_lng = $_POST['end_lng'];
            $estimated_arrival = $_POST['estimated_arrival'];

            $stmt = $conn->prepare("UPDATE routes SET driver_id = ?, vehicle_id = ?, start_lat = ?, start_lng = ?, end_lat = ?, end_lng = ?, estimated_arrival = ? WHERE id = ?");
            $stmt->bind_param("iisssssi", $driver_id, $vehicle_id, $start_lat, $start_lng, $end_lat, $end_lng, $estimated_arrival, $route_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Route updated successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error updating route: ' . $conn->error];
            }
            $stmt->close();
            break;
            
        case 'delete':
            // Delete Route
            $route_id = $_POST['route_id'];
            
            $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
            $stmt->bind_param("i", $route_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Route deleted successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting route: ' . $conn->error];
            }
            $stmt->close();
            break;
            
        default:
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid action specified.'];
            break;
    }
}

// Redirect back to the active routes page
header("Location: active-routes.php");
exit;
?>