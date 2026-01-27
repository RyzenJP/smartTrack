<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php'; // This provides $conn

// Total vehicles - use prepared statements for consistency
$vehicle_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM fleet_vehicles");
$vehicle_stmt->execute();
$totalVehicles = $vehicle_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$vehicle_stmt->close();

// Active vehicles
$active_stmt = $conn->prepare("SELECT COUNT(*) AS active FROM fleet_vehicles WHERE status = 'active'");
$active_stmt->execute();
$activeVehicles = $active_stmt->get_result()->fetch_assoc()['active'] ?? 0;
$active_stmt->close();

// Maintenance vehicles
$maint_stmt = $conn->prepare("SELECT COUNT(*) AS maintenance FROM fleet_vehicles WHERE status = 'maintenance'");
$maint_stmt->execute();
$vehiclesInMaintenance = $maint_stmt->get_result()->fetch_assoc()['maintenance'] ?? 0;
$maint_stmt->close();

// Total users
$user_stmt = $conn->prepare("SELECT COUNT(*) AS users FROM user_table");
$user_stmt->execute();
$totalUsers = $user_stmt->get_result()->fetch_assoc()['users'] ?? 0;
$user_stmt->close();

// Assigned drivers with their current routes
$assignedDriversQuery = "
    SELECT 
        d.user_id,
        d.full_name,
        d.username,
        r.id as route_id,
        r.status as route_status,
        v.plate_number,
        v.unit,
        r.created_at as route_start_time
    FROM user_table d
    LEFT JOIN routes r ON d.user_id = r.driver_id AND r.status = 'active'
    LEFT JOIN fleet_vehicles v ON r.unit = v.unit
    WHERE d.role = 'Driver' AND d.status = 'Active'
    ORDER BY d.full_name
";
// Use prepared statement for consistency (static query but best practice)
$assignedDrivers_stmt = $conn->prepare($assignedDriversQuery);
$assignedDrivers_stmt->execute();
$assignedDriversResult = $assignedDrivers_stmt->get_result();
$assignedDrivers = $assignedDriversResult->fetch_all(MYSQLI_ASSOC);

// Get vehicle information for the mobile device
$deviceID = "MOBILE-001"; // The device ID used in the map
// Get the first available vehicle from the fleet
$vehicleQuery = "
    SELECT 
        v.plate_number,
        v.unit,
        v.status as vehicle_status
    FROM fleet_vehicles v
    WHERE v.status = 'active'
    ORDER BY v.plate_number ASC
    LIMIT 1
";
$vehicleStmt = $conn->prepare($vehicleQuery);
$vehicleStmt->execute();
$vehicleResult = $vehicleStmt->get_result();
$vehicleInfo = $vehicleResult->fetch_assoc();

