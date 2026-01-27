<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_fleet':
            $stmt = $pdo->query("
                SELECT * FROM fleet_vehicles 
                WHERE article NOT LIKE '%Synthetic%' 
                AND plate_number NOT LIKE 'SYN-%'
                ORDER BY last_updated DESC
            ");
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;

        case 'get_fleet_filtered':
            // Get fleet vehicles excluding synthetic vehicles
            $stmt = $pdo->query("
                SELECT id, article, plate_number, status FROM fleet_vehicles 
                WHERE article NOT LIKE '%Synthetic%' 
                AND plate_number NOT LIKE '%SYN%'
                AND status = 'active'
                ORDER BY article ASC
            ");
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;

        case 'add_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO fleet_vehicles (article, unit, plate_number, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['article'],
                $data['unit'],
                $data['plate_number'],
                $data['status']
            ]);
            echo json_encode(['success' => true, 'message' => 'Vehicle added successfully']);
            break;

        case 'update_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE fleet_vehicles SET article=?, unit=?, plate_number=?, status=? WHERE id=?");
            $stmt->execute([
                $data['article'],
                $data['unit'],
                $data['plate_number'],
                $data['status'],
                $data['id']
            ]);

            if (!empty($data['status_changed'])) {
                $stmt = $pdo->prepare("INSERT INTO fleet_status_history (vehicle_id, status, notes) VALUES (?, ?, ?)");
                $stmt->execute([
                    $data['id'],
                    $data['status'],
                    $data['notes'] ?? 'Status updated'
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
            break;

        case 'delete_vehicle':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Vehicle ID is required');
            }

            $stmt = $pdo->prepare("DELETE FROM fleet_vehicles WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
