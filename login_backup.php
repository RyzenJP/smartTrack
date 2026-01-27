<?php
session_start();
require 'db_connection.php';

// If already logged in, redirect away from login page
if (isset($_SESSION['username'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'reservation') {
        header("Location: user/user_dashboard.php");
        exit;
    }
    if (isset($_SESSION['role'])) {
        switch (strtolower($_SESSION['role'])) {
            case 'super admin':
                header("Location: super_admin/homepage.php");
                break;
            case 'admin':
                header("Location: motorpool_admin/admin_homepage.php");
                break;
            case 'dispatcher':
                header("Location: dispatcher/dispatcher-dashboard.php");
                break;
            case 'driver':
                header("Location: driver/navigation.php");
                break;
            case 'mechanic':
                header("Location: mechanic/mechanic_homepage.php");
                break;
            default:
                header("Location: index.php");
                break;
        }
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $_SESSION['old_username'] = $username;

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: index.php");
        exit;
    }

    // First check user_table (internal users)
    $stmt = $conn->prepare("SELECT * FROM user_table WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (strtolower($user['status']) !== 'active') {
            $_SESSION['login_error'] = "Your account is not active.";
            header("Location: index.php");
            exit;
        }

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = 'internal'; // Mark as internal user
            unset($_SESSION['login_error']);
            unset($_SESSION['old_username']);

            // ✅ Update last login
            $update = $conn->prepare("UPDATE user_table SET last_login = NOW() WHERE user_id = ?");
            $update->bind_param("i", $user['user_id']);
            $update->execute();

            // ✅ SweetAlert2 success message
            $_SESSION['login_success'] = "Welcome back, " . $user['full_name'] . "!";

            // ✅ Redirect by role
            switch (strtolower($user['role'])) {
                case 'super admin':
                    header("Location: super_admin/homepage.php");
                    break;
                case 'admin':
                    header("Location: motorpool_admin/admin_homepage.php");
                    break;
                case 'dispatcher':
                    header("Location: dispatcher/dispatcher-dashboard.php");
                    break;
                case 'driver':
                    header("Location: driver/navigation.php");
                    break;
                case 'mechanic':
                    header("Location: mechanic/mechanic_homepage.php");
                    break;
                default:
                    $_SESSION['login_error'] = "Unknown user role.";
                    header("Location: index.php");
                    break;
            }
            exit;
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: index.php");
            exit;
        }
    } else {
        // Check reservation_users table
        $stmt2 = $conn->prepare("SELECT * FROM reservation_users WHERE username = ?");
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows === 1) {
            $user = $result2->fetch_assoc();

            if (strtolower($user['status']) !== 'active') {
                $_SESSION['login_error'] = "Your account is not active. Please wait for admin approval.";
                header("Location: index.php");
                exit;
            }

            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'Requester';
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = 'reservation'; // Mark as reservation user
                unset($_SESSION['login_error']);
                unset($_SESSION['old_username']);

                // ✅ Update last login
                $update = $conn->prepare("UPDATE reservation_users SET last_login = NOW() WHERE id = ?");
                $update->bind_param("i", $user['id']);
                $update->execute();

                // ✅ SweetAlert2 success message
                $_SESSION['login_success'] = "Welcome back, " . $user['full_name'] . "!";

                // ✅ Redirect to user dashboard for reservation users
                header("Location: user/user_dashboard.php");
                exit;
            } else {
                $_SESSION['login_error'] = "Invalid username or password.";
                header("Location: index.php");
                exit;
            }
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: index.php");
            exit;
        }
    }
}
?>