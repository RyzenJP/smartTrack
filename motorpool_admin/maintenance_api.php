<?php
// maintenance_api.php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/db_connection.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// FETCH EVENTS
if ($method === 'GET') {
    // Use prepared statement for consistency (static query but best practice)
    $stmt = $conn->prepare("SELECT * FROM maintenance_schedule");
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            "id" => $row['id'],
            "title" => ucfirst(str_replace('_', ' ', $row['maintenance_type'])),
            "start" => $row['scheduled_date'] . 'T' . $row['start_time'],
            "end" => $row['scheduled_date'] . 'T' . $row['end_time'],
            "extendedProps" => $row
        ];
    }
    $stmt->close();
    echo json_encode($events);
    exit;
}

// CREATE or UPDATE
if ($method === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $vehicle_id = $_POST['vehicle_id'];
    $maintenance_type = $_POST['maintenance_type'];
    $scheduled_date = $_POST['scheduled_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $assigned_mechanic = $_POST['assigned_mechanic'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE maintenance_schedule SET vehicle_id=?, maintenance_type=?, scheduled_date=?, start_time=?, end_time=?, status=?, notes=?, assigned_mechanic=? WHERE id=?");
        $stmt->bind_param("issssssii", $vehicle_id, $maintenance_type, $scheduled_date, $start_time, $end_time, $status, $notes, $assigned_mechanic, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO maintenance_schedule (vehicle_id, maintenance_type, scheduled_date, start_time, end_time, status, notes, assigned_mechanic) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssi", $vehicle_id, $maintenance_type, $scheduled_date, $start_time, $end_time, $status, $notes, $assigned_mechanic);
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
    exit;
}

// DELETE
if ($method === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = intval($_DELETE['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM maintenance_schedule WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Missing ID"]);
    }
    exit;
}

$conn->close();
