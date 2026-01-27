<?php
require_once __DIR__ . '/../db_connection.php';

if (isset($_GET['email'])) {
    $email = trim($_GET['email']);

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "exists" => $result['cnt'] > 0
    ]);
}
?>
