<?php
session_start();
require_once '../db_connection.php';

// Access control: Super Admin only
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header('Location: ../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Help Guide | Smart Track</title>
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

    .navbar-brand {
      color: #000 !important;
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
    background-color: #001d3d;
    color: var(--accent);
    box-shadow: inset 2px 0 0 var(--accent);
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

    /* Help page specific styles */
    .help-header {
      background: linear-gradient(135deg, var(--primary), #001d3d);
      color: white;
      padding: 2rem;
      border-radius: 16px;
      margin-bottom: 2rem;
    }

    .help-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      padding: 2rem;
      margin-bottom: 1.5rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .help-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }

    .help-card h4 {
      color: var(--primary);
      font-weight: 600;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .help-card .icon-circle {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0096c7);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
    }

    .help-section {
      margin-bottom: 1.5rem;
    }

    .help-section h5 {
      color: var(--primary);
      font-weight: 600;
      margin-top: 1.5rem;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--accent);
    }

    .step-list {
      list-style: none;
      padding-left: 0;
    }

    .step-list li {
      padding: 0.75rem 1rem;
      margin-bottom: 0.5rem;
      background: #f8f9fa;
      border-left: 4px solid var(--accent);
      border-radius: 8px;
      transition: background 0.2s ease;
    }

    .step-list li:hover {
      background: #e9ecef;
    }

    .step-list li strong {
      color: var(--primary);
    }

    .feature-badge {
      display: inline-block;
      padding: 0.35rem 0.75rem;
      background: linear-gradient(135deg, var(--accent), #0096c7);
      color: white;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-right: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .alert-info-custom {
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      border-left: 4px solid #2196f3;
      border-radius: 8px;
      padding: 1rem;
      margin: 1rem 0;
    }

    /* Responsive tweaks */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 260px;
        transform: translateX(-100%);
        position: fixed;
      }
      .sidebar.open { transform: translateX(0); }
      .sidebar.collapsed { width: 260px; }
      .main-content,
      .main-content.collapsed { margin-left: 0; padding: 16px; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    
    <!-- Header -->
    <div class="help-header text-center">
      <i class="fas fa-question-circle fa-3x mb-3"></i>
      <h2 class="mb-2">Smart Track System - Help Guide</h2>
      <p class="mb-0">Comprehensive guide for Super Admin features and functionality</p>
    </div>

    <!-- Dashboard Overview -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-tachometer-alt"></i></div>
        Dashboard Overview
      </h4>
      <p>The dashboard provides a real-time overview of your fleet management system with key metrics and live tracking capabilities.</p>
      
      <h5>Key Features</h5>
      <ul class="step-list">
        <li><strong>Fleet Statistics:</strong> View total vehicles, active vehicles, vehicles in maintenance, and total system users at a glance.</li>
        <li><strong>Live GPS Tracking:</strong> Monitor vehicle locations in real-time on an interactive map powered by OpenStreetMap.</li>
        <li><strong>Driver Status:</strong> See which drivers are currently on active routes and their assigned vehicles.</li>
        <li><strong>Quick Actions:</strong> Access frequently used functions directly from the dashboard.</li>
      </ul>
    </div>

    <!-- User Management -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-users"></i></div>
        User Management
      </h4>
      <p>Manage all system users including admins, dispatchers, drivers, mechanics, and reservation users.</p>
      
      <h5>User Types</h5>
      <div class="mb-3">
        <span class="feature-badge"><i class="fas fa-user-shield"></i> Admin</span>
        <span class="feature-badge"><i class="fas fa-headset"></i> Dispatcher</span>
        <span class="feature-badge"><i class="fas fa-id-badge"></i> Driver</span>
        <span class="feature-badge"><i class="fas fa-tools"></i> Mechanic</span>
        <span class="feature-badge"><i class="fas fa-user-check"></i> Reservation Users</span>
      </div>

      <h5>How to Add a New User</h5>
      <ul class="step-list">
        <li><strong>Step 1:</strong> Navigate to the specific user type page (e.g., Admin, Dispatcher, Driver).</li>
        <li><strong>Step 2:</strong> Click the "Add New" button at the top of the page.</li>
        <li><strong>Step 3:</strong> Fill in all required information (Full Name, Username, Email, Password, etc.).</li>
        <li><strong>Step 4:</strong> For drivers, assign a vehicle from the dropdown menu.</li>
        <li><strong>Step 5:</strong> Click "Create" to save the new user.</li>
      </ul>

      <h5>How to Edit or Delete Users</h5>
      <ul class="step-list">
        <li><strong>Edit:</strong> Click the blue <i class="fas fa-edit text-primary"></i> edit icon next to any user, update their information, and save changes.</li>
        <li><strong>Delete:</strong> Click the red <i class="fas fa-trash text-danger"></i> delete icon. Confirm the action in the modal that appears.</li>
        <li><strong>Activate/Deactivate:</strong> For reservation users, use the activate/deactivate buttons to control account access.</li>
      </ul>

      <div class="alert-info-custom">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Tip:</strong> Use the search and filter functions to quickly find specific users. Filters include status (Active/Inactive) and search by name, username, email, or phone.
      </div>
    </div>

    <!-- Reservation Management -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-calendar-check"></i></div>
        Reservation Management
      </h4>
      <p>Manage all vehicle reservation requests from employees. Reservations are automatically assigned to the dispatcher upon approval.</p>
      
      <h5>How to Approve a Reservation</h5>
      <ul class="step-list">
        <li><strong>Step 1:</strong> Go to the "Reservations" page from the sidebar.</li>
        <li><strong>Step 2:</strong> Review pending reservation details (passenger, vehicle, dates, purpose).</li>
        <li><strong>Step 3:</strong> Click the green <i class="fas fa-check text-success"></i> "Approve" button.</li>
        <li><strong>Step 4:</strong> Confirm approval in the modal. The reservation will automatically be assigned to the dispatcher.</li>
      </ul>

      <h5>How to Reject a Reservation</h5>
      <ul class="step-list">
        <li><strong>Step 1:</strong> Click the red <i class="fas fa-times text-danger"></i> "Reject" button next to the reservation.</li>
        <li><strong>Step 2:</strong> Confirm rejection in the modal. The status will change to "Cancelled".</li>
      </ul>

      <h5>Reservation Status</h5>
      <div class="mb-3">
        <span class="feature-badge" style="background: linear-gradient(135deg, #f6c667, #f39c12);"><i class="fas fa-hourglass-half"></i> Pending</span>
        <span class="feature-badge" style="background: linear-gradient(135deg, #21b2d6, #0aa1c9);"><i class="fas fa-user-tag"></i> Assigned</span>
        <span class="feature-badge" style="background: linear-gradient(135deg, #4a67ff, #2a47e6);"><i class="fas fa-flag-checkered"></i> Completed</span>
        <span class="feature-badge" style="background: linear-gradient(135deg, #e74c3c, #c0392b);"><i class="fas fa-times-circle"></i> Cancelled</span>
      </div>

      <div class="alert-info-custom">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Auto-Assignment:</strong> When you approve a reservation, the system automatically finds and assigns it to the dispatcher, skipping the manual assignment step.
      </div>
    </div>

    <!-- Reports -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-chart-bar"></i></div>
        Reports & Analytics
      </h4>
      <p>Generate comprehensive reports for fleet performance, vehicle usage, maintenance history, and driver activity.</p>
      
      <h5>Available Reports</h5>
      <ul class="step-list">
        <li><strong>Vehicle Usage Reports:</strong> Track mileage, trip frequency, and utilization rates.</li>
        <li><strong>Maintenance Reports:</strong> View scheduled and completed maintenance activities.</li>
        <li><strong>Driver Performance:</strong> Analyze driver routes, completion rates, and adherence to schedules.</li>
        <li><strong>Reservation Analytics:</strong> Monitor reservation trends, approval rates, and popular vehicles.</li>
      </ul>

      <h5>How to Generate a Report</h5>
      <ul class="step-list">
        <li><strong>Step 1:</strong> Navigate to the "Reports" page from the sidebar.</li>
        <li><strong>Step 2:</strong> Select the report type from the available options.</li>
        <li><strong>Step 3:</strong> Set date range filters and any additional parameters.</li>
        <li><strong>Step 4:</strong> Click "Generate Report" to view the data.</li>
        <li><strong>Step 5:</strong> Export reports as PDF or Excel for offline analysis.</li>
      </ul>
    </div>

    <!-- Geofencing -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-draw-polygon"></i></div>
        Geofencing Management
      </h4>
      <p>Create virtual boundaries (geofences) to monitor vehicle entry and exit from designated areas. Receive alerts when vehicles deviate from authorized zones.</p>
      
      <h5>How to Create a Geofence</h5>
      <ul class="step-list">
        <li><strong>Step 1:</strong> Go to "Geofencing" from the sidebar dropdown.</li>
        <li><strong>Step 2:</strong> Click "Create New Geofence".</li>
        <li><strong>Step 3:</strong> Use the map tools to draw a polygon around the desired area.</li>
        <li><strong>Step 4:</strong> Name the geofence (e.g., "Bago City Hall", "Service Area").</li>
        <li><strong>Step 5:</strong> Set alert preferences (SMS, email, in-system notification).</li>
        <li><strong>Step 6:</strong> Save the geofence to activate monitoring.</li>
      </ul>

      <h5>Geofence Analytics</h5>
      <ul class="step-list">
        <li><strong>Entry/Exit Logs:</strong> View timestamp records of all vehicles entering or leaving geofenced areas.</li>
        <li><strong>Violation Reports:</strong> Identify unauthorized entries or extended stays outside permitted zones.</li>
        <li><strong>Heat Maps:</strong> Visualize vehicle activity patterns within and around geofences.</li>
      </ul>
    </div>

    <!-- System Features -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-cog"></i></div>
        System Features
      </h4>
      
      <h5>GPS Tracking</h5>
      <ul class="step-list">
        <li><strong>Real-Time Location:</strong> Track vehicles using ESP32 GPS devices with 5-second update intervals.</li>
        <li><strong>Historical Routes:</strong> View past trips and routes taken by any vehicle.</li>
        <li><strong>Speed Monitoring:</strong> Track vehicle speed and receive alerts for speeding violations.</li>
      </ul>

      <h5>Route Deviation Detection</h5>
      <ul class="step-list">
        <li><strong>Active Monitoring:</strong> System automatically detects when drivers deviate from assigned routes.</li>
        <li><strong>Dispatcher Alerts:</strong> Dispatchers receive SMS and in-system alerts for route deviations.</li>
        <li><strong>Distance Threshold:</strong> Alerts trigger when vehicle moves 100+ meters off route.</li>
      </ul>

      <h5>Post-Trip Monitoring</h5>
      <ul class="step-list">
        <li><strong>Unauthorized Movement:</strong> Tracks if vehicles move after trip completion.</li>
        <li><strong>Automatic Alerts:</strong> Sends alerts to dispatchers if completed trips show movement.</li>
        <li><strong>Security Feature:</strong> Helps prevent unauthorized vehicle use.</li>
      </ul>

      <h5>Intelligent Route Planning</h5>
      <ul class="step-list">
        <li><strong>OSRM Integration:</strong> Uses Open Source Routing Machine for optimal route calculation.</li>
        <li><strong>Alternative Routes:</strong> Provides multiple route options with time and distance comparisons.</li>
        <li><strong>Avoid Parameters:</strong> Routes avoid pedestrian paths, cycleways, and restricted areas.</li>
      </ul>
    </div>

    <!-- Troubleshooting -->
    <div class="help-card">
      <h4>
        <div class="icon-circle"><i class="fas fa-wrench"></i></div>
        Frequently Asked Questions
      </h4>
      
      <h5>Frequently Asked Questions</h5>
      <ul class="step-list">
        <li><strong>Q: Can I edit a reservation after approval?</strong><br>A: Yes, use the edit button to modify reservation details even after approval.</li>
        <li><strong>Q: How do I track multiple vehicles at once?</strong><br>A: The dashboard map shows all active vehicles simultaneously with color-coded markers.</li>
        <li><strong>Q: Can drivers see their route history?</strong><br>A: Yes, drivers can access their calendar to view past and upcoming routes.</li>
        <li><strong>Q: How accurate is the GPS tracking?</strong><br>A: ESP32 devices provide accuracy within 5-10 meters under clear sky conditions.</li>
      </ul>

      <div class="alert-info-custom">
        <i class="fas fa-phone-alt me-2"></i>
        <strong>Need More Help?</strong> Contact your system administrator or technical support team for additional assistance.
      </div>
    </div>

    <!-- Contact Support -->
    <div class="help-card text-center">
      <h4>
        <div class="icon-circle mx-auto mb-3"><i class="fas fa-headset"></i></div>
        <div>Contact Support</div>
      </h4>
      <p>If you need further assistance or have questions not covered in this guide, please reach out to technical support.</p>
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
        <a href="mailto:support@smarttrack.gov" class="btn btn-primary">
          <i class="fas fa-envelope me-2"></i>Email Support
        </a>
        <a href="tel:+639123456789" class="btn btn-outline-primary">
          <i class="fas fa-phone me-2"></i>Call Support
        </a>
      </div>
    </div>

  </div>
</div>

<!-- Scripts -->
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  function handleBurgerClick(){
    if (window.innerWidth < 992) { sidebar.classList.toggle('open'); return; }
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
  burgerBtn.addEventListener('click', handleBurgerClick);
  window.addEventListener('resize',()=>{ if(window.innerWidth<992){ mainContent.classList.remove('collapsed'); } });
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
  });
</script>
</body>
</html>