// Fallback vehicle info if none found
if (!$vehicleInfo) {
    $vehicleInfo = [
        'plate_number' => 'ABC-123',
        'unit' => 'VH-001',
        'vehicle_status' => 'Active'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Smart Track</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

    /* Fix dropdown toggle layout - position chevron next to text */
    .sidebar .dropdown-toggle {
      justify-content: flex-start !important;
      align-items: center !important;
      gap: 8px;
    }

    .sidebar .dropdown-toggle > div {
      display: flex;
      align-items: center;
    }

    .sidebar .dropdown-toggle .link-text {
      margin-left: 12px;
    }

    .sidebar .dropdown-chevron {
      margin-left: auto;
      flex-shrink: 0;
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

    /* Responsive tweaks */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 260px;
        transform: translateX(-100%);
        position: fixed;
      }
      .sidebar.open {
        transform: translateX(0);
      }
      .sidebar.collapsed { /* no-op on mobile */
        width: 260px;
      }
      .main-content,
      .main-content.collapsed {
        margin-left: 0;
        padding: 16px;
      }
      #map {
        height: 50vh;
        min-height: 400px;
      }
    }
    
    /* Pulse animation for live indicator */
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }
    
    /* Vehicle info popup styling */
    .vehicle-info-popup {
      font-family: 'Segoe UI', sans-serif;
    }
    
    .vehicle-info-popup .popup-header {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
      padding: 10px 15px;
      margin: -10px -15px 10px -15px;
      border-radius: 5px 5px 0 0;
      font-weight: 600;
    }
    
    .vehicle-info-popup .popup-content {
      font-size: 14px;
    }
    
    .vehicle-info-popup .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      padding: 4px 0;
      border-bottom: 1px solid #eee;
    }
    
    .vehicle-info-popup .info-row:last-child {
      border-bottom: none;
    }
    
    .vehicle-info-popup .info-label {
      font-weight: 600;
      color: var(--primary);
    }
    
    .vehicle-info-popup .info-value {
      color: #666;
    }
    
    .driver-status {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .driver-status.on-route {
      background-color: #d4edda;
      color: #155724;
    }
    
    .driver-status.available {
      background-color: #f8d7da;
      color: #721c24;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

 <!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="row g-4">
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
      <div class="col-md-3">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-satellite-dish"></i></div>
            <div>
              <h6>Active Vehicles</h6>
              <h4><?= $activeVehicles ?></h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-users"></i></div>
            <div>
              <h6>Total Users</h6>
              <h4><?= $totalUsers ?></h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-tools"></i></div>
            <div>
              <h6>Ongoing Maintenance</h6>
              <h4><?= $vehiclesInMaintenance ?></h4>
            </div>
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
// Real-time Clock Functionality
function updateClock() {
  const now = new Date();
  
  // Format time (HH:MM:SS)
  const timeString = now.toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  
  // Format date (Day, Month Date, Year)
  const dateString = now.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
  
  // Update DOM elements
  document.getElementById('real-time-clock').textContent = timeString;
  document.getElementById('real-time-date').textContent = dateString;
}

// Update clock immediately and then every second
updateClock();
setInterval(updateClock, 1000);
</script>

<script>
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
let vehicleName = "<?= addslashes($vehicleInfo['plate_number']) ?>";
let vehicleUnit = "<?= addslashes($vehicleInfo['unit']) ?>";
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
          html: `<div style="background: #28a745; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4); animation: pulse 2s infinite;" title="${vehicleName}"></div>`,
          iconSize: [22, 22]
        })
      }).addTo(map).bindPopup(`
        <div class="vehicle-info-popup" style="min-width: 250px;">
          <div class="popup-header">
            <i class="fas fa-car" style="margin-right: 8px;"></i>🚗 ${vehicleName}
          </div>
          <div class="popup-content">
            <div class="info-row">
              <span class="info-label">Vehicle:</span>
              <span class="info-value">${vehicleName}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Unit:</span>
              <span class="info-value">${vehicleUnit}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Lat:</span>
              <span class="info-value">${latest.lat}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Lng:</span>
              <span class="info-value">${latest.lng}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Time:</span>
              <span class="info-value" id="vehicle-time">${latest.timestamp || new Date().toLocaleString()}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Assigned Driver:</span>
              <span class="info-value" id="driver-name">Loading...</span>
            </div>
            <div class="info-row">
              <span class="info-label">Driver Status:</span>
              <span class="info-value" id="driver-status">Loading...</span>
            </div>
          </div>
        </div>
      `).openPopup();
    } else {
      espMarker.setLatLng(currentLatLng);
    }

    map.setView(currentLatLng, 18);
    
    // Update driver information in popup
    updateDriverInfoInPopup();
  } catch (err) {
    console.error("Fetch error:", err);
  }
}

// Function to update driver information in the popup
async function updateDriverInfoInPopup() {
  try {
    // Get assigned driver information from the assigned drivers data
    const assignedDrivers = <?= json_encode($assignedDrivers) ?>;
    
    // Find the first driver with an active route (on route)
    const driverOnRoute = assignedDrivers.find(driver => driver.route_id);
    const availableDriver = assignedDrivers.find(driver => !driver.route_id);
    
    // Use driver on route if available, otherwise use first available driver
    const selectedDriver = driverOnRoute || availableDriver;
    
    // Update popup content if it exists
    const driverNameElement = document.getElementById('driver-name');
    const driverStatusElement = document.getElementById('driver-status');
    const vehicleTimeElement = document.getElementById('vehicle-time');
    
    if (driverNameElement) {
      if (selectedDriver) {
        driverNameElement.textContent = selectedDriver.full_name;
      } else {
        driverNameElement.textContent = 'No drivers available';
      }
    }
    
    if (driverStatusElement) {
      if (selectedDriver && selectedDriver.route_id) {
        driverStatusElement.innerHTML = '<span class="driver-status on-route">On Route</span>';
      } else if (selectedDriver) {
        driverStatusElement.innerHTML = '<span class="driver-status available">Available</span>';
      } else {
        driverStatusElement.textContent = 'No drivers';
      }
    }
    
    if (vehicleTimeElement) {
      vehicleTimeElement.textContent = new Date().toLocaleString();
    }
    
  } catch (error) {
    console.error('Error updating driver info:', error);
    
    // Fallback to show error state
    const driverNameElement = document.getElementById('driver-name');
    const driverStatusElement = document.getElementById('driver-status');
    
    if (driverNameElement) {
      driverNameElement.textContent = 'Error loading driver info';
    }
    
    if (driverStatusElement) {
      driverStatusElement.textContent = 'Unknown';
    }
  }
}


// --- Initialize map and start updates ---
updateVehicleLocations();
setInterval(updateVehicleLocations, 3000);

// Update driver info every 5 seconds
setInterval(updateDriverInfoInPopup, 5000);
</script>




<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  function handleBurgerClick() {
    if (window.innerWidth < 992) {
      sidebar.classList.toggle('open');
      return;
    }
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

  burgerBtn.addEventListener('click', handleBurgerClick);

  // Ensure correct layout on resize
  window.addEventListener('resize', () => {
    if (window.innerWidth < 992) {
      mainContent.classList.remove('collapsed');
    }
  });
</script>

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