<?php
require_once __DIR__ . '/../db_connection.php'; // This provides $conn

session_start();
// Remove or modify this check based on your requirements
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Driver') {
//     header("Location: index.php");
//     exit;
// }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Security token mismatch. Please refresh the page and try again.');
        }

        $required = ['full_name', 'username', 'email', 'phone', 'role'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Validate phone number format
        if (isset($_POST['phone']) && !preg_match('/^[0-9]{11}$/', $_POST['phone'])) {
            throw new Exception('Phone number must be exactly 11 digits');
        }
        
        // Validate email format
        if (isset($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Validate username format (alphanumeric and underscore only, 4-20 chars)
        if (isset($_POST['username']) && !preg_match('/^[a-zA-Z0-9_]{4,20}$/', $_POST['username'])) {
            throw new Exception('Username must be 4-20 characters, letters, numbers, and underscores only');
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Add new driver using MySQLi
                    $stmt = $conn->prepare("
                        INSERT INTO user_table 
                        (full_name, username, email, phone, password, role, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'Active')
                    ");
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->bind_param(
                        "ssssss",
                        $_POST['full_name'],
                        $_POST['username'],
                        $_POST['email'],
                        $_POST['phone'],
                        $password,
                        $_POST['role']
                    );
                    $stmt->execute();
                    $_SESSION['success'] = 'Driver added successfully';
                    break;
                    
                case 'edit':
                    // Update driver using MySQLi
                    if (!empty($_POST['password'])) {
                        // Update with password
                        $stmt = $conn->prepare("
                            UPDATE user_table SET
                            full_name = ?,
                            username = ?,
                            email = ?,
                            phone = ?,
                            password = ?
                            WHERE user_id = ?
                        ");
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt->bind_param(
                            "sssssi",
                            $_POST['full_name'],
                            $_POST['username'],
                            $_POST['email'],
                            $_POST['phone'],
                            $password,
                            $_POST['user_id']
                        );
                    } else {
                        // Update without password
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
                            $_POST['full_name'],
                            $_POST['username'],
                            $_POST['email'],
                            $_POST['phone'],
                            $_POST['user_id']
                        );
                    }
                    $stmt->execute();
                    $_SESSION['success'] = 'Driver updated successfully';
                    break;
                    
            }
            
            header("Location: driver.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: driver.php");
        exit;
    }
}

// Generate CSRF token only if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Debug CSRF token (remove this in production)
if (isset($_GET['debug_csrf'])) {
    echo "<pre>CSRF Debug Info:\n";
    echo "Session ID: " . session_id() . "\n";
    echo "CSRF Token: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "\n";
    echo "POST Token: " . ($_POST['csrf_token'] ?? 'NOT PROVIDED') . "\n";
    echo "Tokens Match: " . (($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '') ? 'YES' : 'NO') . "\n";
    echo "</pre>";
    exit;
}

// AJAX username availability check
if (isset($_GET['action']) && $_GET['action'] === 'check_username') {
  header('Content-Type: application/json');
  $username = trim($_GET['username'] ?? '');
  if ($username === '') { echo json_encode(['ok'=>false]); exit; }
  $stmt = $conn->prepare("SELECT 1 FROM user_table WHERE username=? LIMIT 1");
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $exists = $stmt->get_result()->num_rows > 0;
  echo json_encode(['ok'=>true,'exists'=>$exists]);
  exit;
}

// Fetch all driver users using MySQLi - use prepared statement for consistency
$stmt = $conn->prepare("
    SELECT user_id, full_name, username, email, phone, role, status, 
           DATE_FORMAT(created_at, '%Y-%m-%d %h:%i %p') as created_at,
           DATE_FORMAT(updated_at, '%Y-%m-%d %h:%i %p') as updated_at,
           DATE_FORMAT(last_login, '%Y-%m-%d %h:%i %p') as last_login
    FROM user_table
    WHERE role = 'Driver'
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
  <title>Driver Management | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    /* Your existing CSS styles remain the same */
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
    }

    /* All other CSS styles from your dispatcher.php */
    /* ... */
    /* Sidebar */
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
      width: 70px;
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
      color: var(--accent);
    }

    .sidebar a.active i {
    color: var(--accent) !important;
    }

    /* Dropdown submenu links design */
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

    /* Custom chevron icon for dropdown */
    .dropdown-chevron {
    color: #ffffff;
    transition: transform 0.3s ease, color 0.2s ease;
    }

    .dropdown-chevron:hover {
    color: var(--accent);
    }

    /* Rotate chevron when dropdown is expanded */
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

    /* Admin Dropdown Menu Styling */
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

    /* Dropdown items */
    .dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    font-size: 0.95rem;
    color: #343a40;
    transition: all 0.3s ease;
    border-radius: 0.35rem;
    }

    /* Hover effect */
    .dropdown-menu .dropdown-item:hover {
    background-color: #001d3d; /* deep navy like sidebar */
    color: var(--accent); /* aqua blue */
    box-shadow: inset 2px 0 0 var(--accent); /* accent highlight */
    }

    /* Icon transition */
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
    /* Add to your existing style section */
