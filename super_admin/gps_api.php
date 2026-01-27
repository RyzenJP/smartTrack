<?php
// gps_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

try {
    // Use existing database connection
    global $conn;
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_devices':
    $stmt = $pdo->query("
        SELECT g.*, f.article as vehicle_unit, f.plate_number as vehicle_plate
        FROM gps_devices g
        LEFT JOIN fleet_vehicles f ON g.vehicle_id = f.id
        ORDER BY g.device_id
    ");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $devices]);
    break;

        case 'get_device':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Device ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM gps_devices 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $device]);
            break;


        case 'add_device':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['device_id']) || empty($data['imei'])) {
                throw new Exception('Device ID and IMEI are required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO gps_devices 
                (device_id, imei, vehicle_id, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['device_id'],
                $data['imei'],
                $data['vehicle_id'] ?: null,
                $data['status'] ?? 'active'
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'GPS device added successfully',
                'id' => $pdo->lastInsertId()
            ]);
            break;

        case 'update_device':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                throw new Exception('Device ID is required for update');
            }
            
            $stmt = $pdo->prepare("
                UPDATE gps_devices SET 
                device_id = ?,
                imei = ?,
                vehicle_id = ?,
                status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['device_id'],
                $data['imei'],
                $data['vehicle_id'] ?: null,
                $data['status'],
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'GPS device updated successfully'
            ]);
            break;

        case 'delete_device':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Device ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM gps_devices WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'GPS device deleted successfully'
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