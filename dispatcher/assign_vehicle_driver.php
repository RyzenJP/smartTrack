<?php
session_start();
require_once '../db_connection.php';

// Check if user is dispatcher
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reservation_id = (int)$_POST['reservation_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'assign' && isset($_POST['assignment_id'])) {
            $assignment_id = (int)$_POST['assignment_id'];
            
            // Get the vehicle_id and driver_id from the assignment
            $assign_query = "SELECT vehicle_id, driver_id FROM vehicle_assignments WHERE id = ? AND status = 'active'";
            $assign_stmt = $conn->prepare($assign_query);
            $assign_stmt->bind_param("i", $assignment_id);
            $assign_stmt->execute();
            $assign_result = $assign_stmt->get_result();
            
            if ($assign_result->num_rows > 0) {
                $assignment = $assign_result->fetch_assoc();
                $vehicle_id = $assignment['vehicle_id'];
                $driver_id = $assignment['driver_id'];
                
                // Update the reservation with both vehicle and driver
                $update_sql = "UPDATE vehicle_reservations 
                              SET vehicle_id = ?, assigned_driver_id = ? 
                              WHERE id = ? AND assigned_dispatcher_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("iiii", $vehicle_id, $driver_id, $reservation_id, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Vehicle and driver assigned successfully!";
                    header("Location: assigned_reservations.php");
                    exit();
                } else {
                    $error = "Error assigning vehicle and driver.";
                }
            } else {
                $error = "Invalid assignment selected.";
            }
        } else {
            $error = "Invalid action or missing data.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// If there's an error, redirect back with error message
if ($error) {
    $_SESSION['error_message'] = $error;
}
header("Location: assigned_reservations.php");
exit();
?>


