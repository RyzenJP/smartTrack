<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information (support both reservation users and system users)
$user_id = $_SESSION['user_id'];
$isReservationUser = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'reservation';

if ($isReservationUser) {
    $sql = "SELECT *, profile_image FROM reservation_users WHERE id = ?";
} else {
    $sql = "SELECT *, profile_image FROM user_table WHERE user_id = ?";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];

    if ($isReservationUser) {
        $update_sql = "UPDATE reservation_users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $full_name, $email, $contact, $user_id);
    } else {
        $update_sql = "UPDATE user_table SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $full_name, $email, $contact, $user_id);
    }
    
    if ($update_stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $error_message = "Failed to update profile. Please try again.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($isReservationUser) {
                $password_sql = "UPDATE reservation_users SET password = ? WHERE id = ?";
            } else {
                $password_sql = "UPDATE user_table SET password = ? WHERE user_id = ?";
            }
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($password_stmt->execute()) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = "Failed to change password. Please try again.";
            }
        } else {
            $password_error = "New passwords do not match.";
        }
    } else {
        $password_error = "Current password is incorrect.";
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/profile_photos/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
            // Update database with new photo path
            if ($isReservationUser) {
                $photo_sql = "UPDATE reservation_users SET profile_image = ? WHERE id = ?";
            } else {
                $photo_sql = "UPDATE user_table SET profile_image = ? WHERE user_id = ?";
            }
            $photo_stmt = $conn->prepare($photo_sql);
            $photo_stmt->bind_param("si", $new_filename, $user_id);
            
            if ($photo_stmt->execute()) {
                $photo_success = "Profile photo updated successfully!";
                // Delete old photo if it exists
                if (isset($user['profile_image']) && $user['profile_image'] !== 'default.png' && file_exists($upload_dir . $user['profile_image'])) {
                    unlink($upload_dir . $user['profile_image']);
                }
                // Refresh user data
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $photo_error = "Failed to update profile photo in database.";
            }
        } else {
            $photo_error = "Failed to upload file.";
        }
    } else {
        $photo_error = "Invalid file type. Please upload JPG, PNG, or GIF images only.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profile | Smart Track</title>
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

    .main-content {
      margin-left: 250px;
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 70px;
    }
    
    .profile-container {
      max-width: 900px;
      width: 100%;
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .navbar {
      position: fixed;      
      top: 0;                
      left: 0;       
      width: 100%;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      border-bottom: 1px solid #dee2e6;
      z-index: 2000;
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }

    .profile-card {
      border: 0;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      overflow: hidden;
    }

    .profile-header {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
      padding: 2rem;
      text-align: center;
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 4px solid white;
      margin: 0 auto 1rem;
      background: rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
    }

    .profile-section {
      border-left: 4px solid var(--accent);
      padding-left: 1rem;
      margin-bottom: 2rem;
    }

    .form-floating {
      margin-bottom: 1rem;
    }

    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .btn-primary:hover {
      background-color: #001d3d;
      border-color: #001d3d;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 250px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
        display: block !important;
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
      
      .profile-container {
        margin: 0 auto;
      }
      
      .navbar {
        z-index: 2000;
      }
      
      .burger-btn {
        display: block;
      }
      
      /* Mobile layout adjustments */
      .col-md-8,
      .col-md-4 {
        margin-bottom: 20px;
      }
      
      .profile-header {
        padding: 1.5rem;
      }
      
      .profile-avatar {
        width: 100px;
        height: 100px;
        font-size: 2.5rem;
      }
      
      h4 {
        font-size: 1.5rem;
      }
      
      /* Mobile form sections */
      .profile-section {
        padding-left: 0.8rem;
        margin-bottom: 1.5rem;
      }
      
      .form-floating {
        margin-bottom: 0.8rem;
      }
      
      /* Mobile buttons */
      .btn {
        padding: 10px 16px;
        font-size: 14px;
      }
    }

    @media (max-width: 575.98px) {
      .main-content {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.5rem;
      }
      
      .profile-header {
        padding: 1rem;
      }
      
      .profile-avatar {
        width: 80px;
        height: 80px;
        font-size: 2rem;
      }
      
      h4 {
        font-size: 1.25rem;
      }
      
      p {
        font-size: 14px;
      }
      
      /* Mobile form fields */
      .form-floating {
        margin-bottom: 15px;
      }
      
      .form-floating > .form-control {
        padding: 12px 10px;
        font-size: 14px;
        border-radius: 8px;
      }
      
      .form-floating > label {
        font-size: 14px;
      }
      
      /* Mobile buttons */
      .btn {
        padding: 8px 14px;
        font-size: 13px;
        width: 100%;
        margin-bottom: 10px;
      }
      
      /* Mobile profile sections */
      .profile-section {
        padding-left: 0.5rem;
        margin-bottom: 1.2rem;
      }
      
      .profile-section h5 {
        font-size: 1rem;
        margin-bottom: 0.8rem;
      }
      
      /* Mobile card body */
      .card-body {
        padding: 16px;
      }
      
      /* Mobile form text */
      .form-text {
        font-size: 12px;
      }
      
      /* Mobile profile preview */
      .profile-preview {
        width: 80px;
        height: 80px;
      }
      
      /* Mobile account info */
      .mb-3 {
        margin-bottom: 12px;
      }
      
      .mb-3 strong {
        font-size: 13px;
      }
      
      .mb-3 span {
        font-size: 13px;
      }
      
      .badge {
        font-size: 11px;
      }
    }

    @media (max-width: 375px) {
      .profile-header {
        padding: 0.8rem;
      }
      
      .profile-avatar {
        width: 70px;
        height: 70px;
        font-size: 1.8rem;
      }
      
      h4 {
        font-size: 1.1rem;
      }
      
      /* Very small screen forms */
      .form-floating > .form-control {
        padding: 10px 8px;
        font-size: 13px;
      }
      
      .form-floating > label {
        font-size: 13px;
      }
      
      .btn {
        padding: 6px 12px;
        font-size: 12px;
      }
      
      .profile-section h5 {
        font-size: 0.9rem;
      }
      
      .card-body {
        padding: 12px;
      }
      
      .form-text {
        font-size: 11px;
      }
      
      .profile-preview {
        width: 70px;
        height: 70px;
      }
      
      .mb-3 strong {
        font-size: 12px;
      }
      
      .mb-3 span {
        font-size: 12px;
      }
      
      .badge {
        font-size: 10px;
      }
      
      /* Mobile file input */
      .form-control[type="file"] {
        font-size: 12px;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/user_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/user_navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header text-center text-white" style="background: linear-gradient(135deg, var(--primary) 0%, #001d3d 100%); padding: 30px; border-radius: 15px 15px 0 0;">
      <div class="profile-avatar mx-auto mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--accent);">
        <i class="fas fa-user"></i>
      </div>
      <h3 class="mb-2"><?= htmlspecialchars($user['full_name']) ?></h3>
      <span class="badge" style="background: linear-gradient(135deg, var(--accent) 0%, var(--light-blue) 100%); padding: 8px 20px; border-radius: 20px; font-size: 0.9rem;">
        <?= $isReservationUser ? 'Requester' : 'User' ?>
      </span>
    </div>

    <!-- Profile Body -->
    <div class="profile-body" style="padding: 30px;">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Account Information</h4>
      </div>

    <?php if (isset($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($photo_success)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $photo_success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($photo_error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $photo_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row">
      <!-- Profile Information -->
      <div class="col-md-8">
        <div class="card profile-card">
          <div class="profile-header">
            <div class="profile-avatar">
              <?php 
              $photo_path = '../uploads/profile_photos/' . ($user['profile_image'] ?? 'default.png');
              if (isset($user['profile_image']) && $user['profile_image'] !== 'default.png' && file_exists($photo_path)): 
              ?>
                <img src="<?php echo $photo_path; ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
              <?php else: ?>
                <i class="fas fa-user"></i>
              <?php endif; ?>
            </div>
            <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <?php if (!$isReservationUser): ?>
            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($user['role']); ?></p>
            <?php endif; ?>
          </div>
          
          <div class="card-body">
            <!-- Basic Information -->
            <div class="profile-section">
              <h5 class="text-primary mb-3">
                <i class="fas fa-info-circle me-2"></i>Basic Information
              </h5>
              
              <form method="POST">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="full_name" name="full_name" 
                             value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                      <label for="full_name">Full Name</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="email" class="form-control" id="email" name="email" 
                             value="<?php echo htmlspecialchars($user['email']); ?>" required>
                      <label for="email">Email Address</label>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="tel" class="form-control" id="contact" name="contact" 
                             value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                      <label for="contact">Contact Number</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <!-- Empty column for spacing -->
                  </div>
                </div>
                
                <div class="text-end">
                  <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update Profile
                  </button>
                </div>
              </form>
            </div>

            <!-- Password Change -->
            <div class="profile-section">
              <h5 class="text-primary mb-3">
                <i class="fas fa-lock me-2"></i>Change Password
              </h5>
              
              <?php if (isset($password_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <i class="fas fa-check-circle me-2"></i>
                  <?php echo $password_success; ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>

              <?php if (isset($password_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="fas fa-exclamation-circle me-2"></i>
                  <?php echo $password_error; ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>
              
              <form method="POST">
                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="current_password" name="current_password" required>
                  <label for="current_password">Current Password</label>
                </div>
                
                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="new_password" name="new_password" required>
                  <label for="new_password">New Password</label>
                </div>
                
                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                  <label for="confirm_password">Confirm New Password</label>
                </div>
                
                <div class="text-end">
                  <button type="submit" name="change_password" class="btn btn-outline-primary">
                    <i class="fas fa-key me-2"></i>Change Password
                  </button>
                </div>
              </form>
            </div>

            <!-- Profile Photo Upload -->
            <div class="profile-section">
              <h5 class="text-primary mb-3">
                <i class="fas fa-camera me-2"></i>Profile Photo
              </h5>
              
              <form method="POST" enctype="multipart/form-data">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="profile_photo" class="form-label">Upload New Photo</label>
                      <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
                      <div class="form-text">Supported formats: JPG, PNG, GIF (Max 5MB)</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-center">
                      <div class="mb-2">
                        <strong>Current Photo:</strong>
                      </div>
                      <div class="profile-preview" style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 3px solid #dee2e6;">
                        <?php 
                        $photo_path = '../uploads/profile_photos/' . ($user['profile_image'] ?? 'default.png');
                        if (isset($user['profile_image']) && $user['profile_image'] !== 'default.png' && file_exists($photo_path)): 
                        ?>
                          <img src="<?php echo $photo_path; ?>" alt="Current Photo" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                          <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user fa-2x text-muted"></i>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="text-end">
                  <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-upload me-2"></i>Upload Photo
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Account Information -->
      <div class="col-md-4">
        <div class="card profile-card">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
              <i class="fas fa-user-cog me-2"></i>Account Information
            </h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong>Username:</strong><br>
              <span class="text-muted"><?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            
            <div class="mb-3">
              <strong>User ID:</strong><br>
              <span class="text-muted">#<?php echo $isReservationUser ? $user['id'] : $user['user_id']; ?></span>
            </div>
            
            <?php if (!$isReservationUser): ?>
            <div class="mb-3">
              <strong>Role:</strong><br>
              <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
              <strong>Member Since:</strong><br>
              <span class="text-muted"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
            </div>
            
            <div class="mb-3">
              <strong>Last Login:</strong><br>
              <span class="text-muted"><?php echo isset($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card profile-card mt-3">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">
              <i class="fas fa-bolt me-2"></i>Quick Actions
            </h5>
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="user_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
              </a>
              <a href="vehicle_reservation.php" class="btn btn-outline-success">
                <i class="fas fa-plus-circle me-2"></i>New Reservation
              </a>
              <a href="my_reservations.php" class="btn btn-outline-info">
                <i class="fas fa-calendar-check me-2"></i>My Reservations
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div> <!-- Close profile-body -->
  </div> <!-- Close profile-container -->
</div> <!-- Close main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
    // Check if we're on mobile
    const isMobile = window.innerWidth <= 991.98;
    
    if (isMobile) {
      // Mobile behavior - toggle sidebar visibility
      sidebar.classList.toggle('show');
    } else {
      // Desktop behavior - toggle sidebar collapse
      const isCollapsed = sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('collapsed');

      // Toggle text visibility
      linkTexts.forEach(text => {
        text.style.display = isCollapsed ? 'none' : 'inline';
      });

      // Manage dropdown chevron interactivity
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

      // Collapse all sidebar dropdowns when sidebar is collapsed
      if (isCollapsed) {
        const openMenus = sidebar.querySelectorAll('.collapse.show');
        openMenus.forEach(menu => {
          const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
          collapseInstance.hide();
        });
      }
    }
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    const isMobile = window.innerWidth <= 991.98;
    if (isMobile && sidebar.classList.contains('show')) {
      if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    }
  });

  // Handle window resize
  window.addEventListener('resize', () => {
    const isMobile = window.innerWidth <= 991.98;
    if (!isMobile) {
      // Reset mobile sidebar state when switching to desktop
      sidebar.classList.remove('show');
    }
  });
</script>

</body>
</html>
