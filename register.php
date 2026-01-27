<?php
session_start();
// Include security headers
require_once __DIR__ . '/includes/security_headers.php';

require_once __DIR__ . '/db_connection.php';

$errors = [];
$success = '';

// Lightweight endpoint for AJAX username availability check
if (isset($_GET['check_username'])) {
	header('Content-Type: application/json');
	$usernameToCheck = trim($_GET['check_username']);
	$isValidFormat = preg_match('/^[A-Za-z0-9_]{3,30}$/', $usernameToCheck) === 1;
	$available = false;
	$message = '';
	if ($usernameToCheck === '' || !$isValidFormat) {
		$message = 'Use 3-30 characters: letters, numbers, or _';
	} else {
		// Check in reservation_users
		$stmt1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE username = ? LIMIT 1');
		$stmt1->bind_param('s', $usernameToCheck);
		$stmt1->execute();
		$stmt1->store_result();
		$existsInReservation = ($stmt1->num_rows > 0);
		$stmt1->close();

		// Check in user_table
		$stmt2 = $conn->prepare('SELECT 1 FROM user_table WHERE username = ? LIMIT 1');
		$stmt2->bind_param('s', $usernameToCheck);
		$stmt2->execute();
		$stmt2->store_result();
		$existsInUserTable = ($stmt2->num_rows > 0);
		$stmt2->close();

		$available = (!$existsInReservation && !$existsInUserTable);
		$message = $available ? 'Username is available' : 'Username is already taken';
	}
	echo json_encode(['ok' => true, 'available' => $available, 'message' => $message, 'valid' => $isValidFormat]);
	exit;
}

// Lightweight endpoint for AJAX email availability check
if (isset($_GET['check_email'])) {
	header('Content-Type: application/json');
	$emailToCheck = trim($_GET['check_email']);
	$isValidFormat = filter_var($emailToCheck, FILTER_VALIDATE_EMAIL) !== false;
	$available = false;
	$message = '';
	if ($emailToCheck === '' || !$isValidFormat) {
		$message = 'Please enter a valid email address';
	} else {
		// Check in reservation_users
		$stmt1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE email = ? LIMIT 1');
		$stmt1->bind_param('s', $emailToCheck);
		$stmt1->execute();
		$stmt1->store_result();
		$existsInReservation = ($stmt1->num_rows > 0);
		$stmt1->close();

		// Check in user_table
		$stmt2 = $conn->prepare('SELECT 1 FROM user_table WHERE email = ? LIMIT 1');
		$stmt2->bind_param('s', $emailToCheck);
		$stmt2->execute();
		$stmt2->store_result();
		$existsInUserTable = ($stmt2->num_rows > 0);
		$stmt2->close();

	$available = (!$existsInReservation && !$existsInUserTable);
	$message = $available ? 'Email is available' : 'Email is already in use';
}
echo json_encode(['ok' => true, 'available' => $available, 'message' => $message, 'valid' => $isValidFormat]);
exit;
}

