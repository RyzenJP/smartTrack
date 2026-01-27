<?php
// Enable error reporting for debugging - SHOW ERRORS
error_reporting(E_ALL);
ini_set('display_errors', 1); // SHOW ERRORS ON SCREEN
ini_set('log_errors', 1);

// Include security headers first (it handles session_start)
try {
    require_once __DIR__ . '/includes/security_headers.php';
} catch (Throwable $e) {
    die("FATAL ERROR loading security headers: " . $e->getMessage() . " on line " . $e->getLine());
}

try {
    require_once 'db_connection.php';
} catch (Throwable $e) {
    die("FATAL ERROR loading database: " . $e->getMessage() . " on line " . $e->getLine());
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = '';
$messageType = '';

// Determine which table to use based on role
$isRequester = (strtolower($role) === 'requester');
$table = $isRequester ? 'reservation_users' : 'user_table';
$idColumn = $isRequester ? 'id' : 'user_id';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    // Include security class for input sanitization (already included in security_headers.php, but ensure it's available)
    if (!class_exists('Security')) {
        require_once __DIR__ . '/config/security.php';
    }
    $security = Security::getInstance();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else if ($action === 'update_profile') {
        $full_name = $security->sanitizeInput(trim($_POST['full_name'] ?? ''), 'string');
        $email = $security->sanitizeInput(trim($_POST['email'] ?? ''), 'email');
        $phone = $security->sanitizeInput(trim($_POST['phone'] ?? ''), 'string');
        $username = $security->sanitizeInput(trim($_POST['username'] ?? ''), 'string');
        
        // Server-side validation
        $errors = [];
        
        // Full name validation
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($full_name) < 2) {
            $errors[] = 'Full name must be at least 2 characters long.';
        } elseif (strlen($full_name) > 100) {
            $errors[] = 'Full name must be less than 100 characters.';
        }
        
        // Email validation
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $errors[] = 'Only Gmail addresses are allowed.';
        } else {
            // Check if email is already taken by another user
            $emailCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE email = ? AND $idColumn != ?");
            $emailCheckStmt->bind_param("si", $email, $user_id);
            $emailCheckStmt->execute();
            $emailResult = $emailCheckStmt->get_result();
            $emailRow = $emailResult->fetch_assoc();
            
            if ($emailRow['count'] > 0) {
                $errors[] = 'Email address is already taken.';
            }
        }
        
        // Phone validation
        if (!empty($phone)) {
            if (!preg_match('/^09[0-9]{9}$/', $phone)) {
                $errors[] = 'Phone number must be 11 digits starting with 09.';
            }
        }
        
        // Username validation (if provided and different from current)
        if (!empty($username) && $username !== $user['username']) {
            if (strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters long.';
            } elseif (strlen($username) > 50) {
                $errors[] = 'Username must be less than 50 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $errors[] = 'Username can only contain letters, numbers, and underscores.';
            } else {
                // Check if username is already taken
                $usernameCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE username = ? AND $idColumn != ?");
                $usernameCheckStmt->bind_param("si", $username, $user_id);
                $usernameCheckStmt->execute();
                $usernameResult = $usernameCheckStmt->get_result();
                $usernameRow = $usernameResult->fetch_assoc();
                
                if ($usernameRow['count'] > 0) {
                    $errors[] = 'Username is already taken.';
                }
            }
        }
        
        if (empty($errors)) {
            // Update profile
            $updateFields = "full_name = ?, email = ?, phone = ?";
            $updateValues = [$full_name, $email, $phone];
            $paramTypes = "sss";
            
            // Add username to update if provided and different
            if (!empty($username) && $username !== $user['username']) {
                $updateFields .= ", username = ?";
                $updateValues[] = $username;
                $paramTypes .= "s";
            }
            
            $updateValues[] = $user_id;
            $paramTypes .= "i";
            
            $stmt = $conn->prepare("UPDATE $table SET $updateFields WHERE $idColumn = ?");
            $stmt->bind_param($paramTypes, ...$updateValues);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                if (!empty($username)) {
                    $_SESSION['username'] = $username;
                }
                $message = 'Profile updated successfully!';
                $messageType = 'success';
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $message = 'Failed to update profile. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = implode(' ', $errors);
            $messageType = 'error';
        }
    } else if ($action === 'change_password') {
        // Password fields should not be sanitized (they're hashed), but validate they exist
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required.';
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = 'New password must be at least 8 characters long.';
            $messageType = 'error';
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $idColumn = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to change password. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }
    }
}

