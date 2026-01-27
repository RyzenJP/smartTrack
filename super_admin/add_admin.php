<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Something went wrong.'];

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $password  = $_POST['password'];
    $created_at = date('Y-m-d H:i:s');
    $status = 'Active';
    $role = 'Admin';

    $errors = [];

    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    $checkSql = "SELECT user_id FROM user_table WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Username or email already exists.";
    $stmt->close();

    $profile_image = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['profile_photo']['tmp_name'];
        $fileName = basename($_FILES['profile_photo']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, and PNG images are allowed.";
        } else {
            $newName = uniqid("admin_", true) . '.' . $fileExt;
            $uploadPath = __DIR__ . '/../uploads/admins/' . $newName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $profile_image = $newName;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if (!empty($errors)) {
        $response['message'] = implode(" ", $errors);
        echo json_encode($response);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO user_table (full_name, username, email, phone, password, role, status, profile_image, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $full_name, $username, $email, $phone, $hashedPassword, $role, $status, $profile_image, $created_at);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Admin added successfully!';
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }

    $stmt->close();
    $conn->close();

    echo json_encode($response);
}
?>