.modal-landscape {
  max-width: 800px; /* Wider than default */
}

.modal-landscape .modal-body {
  padding: 1.5rem;
}

.modal-landscape .form-control,
.modal-landscape .form-select {
  width: 100%;
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
      <!-- Flash Messages -->
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

      <!-- Driver Table -->
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title text-primary fw-bold">Driver Accounts</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
              <i class="fas fa-plus me-1"></i> Add Driver
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
                      <button class="btn btn-sm btn-primary edit-btn" 
                              data-id="<?= $user['user_id'] ?>"
                              data-fullname="<?= htmlspecialchars($user['full_name']) ?>"
                              data-username="<?= htmlspecialchars($user['username']) ?>"
                              data-email="<?= htmlspecialchars($user['email']) ?>"
                              data-phone="<?= htmlspecialchars($user['phone']) ?>"
                              data-role="<?= htmlspecialchars($user['role']) ?>">
                        <i class="fas fa-edit"></i>
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

  <!-- Add Driver Modal - Landscape Version -->
<div class="modal fade" id="addDriverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-landscape">
    <div class="modal-content">
      <form method="POST" action="driver.php" id="addDriverForm" novalidate>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="modal-header">
          <h5 class="modal-title">Add New Driver</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="full_name" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="username" id="driverUsername" minlength="4" required>
                <div class="form-text" id="driverUsernameFeedback"></div>
              </div>
              <div class="mb-3">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" id="driverPhone" pattern="[0-9]{11}" maxlength="11" inputmode="numeric" required>
                <div class="form-text" id="phoneFeedback">Must be 11 digits only</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="password" class="form-control" name="password" id="driverPassword" required minlength="8" style="padding-right: 40px;">
                  <button class="btn btn-link position-absolute" type="button" id="toggleAddDriverPassword" style="top: 50%; right: 8px; transform: translateY(-50%); padding: 0; background: none; border: none; color: #6c757d; z-index: 5;">
                    <i class="fas fa-eye" id="addDriverPasswordIcon"></i>
                  </button>
                </div>
                <div class="form-text">Min 8 chars, use upper, lower, number</div>
                <div class="progress mt-2" style="height: 6px;">
                  <div id="driverPwdStrength" class="progress-bar" role="progressbar" style="width:0%"></div>
                </div>
              </div>
              <input type="hidden" name="role" value="Driver">
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

  <!-- Edit Driver Modal -->
  <div class="modal fade" id="editDriverModal" tabindex="-1" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST" action="driver.php">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="user_id" id="edit_user_id">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <div class="modal-header">
            <h5 class="modal-title">Edit Driver</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Username <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="username" id="edit_username" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" name="email" id="edit_email" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Phone <span class="text-danger">*</span></label>
                  <input type="tel" class="form-control" name="phone" id="edit_phone" required pattern="[0-9]{11}" maxlength="11">
                  <div class="form-text text-muted">Must be 11 digits only</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Password</label>
                  <div class="position-relative">
                    <input type="password" class="form-control" name="password" id="edit_driver_password" minlength="8" style="padding-right: 40px;">
                    <button class="btn btn-link position-absolute" type="button" id="toggleEditDriverPassword" style="top: 50%; right: 8px; transform: translateY(-50%); padding: 0; background: none; border: none; color: #6c757d; z-index: 5;">
                      <i class="fas fa-eye" id="editDriverPasswordIcon"></i>
                    </button>
                  </div>
                  <div class="form-text text-muted">Min 8 chars, use upper, lower, number</div>
                  <div class="progress mt-2" style="height: 6px;">
                    <div id="editDriverPwdStrength" class="progress-bar" role="progressbar" style="width:0%"></div>
                  </div>
                </div>
              </div>
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
        // Clear password field for security
        document.getElementById('edit_driver_password').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('editDriverModal'));
        modal.show();
      });
    });


    // Password toggle functionality for add modal
    const toggleAddDriverPasswordBtn = document.getElementById('toggleAddDriverPassword');
    const addDriverPasswordInput = document.getElementById('driverPassword');
    const addDriverPasswordIcon = document.getElementById('addDriverPasswordIcon');
    
    if (toggleAddDriverPasswordBtn) {
      toggleAddDriverPasswordBtn.addEventListener('click', function() {
        if (addDriverPasswordInput.type === 'password') {
          addDriverPasswordInput.type = 'text';
          addDriverPasswordIcon.classList.remove('fa-eye');
          addDriverPasswordIcon.classList.add('fa-eye-slash');
        } else {
          addDriverPasswordInput.type = 'password';
          addDriverPasswordIcon.classList.remove('fa-eye-slash');
          addDriverPasswordIcon.classList.add('fa-eye');
        }
      });
    }
    
    // Password toggle functionality for edit modal
    const toggleEditDriverPasswordBtn = document.getElementById('toggleEditDriverPassword');
    const editDriverPasswordInput = document.getElementById('edit_driver_password');
    const editDriverPasswordIcon = document.getElementById('editDriverPasswordIcon');
    
    if (toggleEditDriverPasswordBtn) {
      toggleEditDriverPasswordBtn.addEventListener('click', function() {
        if (editDriverPasswordInput.type === 'password') {
          editDriverPasswordInput.type = 'text';
          editDriverPasswordIcon.classList.remove('fa-eye');
          editDriverPasswordIcon.classList.add('fa-eye-slash');
        } else {
          editDriverPasswordInput.type = 'password';
          editDriverPasswordIcon.classList.remove('fa-eye-slash');
          editDriverPasswordIcon.classList.add('fa-eye');
        }
      });
    }

    // Add form validation and username check
    const addDriverForm = document.getElementById('addDriverForm');
    const drvUser = document.getElementById('driverUsername');
    const drvUserFb = document.getElementById('driverUsernameFeedback');
    const drvPwd = document.getElementById('driverPassword');
    const drvPwdBar = document.getElementById('driverPwdStrength');
    const drvPhone = document.getElementById('driverPhone');
    const phoneFeedback = document.getElementById('phoneFeedback');

    function score(p){let s=0; if(p.length>=8)s+=25; if(/[A-Z]/.test(p))s+=25; if(/[a-z]/.test(p))s+=20; if(/\d/.test(p))s+=15; if(/[^A-Za-z0-9]/.test(p))s+=15; return Math.min(100,s);} 
    drvPwd?.addEventListener('input',()=>{const sc=score(drvPwd.value||''); if(drvPwdBar){drvPwdBar.style.width=sc+'%'; drvPwdBar.className='progress-bar '+(sc<40?'bg-danger':sc<70?'bg-warning':'bg-success');}});

    // Password strength for edit modal
    const editDriverPwd = document.getElementById('edit_driver_password');
    const editDriverPwdBar = document.getElementById('editDriverPwdStrength');

    editDriverPwd?.addEventListener('input',()=>{const sc=score(editDriverPwd.value||''); if(editDriverPwdBar){editDriverPwdBar.style.width=sc+'%'; editDriverPwdBar.className='progress-bar '+(sc<40?'bg-danger':sc<70?'bg-warning':'bg-success');}});

    // Live phone validation
    drvPhone?.addEventListener('input', () => {
      const value = drvPhone.value || '';
      
      // Remove any non-numeric characters
      const numericValue = value.replace(/[^0-9]/g, '');
      if (numericValue !== value) {
        drvPhone.value = numericValue;
      }
      
      // Validate phone format
      if (numericValue.length === 0) {
        phoneFeedback.textContent = 'Phone number is required';
        phoneFeedback.className = 'form-text text-danger';
        drvPhone.setCustomValidity('Phone number is required');
      } else if (numericValue.length < 11) {
        phoneFeedback.textContent = `${numericValue.length}/11 digits - Need ${11 - numericValue.length} more`;
        phoneFeedback.className = 'form-text text-warning';
        drvPhone.setCustomValidity('Phone must be 11 digits');
      } else if (numericValue.length > 11) {
        phoneFeedback.textContent = 'Too many digits - max 11 allowed';
        phoneFeedback.className = 'form-text text-danger';
        drvPhone.setCustomValidity('Phone must be exactly 11 digits');
      } else {
        phoneFeedback.textContent = 'Phone number is valid ✓';
        phoneFeedback.className = 'form-text text-success';
        drvPhone.setCustomValidity('');
      }
    });

    let t; drvUser?.addEventListener('input',()=>{clearTimeout(t); drvUserFb.textContent=''; drvUser.dataset.taken='false'; const v=(drvUser.value||'').trim(); if(v.length<4) return; t=setTimeout(async()=>{try{const r=await fetch(`driver.php?action=check_username&username=${encodeURIComponent(v)}`,{cache:'no-store'}); const d=await r.json(); if(d.ok&&d.exists){drvUserFb.textContent='Username is already taken'; drvUserFb.className='form-text text-danger'; drvUser.dataset.taken='true';} else if(d.ok){drvUserFb.textContent='Username is available'; drvUserFb.className='form-text text-success'; drvUser.dataset.taken='false';}}catch(e){} },300);});

    addDriverForm?.addEventListener('submit',(e)=>{
      if(!addDriverForm.checkValidity()){e.preventDefault(); e.stopPropagation(); addDriverForm.classList.add('was-validated'); return;}
      if(drvUser?.dataset.taken==='true'){e.preventDefault(); e.stopPropagation(); alert('Username is already taken.'); drvUser.focus(); return;}
      if(drvPhone && !/^[0-9]{11}$/.test(drvPhone.value)){e.preventDefault(); e.stopPropagation(); alert('Phone number must be exactly 11 digits (numbers only).'); drvPhone.focus(); drvPhone.setCustomValidity('Phone must be exactly 11 digits'); return;} else if(drvPhone) {drvPhone.setCustomValidity('');}
      if(drvPwd && !( /[A-Z]/.test(drvPwd.value)&&/[a-z]/.test(drvPwd.value)&&/\d/.test(drvPwd.value) )){e.preventDefault(); e.stopPropagation(); alert('Password must include uppercase, lowercase, and a number.'); drvPwd.focus();}
    });
  </script>
  <script>
  // Your existing JavaScript for sidebar functionality
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
        toggle.setAttribute('data-bs-toggle', ''); // disable collapse
      } else {
        chevron.classList.remove('disabled-chevron');
        chevron.style.cursor = 'pointer';
        chevron.removeAttribute('title');
        toggle.setAttribute('data-bs-toggle', 'collapse'); // enable collapse
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

  // Sidebar active class handler
  const allSidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');

  allSidebarLinks.forEach(link => {
    link.addEventListener('click', function () {
      allSidebarLinks.forEach(l => l.classList.remove('active'));
      this.classList.add('active');

      const parentCollapse = this.closest('.collapse');
      if (parentCollapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(parentCollapse);
        if (bsCollapse) {
          bsCollapse.show();
        }
      }
    });
  });
  
  document.addEventListener('DOMContentLoaded', function() {
    var addDriverModal = document.getElementById('addDriverModal');
    addDriverModal.addEventListener('shown.bs.modal', function () {
        var navbarHeight = document.querySelector('.navbar').offsetHeight;
        this.style.marginTop = navbarHeight + 'px';
    });
  });

   document.addEventListener('DOMContentLoaded', function() {
    var editDriverModal = document.getElementById('editDriverModal');
    editDriverModal.addEventListener('shown.bs.modal', function () {
        var navbarHeight = document.querySelector('.navbar').offsetHeight;
        this.style.marginTop = navbarHeight + 'px';
    });
  });

</script>
</body>
</html>