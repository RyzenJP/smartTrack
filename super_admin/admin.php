<?php
require_once __DIR__ . '/../db_connection.php'; // Provides $conn database connection

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    header("Location: index.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/security.php';
    $security = Security::getInstance();
    
    try {
        // CSRF protection
        if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Add new admin
                    $full_name = $security->getPost('full_name', 'string', '');
                    $username = $security->getPost('username', 'string', '');
                    $email = $security->getPost('email', 'email', '');
                    $phone = $security->getPost('phone', 'string', '');
                    $password = $_POST['password'] ?? ''; // Not sanitized (needed for password hashing)
                    
                    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                        throw new Exception('All required fields must be filled');
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO user_table 
                        (full_name, username, email, phone, password, role, status)
                        VALUES (?, ?, ?, ?, ?, 'Admin', 'Active')
                    ");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param(
                        "sssss",
                        $full_name,
                        $username,
                        $email,
                        $phone,
                        $hashedPassword
                    );
                    $stmt->execute();
                    $_SESSION['success'] = 'Admin added successfully';
                    break;
                    
                case 'edit':
                    // Update admin (without role)
                    $full_name = $security->getPost('full_name', 'string', '');
                    $username = $security->getPost('username', 'string', '');
                    $email = $security->getPost('email', 'email', '');
                    $phone = $security->getPost('phone', 'string', '');
                    $user_id = $security->getPost('user_id', 'int', 0);
                    
                    if (empty($full_name) || empty($username) || empty($email) || $user_id <= 0) {
                        throw new Exception('All required fields must be filled');
                    }

                    $stmt = $conn->prepare("
                        UPDATE user_table SET
                        full_name = ?,
                        username = ?,
                        email = ?,
                        phone = ?
                        WHERE user_id = ?
                    ");
                    $stmt->bind_param(
                        "ssssi",
                        $full_name,
                        $username,
                        $email,
                        $phone,
                        $user_id
                    );
                    $stmt->execute();
                    $_SESSION['success'] = 'Admin updated successfully';
                    break;
                    
                case 'toggle_status':
                    // Toggle admin status
                    if (empty($_POST['user_id']) || !isset($_POST['current_status'])) {
                        throw new Exception('Missing required parameters for status change');
                    }

                    $new_status = ($_POST['current_status'] === 'Active') ? 'Inactive' : 'Active';
                    $stmt = $conn->prepare("UPDATE user_table SET status = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $new_status, $_POST['user_id']);
                    $stmt->execute();
                    $_SESSION['success'] = 'Admin status updated successfully';
                    break;
            }
            
            header("Location: admin.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: admin.php");
        exit;
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Fetch all admin users - use prepared statement for consistency
$stmt = $conn->prepare("
    SELECT user_id, full_name, username, email, phone, role, status, 
           DATE_FORMAT(created_at, '%Y-%m-%d %h:%i %p') as created_at,
           DATE_FORMAT(updated_at, '%Y-%m-%d %h:%i %p') as updated_at,
           DATE_FORMAT(last_login, '%Y-%m-%d %h:%i %p') as last_login
    FROM user_table
    WHERE role IN ('Super Admin', 'Admin')
    ORDER BY created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$users = [];
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #003566;
            --accent: #00b4d8;
            --bg: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: var(--primary);
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            padding-top: 60px;
            overflow-y: auto;
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
            width: 70px;
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
            color: var(--accent);
        }

        .sidebar .collapse a {
            color: #d9d9d9;
            font-size: 0.95rem;
            padding: 10px 16px;
            margin: 4px 8px;
            border-radius: 0.35rem;
            display: block;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .sidebar .collapse a:hover {
            background-color: #002855;
            color: var(--accent);
        }

        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
        }

        .dropdown-chevron:hover {
            color: var(--accent);
        }

        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(90deg);
        }

        .dropdown-toggle::after {
            display: none;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom: 1px solid #dee2e6;
            z-index: 1100;
        }

        .dropdown-menu {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            min-width: 190px;
            padding: 0.4rem 0;
            background-color: #ffffff;
            animation: fadeIn 0.25s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 0.95rem;
            color: #343a40;
            transition: all 0.3s ease;
            border-radius: 0.35rem;
        }

        .dropdown-menu .dropdown-item:hover {
            background-color: #001d3d;
            color: var(--accent);
            box-shadow: inset 2px 0 0 var(--accent);
        }

        .dropdown-menu .dropdown-item i {
            margin-right: 10px;
            color: #6c757d;
            transition: color 0.3s ease;
        }

        .dropdown-menu .dropdown-item:hover i {
            color: var(--accent);
        }

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary);
            margin-right: 1rem;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .progress {
            background-color: #e9ecef;
            border-radius: 0.25rem;
            overflow: visible;
        }

        .progress-bar {
            transition: width 0.5s ease, background-color 0.5s ease;
        }

        .strength-0 {
            background-color: #dc3545;
            width: 25%;
        }

        .strength-1 {
            background-color: #dc3545;
            width: 25%;
        }

        .strength-2 {
            background-color: #fd7e14;
            width: 50%;
        }

        .strength-3 {
            background-color: #ffc107;
            width: 65%;
        }

        .strength-4 {
            background-color: #28a745;
            width: 80%;
        }

        .strength-5 {
            background-color: #20c997;
            width: 100%;
        }

        .btn-status-active {
            background-color: #28a745;
            color: white;
        }

        .btn-status-inactive {
            background-color: #dc3545;
            color: white;
        }

        .btn-status:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }

        .modal-landscape {
            max-width: 800px;
        }

        .modal-landscape .modal-body {
            padding: 1.5rem;
        }

        .modal-landscape .form-control,
        .modal-landscape .form-select {
            width: 100%;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-primary fw-bold">Admin Accounts</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="fas fa-plus me-1"></i> Add Admin
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($user['role']) ?></td>
                                        <td>
                                            <span class="badge <?= $user['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($user['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-btn me-1" 
                                                    data-id="<?= intval($user['user_id']) ?>"
                                                    data-fullname="<?= htmlspecialchars($user['full_name']) ?>"
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                                    data-phone="<?= htmlspecialchars($user['phone']) ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm <?= $user['status'] === 'Active' ? 'btn-warning' : 'btn-success' ?> toggle-status-btn"
                                                    data-id="<?= intval($user['user_id']) ?>"
                                                    data-status="<?= htmlspecialchars($user['status']) ?>">
                                                <i class="fas <?= $user['status'] === 'Active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-landscape">
            <div class="modal-content">
                <form method="POST" action="admin.php" id="addAdminForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Admin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" required>
                                    <div class="invalid-feedback">Please enter a valid full name</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" required>
                                    <div class="invalid-feedback">Please enter a username</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" required>
                                    <div class="invalid-feedback">Please enter a valid email</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" id="phoneInput" maxlength="11" required>
                                    <div class="invalid-feedback">Please enter exactly 11 digits</div>
                                    <small class="text-muted">Must be 11 digits only</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" id="passwordInput" required>
                                    <div class="invalid-feedback">Please enter a password</div>
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small id="passwordStrengthText" class="text-muted">Password strength</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Admin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                            <div class="invalid-feedback">Please enter a valid full name</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                            <div class="invalid-feedback">Please enter a username</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                            <div class="invalid-feedback">Please enter a valid email</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" id="status_user_id">
                    <input type="hidden" name="current_status" id="current_status">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="statusModalTitle">Confirm Status Change</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="statusModalMessage">Are you sure you want to change this admin's status?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="statusConfirmBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit button click handler
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_user_id').value = this.dataset.id;
                document.getElementById('edit_full_name').value = this.dataset.fullname;
                document.getElementById('edit_username').value = this.dataset.username;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_phone').value = this.dataset.phone;
                
                const modal = new bootstrap.Modal(document.getElementById('editAdminModal'));
                modal.show();
            });
        });

        // Status toggle button click handler
        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.dataset.id;
                const currentStatus = this.dataset.status;
                
                // Debug logging
                console.log('Toggle button clicked:', { userId, currentStatus });
                console.log('Button element:', this);
                console.log('All data attributes:', this.dataset);
                
                // Validate data before proceeding
                if (!userId || userId === '0' || userId === '') {
                    console.error('Invalid user ID:', userId);
                    alert('Error: Invalid user ID. Please refresh the page and try again.');
                    return;
                }
                
                if (!currentStatus) {
                    console.error('Invalid status:', currentStatus);
                    alert('Error: Invalid status. Please refresh the page and try again.');
                    return;
                }
                
                // Set form values
                const userIdField = document.getElementById('status_user_id');
                const currentStatusField = document.getElementById('current_status');
                
                userIdField.value = userId;
                currentStatusField.value = currentStatus;
                
                // Verify values were set
                console.log('Form values set:', { 
                    userId: userIdField.value, 
                    currentStatus: currentStatusField.value 
                });
                
                const modalTitle = document.getElementById('statusModalTitle');
                const modalMessage = document.getElementById('statusModalMessage');
                const confirmBtn = document.getElementById('statusConfirmBtn');
                
                if (currentStatus === 'Active') {
                    modalTitle.textContent = 'Deactivate Admin Account';
                    modalMessage.textContent = 'Are you sure you want to deactivate this admin account? The user will no longer be able to access the system.';
                    confirmBtn.className = 'btn btn-warning';
                    confirmBtn.textContent = 'Deactivate';
                } else {
                    modalTitle.textContent = 'Activate Admin Account';
                    modalMessage.textContent = 'Are you sure you want to activate this admin account? The user will regain access to the system.';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = 'Activate';
                }
                
                const modal = new bootstrap.Modal(document.getElementById('statusModal'));
                modal.show();
            });
        });

        // Phone number validation
        document.getElementById('phoneInput').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
            
            if (this.value.length !== 11) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Password strength indicator
        document.getElementById('passwordInput').addEventListener('input', function(e) {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            
            // Reset classes
            strengthBar.className = 'progress-bar';
            
            // Calculate strength (0-5 scale)
            let strength = 0;
            
            // Length check
            if (password.length > 0) strength = 1;
            if (password.length >= 8) strength = 2;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength += 1; // Uppercase
            if (/[0-9]/.test(password)) strength += 1; // Numbers
            if (/[^A-Za-z0-9]/.test(password)) strength += 1; // Special chars
            
            // Cap at 5
            strength = Math.min(strength, 5);
            
            // Update UI
            strengthBar.classList.add(`strength-${strength}`);
            
            // Update text
            const messages = [
                '', // 0 (empty)
                'Very Weak', // 1
                'Weak',      // 2
                'Medium',    // 3
                'Strong',    // 4
                'Very Strong' // 5
            ];
            
            strengthText.textContent = password.length === 0 
                ? 'Password strength' 
                : `${messages[strength]} password`;
        });

        // Form validation
        document.getElementById('addAdminForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check phone number
            const phoneInput = document.getElementById('phoneInput');
            if (phoneInput.value.length !== 11) {
                phoneInput.classList.add('is-invalid');
                isValid = false;
            } else {
                phoneInput.classList.remove('is-invalid');
            }
            
            // Check other required fields
            const requiredInputs = this.querySelectorAll('[required]');
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        // Sidebar toggle functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const linkTexts = document.querySelectorAll('.link-text');
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

        burgerBtn.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');

            linkTexts.forEach(text => {
                text.style.display = isCollapsed ? 'none' : 'inline';
            });

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

            if (isCollapsed) {
                const openMenus = sidebar.querySelectorAll('.collapse.show');
                openMenus.forEach(menu => {
                    const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
                    collapseInstance.hide();
                });
            }
        });

        // Status modal form submission debugging
        document.addEventListener('DOMContentLoaded', function() {
            const statusForm = document.querySelector('#statusModal form');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    console.log('Status form submitting...');
                    console.log('Form data:', new FormData(this));
                    
                    // Check if values are set
                    const userId = document.getElementById('status_user_id').value;
                    const currentStatus = document.getElementById('current_status').value;
                    
                    console.log('Before submit - userId:', userId, 'currentStatus:', currentStatus);
                    
                    if (!userId || !currentStatus) {
                        console.error('Form values not set properly!');
                        e.preventDefault();
                        alert('Error: Form values not set properly. Please try again.');
                        return false;
                    }
                });
            }
        });

        // Modal positioning to account for navbar
        document.addEventListener('DOMContentLoaded', function() {
            const modals = ['addAdminModal', 'editAdminModal', 'statusModal'];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('shown.bs.modal', function() {
                        const navbarHeight = document.querySelector('.navbar').offsetHeight;
                        this.style.marginTop = navbarHeight + 'px';
                    });
                }
            });
        });
    </script>
</body>
</html>