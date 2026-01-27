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
        case 'save_route':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['name', 'start_lat', 'start_lng', 'end_lat', 'end_lng', 'driver_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO routes 
                (name, start_lat, start_lng, end_lat, end_lng, distance, duration, driver_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['name'],
                $data['start_lat'],
                $data['start_lng'],
                $data['end_lat'],
                $data['end_lng'],
                $data['distance'],
                $data['duration'],
                $data['driver_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Route saved successfully',
                'id' => $pdo->lastInsertId()
            ]);
            break;

        case 'update_route':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                throw new Exception("Route ID is required for update");
            }
            
            $stmt = $pdo->prepare("
                UPDATE routes SET
                name = ?,
                start_lat = ?,
                start_lng = ?,
                end_lat = ?,
                end_lng = ?,
                distance = ?,
                duration = ?,
                driver_id = ?,
                updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['start_lat'],
                $data['start_lng'],
                $data['end_lat'],
                $data['end_lng'],
                $data['distance'],
                $data['duration'],
                $data['driver_id'],
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Route updated successfully'
            ]);
            break;

        case 'get_routes':
            // Use prepared statement for consistency (static query but best practice)
            $stmt = $pdo->prepare("
                SELECT r.*, a.driver_name, a.plate_number 
                FROM routes r
                LEFT JOIN vehicle_assignments a ON r.driver_id = a.id
                ORDER BY r.created_at DESC
            ");
            $stmt->execute();
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $routes]);
            break;

       case 'get_route':
            // Use prepared statement for consistency (static query but best practice)
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    COALESCE(u.full_name, 'No Driver Assigned') AS driver_name
                FROM routes r
                LEFT JOIN vehicle_assignments va ON r.driver_id = va.driver_id
                LEFT JOIN user_table u ON va.driver_id = u.user_id
                ORDER BY r.id DESC
            ");
            $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;



        case 'delete_route':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception("Route ID is required");
            }
            
            $stmt = $pdo->prepare("DELETE FROM routes WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Route deleted successfully'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}