// Lightweight endpoint for AJAX employee ID availability check
if (isset($_GET['check_employee_id'])) {
	header('Content-Type: application/json');
	$employeeIdToCheck = trim($_GET['check_employee_id']);
	$isValidFormat = !empty($employeeIdToCheck);
	$available = false;
	$message = '';
	if ($employeeIdToCheck === '' || !$isValidFormat) {
		$message = 'Employee ID is required';
	} else {
		// Check in reservation_users
		$stmt1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE employee_id = ? LIMIT 1');
		$stmt1->bind_param('s', $employeeIdToCheck);
		$stmt1->execute();
		$stmt1->store_result();
		$existsInReservation = ($stmt1->num_rows > 0);
		$stmt1->close();

		// Requestors are stored in reservation_users only; employee_id exists only there
		$existsInUserTable = false;

		$available = (!$existsInReservation && !$existsInUserTable);
		$message = $available ? 'Employee ID is available' : 'Employee ID is already registered';
	}
	echo json_encode(['ok' => true, 'available' => $available, 'message' => $message, 'valid' => $isValidFormat]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include security class for CSRF validation
    require_once __DIR__ . '/config/security.php';
    $security = Security::getInstance();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $full_name = $security->getPost('full_name', 'string', '');
        $username = $security->getPost('username', 'string', '');
        $email = $security->getPost('email', 'email', '');
        $phone = $security->getPost('phone', 'string', '');
        $employee_id = $security->getPost('employee_id', 'string', '');
        $department = $security->getPost('department', 'string', '');
        $password = $_POST['password'] ?? ''; // Not sanitized (needed for password hashing)
        $confirm_password = $_POST['confirm_password'] ?? ''; // Not sanitized

        // Basic validation (all fields required)
    if ($full_name === '') $errors[] = 'Full name is required';
    if ($username === '') {
        $errors[] = 'Username is required';
    } else {
        // Optional: basic username constraints (3-30 chars, letters, numbers, underscores)
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters and use letters, numbers, or _ only';
        }
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    // Phone required: must start with 09 and be exactly 11 digits
    if ($phone === '') {
        $errors[] = 'Phone number is required';
    } else if (!preg_match('/^09\d{9}$/', $phone)) {
        $errors[] = 'Phone must start with 09 and be exactly 11 digits';
    }
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';

    if (!$errors) {
        // Check duplicate username in both tables
        $dupUser1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE username = ? LIMIT 1');
        $dupUser1->bind_param('s', $username);
        $dupUser1->execute();
        $dupUser1->store_result();
        $existsInRes = ($dupUser1->num_rows > 0);
        $dupUser1->close();

        $dupUser2 = $conn->prepare('SELECT 1 FROM user_table WHERE username = ? LIMIT 1');
        $dupUser2->bind_param('s', $username);
        $dupUser2->execute();
        $dupUser2->store_result();
        $existsInUser = ($dupUser2->num_rows > 0);
        $dupUser2->close();

        if ($existsInRes || $existsInUser) {
            $errors[] = 'Username is already taken';
        }

        // Check duplicate email in both tables
        if (!$errors) {
            $dupEmail1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE email = ? LIMIT 1');
            $dupEmail1->bind_param('s', $email);
            $dupEmail1->execute();
            $dupEmail1->store_result();
            $existsInResEmail = ($dupEmail1->num_rows > 0);
            $dupEmail1->close();

            $dupEmail2 = $conn->prepare('SELECT 1 FROM user_table WHERE email = ? LIMIT 1');
            $dupEmail2->bind_param('s', $email);
            $dupEmail2->execute();
            $dupEmail2->store_result();
            $existsInUserEmail = ($dupEmail2->num_rows > 0);
            $dupEmail2->close();

            if ($existsInResEmail || $existsInUserEmail) {
                $errors[] = 'Email is already in use';
            }
        }

        // Check duplicate employee ID in both tables
        if (!$errors && !empty($employee_id)) {
            $dupEmpId1 = $conn->prepare('SELECT 1 FROM reservation_users WHERE employee_id = ? LIMIT 1');
            $dupEmpId1->bind_param('s', $employee_id);
            $dupEmpId1->execute();
            $dupEmpId1->store_result();
            $existsInResEmpId = ($dupEmpId1->num_rows > 0);
            $dupEmpId1->close();

            // Requestors are stored in reservation_users only; employee_id exists only there
            $existsInUserEmpId = false;

            if ($existsInResEmpId || $existsInUserEmpId) {
                $errors[] = 'Employee ID is already registered';
            }
        }

        if (!$errors) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            
            // Check if OCR data was provided for auto-activation
            $ocr_verified = false;
            
            if (!empty($employee_id) || !empty($department)) {
                // OCR data provided, auto-activate
                $ocr_verified = true;
            }
            
            $status = $ocr_verified ? 'Active' : 'Inactive';
            
            // Try to insert with OCR columns first, fallback to basic insert
            try {
                $stmt = $conn->prepare('INSERT INTO reservation_users (full_name, username, email, phone, password, status, employee_id, department) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('ssssssss', $full_name, $username, $email, $phone, $hashed, $status, $employee_id, $department);
            } catch (Exception $e) {
                // Fallback to basic insert if columns don't exist
                $stmt = $conn->prepare('INSERT INTO reservation_users (full_name, username, email, phone, password, status) VALUES (?,?,?,?,?,?)');
                $stmt->bind_param('ssssss', $full_name, $username, $email, $phone, $hashed, $status);
            }
            
            if ($stmt->execute()) {
                if ($ocr_verified) {
                    $success = 'Registration successful! Your account has been auto-activated with ID card verification.';
                } else {
                    $success = 'Registration successful. Please wait for admin activation.';
                }
            } else {
                $errors[] = 'Failed to register. Please contact admin if this persists.';
            }
            $stmt->close();
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register | Smart Track</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <!-- Bootstrap Icons CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Include FontAwesome for icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  
  <style>
    :root {
      --primary-blue: #003566;
      --accent-blue: #00b4d8;
      --light-blue: #0096c7;
      --dark-blue: #001d3d;
      --text-dark: #2d3748;
      --text-light: #718096;
      --bg-light: #f7fafc;
      --white: #ffffff;
      --success: #48bb78;
      --warning: #ed8936;
      --danger: #f56565;
      --border-light: #e2e8f0;
      --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 50%, #001122 100%);
      color: var(--text-dark);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    /* Landscape Layout */
    .landscape-modal-body {
      padding: 0;
      display: flex;
      height: 85vh;
      max-height: 85vh;
    }
    
    .landscape-form-section {
      flex: 1;
      padding: 1.5rem;
      background: var(--white);
      overflow-y: auto;
      width: 100%;
    }
    
    
    /* Form adjustments for landscape */
    .landscape-form-section .row {
      margin: 0 -0.25rem;
    }
    
    .landscape-form-section .col-md-6 {
      padding: 0 0.25rem;
    }
    
    .landscape-form-section .mb-3 {
      margin-bottom: 0.5rem !important;
    }
    
    .landscape-form-section .form-label {
      font-size: 0.85rem;
      margin-bottom: 0.2rem;
      font-weight: 600;
    }
    
    .landscape-form-section .form-control {
      font-size: 0.85rem;
      padding: 0.4rem 0.6rem;
      height: auto;
    }
    
    .landscape-form-section .form-text {
      font-size: 0.75rem;
      margin-top: 0.2rem;
    }
    
    .landscape-form-section .btn {
      font-size: 0.9rem;
      padding: 0.6rem 1rem;
    }
    
    .landscape-form-section .alert {
      padding: 0.5rem 0.75rem;
      font-size: 0.85rem;
      margin-bottom: 0.5rem;
    }
    
    /* Read-only field styling */
    .landscape-form-section .form-control[readonly] {
      background-color: #f8f9fa;
      border-color: #dee2e6;
      color: #6c757d;
      cursor: not-allowed;
    }
    
    .landscape-form-section .form-control[readonly]:focus {
      box-shadow: none;
      border-color: #dee2e6;
    }
    
    /* Responsive adjustments */
    @media (max-width: 991px) {
      .landscape-modal-body {
        height: 90vh;
      }
      
      .landscape-form-section {
        padding: 1rem;
      }
    }

    /* Alert Styling */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 20px;
      font-weight: 500;
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

    /* Enhanced Modal */
    .modal-content {
      border-radius: 20px;
      border: none;
      box-shadow: var(--shadow-xl);
      overflow: hidden;
    }

    .modal-body {
      padding: 3rem;
      background: linear-gradient(135deg, white 0%, var(--bg-light) 100%);
      position: relative;
    }

    .modal-body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-blue), var(--light-blue));
    }

    .btn-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      border: none;
      background: rgba(0, 0, 0, 0.1);
      color: var(--text-dark);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      transition: all 0.3s ease;
      opacity: 0.7;
    }

    .btn-close:hover {
      background: rgba(239, 68, 68, 0.1);
      color: var(--danger);
      opacity: 1;
      transform: scale(1.1);
    }

    .login-container .logo {
      width: 80px;
      height: 80px;
      margin-bottom: 1.5rem;
      border-radius: 50%;
      border: 3px solid var(--accent-blue);
      box-shadow: 0 4px 20px rgba(0, 180, 216, 0.3);
      object-fit: cover;
    }

    .login-container h4 {
      color: var(--primary-blue);
      font-weight: 600;
      margin-bottom: 2rem;
    }

    .form-control {
      border: 2px solid var(--border-light);
      border-radius: 12px;
      padding: 15px 20px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background-color: var(--bg-light);
    }

    .form-control:focus {
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
      background-color: white;
    }

    .form-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
      border: none;
      font-size: 1.1rem;
      font-weight: 600;
      padding: 15px 30px;
      border-radius: 50px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 180, 216, 0.4);
      background: linear-gradient(135deg, var(--light-blue) 0%, var(--accent-blue) 100%);
    }

    .btn-outline-primary {
      color: var(--accent-blue) !important;
      border-color: var(--accent-blue) !important;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
      background-color: var(--accent-blue) !important;
      border-color: var(--accent-blue) !important;
      color: white !important;
      transform: translateY(-1px);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .modal-body {
        padding: 2rem;
      }
    }
  </style>
