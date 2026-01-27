<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a dispatcher
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_connection.php';

try {
    // Get filter parameters
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $myOnly = isset($_GET['myOnly']) && $_GET['myOnly'] == '1';
    $currentUserId = $_SESSION['user_id'];

    // Build the SQL query with prepared statement for security
    $sql = "SELECT 
                vr.id,
                vr.requester_name,
                vr.department,
                vr.contact,
                vr.purpose,
                vr.origin,
                vr.destination,
                vr.start_datetime,
                vr.end_datetime,
                vr.passengers,
                vr.status,
                vr.notes,
                vr.assigned_dispatcher_id,
                CONCAT(fv.article, ' - ', fv.plate_number) as vehicle_info
            FROM vehicle_reservations vr
            LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id
            WHERE 1=1";

    $params = [];
    $types = '';

    // Apply filters using prepared statement
    if ($statusFilter !== 'all') {
        $sql .= " AND vr.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    } else {
        // Exclude 'approved' from default results per business rule
        $sql .= " AND vr.status <> 'approved'";
    }

    if ($myOnly) {
        $sql .= " AND vr.assigned_dispatcher_id = ?";
        $params[] = $currentUserId;
        $types .= 'i';
    }

    $sql .= " ORDER BY vr.start_datetime ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
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
        $title = $row['requester_name'];
        if ($row['purpose']) {
            $title .= ' - ' . substr($row['purpose'], 0, 30);
            if (strlen($row['purpose']) > 30) {
                $title .= '...';
            }
        }

        // Create event description for tooltip
        $description = "Requester: " . $row['requester_name'] . "\n";
        $description .= "Purpose: " . ($row['purpose'] ?? 'N/A') . "\n";
        $description .= "From: " . ($row['origin'] ?? 'N/A') . " → To: " . ($row['destination'] ?? 'N/A') . "\n";
        $description .= "Status: " . ucfirst($row['status']);

        $event = [
            'id' => $row['id'],
            'title' => $title,
            'start' => $row['start_datetime'],
            'end' => $row['end_datetime'],
            'className' => $eventClass,
            'description' => $description,
            'extendedProps' => [
                'id' => $row['id'],
                'requester_name' => $row['requester_name'],
                'department' => $row['department'],
                'contact' => $row['contact'],
                'purpose' => $row['purpose'],
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'passengers' => $row['passengers'],
                'status' => $row['status'],
                'notes' => $row['notes'],
                'vehicle_info' => $row['vehicle_info'],
                'assigned_to_me' => ($row['assigned_dispatcher_id'] == $currentUserId)
            ]
        ];

        $events[] = $event;
    }

    echo json_encode($events);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>

