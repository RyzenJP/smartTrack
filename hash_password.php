<?php
require 'db_connection.php';

// Fetch all users - use prepared statement for consistency
$stmt = $conn->prepare("SELECT user_id, password FROM user_table");
$stmt->execute();
$result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {
    $userId = $user['user_id'];
    $plainPassword = $user['password'];

    // Only hash if not already hashed (basic check: hashed passwords start with "$2")
    if (strpos($plainPassword, '$2') !== 0) {
        $hashed = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Update the password in DB
        $stmt = $conn->prepare("UPDATE user_table SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $userId);
        $stmt->execute();

        echo "Password hashed for user ID $userId<br>";
    } else {
        echo "User ID $userId already has a hashed password<br>";
    }
}
$stmt->close();
$conn->close();
?>
