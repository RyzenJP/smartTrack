<?php
// mechanic_api.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db_connection.php';

// simple role check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = strtolower($_SESSION['role']);

// Accept action via GET or POST action param
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        // Create new work order
        case 'create':
            // allow admin/dispatcher to create, but mechanics can also create for themselves
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $scheduled_start = $_POST['scheduled_start'] ?: null;
            $scheduled_end = $_POST['scheduled_end'] ?: null;

            if (!$vehicle_id || !$title) throw new Exception('Missing required fields');

            $stmt = $conn->prepare("INSERT INTO work_orders (vehicle_id, created_by, title, description, priority, scheduled_start, scheduled_end) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $vehicle_id, $userId, $title, $description, $priority, $scheduled_start, $scheduled_end);
            $stmt->execute();
            echo json_encode(['success'=>true, 'id'=>$stmt->insert_id]);
            exit;
        break;

        // Update a work order (edit fields)
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');

            // only certain roles or assigned mechanic can update; keep simple: admin or assigned mechanic
            $stmt = $conn->prepare("SELECT assigned_to FROM work_orders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $assigned = $res ? (int)$res['assigned_to'] : null;

            if (!in_array($role, ['admin','super admin']) && $assigned !== $userId) {
                throw new Exception('Not authorized to edit this order');
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $scheduled_start = $_POST['scheduled_start'] ?: null;
            $scheduled_end = $_POST['scheduled_end'] ?: null;

            $stmt = $conn->prepare("UPDATE work_orders SET vehicle_id=?, title=?, description=?, priority=?, scheduled_start=?, scheduled_end=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("isssssi", $vehicle_id, $title, $description, $priority, $scheduled_start, $scheduled_end, $id);
            $stmt->execute();

            echo json_encode(['success'=>true]);
            exit;
        break;

        // Assign a work order to a mechanic
        case 'assign':
            // only admin/dispatcher allowed
            if (!in_array($role, ['admin','super admin'])) throw new Exception('Not authorized');

            $id = intval($_POST['id'] ?? 0);
            $assign_to = intval($_POST['assign_to'] ?? 0);
            if (!$id || !$assign_to) throw new Exception('Missing id or assign_to');

            $stmt = $conn->prepare("UPDATE work_orders SET assigned_to=?, status = 'assigned', updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ii", $assign_to, $id);
            $stmt->execute();

            // insert notification for assigned mechanic - use prepared statement for security
            $msg = "You have been assigned work order #$id.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif_stmt->bind_param("is", $assign_to, $msg);
            $notif_stmt->execute();
            $notif_stmt->close();

            echo json_encode(['success'=>true]);
            exit;
        break;

        // Change status (in_progress, completed, cancelled)
        case 'change_status':
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!$id || !$status) throw new Exception('Missing id or status');

            // allow assigned mechanic or admin
            $stmt = $conn->prepare("SELECT assigned_to FROM work_orders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $assigned = $r ? (int)$r['assigned_to'] : null;

            if (!in_array($role, ['admin','super admin']) && $assigned !== $userId) {
                throw new Exception('Not authorized to change status');
            }

            $stmt = $conn->prepare("UPDATE work_orders SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();

            // notify creator and assigned mechanic - use prepared statements for security
            $noteMsg = "Work order #$id status changed to $status.";
            // get created_by and assigned_to
            $q_stmt = $conn->prepare("SELECT created_by, assigned_to FROM work_orders WHERE id = ?");
            $q_stmt->bind_param("i", $id);
            $q_stmt->execute();
            $row = $q_stmt->get_result()->fetch_assoc();
            $q_stmt->close();
            if ($row) {
                $creator = intval($row['created_by']);
                $ass = intval($row['assigned_to']);
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                if ($creator) {
                    $notif_stmt->bind_param("is", $creator, $noteMsg);
                    $notif_stmt->execute();
                }
                if ($ass) {
                    $notif_stmt->bind_param("is", $ass, $noteMsg);
                    $notif_stmt->execute();
                }
                $notif_stmt->close();
            }

            echo json_encode(['success'=>true]);
            exit;
        break;

        // Delete a work order
        case 'delete':
            // only admin or the creator or assigned mechanic can delete; keep simple: admin or super admin
            if (!in_array($role, ['admin','super admin'])) throw new Exception('Not authorized to delete');

            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');

            $stmt = $conn->prepare("DELETE FROM work_orders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            echo json_encode(['success'=>true]);
            exit;
        break;

        // Get list (with optional filters)
        case 'list':
            // optional status filter: new, assigned, completed, my-assigned etc.
            $status = $_GET['status'] ?? '';
            $filter = '';
            $params = [];
            if ($status === 'new') {
                $filter = "WHERE status = 'new'";
            } elseif ($status === 'assigned') {
                $filter = "WHERE status = 'assigned'";
            } elseif ($status === 'completed') {
                $filter = "WHERE status = 'completed'";
            } elseif ($status === 'my') {
                // assigned to me
                $filter = "WHERE assigned_to = ?";
                $params[] = $userId;
            } // else no filter

            // build query
            if (count($params) === 1) {
                $stmt = $conn->prepare("
                    SELECT wo.*, fv.article AS vehicle_article, fv.plate_number, u.full_name AS creator_name, ua.full_name AS assigned_name
                    FROM work_orders wo
                    LEFT JOIN fleet_vehicles fv ON wo.vehicle_id = fv.id
                    LEFT JOIN user_table u ON wo.created_by = u.user_id
                    LEFT JOIN user_table ua ON wo.assigned_to = ua.user_id
                    $filter
                    ORDER BY wo.created_at DESC
                ");
                $stmt->bind_param("i", $params[0]);
                $stmt->execute();
            } else {
                $sql = "
                    SELECT wo.*, fv.article AS vehicle_article, fv.plate_number, u.full_name AS creator_name, ua.full_name AS assigned_name
                    FROM work_orders wo
                    LEFT JOIN fleet_vehicles fv ON wo.vehicle_id = fv.id
                    LEFT JOIN user_table u ON wo.created_by = u.user_id
                    LEFT JOIN user_table ua ON wo.assigned_to = ua.user_id
                    $filter
                    ORDER BY wo.created_at DESC
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            }

            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode(['success'=>true,'data'=>$rows]);
            exit;
        break;

        // Get single item
        case 'get':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $stmt = $conn->prepare("SELECT wo.*, fv.article AS vehicle_article, fv.plate_number, u.full_name AS creator_name, ua.full_name AS assigned_name FROM work_orders wo LEFT JOIN fleet_vehicles fv ON wo.vehicle_id=fv.id LEFT JOIN user_table u ON wo.created_by=u.user_id LEFT JOIN user_table ua ON wo.assigned_to=ua.user_id WHERE wo.id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo json_encode(['success'=>true,'data'=>$row]);
            exit;
        break;

        default:
            echo json_encode(['success'=>false,'message'=>'Invalid action']);
            exit;
    }
} catch (Exception $ex) {
    echo json_encode(['success'=>false,'message'=>$ex->getMessage()]);
    exit;
}
