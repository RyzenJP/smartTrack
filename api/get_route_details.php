<?php
require_once __DIR__ . '/../../db_connection.php';

header('Content-Type: application/json');

$routeId = $_GET['id'] ?? 0;

$query = "SELECT r.*, v.article as vehicle_name, v.plate_number, 
                 u.full_name as driver_name, u.phone as driver_phone
          FROM routes r
          LEFT JOIN fleet_vehicles v ON r.vehicle_id = v.id
          LEFT JOIN user_table u ON r.driver_id = u.user_id
          WHERE r.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $routeId);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_assoc());
?>