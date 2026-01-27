<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user's phone number and department from database
$user_phone = '';
$user_department = '';
if ($_SESSION['user_type'] === 'reservation') {
    $user_query = "SELECT phone, department FROM reservation_users WHERE id = ?";
} else {
    $user_query = "SELECT phone FROM user_table WHERE user_id = ?";
}
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_phone = $user_data['phone'] ?? '';
    if ($_SESSION['user_type'] === 'reservation') {
        $user_department = $user_data['department'] ?? '';
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $requester_name = $_POST['requester_name'];
    $department = $_POST['department'];
    $contact = $_POST['contact'];
    $purpose = $_POST['purpose'];
    $origin = $_POST['origin'];
    $destination = $_POST['destination'];
    
    // Combine date and time fields
    $start_date = $_POST['start_date'];
    $start_hour = $_POST['start_hour'];
    $start_minute = $_POST['start_minute'];
    $start_ampm = $_POST['start_ampm'];
    
    // Convert to 24-hour format
    $start_hour_24 = $start_hour;
    if ($start_ampm == 'PM' && $start_hour != '12') {
        $start_hour_24 = (int)$start_hour + 12;
    } elseif ($start_ampm == 'AM' && $start_hour == '12') {
        $start_hour_24 = '00';
    }
    $start_datetime = $start_date . ' ' . $start_hour_24 . ':' . $start_minute . ':00';
    
    $end_date = $_POST['end_date'];
    $end_hour = $_POST['end_hour'];
    $end_minute = $_POST['end_minute'];
    $end_ampm = $_POST['end_ampm'];
    
    $end_datetime = null;
    if ($end_date && $end_hour && $end_minute && $end_ampm) {
        $end_hour_24 = $end_hour;
        if ($end_ampm == 'PM' && $end_hour != '12') {
            $end_hour_24 = (int)$end_hour + 12;
        } elseif ($end_ampm == 'AM' && $end_hour == '12') {
            $end_hour_24 = '00';
        }
        $end_datetime = $end_date . ' ' . $end_hour_24 . ':' . $end_minute . ':00';
    }
    
    $passengers = $_POST['passengers'];
    $notes = $_POST['notes'];
    
    // Handle file upload
    $attachment_path = null;
    $attachment_original_name = null;
    $attachment_size = null;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/reservations/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $file_name = 'reservation_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
                $attachment_path = 'uploads/reservations/' . $file_name;
                $attachment_original_name = $_FILES['attachment']['name'];
                $attachment_size = $_FILES['attachment']['size'];
            } else {
                $error = "Failed to upload attachment. Please try again.";
            }
        } else {
            $error = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
        }
    }
    
    try {
        // Get the single dispatcher (excluding super_admin) for auto-assignment
        $dispatcher_id = null;
        $status = 'pending'; // Default status
        
        // Use prepared statement for consistency (static query but best practice)
        $dispatcher_stmt = $conn->prepare("SELECT user_id FROM user_table WHERE role = 'dispatcher' AND role != 'super admin' LIMIT 1");
        $dispatcher_stmt->execute();
        $dispatcher_result = $dispatcher_stmt->get_result();
        
        if ($dispatcher_result && $dispatcher_result->num_rows > 0) {
            $dispatcher = $dispatcher_result->fetch_assoc();
            $dispatcher_id = $dispatcher['user_id'];
            $status = 'assigned'; // Auto-assign immediately
        }
        $dispatcher_stmt->close();
        
        $sql = "INSERT INTO vehicle_reservations (requester_name, department, contact, purpose, origin, destination, start_datetime, end_datetime, passengers, status, notes, attachment_path, attachment_original_name, attachment_size, created_by, assigned_dispatcher_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssissssiii", 
            $requester_name, $department, $contact, $purpose, $origin, $destination, 
            $start_datetime, $end_datetime, $passengers, $status, $notes, 
            $attachment_path, $attachment_original_name, $attachment_size, 
            $_SESSION['user_id'], $dispatcher_id);
        
        if ($stmt->execute()) {
            if ($dispatcher_id) {
                $message = "Vehicle reservation submitted and automatically assigned to dispatcher!";
            } else {
                $message = "Vehicle reservation submitted successfully!";
            }
        } else {
            $error = "Error submitting reservation. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>New Reservation | Smart Track</title>
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
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    .navbar {
      position: fixed;      
      top: 0;                
      left: 0;       
      width: 100%;
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

    .card-icon {
      font-size: 2rem;
      color: var(--accent);
    }

    .card h6 {
      font-weight: 500;
    }

    .card h4 {
      font-weight: bold;
    }

    /* Modern form styling */
    .form-card {
      border: 0;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      overflow: hidden;
    }

    .form-header {
      background: linear-gradient(135deg, var(--primary), #001d3d);
      color: white;
      padding: 24px;
      border-radius: 16px 16px 0 0;
    }

    .form-section {
      background: white;
      padding: 32px;
      border-radius: 0 0 16px 16px;
    }

    .form-floating {
      margin-bottom: 20px;
    }

    .form-floating > .form-control {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 16px 12px;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    .form-floating > .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
    }

    .form-floating > label {
      color: #6c757d;
      font-weight: 500;
    }

    .btn-submit {
      background: linear-gradient(135deg, var(--accent), #0096c7);
      border: 0;
      border-radius: 12px;
      padding: 14px 32px;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 180, 216, 0.4);
    }

    .form-icon {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--accent);
      z-index: 5;
    }

    .input-group {
      position: relative;
    }
    
    /* Readonly field styling */
    .form-control[readonly] {
      background-color: #f8f9fa;
      border-color: #e9ecef;
      color: #6c757d;
      cursor: not-allowed;
    }
    
    .form-control[readonly]:focus {
      background-color: #f8f9fa;
      border-color: #e9ecef;
      box-shadow: none;
    }
    
    /* DateTime input styling */
    input[type="datetime-local"] {
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
    }
    
    input[type="datetime-local"]::-webkit-calendar-picker-indicator {
      background: transparent;
      bottom: 0;
      color: transparent;
      cursor: pointer;
      height: auto;
      left: 0;
      position: absolute;
      right: 0;
      top: 0;
      width: auto;
    }
    
    /* Ensure datetime inputs show time with AM/PM */
    input[type="datetime-local"] {
      color-scheme: light;
      -webkit-appearance: textfield;
    }
    
    /* Force 12-hour format with AM/PM */
    input[type="datetime-local"]::-webkit-datetime-edit-text {
      color: var(--primary);
    }
    
    input[type="datetime-local"]::-webkit-datetime-edit-month-field,
    input[type="datetime-local"]::-webkit-datetime-edit-day-field,
    input[type="datetime-local"]::-webkit-datetime-edit-year-field {
      color: var(--primary);
    }
    
    input[type="datetime-local"]::-webkit-datetime-edit-hour-field,
    input[type="datetime-local"]::-webkit-datetime-edit-minute-field {
      color: var(--primary);
    }
    
    /* Toast container styling */
    .toast-container {
      position: fixed;
      top: 70px; /* Below navbar */
      right: 20px;
      z-index: 1300;
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
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
      
      .navbar {
        z-index: 2000;
      }
      
      .burger-btn {
        display: block;
      }
      
      /* Mobile form adjustments */
      .col-lg-10 {
        padding: 0;
      }
      
      .form-card {
        border-radius: 12px;
      }
      
      .form-header {
        padding: 20px;
        border-radius: 12px 12px 0 0;
      }
      
      .form-section {
        padding: 24px;
        border-radius: 0 0 12px 12px;
      }
      
      /* Mobile toast positioning */
      .toast-container {
        right: 10px;
        left: 10px;
        top: 80px;
      }
    }

    @media (max-width: 575.98px) {
      .main-content {
        padding: 10px;
      }
      
      .form-header {
        padding: 16px;
      }
      
      .form-section {
        padding: 20px;
      }
      
      h3 {
        font-size: 1.25rem;
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
      
      /* Mobile time selectors */
      .row.g-2 .col-4 {
        margin-bottom: 8px;
      }
      
      .form-select {
        padding: 8px;
        font-size: 13px;
      }
      
      .form-label {
        font-size: 14px;
        margin-bottom: 8px;
      }
      
      /* Mobile buttons */
      .d-flex.justify-content-end {
        flex-direction: column;
        gap: 10px;
      }
      
      .btn-lg {
        padding: 12px 20px;
        font-size: 14px;
        width: 100%;
      }
      
      /* Mobile textarea */
      textarea.form-control {
        min-height: 80px;
        font-size: 14px;
      }
    }

    @media (max-width: 375px) {
      .form-header {
        padding: 12px;
      }
      
      .form-section {
        padding: 16px;
      }
      
      h3 {
        font-size: 1.1rem;
      }
      
      .form-floating > .form-control {
        padding: 10px 8px;
        font-size: 13px;
      }
      
      .form-select {
        padding: 6px;
        font-size: 12px;
      }
      
      .btn-lg {
        padding: 10px 16px;
        font-size: 13px;
      }
      
      textarea.form-control {
        min-height: 70px;
        font-size: 13px;
      }
      
      /* Toast adjustments for very small screens */
      .toast-container {
        top: 70px;
        left: 5px;
        right: 5px;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/user_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/user_navbar.php'; ?>

 <!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <!-- Toast Container for messages -->
    <div class="toast-container">
      <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="toastBody">
            <!-- Message will be inserted here -->
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
    
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="form-card">
          <div class="form-header">
            <h3 class="mb-0"><i class="fas fa-car me-2"></i>Vehicle Reservation Request</h3>
            <p class="mb-0 mt-2 opacity-75">Fill out the form below to request a vehicle</p>
          </div>
          
          <div class="form-section">
            
            <form method="POST" action="" enctype="multipart/form-data">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="requester_name" name="requester_name" placeholder="Full Name" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>" readonly required>
                    <label for="requester_name"><i class="fas fa-user me-2"></i>Full Name *</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="department" name="department" placeholder="Department" value="<?= htmlspecialchars($user_department) ?>" readonly>
                    <label for="department"><i class="fas fa-building me-2"></i>Department</label>
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="tel" class="form-control" id="contact" name="contact" placeholder="Contact Number" value="<?= htmlspecialchars($user_phone) ?>" readonly required>
                    <label for="contact"><i class="fas fa-phone me-2"></i>Contact Number *</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="number" class="form-control" id="passengers" name="passengers" placeholder="Number of Passengers" min="1" max="20">
                    <label for="passengers"><i class="fas fa-users me-2"></i>Number of Passengers</label>
                  </div>
                </div>
              </div>
              
              <div class="form-floating">
                <textarea class="form-control" id="purpose" name="purpose" placeholder="Purpose of Trip" style="height: 100px" required></textarea>
                <label for="purpose"><i class="fas fa-clipboard-list me-2"></i>Purpose of Trip *</label>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="origin" name="origin" placeholder="Origin" required>
                    <label for="origin"><i class="fas fa-map-marker-alt me-2"></i>Origin *</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="destination" name="destination" placeholder="Destination" required>
                    <label for="destination"><i class="fas fa-flag me-2"></i>Destination *</label>
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="date" class="form-control" id="start_date" name="start_date" min="<?= date('Y-m-d') ?>" required>
                    <label for="start_date"><i class="fas fa-calendar me-2"></i>Start Date *</label>
                  </div>
                  <div class="mt-2">
                    <label class="form-label"><i class="fas fa-clock me-2"></i>Start Time *</label>
                    <div class="row g-2">
                      <div class="col-4">
                        <select class="form-select" id="start_hour" name="start_hour" required>
                          <option value="">Hour</option>
                          <option value="01">1</option><option value="02">2</option><option value="03">3</option><option value="04">4</option>
                          <option value="05">5</option><option value="06">6</option><option value="07">7</option><option value="08">8</option>
                          <option value="09">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                        </select>
                      </div>
                      <div class="col-4">
                        <select class="form-select" id="start_minute" name="start_minute" required>
                          <option value="">Min</option>
                          <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                        </select>
                      </div>
                      <div class="col-4">
                        <select class="form-select" id="start_ampm" name="start_ampm" required>
                          <option value="">AM/PM</option>
                          <option value="AM">AM</option>
                          <option value="PM">PM</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="date" class="form-control" id="end_date" name="end_date" min="<?= date('Y-m-d') ?>">
                    <label for="end_date"><i class="fas fa-calendar me-2"></i>End Date</label>
                  </div>
                  <div class="mt-2">
                    <label class="form-label"><i class="fas fa-clock me-2"></i>End Time</label>
                    <div class="row g-2">
                      <div class="col-4">
                        <select class="form-select" id="end_hour" name="end_hour">
                          <option value="">Hour</option>
                          <option value="01">1</option><option value="02">2</option><option value="03">3</option><option value="04">4</option>
                          <option value="05">5</option><option value="06">6</option><option value="07">7</option><option value="08">8</option>
                          <option value="09">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                        </select>
                      </div>
                      <div class="col-4">
                        <select class="form-select" id="end_minute" name="end_minute">
                          <option value="">Min</option>
                          <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                        </select>
                      </div>
                      <div class="col-4">
                        <select class="form-select" id="end_ampm" name="end_ampm">
                          <option value="">AM/PM</option>
                          <option value="AM">AM</option>
                          <option value="PM">PM</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="form-floating">
                <textarea class="form-control" id="notes" name="notes" placeholder="Additional Notes" style="height: 80px"></textarea>
                <label for="notes"><i class="fas fa-sticky-note me-2"></i>Additional Notes</label>
              </div>
              
              <div class="mb-4">
                <label for="attachment" class="form-label">
                  <i class="fas fa-paperclip me-2"></i>Attachment (Optional)
                </label>
                <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                <div class="form-text">
                  <i class="fas fa-info-circle me-1"></i>
                  Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX, TXT (Max 10MB)
                </div>
              </div>
              
              <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="user_dashboard.php" class="btn btn-outline-secondary btn-lg">
                  <i class="fas fa-arrow-left me-2"></i>Cancel
                </a>
                <button type="submit" class="btn btn-submit btn-lg">
                  <i class="fas fa-paper-plane me-2"></i>Submit Reservation
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- JS -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();

        Swal.fire({
          title: 'Log out?',
          text: "Are you sure you want to log out?",
          icon: 'question',
          iconColor: '#00b4d8',
          showCancelButton: true,
          confirmButtonColor: '#003566',
          cancelButtonColor: '#6c757d',
          confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Yes',
          cancelButtonText: '<i class="fas fa-times-circle me-1"></i> Cancel',
          reverseButtons: true,
          background: '#f8f9fa',
          color: '#212529',
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn',
            cancelButton: 'swal-btn'
          },
          didRender: () => {
            const buttons = document.querySelectorAll('.swal-btn');
            buttons.forEach(btn => {
              btn.style.minWidth = '120px';
              btn.style.padding = '10px 16px';
              btn.style.fontSize = '15px';
            });
          }
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire({
              title: 'Logging out...',
              icon: 'info',
              showConfirmButton: false,
              timer: 1000,
              willClose: () => {
                window.location.href = '../logout.php';
              }
            });
          }
        });
      });
    }

    // Handle separate date and time fields
    const startDate = document.getElementById('start_date');
    const startHour = document.getElementById('start_hour');
    const startMinute = document.getElementById('start_minute');
    const startAmPm = document.getElementById('start_ampm');
    
    const endDate = document.getElementById('end_date');
    const endHour = document.getElementById('end_hour');
    const endMinute = document.getElementById('end_minute');
    const endAmPm = document.getElementById('end_ampm');

    // Show time preview when time fields change
    function showTimePreview(dateField, hourField, minuteField, ampmField, container) {
      function updatePreview() {
        if (dateField.value && hourField.value && minuteField.value && ampmField.value) {
          const timePreview = `${hourField.value}:${minuteField.value} ${ampmField.value}`;
          const preview = document.createElement('small');
          preview.className = 'text-success mt-1 d-block';
          preview.innerHTML = `<i class="fas fa-clock me-1"></i>Selected: ${timePreview}`;
          
          // Remove existing preview
          const existingPreview = container.querySelector('.text-success');
          if (existingPreview) {
            existingPreview.remove();
          }
          
          container.appendChild(preview);
        }
      }
      
      [dateField, hourField, minuteField, ampmField].forEach(field => {
        field.addEventListener('change', updatePreview);
      });
    }

    // Apply preview to start time
    showTimePreview(startDate, startHour, startMinute, startAmPm, startDate.parentNode);
    
    // Apply preview to end time
    showTimePreview(endDate, endHour, endMinute, endAmPm, endDate.parentNode);

    // Date validation
    startDate.addEventListener('change', function() {
      if (this.value) {
        // Set minimum end date to start date
        endDate.min = this.value;
        
        // If end date is already set and is before start date, clear it
        if (endDate.value && endDate.value < this.value) {
          endDate.value = '';
          endHour.value = '';
          endMinute.value = '';
          endAmPm.value = '';
        }
      }
    });

    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const startDateValue = startDate.value;
      const endDateValue = endDate.value;
      const now = new Date();
      const today = now.toISOString().split('T')[0];
      
      // Check if start date is in the past
      if (startDateValue < today) {
        e.preventDefault();
        alert('Start date cannot be in the past!');
        return false;
      }
      
      // Check if end date is after start date
      if (endDateValue && endDateValue < startDateValue) {
        e.preventDefault();
        alert('End date must be after start date!');
        return false;
      }
      
      // Check if end time is after start time (if same date)
      if (endDateValue && endDateValue === startDateValue && 
          endHour.value && endMinute.value && endAmPm.value &&
          startHour.value && startMinute.value && startAmPm.value) {
        
        // Convert to 24-hour format for comparison
        let startHour24 = parseInt(startHour.value);
        if (startAmPm.value === 'PM' && startHour24 !== 12) startHour24 += 12;
        if (startAmPm.value === 'AM' && startHour24 === 12) startHour24 = 0;
        
        let endHour24 = parseInt(endHour.value);
        if (endAmPm.value === 'PM' && endHour24 !== 12) endHour24 += 12;
        if (endAmPm.value === 'AM' && endHour24 === 12) endHour24 = 0;
        
        const startTime = startHour24 * 60 + parseInt(startMinute.value);
        const endTime = endHour24 * 60 + parseInt(endMinute.value);
        
        if (endTime <= startTime) {
          e.preventDefault();
          alert('End time must be after start time!');
          return false;
        }
      }
    });

    // Show toast message if available
    <?php if ($message): ?>
      const toastLiveExample = document.getElementById('liveToast');
      const toastBody = document.getElementById('toastBody');
      toastBody.innerHTML = '<i class="fas fa-check-circle me-2"></i><?= $message ?>';
      const toast = new bootstrap.Toast(toastLiveExample);
      toast.show();
    <?php endif; ?>
    <?php if ($error): ?>
      const toastLiveExample = document.getElementById('liveToast');
      const toastBody = document.getElementById('toastBody');
      toastLiveExample.classList.remove('bg-success');
      toastLiveExample.classList.add('bg-danger');
      toastBody.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i><?= $error ?>';
      const toast = new bootstrap.Toast(toastLiveExample);
      toast.show();
    <?php endif; ?>
  });
</script>
</body>
</html>