// Get user's role-specific dashboard link
$dashboardLinks = [
    'super admin' => 'super_admin/homepage.php',
    'admin' => 'motorpool_admin/admin_homepage.php',
    'dispatcher' => 'dispatcher/dispatcher-dashboard.php',
    'driver' => 'driver/driver-dashboard.php',
    'mechanic' => 'mechanic/mechanic_homepage.php',
    'requester' => 'user/user_dashboard.php'
];

$dashboardLink = $dashboardLinks[strtolower($role)] ?? 'index.php';

// Determine which sidebar and navbar to include based on role
$roleLower = strtolower($role);
$sidebarFile = '';
$navbarFile = '';

switch ($roleLower) {
    case 'super admin':
        $sidebarFile = 'pages/sidebar.php';
        $navbarFile = 'pages/navbar.php';
        break;
    case 'admin':
        $sidebarFile = 'pages/admin_sidebar.php';
        $navbarFile = 'pages/admin_navbar.php';
        break;
    case 'dispatcher':
        $sidebarFile = 'pages/dispatcher_sidebar.php';
        $navbarFile = 'pages/dispatcher_navbar.php';
        break;
    case 'driver':
        $sidebarFile = 'pages/driver_sidebar.php';
        $navbarFile = 'pages/driver_navbar.php';
        break;
    case 'mechanic':
        $sidebarFile = 'pages/mechanic_sidebar.php';
        $navbarFile = 'pages/mechanic_navbar.php';
        break;
    case 'requester':
        $sidebarFile = 'pages/user_sidebar.php';
        $navbarFile = 'pages/user_navbar.php';
        break;
    default:
        // Default to general sidebar/navbar if role not found
        $sidebarFile = 'pages/sidebar.php';
        $navbarFile = 'pages/navbar.php';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Track System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Sidebar and Navbar styling for profile page */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: var(--primary-blue);
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            padding-top: 60px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #ffffff20 #001d3d;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: #ffffffcc;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar.collapsed {
            width: 70px !important;
        }

        /* Hide chevrons when sidebar is collapsed */
        .sidebar.collapsed .dropdown-chevron {
            display: none;
        }

        .sidebar a {
            display: block;
            padding: 14px 20px;
            color: #d9d9d9;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #001d3d;
            color: var(--accent-blue);
        }

        .sidebar a.active i {
            color: var(--accent-blue) !important;
        }

        .sidebar .collapse a {
            padding: 10px 20px 10px 40px;
            font-size: 0.9rem;
        }

        .sidebar .collapse a:hover {
            background-color: #002855;
            color: var(--accent-blue);
        }

        /* Custom chevron icon for dropdown */
        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
        }

        .dropdown-chevron:hover {
            color: var(--accent-blue);
        }

        /* Rotate chevron when dropdown is expanded */
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(90deg);
        }

        .dropdown-toggle::after {
            display: none;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1001;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom: 1px solid #dee2e6;
        }

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary-blue);
            margin-right: 1rem;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .burger-btn:hover {
            background-color: rgba(0, 53, 102, 0.1);
        }

        .main-content {
            transition: margin-left 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 70px);
            padding: 20px;
            margin-left: 250px;
            margin-top: 70px;
            padding-top: 2vh;
            justify-content: center !important;
        }

        .main-content.collapsed {
            margin-left: 70px !important;
        }

        /* Password toggle button styling */
        .btn-link {
            position: absolute !important;
            right: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            padding: 6px !important;
            color: var(--accent-blue) !important;
            border: none !important;
            background: transparent !important;
            z-index: 10 !important;
            width: 32px !important;
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .btn-link:hover {
            color: var(--light-blue) !important;
            background: transparent !important;
        }
        
        .btn-link:focus {
            box-shadow: none !important;
            outline: none !important;
        }
        
        .btn-link i {
            font-size: 16px !important;
            width: 16px !important;
            height: 16px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Ensure eye icons display correctly */
        .btn-link i.fa-eye::before {
            content: "\f06e" !important;
        }
        
        .btn-link i.fa-eye-slash::before {
            content: "\f070" !important;
        }
        
        /* Force proper icon display */
        .btn-link i.fas {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 900 !important;
        }
        
        /* Input container for password fields */
        .password-input-container {
            position: relative !important;
        }
        
        .password-input-container .form-control {
            padding-right: 55px !important;
            padding-left: 16px !important;
            text-indent: 0 !important;
            box-sizing: border-box !important;
        }
        
        /* Ensure input text doesn't overlap with eye icon */
        .password-input-container .form-control:focus {
            padding-right: 55px !important;
        }
        
        .password-input-container .form-control::placeholder {
            padding-right: 0 !important;
        }

         /* Mobile responsive styles */
        @media (max-width: 991.98px) {
            .sidebar { 
                width: 260px; 
                transform: translateX(-100%); 
                position: fixed; 
                top: 0; 
                left: 0; 
                height: 100vh; 
                z-index: 1101; 
            }
            .sidebar.open { 
                transform: translateX(0); 
            }
            .main-content { 
                margin-left: 0 !important; 
                padding: 16px;
                display: flex !important;
                justify-content: center !important;
                align-items: flex-start !important;
                padding-top: 2vh !important;
                justify-content: center !important;
            }
            
            .profile-container {
                margin: 0 auto;
                max-width: 100% !important;
            }
            
            /* Mobile button spacing fixes */
            .text-end {
                margin-top: 20px !important;
                text-align: center !important;
            }
            
            .btn {
                width: 100% !important;
                max-width: 100% !important;
                padding: 12px 20px !important;
                font-size: 16px !important;
                margin-top: 10px !important;
            }
            
            .btn-primary {
                background-color: var(--accent-blue) !important;
                border-color: var(--accent-blue) !important;
                border-radius: 8px !important;
            }
            
            .btn-primary:hover {
                background-color: var(--light-blue) !important;
                border-color: var(--light-blue) !important;
            }
            
            /* Form spacing on mobile */
            .row {
                margin-bottom: 15px !important;
            }
            
            .col-md-6 {
                margin-bottom: 15px !important;
            }
            
            /* Section spacing */
            .section-title {
                margin-top: 25px !important;
                margin-bottom: 20px !important;
            }
            
            /* Card spacing */
            .card {
                margin-bottom: 20px !important;
                border-radius: 12px !important;
            }
            
            .card-body {
                padding: 20px !important;
            }
            
            /* Input field improvements for mobile */
            .form-control {
                padding: 12px 16px !important;
                font-size: 16px !important;
                border-radius: 8px !important;
                margin-bottom: 10px !important;
            }
            
            .form-label {
                font-weight: 600 !important;
                margin-bottom: 8px !important;
                color: var(--text-dark) !important;
            }
            
            .form-text {
                font-size: 14px !important;
                margin-top: 5px !important;
            }
            
            /* Better touch targets */
            .btn {
                min-height: 48px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            /* Icon spacing in buttons */
            .btn i {
                margin-right: 8px !important;
            }
            
            /* Mobile password toggle button fixes */
            .btn-link {
                right: 12px !important;
                padding: 6px !important;
                width: 32px !important;
                height: 32px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            .btn-link i {
                font-size: 16px !important;
                width: 16px !important;
                height: 16px !important;
                margin: 0 !important;
            }
            
            .password-input-container .form-control {
                padding-right: 55px !important;
                padding-left: 16px !important;
            }
            
            /* Mobile eye icon fixes */
            .btn-link i.fa-eye::before {
                content: "\f06e" !important;
                font-family: "Font Awesome 6 Free" !important;
                font-weight: 900 !important;
            }
            
            .btn-link i.fa-eye-slash::before {
                content: "\f070" !important;
                font-family: "Font Awesome 6 Free" !important;
                font-weight: 900 !important;
            }
        }
        
        :root {
            --primary-blue: #003566;
            --accent-blue: #00b4d8;
            --light-blue: #0096c7;
            --dark-blue: #001d3d;
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
            --success: #48bb78;
            --danger: #f56565;
            --border-light: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e6f2ff 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .profile-container {
            max-width: 800px;
            width: 100%;
            margin: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
        }

        .profile-body {
            background: white;
            padding: 30px;
        }

        .form-control {
            border: 2px solid var(--border-light);
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
        }

        .btn-secondary {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .alert-success {
            background-color: rgba(72, 187, 120, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background-color: rgba(245, 101, 101, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #e9ecef;
        }

        .info-card .label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .info-card .value {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .section-title {
            color: var(--text-dark);
            font-weight: 600;
            margin: 24px 0 16px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            color: var(--accent-blue);
            margin-right: 8px;
            font-size: 1rem;
        }

        .badge-role {
            background: var(--accent-blue);
            color: white;
            padding: 6px 16px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
    </style>
</head>
<body>
<?php 
// Include the appropriate sidebar and navbar based on role
try {
    if (!empty($sidebarFile) && file_exists($sidebarFile)) {
        include $sidebarFile;
    } else {
        error_log("Sidebar file not found: " . $sidebarFile);
    }
} catch (Throwable $e) {
    error_log("Error including sidebar: " . $e->getMessage());
}

try {
    if (!empty($navbarFile) && file_exists($navbarFile)) {
        include $navbarFile;
    } else {
        error_log("Navbar file not found: " . $navbarFile);
    }
} catch (Throwable $e) {
    error_log("Error including navbar: " . $e->getMessage());
}
?>
    
    <div class="main-content" id="mainContent">
        <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <span class="badge-role"><?= htmlspecialchars($role) ?></span>
        </div>

        <div class="profile-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> d-flex align-items-center" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            <?php endif; ?>

            <!-- Account Information -->
            <h5 class="section-title"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-user me-2"></i>Username</div>
                        <div class="value"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-shield-alt me-2"></i>Role</div>
                        <div class="value"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
                <?php if ($isRequester && !empty($user['employee_id'])): ?>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-id-badge me-2"></i>Employee ID</div>
                        <div class="value"><?= htmlspecialchars($user['employee_id']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-building me-2"></i>Department</div>
                        <div class="value"><?= htmlspecialchars($user['department'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-calendar me-2"></i>Member Since</div>
                        <div class="value"><?= date('F d, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="label"><i class="fas fa-check-circle me-2"></i>Status</div>
                        <div class="value">
                            <span class="badge bg-<?= $user['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                <?= htmlspecialchars($user['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile -->
            <h5 class="section-title"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            <form method="POST" action="" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">
                            <i class="fas fa-user me-1"></i>Full Name
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone me-1"></i>Phone Number
                        </label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               pattern="^09\d{9}$" placeholder="09XXXXXXXXX">
                        <div class="form-text">Format: 09XXXXXXXXX</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-at me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        <div class="form-text">Username cannot be changed</div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>

            <!-- Change Password -->
            <h5 class="section-title"><i class="fas fa-key me-2"></i>Change Password</h5>
            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Current Password
                        </label>
                        <div class="password-input-container">
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" placeholder="Enter your current password" required>
                            <button type="button" class="btn btn-link" 
                                    onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>New Password
                        </label>
                        <div class="password-input-container">
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" placeholder="Enter new password" 
                                   minlength="8" required>
                            <button type="button" class="btn btn-link" 
                                    onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Confirm New Password
                        </label>
                        <div class="password-input-container">
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" placeholder="Confirm new password" 
                                   minlength="8" required>
                            <button type="button" class="btn btn-link" 
                                    onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </div>
            </form>

            <!-- Back Button -->
            <div class="text-center mt-4">
                <a href="<?= $dashboardLink ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    </div> <!-- Close main-content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Comprehensive Sidebar and Burger Button Functionality
        (function(){
            const burger = document.getElementById('burgerBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (!burger || !sidebar) {
                console.error('Burger button or sidebar not found!');
                return;
            }
            
            function isMobile(){ return window.innerWidth < 992; }
            let backdrop;
            
            function ensureBackdrop(){
                if (backdrop) return backdrop;
                backdrop = document.createElement('div');
                backdrop.style.position = 'fixed';
                backdrop.style.top = '0';
                backdrop.style.left = '0';
                backdrop.style.right = '0';
                backdrop.style.bottom = '0';
                backdrop.style.background = 'rgba(0,0,0,0.25)';
                backdrop.style.zIndex = '1100';
                backdrop.style.display = 'none';
                document.body.appendChild(backdrop);
                backdrop.addEventListener('click', closeSidebar);
                return backdrop;
            }
            
            function openSidebar(){
                sidebar.classList.add('open');
                const b = ensureBackdrop();
                b.style.display = 'block';
            }
            
            function closeSidebar(){
                sidebar.classList.remove('open');
                if (backdrop) backdrop.style.display = 'none';
            }
            
            function toggleSidebar(){
                if(!isMobile()) {
                    // Desktop: Toggle collapsed state
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    
                    if (mainContent) {
                        mainContent.classList.toggle('collapsed');
                    }
                    
                    // Toggle text visibility
                    const linkTexts = document.querySelectorAll('.link-text');
                    linkTexts.forEach(text => {
                        text.style.display = isCollapsed ? 'none' : 'inline';
                    });
                    
                    // Manage dropdown chevron interactivity
                    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
                    dropdownToggles.forEach(toggle => {
                        const chevron = toggle.querySelector('.dropdown-chevron');
                        if (isCollapsed) {
                            chevron.classList.add('disabled-chevron');
                            chevron.style.cursor = 'not-allowed';
                            chevron.setAttribute('title', 'Expand sidebar to activate');
                            toggle.setAttribute('data-bs-toggle', '');
                        } else {
                            chevron.classList.remove('disabled-chevron');
                            chevron.style.cursor = 'pointer';
                            chevron.removeAttribute('title');
                            toggle.setAttribute('data-bs-toggle', 'collapse');
                        }
                    });
                    
                    // Collapse all sidebar dropdowns when sidebar is collapsed
                    if (isCollapsed) {
                        const openMenus = sidebar.querySelectorAll('.collapse.show');
                        openMenus.forEach(menu => {
                            const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
                            collapseInstance.hide();
                        });
                    }
                } else {
                    // Mobile: Toggle open/closed
                    if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar();
                }
            }
            
            // Bind burger button events
            if (!burger.dataset.bound){
                burger.addEventListener('click', function(e){ 
                    e.preventDefault(); 
                    e.stopPropagation(); 
                    toggleSidebar(); 
                });
                // Support touch
                burger.addEventListener('touchstart', function(e){ 
                    e.preventDefault(); 
                    e.stopPropagation(); 
                    toggleSidebar(); 
                }, { passive: false });
                burger.dataset.bound = '1';
            }
            
            // Alternative direct binding (backup method)
            burger.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Direct toggle without complex logic
                const isCollapsed = sidebar.classList.toggle('collapsed');
                
                if (mainContent) {
                    mainContent.classList.toggle('collapsed');
                }
                
                // Update link text visibility
                const linkTexts = document.querySelectorAll('.link-text');
                linkTexts.forEach(text => {
                    text.style.display = isCollapsed ? 'none' : 'inline';
                });
            };
            
            
            // Prevent clicks inside sidebar from closing it
            sidebar.addEventListener('click', function(e){ e.stopPropagation(); });
            sidebar.addEventListener('touchstart', function(e){ e.stopPropagation(); }, { passive: true });
            
            // Close when clicking outside on mobile
            document.addEventListener('click', function(e){
                if(!isMobile()) return;
                if(!sidebar.contains(e.target) && !burger.contains(e.target)) closeSidebar();
            });
            
            // Ensure closed on resize to desktop
            window.addEventListener('resize', function(){ 
                if(!isMobile()){ closeSidebar(); } 
            });
        })();

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Initialize eye icons on page load
        document.addEventListener('DOMContentLoaded', function() {
            const passwordFields = ['current_password', 'new_password', 'confirm_password'];
            
            passwordFields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (field) {
                    const button = field.nextElementSibling;
                    const icon = button.querySelector('i');
                    
                    // Ensure the icon starts with fa-eye class
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    
                    // Ensure field starts as password type
                    field.type = 'password';
                }
            });
        });

        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // ===== INPUT VALIDATIONS =====

        // Phone Number Validation - Only Numbers
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove any non-numeric characters
                let value = e.target.value.replace(/[^0-9]/g, '');
                
                // Limit to 11 digits
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                
                e.target.value = value;
                
                // Real-time validation feedback
                validatePhone();
            });
            
            phoneInput.addEventListener('paste', function(e) {
                e.preventDefault();
                let paste = (e.clipboardData || window.clipboardData).getData('text');
                let numericOnly = paste.replace(/[^0-9]/g, '').substring(0, 11);
                e.target.value = numericOnly;
                validatePhone();
            });
            
            function validatePhone() {
                const phone = phoneInput.value;
                const feedback = document.getElementById('phoneFeedback') || createFeedback('phoneFeedback', phoneInput);
                
                if (phone.length === 0) {
                    feedback.innerHTML = '<i class="fas fa-info-circle text-muted"></i> Format: 09XXXXXXXXX';
                    feedback.className = 'form-text text-muted';
                } else if (phone.length < 11) {
                    feedback.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> Phone number must be 11 digits';
                    feedback.className = 'form-text text-warning';
                } else if (!phone.startsWith('09')) {
                    feedback.innerHTML = '<i class="fas fa-times text-danger"></i> Phone must start with 09';
                    feedback.className = 'form-text text-danger';
                } else {
                    feedback.innerHTML = '<i class="fas fa-check text-success"></i> Valid phone number';
                    feedback.className = 'form-text text-success';
                }
            }
        }

        // Email Validation
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', validateEmail);
            emailInput.addEventListener('blur', validateEmail);
            
            function validateEmail() {
                const email = emailInput.value.trim();
                const feedback = document.getElementById('emailFeedback') || createFeedback('emailFeedback', emailInput);
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const gmailRegex = /^[^\s@]+@gmail\.com$/i;
                
                if (email.length === 0) {
                    feedback.innerHTML = '<i class="fas fa-info-circle text-muted"></i> Enter your email address';
                    feedback.className = 'form-text text-muted';
                } else if (!emailRegex.test(email)) {
                    feedback.innerHTML = '<i class="fas fa-times text-danger"></i> Invalid email format';
                    feedback.className = 'form-text text-danger';
                } else if (!gmailRegex.test(email)) {
                    feedback.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> Only @gmail.com emails are allowed';
                    feedback.className = 'form-text text-warning';
                } else {
                    feedback.innerHTML = '<i class="fas fa-check text-success"></i> Valid email address';
                    feedback.className = 'form-text text-success';
                }
            }
        }

        // Username Validation with Availability Check
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            let usernameTimeout;
            usernameInput.addEventListener('input', function() {
                clearTimeout(usernameTimeout);
                usernameTimeout = setTimeout(validateUsername, 500); // Debounce
            });
            
            function validateUsername() {
                const username = usernameInput.value.trim();
                const feedback = document.getElementById('usernameFeedback') || createFeedback('usernameFeedback', usernameInput);
                
                if (username.length === 0) {
                    feedback.innerHTML = '<i class="fas fa-info-circle text-muted"></i> Username cannot be changed';
                    feedback.className = 'form-text text-muted';
                    return;
                }
                
                // Check username availability via AJAX
                checkUsernameAvailability(username, feedback);
            }
            
            function checkUsernameAvailability(username, feedback) {
                feedback.innerHTML = '<i class="fas fa-spinner fa-spin text-info"></i> Checking availability...';
                feedback.className = 'form-text text-info';
                
                fetch('api/check_username_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'username=' + encodeURIComponent(username) + '&current_user_id=' + <?= $user_id ?>
                })
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedback.innerHTML = '<i class="fas fa-check text-success"></i> Username is available';
                        feedback.className = 'form-text text-success';
                    } else {
                        feedback.innerHTML = '<i class="fas fa-times text-danger"></i> Username is already taken';
                        feedback.className = 'form-text text-danger';
                    }
                })
                .catch(error => {
                    feedback.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> Could not verify username';
                    feedback.className = 'form-text text-warning';
                });
            }
        }

        // Password Strength Indicator
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = newPasswordInput.value;
                const strengthBar = document.getElementById('passwordStrengthBar') || createPasswordStrengthBar();
                const strengthText = document.getElementById('passwordStrengthText') || createPasswordStrengthText();
                
                updatePasswordStrength(password, strengthBar, strengthText);
            });
        }
        
        function createPasswordStrengthBar() {
            const container = newPasswordInput.parentElement;
            const strengthContainer = document.createElement('div');
            strengthContainer.className = 'mt-2';
            strengthContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">Password Strength:</small>
                    <small id="passwordStrengthText" class="fw-bold">Weak</small>
                </div>
                <div class="progress" style="height: 4px;">
                    <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
            `;
            container.appendChild(strengthContainer);
            return document.getElementById('passwordStrengthBar');
        }
        
        function createPasswordStrengthText() {
            return document.getElementById('passwordStrengthText');
        }
        
        function updatePasswordStrength(password, strengthBar, strengthText) {
            let score = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) {
                score += 25;
            } else {
                feedback.push('At least 8 characters');
            }
            
            // Uppercase check
            if (/[A-Z]/.test(password)) {
                score += 25;
            } else {
                feedback.push('Uppercase letter');
            }
            
            // Lowercase check
            if (/[a-z]/.test(password)) {
                score += 25;
            } else {
                feedback.push('Lowercase letter');
            }
            
            // Number check
            if (/\d/.test(password)) {
                score += 25;
            } else {
                feedback.push('Number');
            }
            
            // Special character bonus
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                score += 10;
            }
            
            // Update UI
            strengthBar.style.width = Math.min(score, 100) + '%';
            
            if (score < 50) {
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak';
                strengthText.className = 'fw-bold text-danger';
            } else if (score < 75) {
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Medium';
                strengthText.className = 'fw-bold text-warning';
            } else if (score < 90) {
                strengthBar.className = 'progress-bar bg-info';
                strengthText.textContent = 'Good';
                strengthText.className = 'fw-bold text-info';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong';
                strengthText.className = 'fw-bold text-success';
            }
        }

        // Helper function to create feedback elements
        function createFeedback(id, input) {
            const feedback = document.createElement('div');
            feedback.id = id;
            feedback.className = 'form-text text-muted';
            input.parentElement.appendChild(feedback);
            return feedback;
        }

        // Form submission validation
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate phone
            const phone = document.getElementById('phone').value;
            if (phone.length > 0 && (phone.length !== 11 || !phone.startsWith('09'))) {
                isValid = false;
                alert('Please enter a valid phone number (09XXXXXXXXX)');
            }
            
            // Validate email
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const gmailRegex = /^[^\s@]+@gmail\.com$/i;
            if (!emailRegex.test(email) || !gmailRegex.test(email)) {
                isValid = false;
                alert('Please enter a valid Gmail address');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        }
    </script>
</body>
</html>
