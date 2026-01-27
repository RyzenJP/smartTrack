<?php
header('Content-Type: application/json');
require_once '../db_connection.php';

// Secure CORS configuration
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true); // Allow credentials for authenticated requests

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message], $status);
}

try {
    switch ($method) {
        case 'GET':
            // Get reservations with filters
            require_once __DIR__ . '/../config/security.php';
            $security = Security::getInstance();
            
            $status = $security->getGet('status', 'string', 'all');
            $user_id = $security->getGet('user_id', 'int', null);
            $dispatcher_id = $security->getGet('dispatcher_id', 'int', null);
            
            $where_conditions = [];
            $params = [];
            $types = '';
            
            if ($status !== 'all') {
                $where_conditions[] = "status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            if ($user_id) {
                $where_conditions[] = "created_by = ?";
                $params[] = $user_id;
                $types .= 'i';
            }
            
            if ($dispatcher_id) {
                $where_conditions[] = "assigned_dispatcher_id = ?";
                $params[] = $dispatcher_id;
                $types .= 'i';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $sql = "SELECT vr.*, fv.article, fv.unit, fv.plate_number, fv.status as vehicle_status,
                           ut.full_name as dispatcher_name, ut.email as dispatcher_email
                    FROM vehicle_reservations vr 
                    LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id 
                    LEFT JOIN user_table ut ON vr.assigned_dispatcher_id = ut.id
                    $where_clause 
                    ORDER BY vr.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reservations = [];
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
            
            sendResponse(['reservations' => $reservations]);
            break;
            
        case 'POST':
            // Create new reservation
            $required_fields = ['requester_name', 'contact', 'purpose', 'origin', 'destination', 'start_datetime', 'end_datetime'];
            
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendError("Missing required field: $field");
                }
            }
            
            // Get the single dispatcher (excluding super_admin) for auto-assignment
            $dispatcher_id = null;
            $status = 'pending'; // Default status
            
            // Use prepared statement for consistency (static query but best practice)
            $dispatcher_stmt = $conn->prepare("SELECT user_id FROM user_table WHERE role = 'dispatcher' AND role != 'super admin' LIMIT 1");
            $dispatcher_stmt->execute();
            $dispatcher_result = $dispatcher_stmt->get_result();
            
            if ($dispatcher_result && $dispatcher_result->num_rows > 0) {
                $dispatcher = $dispatcher_result->fetch_assoc();
                $dispatcher_id = $dispatcher['user_id'];
                $status = 'assigned'; // Auto-assign immediately
            }
            $dispatcher_stmt->close();
            
            $sql = "INSERT INTO vehicle_reservations (requester_name, department, contact, purpose, origin, destination, start_datetime, end_datetime, passengers, status, notes, created_by, assigned_dispatcher_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssissii", 
                $input['requester_name'],
                $input['department'] ?? null,
                $input['contact'],
                $input['purpose'],
                $input['origin'],
                $input['destination'],
                $input['start_datetime'],
                $input['end_datetime'],
                $input['passengers'] ?? 1,
                $status,
                $input['notes'] ?? null,
                $input['created_by'] ?? null,
                $dispatcher_id
            );
            
            if ($stmt->execute()) {
                $message = $dispatcher_id ? 'Reservation created and automatically assigned to dispatcher!' : 'Reservation created successfully';
                sendResponse(['message' => $message, 'id' => $conn->insert_id], 201);
            } else {
                sendError('Failed to create reservation');
            }
            break;
            
        case 'PUT':
            // Update reservation
            $reservation_id = $_GET['id'] ?? null;
            if (!$reservation_id) {
                sendError('Reservation ID required');
            }
            
            // If status is being set to 'approved' or 'assigned' and no dispatcher is assigned, auto-assign
            if (isset($input['status']) && ($input['status'] === 'approved' || $input['status'] === 'assigned') && !isset($input['assigned_dispatcher_id'])) {
                // Get the single dispatcher (excluding super_admin) - use prepared statement
                $dispatcher_stmt = $conn->prepare("SELECT user_id FROM user_table WHERE role = 'dispatcher' AND role != 'super admin' LIMIT 1");
                $dispatcher_stmt->execute();
                $dispatcher_result = $dispatcher_stmt->get_result();
                
                if ($dispatcher_result && $dispatcher_result->num_rows > 0) {
                    $dispatcher = $dispatcher_result->fetch_assoc();
                    $input['assigned_dispatcher_id'] = $dispatcher['user_id'];
                    $input['status'] = 'assigned'; // Always set to assigned when auto-assigning
                }
                $dispatcher_stmt->close();
            }
            
            $allowed_fields = ['status', 'assigned_dispatcher_id', 'vehicle_id', 'notes'];
            $update_fields = [];
            $params = [];
            $types = '';
            
            foreach ($allowed_fields as $field) {
                if (isset($input[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $input[$field];
                    $types .= 's';
                }
            }
            
            if (empty($update_fields)) {
                sendError('No valid fields to update');
            }
            
            $params[] = $reservation_id;
            $types .= 'i';
            
            $sql = "UPDATE vehicle_reservations SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                sendResponse(['message' => 'Reservation updated successfully']);
            } else {
                sendError('Failed to update reservation');
            }
            break;
            
        case 'DELETE':
            // Delete reservation (soft delete by changing status)
            $reservation_id = $_GET['id'] ?? null;
            if (!$reservation_id) {
                sendError('Reservation ID required');
            }
            
            $sql = "UPDATE vehicle_reservations SET status = 'cancelled' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $reservation_id);
            
            if ($stmt->execute()) {
                sendResponse(['message' => 'Reservation cancelled successfully']);
            } else {
                sendError('Failed to cancel reservation');
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
?>