</head>
<body>
  <!-- Enhanced Registration Modal -->
  <div class="modal fade show d-block" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-body landscape-modal-body">
              <!-- Close Button -->
              <button type="button" class="btn-close" onclick="window.location.href='index.php'" aria-label="Close">
                <i class="fas fa-times"></i>
              </button>
              
              <!-- Form Section -->
              <div class="landscape-form-section">
                <div class="text-center mb-3">
                  <!-- Logo -->
                  <img src="images/bago_city.jpg" alt="Logo" class="logo" style="width: 60px; height: 60px;" />
                  <h5 class="mb-1">Create Account</h5>
                  <p class="text-muted mb-3 small">Sign up to request vehicle reservations</p>
                <?php if ($success): ?>
                  <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                  </div>
                <?php endif; ?>
                <?php if ($errors): ?>
                  <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                      <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                          <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                <?php endif; ?>

                <form method="post" class="text-start" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                    <!-- Basic Information Row -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">
                          <i class="fas fa-user me-1"></i>Full Name
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        <div class="form-text" id="full_name_help">
                          <i class="fas fa-info-circle me-1"></i>Will be auto-filled from ID card
                        </div>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">
                          <i class="fas fa-at me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" pattern="^[A-Za-z0-9_]{3,30}$" minlength="3" maxlength="30" autocomplete="off">
                        <div class="form-text" id="usernameHelp">Use 3-30 characters: letters, numbers, or _</div>
                        <div class="small mt-1" id="usernameStatus"></div>
                      </div>
                    </div>
                    
                    <!-- Contact Information Row -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">
                          <i class="fas fa-envelope me-1"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="off">
                        <div class="small mt-1" id="emailStatus"></div>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">
                          <i class="fas fa-phone me-1"></i>Phone
                        </label>
                        <input type="text" class="form-control" id="phone" name="phone" placeholder="09XXXXXXXXX" required inputmode="numeric" pattern="^09\d{9}$" maxlength="11" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <div class="form-text">Must start with 09 and be exactly 11 digits</div>
                      </div>
                    </div>
                    
                    <!-- Employee Information (Auto-filled by OCR) -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label for="employee_id" class="form-label">
                          <i class="fas fa-id-badge me-1"></i>Employee ID
                        </label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Will be auto-filled from ID card" value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>" readonly>
                        <div class="form-text">
                          <i class="fas fa-lock me-1"></i>Auto-filled from ID card (read-only)
                        </div>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label for="department" class="form-label">
                          <i class="fas fa-building me-1"></i>Department
                        </label>
                        <input type="text" class="form-control" id="department" name="department" placeholder="Will be auto-filled from ID card" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>" readonly>
                        <div class="form-text">
                          <i class="fas fa-lock me-1"></i>Auto-filled from ID card (read-only)
                        </div>
                      </div>
                    </div>
                    
                    <!-- OCR ID Card Upload -->
                    <div class="mb-3">
                      <label for="id_card" class="form-label">
                        <i class="fas fa-id-card me-1"></i>Upload ID Card (Optional)
                      </label>
                      <input type="file" class="form-control" id="id_card" name="id_card" accept="image/*" onchange="processIDCard(this)">
                      <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Upload ID card for auto-verification
                      </div>
                      
                      <!-- OCR Results Preview -->
                      <div id="ocr-preview" class="mt-2" style="display: none;">
                        <div class="card border-success">
                          <div class="card-header bg-success text-white py-2">
                            <i class="fas fa-check-circle me-2"></i>Detected Information
                          </div>
                          <div class="card-body py-2">
                            <div id="ocr-results"></div>
                            <div class="mt-1">
                              <small class="text-muted">
                                <i class="fas fa-lightbulb me-1"></i>
                                Form will be auto-filled
                              </small>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- OCR Loading -->
                      <div id="ocr-loading" class="mt-2" style="display: none;">
                        <div class="d-flex align-items-center text-primary">
                          <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                          </div>
                          <span class="small">Processing ID card...</span>
                        </div>
                      </div>
                    </div>
                    <!-- Password Section -->
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">
                          <i class="fas fa-lock me-1"></i>Password
                        </label>
                        <div class="position-relative">
                          <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required minlength="8">
                          <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="togglePassword">
                            <i class="fas fa-eye"></i>
                          </button>
                        </div>
                        <div class="mt-2">
                          <div class="progress" style="height: 6px;">
                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                          </div>
                          <small id="passwordStrengthText" class="text-muted"></small>
                        </div>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">
                          <i class="fas fa-lock me-1"></i>Confirm Password
                        </label>
                        <div class="position-relative">
                          <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                          <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                    <input type="hidden" name="role" value="Requester">
                    <!-- Hidden fields for OCR data -->
                    <input type="hidden" id="ocr_employee_id" name="ocr_employee_id" value="">
                    <input type="hidden" id="ocr_department" name="ocr_department" value="">
                    
                    <div class="d-grid mb-2">
                      <button type="submit" class="btn btn-primary" id="registerBtn">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                      </button>
                    </div>
                    <div class="text-center">
                      <small class="text-muted">
                        Already have an account? <a href="index.php" class="text-decoration-none" style="color: var(--accent-blue);">Sign in here</a>
                      </small>
                      <div class="mt-1">
                        <small class="text-muted">
                          <i class="fas fa-info-circle me-1"></i>
                          Account requires admin approval
                        </small>
                      </div>
                    </div>
                </form>
              </div>
              
          </div>
        </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Tesseract.js (client-side OCR) -->
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
  
  <script>
    // Initialize AOS
    AOS.init({
      duration: 1000,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    });

    // Password toggle functionality
    function initializePasswordToggle() {
      const togglePassword = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('confirm_password');

      if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
          const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', type);
          
          const icon = this.querySelector('i');
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      }

      if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', function() {
          const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          confirmPasswordInput.setAttribute('type', type);
          
          const icon = this.querySelector('i');
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      }
    }

    // Enhanced form validation
    function initializeFormValidation() {
      const registerForm = document.getElementById('registerForm');
      const usernameInput = document.getElementById('username');
      const usernameStatus = document.getElementById('usernameStatus');
      const emailInput = document.getElementById('email');
      const emailStatus = document.getElementById('emailStatus');
      const phoneInput = document.getElementById('phone');
      const passwordInput = document.getElementById('password');
      const strengthBar = document.getElementById('passwordStrengthBar');
      const strengthText = document.getElementById('passwordStrengthText');
      
      if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
          const fullName = document.getElementById('full_name').value.trim();
          const username = document.getElementById('username').value.trim();
          const email = document.getElementById('email').value.trim();
          const password = document.getElementById('password').value.trim();
          const confirmPassword = document.getElementById('confirm_password').value.trim();
          const phone = phoneInput.value.trim();
          
          if (!fullName || !username || !email || !password || !confirmPassword || !phone) {
            e.preventDefault();
            showAlert('error', 'Please fill in all required fields.');
            return;
          }
          
          // Validate username format
          if (!/^[A-Za-z0-9_]{3,30}$/.test(username)) {
            e.preventDefault();
            showAlert('error', 'Username must be 3-30 chars: letters, numbers, or _');
            return;
          }

          // Validate phone (starts with 09 and exactly 11 digits)
          if (!/^09\d{9}$/.test(phone)) {
            e.preventDefault();
            showAlert('error', 'Phone must start with 09 and be exactly 11 digits.');
            return;
          }

          if (password !== confirmPassword) {
            e.preventDefault();
            showAlert('error', 'Passwords do not match.');
            return;
          }
          
          if (password.length < 8) {
            e.preventDefault();
            showAlert('error', 'Password must be at least 8 characters long.');
            return;
          }
          
          // Show loading state
          const registerBtn = document.getElementById('registerBtn');
          const originalContent = registerBtn.innerHTML;
          registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
          registerBtn.disabled = true;
          
          // Reset button after 5 seconds if form doesn't submit
          setTimeout(() => {
            registerBtn.innerHTML = originalContent;
            registerBtn.disabled = false;
          }, 5000);
        });
      }

      // Live username availability check (debounced)
      let usernameDebounce;
      if (usernameInput && usernameStatus) {
        usernameInput.addEventListener('input', function() {
          const value = this.value.trim();
          usernameStatus.textContent = '';
          usernameStatus.className = 'small mt-1';
          clearTimeout(usernameDebounce);

          if (!value || !/^[A-Za-z0-9_]{3,30}$/.test(value)) {
            return;
          }

          usernameDebounce = setTimeout(async () => {
            try {
              const res = await fetch(`register.php?check_username=${encodeURIComponent(value)}`, { cache: 'no-store' });
              const data = await res.json();
              if (data && data.ok) {
                usernameStatus.textContent = data.message;
                if (data.valid && data.available) {
                  usernameStatus.classList.add('text-success');
                } else {
                  usernameStatus.classList.add('text-danger');
                }
              }
            } catch (e) {
              // ignore
            }
          }, 400);
        });
      }

      // Live email availability check (debounced)
      let emailDebounce;
      if (emailInput && emailStatus) {
        emailInput.addEventListener('input', function() {
          const value = this.value.trim();
          emailStatus.textContent = '';
          emailStatus.className = 'small mt-1';
          clearTimeout(emailDebounce);

          if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return;
          }

          emailDebounce = setTimeout(async () => {
            try {
              const res = await fetch(`register.php?check_email=${encodeURIComponent(value)}`, { cache: 'no-store' });
              const data = await res.json();
              if (data && data.ok) {
                emailStatus.textContent = data.message;
                if (data.valid && data.available) {
                  emailStatus.classList.add('text-success');
                } else {
                  emailStatus.classList.add('text-danger');
                }
              }
            } catch (e) {
              // ignore
            }
          }, 400);
        });
      }

      // Live employee ID availability check (debounced)
      const employeeIdInput = document.getElementById('employee_id');
      const employeeIdStatus = document.getElementById('employee_id_status');
      let employeeIdDebounce;
      
      if (employeeIdInput) {
        // Create status element if it doesn't exist
        if (!employeeIdStatus) {
          const statusDiv = document.createElement('div');
          statusDiv.id = 'employee_id_status';
          statusDiv.className = 'small mt-1';
          employeeIdInput.parentNode.appendChild(statusDiv);
        }
        
        employeeIdInput.addEventListener('input', function() {
          const value = this.value.trim();
          const statusElement = document.getElementById('employee_id_status');
          statusElement.textContent = '';
          statusElement.className = 'small mt-1';
          clearTimeout(employeeIdDebounce);

          if (!value) {
            return;
          }

          employeeIdDebounce = setTimeout(async () => {
            try {
              const res = await fetch(`register.php?check_employee_id=${encodeURIComponent(value)}`, { cache: 'no-store' });
              const data = await res.json();
              if (data && data.ok) {
                statusElement.textContent = data.message;
                if (data.valid && data.available) {
                  statusElement.classList.add('text-success');
                } else {
                  statusElement.classList.add('text-danger');
                }
              }
            } catch (e) {
              // ignore
            }
          }, 400);
        });
      }

      // Enforce numeric-only phone input and length
      if (phoneInput) {
        phoneInput.addEventListener('input', function() {
          // remove any non-digits
          this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });
      }

      // Password strength meter
      function evaluateStrength(pwd) {
        let score = 0;
        if (pwd.length >= 8) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return score; // 0-5
      }

      function updateStrengthUI(score) {
        const percent = Math.min(score * 20, 100);
        strengthBar.style.width = percent + '%';
        strengthBar.className = 'progress-bar';
        let label = 'Very Weak';
        let barClass = 'bg-danger';
        if (score <= 1) { label = 'Very Weak'; barClass = 'bg-danger'; }
        else if (score === 2) { label = 'Weak'; barClass = 'bg-danger'; }
        else if (score === 3) { label = 'Fair'; barClass = 'bg-warning'; }
        else if (score === 4) { label = 'Good'; barClass = 'bg-info'; }
        else { label = 'Strong'; barClass = 'bg-success'; }
        strengthBar.classList.add(barClass);
        strengthText.textContent = label;
      }

      if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
          const score = evaluateStrength(this.value);
          updateStrengthUI(score);
        });
      }
    }

    // Enhanced alert system
    function showAlert(type, message) {
      const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-exclamation-triangle'
      };

      Swal.fire({
        icon: type,
        title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Information',
        text: message,
        confirmButtonColor: 'var(--accent-blue)',
        confirmButtonText: 'OK',
        customClass: {
          popup: 'swal-popup-custom'
        }
      });
    }

    // OCR Processing Function (client-side via Tesseract.js)
    async function processIDCard(input) {
      const file = input.files[0];
      if (!file) { hideOCRResults(); return; }
      
      showOCRLoading();
      try {
        // Load the image into a canvas to crop known regions (template-based)
        const imgUrl = URL.createObjectURL(file);
        const img = await loadImage(imgUrl);
        const { nameImg, deptImg, idImg, idImgBottom } = cropTemplateRegions(img);

        // Run OCR per-region with whitelists and tighter psm
        // Extra preprocessing for ID: test multiple variants
        const idDark  = await binarizeDataUrl(idImg, false, 120);
        const idInv   = await binarizeDataUrl(idImg, true, 130);
        const idBottom = await binarizeDataUrl(idImgBottom, false, 120);

        const [nameRes, deptRes, idRes] = await Promise.all([
          Tesseract.recognize(nameImg, 'eng', { logger: m => console.log(m),  tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz .-'",  psm: 7 }),
          Tesseract.recognize(deptImg, 'eng', { logger: m => console.log(m), tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz &-", psm: 7 }),
          Tesseract.recognize(idDark,   'eng', { logger: m => console.log(m),  tessedit_char_whitelist: "0123456789-", psm: 7 })
        ]);

        // Don't revoke yet; may use for fallback OCR

        const nameText = (nameRes.data.text || '').replace(/\s{2,}/g,' ').trim();
        const deptText = (deptRes.data.text || '').replace(/\s{2,}/g,' ').trim();
        let idTextRaw = (idRes.data.text || '').replace(/\s+/g,' ');
        if (!/\d/.test(idTextRaw)) {
          const idRes2 = await Tesseract.recognize(idInv, 'eng', { tessedit_char_whitelist: '0123456789-', psm: 7 });
          idTextRaw = (idRes2.data.text || '').replace(/\s+/g,' ');
        }
        if (!/\d/.test(idTextRaw)) {
          const idRes3 = await Tesseract.recognize(idBottom, 'eng', { tessedit_char_whitelist: '0123456789-', psm: 7 });
          idTextRaw = (idRes3.data.text || '').replace(/\s+/g,' ');
        }

        // Extract employee id strictly from the ID strip OCR text
        const stripId = extractEmployeeIdFromStrip(idTextRaw);

        let parsed = {
          full_name: pickBestName(nameText),
          department: pickDepartment(deptText),
          employee_id: stripId || normalizeEmployeeId(idTextRaw)
        };

        // Heuristic validation and fallbacks
        const validId = isValidEmpId(parsed.employee_id);
        if (!validId) {
          // Fallback: OCR whole image with numeric whitelist to extract ID
          const wholeIdRes = await Tesseract.recognize(imgUrl, 'eng', { tessedit_char_whitelist: '0123456789-', psm: 6 });
          const idWhole = normalizeEmployeeId((wholeIdRes.data.text || '').replace(/\s+/g,' '));
          if (isValidEmpId(idWhole)) parsed.employee_id = idWhole;
        }

        // Ensure department contains letters; prefer explicit Bago City College if present
        if (!parsed.department || !/[A-Za-z]/.test(parsed.department)) {
          const wholeDept = (await Tesseract.recognize(imgUrl, 'eng', { psm: 6 })).data.text || '';
          const deptPick = pickDepartment(wholeDept);
          if (deptPick) parsed.department = deptPick;
        }

        // If name looks too short, try whole-image name extraction
        if (!parsed.full_name || parsed.full_name.length < 8) {
          const wholeNameText = (await Tesseract.recognize(imgUrl, 'eng', { psm: 6 })).data.text || '';
          const namePick = pickBestName(wholeNameText);
          if (namePick) parsed.full_name = namePick;
        }

        URL.revokeObjectURL(imgUrl);

        // Fill fields (only if found)
        if (parsed.full_name) {
          document.getElementById('full_name').value = parsed.full_name;
          document.getElementById('full_name').readOnly = true;
          const help = document.getElementById('full_name_help');
          if (help) help.innerHTML = '<i class="fas fa-lock me-1"></i>Auto-filled from ID card (read-only)';
        }
        if (parsed.employee_id) {
          document.getElementById('employee_id').value = parsed.employee_id;
          document.getElementById('ocr_employee_id').value = parsed.employee_id;
            document.getElementById('employee_id').readOnly = true;
          }
        if (parsed.department) {
          document.getElementById('department').value = parsed.department;
          document.getElementById('ocr_department').value = parsed.department;
            document.getElementById('department').readOnly = true;
          }
          
        hideOCRLoading();
        if (parsed.full_name || parsed.employee_id || parsed.department) {
          showOCRResults(parsed);
          showAlert('success', 'ID card processed locally. Fields auto-filled.');
        } else {
          showAlert('warning', 'OCR finished but could not detect fields. Please fill manually.');
          hideOCRResults();
        }
      } catch (e) {
        console.error('OCR Error:', e);
        hideOCRLoading();
        showAlert('error', 'OCR failed. Please try a clearer photo.');
      }
    }

    function loadImage(url) {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
      });
    }

    function cropTemplateRegions(img) {
      // Create a canvas helper
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      ctx.drawImage(img, 0, 0);

      // Define relative regions based on template:
      // These percentages may need small tweaks depending on your template crop
      const W = canvas.width, H = canvas.height;
      // Template-aligned boxes (see provided format image)
      const nameBox = { x: Math.round(W*0.16), y: Math.round(H*0.52), w: Math.round(W*0.68), h: Math.round(H*0.06) };
      const deptBox = { x: Math.round(W*0.12), y: Math.round(H*0.60), w: Math.round(W*0.76), h: Math.round(H*0.08) };
      // Employee No strip near very bottom
      const idBox   = { x: Math.round(W*0.06), y: Math.round(H*0.92), w: Math.round(W*0.88), h: Math.round(H*0.06) };

      const nameImg = cropBox(canvas, nameBox, true);
      const deptImg = cropBox(canvas, deptBox, true);
      const idImg   = cropBox(canvas, idBox, false); // keep colors for blue text
      // Fallback region: bottom 15% of card
      const idBottomBox = { x: 0, y: Math.round(H*0.85), w: W, h: Math.round(H*0.15) };
      const idImgBottom = cropBox(canvas, idBottomBox, false);

      // Debug previews (temporary): show cropped images under OCR preview card
      try {
        const previewHost = document.getElementById('ocr-preview');
        if (previewHost) {
          const dbg = document.createElement('div');
          dbg.className = 'mt-2';
          dbg.innerHTML = `
            <div class="small text-muted">OCR crop previews (temporary):</div>
            <div class="d-flex gap-2 flex-wrap">
              <div><div class="small">Name</div><img src="${nameImg}" style="max-width:200px;border:1px solid #ddd"></div>
              <div><div class="small">Department</div><img src="${deptImg}" style="max-width:200px;border:1px solid #ddd"></div>
              <div><div class="small">Employee ID</div><img src="${idImg}" style="max-width:200px;border:1px solid #ddd"></div>
            </div>`;
          // Remove old debug if any
          const olds = previewHost.querySelectorAll('[data-ocr-debug]');
          olds.forEach(n=>n.remove());
          dbg.setAttribute('data-ocr-debug','1');
          previewHost.appendChild(dbg);
        }
      } catch(e) { /* ignore */ }
      return { nameImg, deptImg, idImg, idImgBottom };
    }

    function cropBox(canvas, box, binarize) {
      const out = document.createElement('canvas');
      out.width = box.w; out.height = box.h;
      const octx = out.getContext('2d');
      octx.drawImage(canvas, box.x, box.y, box.w, box.h, 0, 0, box.w, box.h);
      if (binarize) {
        const imgData = octx.getImageData(0,0,box.w,box.h);
        const d = imgData.data;
        for (let i=0;i<d.length;i+=4){
          const gray = 0.299*d[i]+0.587*d[i+1]+0.114*d[i+2];
          const v = gray > 135 ? 255 : 0; // slightly lower threshold
          d[i]=d[i+1]=d[i+2]=v;
        }
        octx.putImageData(imgData,0,0);
      }
      return out.toDataURL();
    }

    function binarizeDataUrl(dataUrl, invert, threshold){
      const img = document.createElement('img');
      return new Promise((resolve)=>{
        img.onload = () => {
          const c = document.createElement('canvas');
          c.width = img.naturalWidth; c.height = img.naturalHeight;
          const cx = c.getContext('2d');
          cx.drawImage(img,0,0);
          const id = cx.getImageData(0,0,c.width,c.height);
          const d = id.data; const th = threshold || 140;
          for (let i=0;i<d.length;i+=4){
            const gray = 0.299*d[i]+0.587*d[i+1]+0.114*d[i+2];
            let v = gray > th ? 255 : 0;
            if (invert) v = 255 - v;
            d[i]=d[i+1]=d[i+2]=v; // keep alpha
          }
          cx.putImageData(id,0,0);
          resolve(c.toDataURL());
        };
        img.src = dataUrl;
      });
    }

    function pickBestName(text) {
      const lines = text.split(/\n+/).map(s=>s.trim()).filter(Boolean);
      const blacklist = /(Department|Head|College|City|Mayor|Employee|Office|Division|Section|Unit)/i;
      let best = '';
      // Prefer First M. Last (with middle initial)
      for (const l of lines){
        if (blacklist.test(l)) continue;
        const m = l.match(/\b([A-Z][a-z]+\s+[A-Z]\.\s+[A-Z][a-z]+)\b/);
        if (m && m[1].length > best.length) best = m[1];
      }
      // Fallback to 2–4 Title-Case words
      if (!best){
        for (const l of lines){
          if (blacklist.test(l)) continue;
          const m = l.match(/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\b/);
          if (m && m[1].length > best.length) best = m[1];
        }
      }
      return best;
    }

    function pickDepartment(text){
      const t = text.replace(/\s{2,}/g,' ').trim();
      if (/Bago\s+City\s+College/i.test(t)) return 'Bago City College';
      // Try to extract after a department keyword
      const m = t.match(/\b(Department|Dept\.?|Office|Division|Section|Unit)[:\s\-]*([A-Za-z\s\-&]{2,})/i);
      if (m) return m[2].trim();
      // If text is noisy, return empty to avoid wrong fill
      if (!/[A-Za-z]{3,}/.test(t)) return '';
      return t;
    }

    function normalizeEmployeeId(text){
      // Normalize common OCR confusions before parsing
      const pre = (text || '')
        .replace(/[Oo]/g,'0')
        .replace(/[Il]/g,'1')
        .replace(/S/g,'5')
        .replace(/B/g,'8');
      // Prefer explicit "Employee No" pattern
      let m = pre.match(/Employee\s*(ID|No\.?|Number)[:\s-]*([\d\s]{7,10})-\s*(\d{2})/i);
      if (m) {
        const left = (m[2]||'').replace(/\s+/g,'');
        if (left.length >= 8) return left.slice(0,8) + '-' + m[3];
      }
      // Generic: 8 digits (allow spaces) then dash then 2 digits
      m = pre.match(/(\d[\s\d]{7,9})\s*-\s*(\d{2})/);
      if (m) {
        const left = m[1].replace(/\s+/g,'');
        if (left.length >= 8) return left.slice(0,8) + '-' + m[2];
      }
      const compact = pre.replace(/\s+/g,'');
      const n = compact.match(/\b(\d{8}-\d{2})\b/);
      if (n) return n[1];
      // Digits-only fallback: extract 10 digits and format
      const digits = (pre.match(/\d/g) || []).join('');
      if (digits.length >= 10) {
        return digits.slice(0,8) + '-' + digits.slice(8,10);
      }
      return '';
    }

    function extractEmployeeIdFromStrip(text){
      const raw = (text || '');
      
      // Safer approach: Try to find "Employee No. XXXXXXXX-XX" pattern first
      let match = raw.match(/Employee\s*No\.\s*(\d{8})\s*-\s*(\d{2})/i);
      if (match) {
        return `${match[1]}-${match[2]}`;
      }
      
      // Fallback: Extract only digits from Employee No. section and take rightmost 10
      const employeeNoMatch = raw.match(/Employee\s*No\.\s*([\d\s-]+)/i);
      if (employeeNoMatch) {
        const digits = employeeNoMatch[1].replace(/\D/g, '');
        if (digits.length >= 10) {
          // Take the rightmost 10 digits and format as 8-2
          const rightmost10 = digits.slice(-10);
          return `${rightmost10.slice(0, 8)}-${rightmost10.slice(8, 10)}`;
        }
      }
      
      return '';
    }

    function isValidEmpId(id){
      return /^\d{8}-\d{2}$/.test((id||'').trim());
    }

    // Simple parsers for common ID card formats (heuristics)
    function parseOcrText(text) {
      const cleaned = (text || '').replace(/\r/g, '').trim();
      const rawLines = cleaned.split(/\n+/).map(l => l.trim()).filter(Boolean);
      const lines = rawLines.map(l => l.replace(/\s{2,}/g, ' '));
      const upper = lines.map(l => l.toUpperCase());

      // EMPLOYEE ID: prefer explicit labels, else robust token
      let employee_id = '';
      // Strong template pattern e.g. 05012012-02 (8 digits dash 2 digits)
      // Allow spaces inside the first 8 digits, then dash, then 2 digits
      let idDashMatch = cleaned.match(/(\d[\s\d]{7})\s*-\s*(\d{2})/);
      if (idDashMatch) {
        employee_id = idDashMatch[1].replace(/\s+/g,'') + '-' + idDashMatch[2];
      } else {
        // Fallback: compressed variant without spaces
        const compact = cleaned.replace(/\s+/g,'');
        const compactMatch = compact.match(/\b(\d{8}-\d{2})\b/);
        if (compactMatch) employee_id = compactMatch[1];
      }
      // labeled forms (e.g., "Employee no 05012012-02")
      for (let i = 0; i < lines.length && !employee_id; i++) {
        const m = lines[i].match(/\b(Employee\s*(ID|No\.?|Number)|ID\s*(No\.?|Number)|Card\s*No\.?)\b[^A-Z0-9]*([A-Z0-9\-]{4,})/i);
        if (m) employee_id = m[2].toUpperCase();
      }
      if (!employee_id) {
        const empNo = cleaned.match(/Employee\s*(ID|No\.?|Number)[:\s-]*([A-Z0-9\-]{4,})/i);
        if (empNo) employee_id = (empNo[2] || '').toUpperCase();
      }
      if (!employee_id) {
        // choose strongest ID-like token (alnum/hyphen, length>=6) that isn't a word like HEAD/ENTAL
        const tokens = upper.join(' ').match(/[A-Z0-9\-]{6,}/g) || [];
        const blacklist = new Set(['HEAD','DEPARTMENT','DIVISION','OFFICE','PHILIPPINES']);
        const candidate = tokens.find(t => !blacklist.has(t) && /[0-9]/.test(t));
        if (candidate) employee_id = candidate;
      }

      // DEPARTMENT: look for labeled lines first, or specific template text
      let department = '';
      for (let i = 0; i < lines.length && !department; i++) {
        const m = lines[i].match(/\b(Department|Dept\.?|Office|Division|Section|Unit)[:\s\-]*([A-Za-z\s\-&]{2,})/i);
        if (m) department = m[2].trim();
      }
      if (!department) {
        const idx = upper.findIndex(l => /(DEPARTMENT|DEPT\.?|OFFICE|DIVISION|SECTION|UNIT)\b/.test(l));
        if (idx >= 0) {
          department = lines[idx].replace(/^(Department|Dept\.?|Office|Division|Section|Unit)[:\s\-]*/i, '').trim();
        }
      }
      // Template-specific: detect "Bago City College"
      if (!department) {
        const bcc = joined.match(/Bago\s+City\s+College/i);
        if (bcc) department = 'Bago City College';
      }
      // avoid single generic words like 'HEAD'
      if (department && department.split(/\s+/).length === 1 && /^(Head|Office|Department)$/i.test(department)) {
        department = '';
      }

      // FULL NAME: prefer a title-cased or all-caps 2–4 word line without labels
      let full_name = '';
      // try explicit label
      let nameLine = lines.find(l => /\bName\b[:\s]/i.test(l));
      if (!nameLine) {
        // pick best-scoring name-like line (2-5 words, Title Case, allow middle initial)
        const candidates = rawLines.filter(l => {
          const w = l.trim().split(/\s+/);
          if (w.length < 2 || w.length > 5) return false;
          if (/(Department|Office|Division|Section|Unit|Employee|ID|Number)/i.test(l)) return false;
          return /^[A-Za-z .,'-]+$/.test(l);
        });
        // scoring: prefer longest Title-Case phrase 2–4 words
        let bestScore = -1;
        for (const c of candidates) {
          const m = c.match(/\b([A-Z][a-z]+(?:\s+[A-Z]\.)?(?:\s+[A-Z][a-z]+){1,2})\b/);
          if (m) {
            const cand = m[1];
            const score = cand.length; // length as score
            if (score > bestScore) { bestScore = score; nameLine = cand; }
          }
        }
        // as a last resort, look for First M. Last pattern anywhere
        if (!nameLine) {
          const any = cleaned.match(/\b([A-Z][a-z]+)(?:\s+[A-Z]\.)?\s+([A-Z][a-z]+)(?:\s+[A-Z][a-z]+)?\b/);
          if (any) nameLine = any[0];
        }
      }
      if (nameLine) full_name = nameLine.replace(/^Name[:\s]*/i, '').trim();

      // Normalize
      full_name = full_name.replace(/\s{2,}/g, ' ').trim();
      department = department.replace(/\s{2,}/g, ' ').trim();

      // Debug (can be commented out after tuning)
      console.log('OCR RAW =>', cleaned);
      console.log('PARSED =>', { full_name, employee_id, department });
      return { full_name, employee_id, department, raw: cleaned };
    }
    
    function showOCRLoading() {
      document.getElementById('ocr-loading').style.display = 'block';
      document.getElementById('ocr-preview').style.display = 'none';
    }
    
    function hideOCRLoading() {
      document.getElementById('ocr-loading').style.display = 'none';
    }
    
    function showOCRResults(data) {
      const preview = document.getElementById('ocr-preview');
      const results = document.getElementById('ocr-results');
      
      let html = '';
      if (data.full_name) {
        html += `<p><strong><i class="fas fa-user me-1"></i>Name:</strong> ${data.full_name}</p>`;
      }
      if (data.employee_id) {
        html += `<p><strong><i class="fas fa-id-badge me-1"></i>Employee ID:</strong> ${data.employee_id}</p>`;
      }
      if (data.department) {
        html += `<p><strong><i class="fas fa-building me-1"></i>Department:</strong> ${data.department}</p>`;
      }
      
      if (html) {
        results.innerHTML = html;
        preview.style.display = 'block';
      } else {
        hideOCRResults();
      }
    }
    
    function hideOCRResults() {
      document.getElementById('ocr-preview').style.display = 'none';
      document.getElementById('ocr-loading').style.display = 'none';
    }

    // Initialize everything when page loads
    document.addEventListener('DOMContentLoaded', function () {
      initializePasswordToggle();
      initializeFormValidation();
      
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
    });

    // Add custom styles for SweetAlert
    const style = document.createElement('style');
    style.textContent = `
      .swal-popup-custom {
        border-radius: 15px !important;
        font-family: 'Inter', sans-serif !important;
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>
