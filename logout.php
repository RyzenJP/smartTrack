<?php
session_start();

// 🔒 Clear session cookie if exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// 🧹 Unset all session variables
session_unset();

// 🔐 Destroy the session
session_destroy();

// ✅ Optional: Redirect to landing or login page
header("Location: index.php"); // or "home.html" or "login.php"
exit();
?>
