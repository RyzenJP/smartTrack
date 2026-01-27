<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Clear security log
if (file_exists('security.log')) {
    file_put_contents('security.log', '');
    echo json_encode(['success' => true, 'message' => 'Security log cleared']);
} else {
    echo json_encode(['success' => false, 'message' => 'Security log not found']);
}
?>
