<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Only mechanics can access
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit();
}

$mechanicId = $_SESSION['user_id'];

// Handle form submission for scheduling / accepting a work order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work_id = $_POST['work_id'];
    $start_date_str = $_POST['start_date'];

    // Server-side validation for future dates only
    $start_date = new DateTime($start_date_str);
    $today = new DateTime();
    
    // Set reasonable scheduling window (3 months from now)
    $max_scheduling_date = clone $today;
    $max_scheduling_date->modify('+3 months');
    
    // Check if start date is too far in the future (beyond 3 months)
    if ($start_date > $max_scheduling_date) {
        $_SESSION['error'] = "Start date cannot be more than 3 months in the future";
        header("Location: new-work-orders.php");
        exit();
    }
    
    // Check if start date is in the past
    if ($start_date < $today) {
        $_SESSION['error'] = "Start date cannot be in the past. Please select a future date";
        header("Location: new-work-orders.php");
        exit();
    }

    // First, get the maintenance request details
    $getRequest = $conn->prepare("SELECT vehicle_id, maintenance_type, notes, request_date FROM maintenance_requests WHERE id = ?");
    $getRequest->bind_param("i", $work_id);
    $getRequest->execute();
    $requestResult = $getRequest->get_result();
    $requestData = $requestResult->fetch_assoc();
    $getRequest->close();

    if ($requestData) {
        // Insert into maintenance_schedules table with the mechanic assigned
        $stmt = $conn->prepare("
            INSERT INTO maintenance_schedules 
            (vehicle_id, maintenance_type, notes, scheduled_date, start_time, status, assigned_mechanic)
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        $scheduled_date = date('Y-m-d', strtotime($start_date_str));
        $stmt->bind_param(
            "issssi", 
            $requestData['vehicle_id'], 
            $requestData['maintenance_type'], 
            $requestData['notes'],
            $scheduled_date,
            $start_date_str, 
            $mechanicId
        );

        if ($stmt->execute()) {
            // Update the original request to 'approved' status
            $updateRequest = $conn->prepare("UPDATE maintenance_requests SET status = 'approved' WHERE id = ?");
            $updateRequest->bind_param("i", $work_id);
            $updateRequest->execute();
            $updateRequest->close();

            $_SESSION['success'] = "Work order scheduled successfully and assigned to you!";
        } else {
            $_SESSION['error'] = "Failed to schedule the work order: " . $conn->error;
        }

        $stmt->close();
    } else {
        $_SESSION['error'] = "Maintenance request not found.";
    }

    header("Location: new-work-orders.php");
    exit();
}

