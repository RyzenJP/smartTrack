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
        case 'get_routes':
            // Use prepared statement for consistency (static query but best practice)
            $stmt = $pdo->prepare("
                SELECT r.*, a.driver_name 
                FROM routes r
                LEFT JOIN vehicle_assignments a ON r.driver_id = a.id
                ORDER BY r.route_name
            ");
            $stmt->execute();
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $routes]);
            break;

        case 'get_routes':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Route ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM routes 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $route]);
            break;

        case 'add_route':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                INSERT INTO routes 
                (route_name, point_a, point_b, distance, travel_time, driver_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['route_name'],
                $data['point_a'],
                $data['point_b'],
                $data['distance'],
                $data['travel_time'],
                $data['driver_id'],
                $data['status']
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Route added successfully',
                'id' => $pdo->lastInsertId()
            ]);
            break;

        case 'update_route':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                UPDATE routes SET 
                route_name = ?,
                point_a = ?,
                point_b = ?,
                distance = ?,
                travel_time = ?,
                driver_id = ?,
                status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['route_name'],
                $data['point_a'],
                $data['point_b'],
                $data['distance'],
                $data['travel_time'],
                $data['driver_id'],
                $data['status'],
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Route updated successfully'
            ]);
            break;

        case 'delete_route':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Route ID is required');
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