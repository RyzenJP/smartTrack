<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_assignments':
            $stmt = $pdo->query("
                SELECT a.*, v.unit as vehicle_unit, v.plate_number, 
                       u.full_name as driver_name, u.phone as phone_number
                FROM vehicle_assignments a
                LEFT JOIN fleet_vehicles v ON a.vehicle_id = v.id
                LEFT JOIN user_table u ON a.driver_id = u.user_id
                ORDER BY a.date_assigned DESC
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'get_routes':
    $stmt = $pdo->query("
        SELECT * 
        FROM routes
        ORDER BY id DESC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    break;
   

        case 'get_assignment':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Assignment ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT a.*, v.unit as vehicle_unit, v.plate_number
                FROM vehicle_assignments a
                LEFT JOIN fleet_vehicles v ON a.vehicle_id = v.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assignment) {
                throw new Exception('Assignment not found');
            }
            
            echo json_encode(['success' => true, 'data' => $assignment]);
            break;

        case 'add_assignment':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Get driver details
            $stmt = $pdo->prepare("SELECT user_id, full_name, phone FROM user_table WHERE user_id = ?");
            $stmt->execute([$data['driver_id']]);
            $driver = $stmt->fetch();
            
            if (!$driver) {
                throw new Exception('Driver not found');
            }

            $stmt = $pdo->prepare("
                INSERT INTO vehicle_assignments 
                (driver_id, driver_name, phone_number, vehicle_id, status, date_assigned)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $driver['user_id'],
                $driver['full_name'],
                $driver['phone'],
                $data['vehicle_id'],
                $data['status'] ?? 'active'
            ]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update_assignment':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                throw new Exception('Assignment ID is required');
            }
            
            // Get driver details if driver_id is being updated
            if (!empty($data['driver_id'])) {
                $stmt = $pdo->prepare("SELECT full_name, phone FROM user_table WHERE user_id = ?");
                $stmt->execute([$data['driver_id']]);
                $driver = $stmt->fetch();
                
                if (!$driver) {
                    throw new Exception('Driver not found');
                }
                
                $data['driver_name'] = $driver['full_name'];
                $data['phone_number'] = $driver['phone'];
            }

            $setParts = [];
            $params = [];
            
            foreach (['driver_id', 'driver_name', 'phone_number', 'vehicle_id', 'status'] as $field) {
                if (isset($data[$field])) {
                    $setParts[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            $params[] = $data['id'];
            
            $stmt = $pdo->prepare("
                UPDATE vehicle_assignments SET 
                ".implode(', ', $setParts)."
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Assignment updated']);
            break;

        case 'delete_assignment':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Assignment ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM vehicle_assignments WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Assignment deleted']);
            break;

        case 'get_drivers':
            $stmt = $pdo->query("
                SELECT user_id, full_name, phone 
                FROM user_table 
                WHERE role = 'Driver'
                ORDER BY full_name
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_driver_phone':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Driver ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT phone FROM user_table WHERE user_id = ?");
            $stmt->execute([$id]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$driver) {
                throw new Exception('Driver not found');
            }
            
            echo json_encode(['success' => true, 'phone' => $driver['phone']]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}