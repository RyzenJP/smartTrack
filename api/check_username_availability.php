<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$current_user_id = intval($_POST['current_user_id'] ?? 0);

if (empty($username)) {
    echo json_encode(['available' => false, 'message' => 'Username is required']);
    exit;
}

// Determine which table to check based on current user's role
$role = $_SESSION['role'] ?? '';
$isRequester = ($role === 'Requester');
$table = $isRequester ? 'reservation_users' : 'user_table';
$idColumn = $isRequester ? 'id' : 'user_id';

// Check if username exists (excluding current user)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE username = ? AND $idColumn != ?");
$stmt->bind_param("si", $username, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$available = ($row['count'] == 0);

echo json_encode([
    'available' => $available,
    'message' => $available ? 'Username is available' : 'Username is already taken'
]);
?>
