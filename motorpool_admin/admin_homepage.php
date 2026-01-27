<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'motor_pool_admin'])) {
    header("Location: ../index.php");
    exit;
}

// Database connection
require_once __DIR__ . '/../db_connection.php';

// Selected month (YYYY-MM) - defaults to current month
$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
    ? $_GET['month']
    : date('Y-m');
[$selYear, $selMonth] = explode('-', $selectedMonth);

// Fetch dashboard statistics
try {
    // Total Mechanics - use prepared statement for consistency
    $totalMechanics_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_table WHERE role = 'mechanic'");
    $totalMechanics_stmt->execute();
    $totalMechanicsResult = $totalMechanics_stmt->get_result();
    $totalMechanics = $totalMechanicsResult->fetch_assoc()['total'];
    $totalMechanics_stmt->close();

    // Maintenance Completed (selected month) - use prepared statement for security
    $completedQuery = "SELECT COUNT(*) as completed FROM maintenance_schedules 
                       WHERE status = 'completed' 
                       AND MONTH(created_at) = ? 
                       AND YEAR(created_at) = ?";
    $completed_stmt = $conn->prepare($completedQuery);
    $completed_stmt->bind_param("ii", $selMonth, $selYear);
    $completed_stmt->execute();
    $completedResult = $completed_stmt->get_result();
    $maintenanceCompleted = $completedResult->fetch_assoc()['completed'];
    $completed_stmt->close();

    // Ongoing Maintenance - use prepared statement for consistency
    $ongoing_stmt = $conn->prepare("SELECT COUNT(*) as ongoing FROM maintenance_schedules WHERE status = 'in_progress'");
    $ongoing_stmt->execute();
    $ongoingResult = $ongoing_stmt->get_result();
    $ongoingMaintenance = $ongoingResult->fetch_assoc()['ongoing'];
    $ongoing_stmt->close();

    // Pending Maintenance - use prepared statement for consistency
    $pending_stmt = $conn->prepare("SELECT COUNT(*) as pending FROM maintenance_schedules WHERE status = 'pending'");
    $pending_stmt->execute();
    $pendingResult = $pending_stmt->get_result();
    $pendingMaintenance = $pendingResult->fetch_assoc()['pending'];
    $pending_stmt->close();

    // Total Vehicles - use prepared statement for consistency
    $totalVehicles_stmt = $conn->prepare("SELECT COUNT(*) as total FROM fleet_vehicles");
    $totalVehicles_stmt->execute();
    $totalVehiclesResult = $totalVehicles_stmt->get_result();
    $totalVehicles = $totalVehiclesResult->fetch_assoc()['total'];
    $totalVehicles_stmt->close();

    // Active Drivers - use prepared statement for consistency
    $activeDrivers_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_table WHERE role = 'driver'");
    $activeDrivers_stmt->execute();
    $activeDriversResult = $activeDrivers_stmt->get_result();
    $activeDrivers = $activeDriversResult->fetch_assoc()['total'];
    $activeDrivers_stmt->close();

    // Overdue Maintenance - use prepared statement for consistency
    $overdue_stmt = $conn->prepare("SELECT COUNT(*) as overdue FROM maintenance_schedules 
                                    WHERE status IN ('pending', 'in_progress') 
                                    AND scheduled_date < CURDATE()");
    $overdue_stmt->execute();
    $overdueResult = $overdue_stmt->get_result();
    $overdueMaintenance = $overdueResult->fetch_assoc()['overdue'];
    $overdue_stmt->close();

} catch (Exception $e) {
    // Fallback to static values if database query fails
    $totalMechanics = 0;
    $maintenanceCompleted = 0;
    $ongoingMaintenance = 0;
    $pendingMaintenance = 0;
    $totalVehicles = 0;
    $activeDrivers = 0;
    $overdueMaintenance = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Smart Track</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
      margin: 0;
      padding: 0;
      overflow-x: hidden;
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

    .card-icon.text-warning {
      color: #ffc107 !important;
    }

    .text-warning {
      color: #ffc107 !important;
    }

    .text-success {
      color: #198754 !important;
    }

    #map {
      width: 100%;
      height: 70vh;
      min-height: 500px;
      background-color: #e9ecef;
      border-radius: 0.5rem;
    }

    /* Vehicle marker styling */
    .vehicle-marker {
      cursor: pointer;
      transition: transform 0.2s ease;
    }

    .vehicle-marker:hover {
      transform: scale(1.3);
    }

    /* Pulsing animation for vehicle markers */
    @keyframes pulse {
      0% {
        transform: scale(1);
        opacity: 1;
      }
      50% {
        transform: scale(1.1);
        opacity: 0.8;
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        margin-top: 60px;
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
      
      /* Fix month selector on mobile */
      .month-selector-form {
        flex-direction: column !important;
        gap: 1rem !important;
        align-items: stretch !important;
      }
      
      .month-selector-form .form-control {
        max-width: 100% !important;
        width: 100% !important;
      }
      
      .month-selector-form .btn {
        width: 100% !important;
        padding: 12px !important;
      }
      
      .month-selector-label {
        text-align: center !important;
        margin-bottom: 0.5rem !important;
      }
      
      #map {
        height: 50vh;
        min-height: 400px;
      }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
  <!-- Navbar -->
  <?php include __DIR__ . '/../pages/admin_navbar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-screwdriver-wrench"></i></div>
              <div>
                <h6>Total Mechanics</h6>
                <h4><?= $totalMechanics ?></h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-clipboard-check"></i></div>
              <div>
                <h6>Completed This Month</h6>
                <h4><?= $maintenanceCompleted ?></h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-spinner fa-spin"></i></div>
              <div>
                <h6>Pending Maintenance</h6>
                <h4><?= $pendingMaintenance + $ongoingMaintenance ?></h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-car"></i></div>
              <div>
                <h6>Total Vehicles</h6>
                <h4><?= $totalVehicles ?></h4>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Second Row of Statistics -->
      <div class="row g-4 mt-2">
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-user-tie"></i></div>
              <div>
                <h6>Active Drivers</h6>
                <h4><?= $activeDrivers ?></h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-chart-line"></i></div>
              <div>
                <h6>Completion Rate</h6>
                <h4><?= $totalVehicles > 0 ? min(100, round(($maintenanceCompleted / max($totalVehicles, 1)) * 100, 1)) : 0 ?>%</h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-calendar-check"></i></div>
              <div>
                <h6>Selected Month</h6>
                <h4><?= date('M Y', strtotime($selectedMonth . '-01')) ?></h4>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 card-icon"><i class="fas fa-tools"></i></div>
              <div>
                <h6>Total Jobs</h6>
                <h4><?= $pendingMaintenance + $ongoingMaintenance + $maintenanceCompleted ?></h4>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Month Selector -->
      <div class="row mt-4">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-body">
              <form class="d-flex align-items-center gap-3 month-selector-form" method="get" action="">
                <div class="d-flex align-items-center gap-2 month-selector-label">
                  <i class="fas fa-calendar-alt text-primary" style="font-size: 1.2rem;"></i>
                  <label for="monthPicker" class="form-label mb-0 fw-medium">Select Month:</label>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <input type="month" id="monthPicker" name="month" 
                         class="form-control shadow-sm" 
                         style="max-width: 200px; border-radius: 8px; border: 1px solid #dee2e6;" 
                         value="<?= htmlspecialchars($selectedMonth) ?>">
                  <button type="submit" class="btn btn-primary shadow-sm px-4" 
                          style="border-radius: 8px; background: var(--primary); border: none; font-weight: 500;">
                    <i class="fas fa-search me-2"></i>Apply
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Map Section -->
      <div class="row mt-4">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title text-primary">Vehicle Live Location (GIS Map)</h5>
              <div id="map"></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- JS -->
<script>
// --- Map Implementation ---
const map = L.map("map").setView([10.5537, 122.9142], 20);

// --- Base Layers ---
let street = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let satellite = L.tileLayer(
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
  {
    attribution: "Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics"
  }
);

let hybrid = L.tileLayer(
  "https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}",
  {
    subdomains: ["mt0", "mt1", "mt2", "mt3"],
    attribution: "Map data &copy; Google",
    maxZoom: 20
  }
);

// --- Layer Control ---
let baseMaps = {
  "Street Map": street,
  "Satellite": satellite,
  "Hybrid": hybrid
};

L.control.layers(baseMaps).addTo(map);

// --- Vehicle tracking variables ---
let deviceID = "MOBILE-001";
let espMarker = null;
let vehicleMarkers = new Map();
let vehicles = new Map();

// --- Update vehicle locations ---
async function updateVehicleLocations() {
  try {
    const latestRes = await fetch(`../get_latest_location.php?device_id=${deviceID}`);
    const latest = await latestRes.json();

    console.log('Latest location response:', latest);

    if (!latest.lat || !latest.lng) {
      console.error('Invalid location data:', latest);
      return;
    }

    const currentLatLng = [parseFloat(latest.lat), parseFloat(latest.lng)];

    // Update or create vehicle marker with green styling
    if (!espMarker) {
      espMarker = L.marker(currentLatLng, {
        icon: L.divIcon({
          className: 'vehicle-marker',
          html: `<div style="background: #28a745; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4); animation: pulse 2s infinite;" title="Vehicle Location"></div>`,
          iconSize: [22, 22]
        })
      }).addTo(map).bindPopup(`
        <div style="min-width: 200px;">
          <b>🚗 Vehicle Location</b><br>
          <b>Device:</b> ${deviceID}<br>
          <b>Lat:</b> ${latest.lat}<br>
          <b>Lng:</b> ${latest.lng}<br>
          <b>Time:</b> ${latest.timestamp || 'Unknown'}
        </div>
      `).openPopup();
    } else {
      espMarker.setLatLng(currentLatLng);
    }

    map.setView(currentLatLng, 18);
  } catch (err) {
    console.error("Fetch error:", err);
  }
}


// --- Initialize map and start updates ---
updateVehicleLocations();
setInterval(updateVehicleLocations, 3000);

// --- Sidebar functionality ---
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

burgerBtn.addEventListener('click', () => {
  // Check if mobile view
  const isMobile = window.innerWidth <= 768;
  
  if (isMobile) {
    // Mobile: slide in/out
    sidebar.classList.toggle('show');
  } else {
    // Desktop: collapse/expand
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

    // 🚨 Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  }
});
</script>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.getElementById("logoutBtn").addEventListener("click", function(e) {
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
          btn.style.minWidth = '120px'; // same width
          btn.style.padding = '10px 16px'; // same padding
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
</script>
</body>
</html>