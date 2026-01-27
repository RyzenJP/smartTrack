<?php
require_once __DIR__ . '/../db_connection.php';

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_table WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "exists" => $result['cnt'] > 0
    ]);
}
?>
