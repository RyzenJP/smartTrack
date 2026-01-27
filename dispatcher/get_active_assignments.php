<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_connection.php';

try {
    // Check if specific driver_id is requested - use prepared statement for security
    $driverId = null;
    if (isset($_GET['driver_id']) && !empty($_GET['driver_id'])) {
        $driverId = intval($_GET['driver_id']);
    }
    
    // Get active assignments with driver and vehicle details - use prepared statement
    $query = "
        SELECT 
            va.id as assignment_id,
            va.driver_id,
            u.full_name as driver_name,
            u.phone as driver_phone,
            va.vehicle_id,
            v.unit as vehicle_unit,
            v.plate_number,
            v.article as vehicle_type,
            va.status as assignment_status,
            va.date_assigned
        FROM vehicle_assignments va
        INNER JOIN user_table u ON va.driver_id = u.user_id
        INNER JOIN fleet_vehicles v ON va.vehicle_id = v.id
        WHERE va.status = 'active' 
        AND u.role = 'Driver' 
        AND u.status = 'Active'";
    
    if ($driverId !== null) {
        $query .= " AND va.driver_id = ?";
    }
    
    $query .= " ORDER BY va.date_assigned DESC";
    
    $stmt = $conn->prepare($query);
    if ($driverId !== null) {
        $stmt->bind_param("i", $driverId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = [
                'assignment_id' => $row['assignment_id'],
                'driver_id' => $row['driver_id'],
                'driver_name' => $row['driver_name'],
                'driver_phone' => $row['driver_phone'],
                'vehicle_id' => $row['vehicle_id'],
                'vehicle_unit' => $row['vehicle_unit'],
                'plate_number' => $row['plate_number'],
                'vehicle_type' => $row['vehicle_type'],
                'assignment_status' => $row['assignment_status'],
                'date_assigned' => $row['date_assigned']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'assignments' => $assignments,
            'count' => count($assignments)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'assignments' => [],
            'count' => 0,
            'message' => 'No active assignments found'
        ]);
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
