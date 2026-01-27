<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connection.php';
session_start();
// Use prepared statement for consistency (static query but best practice)
$stmt = $conn->prepare("SELECT id, article, plate_number FROM fleet_vehicles ORDER BY article ASC");
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while($r = $result->fetch_assoc()) $data[] = $r;
$stmt->close();
echo json_encode(['success'=>true,'data'=>$data]);
