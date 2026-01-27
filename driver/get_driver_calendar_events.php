<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a driver
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_connection.php';

try {
    $driverId = $_SESSION['user_id'];

    // Get all routes for this driver
    $sql = "SELECT 
                r.id,
                r.name as route_name,
                r.start_lat,
                r.start_lng,
                r.end_lat,
                r.end_lng,
                r.distance,
                r.duration,
                r.status,
                r.created_at,
                r.updated_at,
                r.unit,
                fv.plate_number
            FROM routes r
            LEFT JOIN fleet_vehicles fv ON r.unit = fv.unit
            WHERE r.driver_id = ? AND r.is_deleted = 0
            ORDER BY r.created_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $events = [];

    while ($row = $result->fetch_assoc()) {
        // Determine event color based on status
        $eventClass = 'event-' . $row['status'];
        
        // Create event title
        $title = $row['route_name'] ?? 'Route #' . $row['id'];
        if ($row['unit']) {
            $title .= ' - ' . $row['unit'];
        }

        // Create event description for tooltip
        $description = "Route: " . ($row['route_name'] ?? 'N/A') . "\n";
        $description .= "Unit: " . ($row['unit'] ?? 'N/A') . "\n";
        $description .= "Distance: " . ($row['distance'] ?? 'N/A') . "\n";
        $description .= "Duration: " . ($row['duration'] ?? 'N/A') . "\n";
        $description .= "Status: " . ucfirst($row['status']);

        $event = [
            'id' => $row['id'],
            'title' => $title,
            'start' => $row['created_at'],
            'end' => $row['updated_at'],
            'className' => $eventClass,
            'description' => $description,
            'extendedProps' => [
                'id' => $row['id'],
                'route_name' => $row['route_name'],
                'unit' => $row['unit'],
                'plate_number' => $row['plate_number'],
                'distance' => $row['distance'],
                'duration' => $row['duration'],
                'status' => $row['status'],
                'start_lat' => $row['start_lat'],
                'start_lng' => $row['start_lng'],
                'end_lat' => $row['end_lat'],
                'end_lng' => $row['end_lng']
            ]
        ];

        $events[] = $event;
    }

    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>