// Fetch pending maintenance requests - use prepared statement for consistency
$requests_stmt = $conn->prepare("
    SELECT mr.id, fv.article, fv.plate_number, mr.maintenance_type, mr.notes, mr.request_date
    FROM maintenance_requests mr
    JOIN fleet_vehicles fv ON mr.vehicle_id = fv.id
    WHERE mr.status = 'pending'
    ORDER BY mr.request_date ASC
");
$requests_stmt->execute();
$requests = $requests_stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>New Work Orders</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

    /* Fix dropdown toggle layout - position chevron at far right edge */
    .sidebar .dropdown-toggle {
      justify-content: space-between !important;
      align-items: center !important;
      cursor: pointer !important;
      pointer-events: auto !important;
    }

    .sidebar .dropdown-toggle > div {
      display: flex;
      align-items: center;
      flex: 1;
    }

    .sidebar .dropdown-toggle .link-text {
      margin-left: 12px;
    }

    .sidebar .dropdown-chevron {
      margin-left: auto;
      flex-shrink: 0;
      position: absolute;
      right: 15px;
    }

    /* Smooth collapse animation */
    .sidebar .collapse {
      transition: height 0.3s ease, opacity 0.3s ease;
      overflow: hidden;
    }

    .sidebar .collapse:not(.show) {
      height: 0 !important;
      opacity: 0;
    }

    .sidebar .collapse.show {
      height: auto !important;
      opacity: 1;
    }

    .sidebar .dropdown-toggle:hover {
      background-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Calendar validation styles */
    .form-control:invalid {
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .form-control:valid {
      border-color: #28a745 !important;
      box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    }

    .calendar-validation-message {
      font-size: 0.75rem;
      color: #dc3545;
      margin-top: 2px;
    }

    .calendar-validation-success {
      color: #28a745;
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
  </style>
</head>
   <?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>
<body>
  
 

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
                        <h5 class="card-title text-primary fw-bold">Pending Work Orders</h5>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Plate Number</th>
                                    <th>Maintenance Type</th>
                                    <th>Notes</th>
                                    <th>Request Date</th>
                                    <th>Schedule</th>
                                </tr>
                            </thead>
                            <tbody>
                              <?php 
                              $grouped_requests = [];
                              while ($r = $requests->fetch_assoc()) {
                                  // Extract request ID from notes if it exists
                                  $request_id = null;
                                  $driver_notes = $r['notes'];
                                  $maintenance_types = [$r['maintenance_type']];
                                  
                                  if (preg_match('/\[Request ID: ([^\]]+)\]/', $r['notes'], $matches)) {
                                      $request_id = $matches[1];
                                      // Extract driver notes (remove request ID and grouping info)
                                      $driver_notes = preg_replace('/\[Request ID: [^\]]+\]\s*/', '', $r['notes']);
                                      $driver_notes = preg_replace('/\s*\(Part of multi-service request: [^)]+\)/', '', $driver_notes);
                                      
                                      // Extract all maintenance types from grouping info
                                      if (preg_match('/\(Part of multi-service request: ([^)]+)\)/', $r['notes'], $type_matches)) {
                                          $maintenance_types = array_map('trim', explode(',', $type_matches[1]));
                                      }
                                  }
                                  
                                  if ($request_id) {
                                      if (!isset($grouped_requests[$request_id])) {
                                          $grouped_requests[$request_id] = [
                                              'id' => $r['id'],
                                              'article' => $r['article'],
                                              'plate_number' => $r['plate_number'],
                                              'maintenance_types' => $maintenance_types,
                                              'driver_notes' => $driver_notes,
                                              'request_date' => $r['request_date']
                                          ];
                                      }
                                  } else {
                                      // Single maintenance request
                                      $grouped_requests['single_' . $r['id']] = [
                                          'id' => $r['id'],
                                          'article' => $r['article'],
                                          'plate_number' => $r['plate_number'],
                                          'maintenance_types' => $maintenance_types,
                                          'driver_notes' => $driver_notes,
                                          'request_date' => $r['request_date']
                                      ];
                                  }
                              }
                              
                              foreach ($grouped_requests as $request): ?>
                                  <tr>
                                      <td><?= htmlspecialchars($request['article']) ?></td>
                                      <td><?= htmlspecialchars($request['plate_number']) ?></td>
                                      <td>
                                          <?php if (count($request['maintenance_types']) > 1): ?>
                                              <span class="badge bg-primary me-1"><?= implode('</span> <span class="badge bg-primary me-1">', $request['maintenance_types']) ?></span>
                                          <?php else: ?>
                                              <span class="badge bg-primary"><?= htmlspecialchars($request['maintenance_types'][0]) ?></span>
                                          <?php endif; ?>
                                      </td>
                                      <td><?= htmlspecialchars($request['driver_notes']) ?></td>
                                      <td><?= htmlspecialchars($request['request_date']) ?></td>
                                      <td>
                                          <form method="POST" class="d-flex gap-2">
                                              <input type="hidden" name="work_id" value="<?= $request['id'] ?>">
                                              <input type="date" name="start_date" class="form-control form-control-sm" required>
                                              <button type="submit" class="btn btn-success btn-sm">Schedule</button>
                                          </form>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_SESSION['login_success'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Login Successful',
        text: '<?= $_SESSION['login_success'] ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const linkTexts = document.querySelectorAll('.link-text');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    // Function to check if device is mobile
    function isMobile() {
      return window.innerWidth < 992;
    }

    // Function to handle desktop sidebar behavior
    function handleDesktopSidebar() {
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
    }

    // Add event listener to burger button (only for desktop - navbar handles mobile)
    if (burgerBtn && sidebar && mainContent && !isMobile()) {
      burgerBtn.addEventListener('click', () => {
        if (!isMobile()) {
          // Desktop behavior - toggle collapsed state
          handleDesktopSidebar();
        }
      });
    }

    // Handle window resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 991.98) {
        sidebar.classList.remove('show', 'open');
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        linkTexts.forEach(text => {
          text.style.display = 'inline';
        });
      } else {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        linkTexts.forEach(text => {
          text.style.display = 'inline';
        });
      }
    });

    // Initialize chevron rotation based on current state
    dropdownToggles.forEach(toggle => {
      const targetId = toggle.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
      const chevron = toggle.querySelector('.dropdown-chevron');
      
      if (targetElement && chevron) {
        const isOpen = targetElement.classList.contains('show');
        chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
      }
    });

    // Global event listener for all dropdown state changes
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          const target = mutation.target;
          if (target.classList.contains('collapse')) {
            const toggle = document.querySelector(`[data-bs-target="#${target.id}"]`);
            if (toggle) {
              const chevron = toggle.querySelector('.dropdown-chevron');
              if (chevron) {
                const isOpen = target.classList.contains('show');
                chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
              }
            }
          }
        }
      });
    });

    // Observe all collapse elements
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(element => {
      observer.observe(element, { attributes: true, attributeFilter: ['class'] });
    });

    // Handle mobile dropdown behavior
    dropdownToggles.forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        if (isMobile()) {
          // On mobile, prevent default Bootstrap behavior and handle manually
          e.preventDefault();
          e.stopPropagation();
          const targetId = toggle.getAttribute('data-bs-target');
          const targetElement = document.querySelector(targetId);
          
          if (targetElement) {
            const isCollapsed = targetElement.classList.contains('show');
            
            // Close all other dropdowns first
            const allDropdowns = document.querySelectorAll('.collapse');
            allDropdowns.forEach(dropdown => {
              if (dropdown !== targetElement) {
                dropdown.classList.remove('show');
              }
            });
            
            // Toggle current dropdown
            if (isCollapsed) {
              targetElement.classList.remove('show');
            } else {
              targetElement.classList.add('show');
            }
            
            // Update aria-expanded
            toggle.setAttribute('aria-expanded', !isCollapsed);
            
            // Rotate chevron
            const chevron = toggle.querySelector('.dropdown-chevron');
            if (chevron) {
              chevron.style.transform = !isCollapsed ? 'rotate(90deg)' : 'rotate(0deg)';
            }
          }
        }
      });
    });

    // Prevent sub-menu clicks from interfering with dropdown toggle
    const subMenuLinks = document.querySelectorAll('.collapse a');
    subMenuLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        // Don't prevent navigation, but ensure dropdown state is maintained
        e.stopPropagation();
      });
    });

    // Comprehensive dropdown toggle fix - ensure it works like mechanic_homepage.php
    setTimeout(() => {
      const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
      
      dropdownToggles.forEach(toggle => {
        // Remove any existing event listeners by cloning the element
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
        
        // Add comprehensive click handler
        newToggle.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          
          const targetId = newToggle.getAttribute('data-bs-target');
          const targetElement = document.querySelector(targetId);
          const chevron = newToggle.querySelector('.dropdown-chevron');
          
          if (targetElement && chevron) {
            const isCurrentlyOpen = targetElement.classList.contains('show');
            
            // Close all other dropdowns first
            const allDropdowns = document.querySelectorAll('.collapse');
            allDropdowns.forEach(dropdown => {
              if (dropdown !== targetElement && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                // Update other chevrons
                const otherToggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
                if (otherToggle) {
                  const otherChevron = otherToggle.querySelector('.dropdown-chevron');
                  if (otherChevron) {
                    otherChevron.style.transform = 'rotate(0deg)';
                    otherToggle.setAttribute('aria-expanded', 'false');
                  }
                }
              }
            });
            
            // Toggle current dropdown
            if (isCurrentlyOpen) {
              targetElement.classList.remove('show');
              chevron.style.transform = 'rotate(0deg)';
              newToggle.setAttribute('aria-expanded', 'false');
            } else {
              targetElement.classList.add('show');
              chevron.style.transform = 'rotate(90deg)';
              newToggle.setAttribute('aria-expanded', 'true');
            }
          }
        });
        
        // Ensure chevron starts in correct position
        const targetId = newToggle.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
        const chevron = newToggle.querySelector('.dropdown-chevron');
        
        if (targetElement && chevron) {
          const isOpen = targetElement.classList.contains('show');
          chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
          newToggle.setAttribute('aria-expanded', isOpen);
        }
      });
    }, 100); // Small delay to ensure DOM is ready and navbar script has loaded

    // Calendar validation for current and future dates only
    function initializeCalendarValidation() {
      // Set minimum date to today (no past dates)
      const today = new Date();
      const minDate = new Date(today);
      minDate.setHours(0, 0, 0, 0);
      
      // Set maximum date to 3 months from now (reasonable scheduling window)
      const maxDate = new Date(today);
      maxDate.setMonth(maxDate.getMonth() + 3);
      maxDate.setHours(23, 59, 59, 999);
      
      // Format dates for date input
      const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      };
      
      const minDateStr = formatDate(minDate);
      const maxDateStr = formatDate(maxDate);
      
      // Add validation to all date inputs
      const dateInputs = document.querySelectorAll('input[type="date"]');
      dateInputs.forEach(input => {
        // Set min and max attributes
        input.setAttribute('min', minDateStr);
        input.setAttribute('max', maxDateStr);
        
        // Add custom validation
        input.addEventListener('input', function() {
          validateDateInput(this);
        });
        
        input.addEventListener('change', function() {
          validateDateInput(this);
        });
      });
    }
    
    function validateDateInput(input) {
      const selectedDate = new Date(input.value);
      const today = new Date();
      
      // Remove any existing validation messages
      const existingMessage = input.parentNode.querySelector('.calendar-validation-message');
      if (existingMessage) {
        existingMessage.remove();
      }
      
      // Check if input has a value
      if (!input.value) {
        input.setCustomValidity('');
        return;
      }
      
      // Check if selected date is in the past
      if (selectedDate < today) {
        input.setCustomValidity('Cannot select past dates. Please select a future date');
        input.classList.add('is-invalid');
        
        // Add validation message
        const message = document.createElement('div');
        message.className = 'calendar-validation-message';
        message.textContent = 'Cannot select past dates';
        input.parentNode.appendChild(message);
        
        return;
      }
      
      // Valid date
      input.setCustomValidity('');
      input.classList.remove('is-invalid');
      input.classList.add('is-valid');
      
      // Add success message
      const message = document.createElement('div');
      message.className = 'calendar-validation-message calendar-validation-success';
      message.textContent = '✓ Valid date';
      input.parentNode.appendChild(message);
    }
    
    // Initialize calendar validation
    initializeCalendarValidation();
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Enhanced logout functionality - works reliably
  function initializeLogout() {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn && !logoutBtn.dataset.initialized) {
      // Mark as initialized to prevent multiple event listeners
      logoutBtn.dataset.initialized = 'true';
      
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

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
  }

  // Initialize logout when DOM is ready
  document.addEventListener("DOMContentLoaded", initializeLogout);

  // Also try to initialize after a short delay to ensure all scripts are loaded
  setTimeout(initializeLogout, 500);
</script>
</body>
</html>
