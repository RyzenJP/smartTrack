<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// Real-time data queries (filter out synthetic vehicles) - use prepared statements for consistency
$activeVehicles_stmt = $conn->prepare("SELECT COUNT(*) FROM fleet_vehicles WHERE status = 'active' AND article NOT LIKE '%Synthetic%' AND plate_number NOT LIKE 'SYN-%' AND plate_number NOT LIKE '%SYN%'");
$activeVehicles_stmt->execute();
$activeVehicles = $activeVehicles_stmt->get_result()->fetch_row()[0];
$activeVehicles_stmt->close();

$onRoute_stmt = $conn->prepare("SELECT COUNT(DISTINCT r.unit) FROM routes r JOIN fleet_vehicles v ON r.unit = v.unit WHERE r.status = 'active' AND v.article NOT LIKE '%Synthetic%' AND v.plate_number NOT LIKE 'SYN-%' AND v.plate_number NOT LIKE '%SYN%'");
$onRoute_stmt->execute();
$onRoute = $onRoute_stmt->get_result()->fetch_row()[0];
$onRoute_stmt->close();

$availableDrivers_stmt = $conn->prepare("SELECT COUNT(*)
                                  FROM user_table u
                                  WHERE u.role = 'Driver' AND u.status = 'Active'
                                  AND (
                                    NOT EXISTS (
                                      SELECT 1 FROM vehicle_assignments a
                                      WHERE a.driver_id = u.user_id AND a.status = 'active'
                                    )
                                    OR EXISTS (
                                      SELECT 1 FROM vehicle_assignments a
                                      WHERE a.driver_id = u.user_id AND a.status = 'available'
                                    )
                                  )");
$availableDrivers_stmt->execute();
$availableDrivers = $availableDrivers_stmt->get_result()->fetch_row()[0];
$availableDrivers_stmt->close();
// Get pending assignments (using prepared statement for security)
$dispatcher_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_reservations WHERE assigned_dispatcher_id = ? AND status = 'assigned'");
$stmt->bind_param("i", $dispatcher_id);
$stmt->execute();
$pendingAssignments = $stmt->get_result()->fetch_row()[0] ?? 0;

// Get active routes data for the map (filter out synthetic vehicles) - use prepared statement for consistency
$activeRoutes_stmt = $conn->prepare("
    SELECT r.*, v.plate_number, d.full_name AS driver_name 
    FROM routes r
    JOIN fleet_vehicles v ON r.unit = v.unit
    JOIN user_table d ON r.driver_id = d.user_id
    WHERE r.status = 'active'
    AND v.article NOT LIKE '%Synthetic%'
    AND v.plate_number NOT LIKE 'SYN-%'
    AND v.plate_number NOT LIKE '%SYN%'
");
$activeRoutes_stmt->execute();
$activeRoutes = $activeRoutes_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dispatcher Dashboard | Smart Track</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
      margin-top: 20px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    /* Simple card styles to match system */
    .card {
      border: none;
      border-radius: 0.5rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
    
    /* Add modal styles that match your UI */
    .modal-content {
      border-radius: 0.5rem;
      border: none;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .modal-header {
      background-color: var(--primary);
      color: white;
      border-bottom: none;
    }
    .modal-footer {
      border-top: none;
    }
    .alert-item {
      transition: all 0.3s;
      border-left: 4px solid var(--accent);
    }
    
    
    /* Highlight marker styles */
    .highlight-marker {
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    
    .highlight-marker:hover {
      transform: scale(1.2);
    }
    
    /* Action button styles */
    .action-btn {
      cursor: pointer !important;
      pointer-events: auto !important;
      position: relative;
      z-index: 10;
      transition: all 0.2s ease;
    }
    
    .action-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .action-btn:active {
      transform: scale(0.95);
    }
    
    /* Ensure modal content is clickable */
    .modal-content {
      pointer-events: auto !important;
    }
    
    .modal-body {
      pointer-events: auto !important;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-0">
    <!-- Dashboard Stats Cards -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title text-primary fw-bold mb-3">Dashboard Overview</h5>
            <div class="row">
              <!-- Status Summary Cards -->
              <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">Active Vehicles</h6>
                    <h4 class="card-title"><?= $activeVehicles ?></h4>
                    <small class="text-light">Fleet Status</small>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">On Route</h6>
                    <h4 class="card-title"><?= $onRoute ?></h4>
                    <small class="text-light">Active Routes</small>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">Available Drivers</h6>
                    <h4 class="card-title"><?= $availableDrivers ?></h4>
                    <small class="text-light">Ready to Assign</small>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3 mb-3">
                <div class="card bg-warning text-dark">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">My Reservations</h6>
                    <h4 class="card-title"><?= $pendingAssignments ?></h4>
                    <small class="text-muted">Awaiting Action</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Active Routes Modal -->
<div class="modal fade" id="activeRoutesModal" tabindex="-1" aria-hidden="true" style="z-index: 1200;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">All Active Routes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Route ID</th>
                <th>Driver</th>
                <th>Vehicle</th>
                <th>Start Point</th>
                <th>End Point</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while($route = $activeRoutes->fetch_assoc()): ?>
                <tr>
                  <td><?= $route['id'] ?></td>
                  <td><?= htmlspecialchars($route['driver_name']) ?></td>
                  <td><?= htmlspecialchars($route['unit'] . ' (' . $route['plate_number'] . ')') ?></td>
                  <td><?= $route['start_lat'] ?>, <?= $route['start_lng'] ?></td>
                  <td><?= $route['end_lat'] ?>, <?= $route['end_lng'] ?></td>
                  <td><span class="badge bg-success">Active</span></td>
                  <td>
                    <button class="btn btn-sm btn-info action-btn" 
                            data-route-id="<?= $route['id'] ?>" 
                            data-start-lat="<?= $route['start_lat'] ?>" 
                            data-start-lng="<?= $route['start_lng'] ?>" 
                            data-end-lat="<?= $route['end_lat'] ?>" 
                            data-end-lng="<?= $route['end_lng'] ?>"
                            title="View route on map">
                      <i class="fas fa-map-marked-alt"></i>
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// No map needed - using cards instead

// No route cards or statistics needed

// Focus on specific route
function focusRoute(routeId, startLat, startLng, endLat, endLng) {
    console.log('=== FOCUS ROUTE CALLED ===');
    console.log('Parameters:', {routeId, startLat, startLng, endLat, endLng});
    console.log('Map object:', map);
    console.log('Map center before:', map.getCenter());
    console.log('Map zoom before:', map.getZoom());
    
    // Validate coordinates
    if (!startLat || !startLng || !endLat || !endLng || 
        isNaN(startLat) || isNaN(startLng) || isNaN(endLat) || isNaN(endLng)) {
        console.error('Invalid coordinates:', {startLat, startLng, endLat, endLng});
        Swal.fire({
            position: 'top-end',
            icon: 'error',
            title: 'Invalid route coordinates',
            text: 'Cannot display route on map',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }
    
    // Close the modal first
    $('#activeRoutesModal').modal('hide');
    
    // Clear existing route highlights
    clearRouteHighlights();
    
    // Create route line for the selected route (highlighted version)
    const startPoint = L.latLng(startLat, startLng);
    const endPoint = L.latLng(endLat, endLng);
    
    console.log('Start point created:', startPoint);
    console.log('End point created:', endPoint);
    
    // Calculate distance to determine line style
    const distance = startPoint.distanceTo(endPoint);
    console.log('Distance calculated:', distance, 'meters');
    
    // Use the same working routing implementation from active-routes.php
    console.log('Creating road-aligned route using active-routes.php method...');
    
    // Remove any existing routing control
    if (window.currentRouteControl) {
        map.removeControl(window.currentRouteControl);
    }
    
    // Create routing control using the same method as active-routes.php
    window.currentRouteControl = L.Routing.control({
        waypoints: [
            L.latLng(startPoint.lat, startPoint.lng),
            L.latLng(endPoint.lat, endPoint.lng)
        ],
        routeWhileDragging: true,
        show: false
                }).addTo(map);

    console.log('Routing control created and added to map using active-routes.php method');
    
    // Fit map to show the route
    map.fitBounds([
        [startPoint.lat, startPoint.lng],
        [endPoint.lat, endPoint.lng]
    ]);
    
    console.log('Map fitted to route bounds');
    
    // Show success message
    Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: `Route Displayed! Distance: ${Math.round(distance)}m`,
        text: `From: ${startPoint.lat.toFixed(6)}, ${startPoint.lng.toFixed(6)} To: ${endPoint.lat.toFixed(6)}, ${endPoint.lng.toFixed(6)}`,
        showConfirmButton: false,
        timer: 5000
    });
    
    // Create VERY obvious markers
    const highlightStartMarker = L.marker(startPoint, {
        icon: L.divIcon({
            className: 'highlight-marker',
            html: '<div style="background: red; width: 40px; height: 40px; border-radius: 50%; border: 5px solid white; box-shadow: 0 0 20px rgba(255,0,0,1); font-size: 20px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">A</div>',
            iconSize: [50, 50],
            iconAnchor: [25, 25]
        })
    });
    
    const highlightEndMarker = L.marker(endPoint, {
        icon: L.divIcon({
            className: 'highlight-marker',
            html: '<div style="background: blue; width: 40px; height: 40px; border-radius: 50%; border: 5px solid white; box-shadow: 0 0 20px rgba(0,0,255,1); font-size: 20px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">B</div>',
            iconSize: [50, 50],
            iconAnchor: [25, 25]
        })
    });
    
    console.log('Markers created:', {highlightStartMarker, highlightEndMarker});
    
    // Add markers to map
    highlightStartMarker.addTo(map);
    highlightEndMarker.addTo(map);
    console.log('Markers added to map');
    
    // Bind popups
    highlightStartMarker.bindPopup(`<b>🎯 POINT A</b><br>Route ID: ${routeId}<br>Start Point<br>Lat: ${startLat}<br>Lng: ${startLng}`).openPopup();
    highlightEndMarker.bindPopup(`<b>🎯 POINT B</b><br>Route ID: ${routeId}<br>End Point<br>Lat: ${endLat}<br>Lng: ${endLng}`);
    
    // Store references for clearing later
    window.currentRouteHighlight = {
        line: highlightLine,
        startMarker: highlightStartMarker,
        endMarker: highlightEndMarker
    };
    
    // Show clear highlight button
    document.getElementById('clearHighlightBtn').style.display = 'inline-block';
    
    // Force map to center on the route with high zoom
    const center = L.latLngBounds([startPoint, endPoint]).getCenter();
    console.log('Center calculated:', center);
    
    // Set map view to center with high zoom
    map.setView(center, 19);
    console.log('Map view set to:', center, 'zoom 19');
    
    // Also try fitBounds as backup
    const bounds = L.latLngBounds([startPoint, endPoint]);
    map.fitBounds(bounds, {padding: [20, 20], maxZoom: 19});
    console.log('Map fitBounds called');
    
    console.log('Map center after:', map.getCenter());
    console.log('Map zoom after:', map.getZoom());
    console.log('=== ROUTE HIGHLIGHT COMPLETE ===');
    
    // Show success message
    Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: `Route ${routeId} highlighted!`,
        text: `Look for RED line with A and B markers! Distance: ${Math.round(distance)}m`,
        showConfirmButton: false,
        timer: 5000
    });
}

// Clear route highlights
function clearRouteHighlights() {
    // Clear routing control
    if (window.currentRouteControl) {
        map.removeControl(window.currentRouteControl);
        window.currentRouteControl = null;
        console.log('Routing control cleared');
    }
    
    if (window.currentRouteHighlight) {
        if (window.currentRouteHighlight.line) {
            map.removeLayer(window.currentRouteHighlight.line);
        }
        if (window.currentRouteHighlight.startMarker) {
            map.removeLayer(window.currentRouteHighlight.startMarker);
        }
        if (window.currentRouteHighlight.endMarker) {
            map.removeLayer(window.currentRouteHighlight.endMarker);
        }
        window.currentRouteHighlight = null;
        
        // Hide clear highlight button
        document.getElementById('clearHighlightBtn').style.display = 'none';
        
        // Show success message
        Swal.fire({
            position: 'top-end',
            icon: 'info',
            title: 'Route highlight cleared',
            showConfirmButton: false,
            timer: 1500
        });
    }
    
    // Also clear test route if it exists
    if (window.testRouteElements) {
        map.removeLayer(window.testRouteElements.line);
        map.removeLayer(window.testRouteElements.startMarker);
        map.removeLayer(window.testRouteElements.endMarker);
        window.testRouteElements = null;
        console.log('Test route cleared');
    }
}

// Test route function
function testRoute() {
    console.log('Testing route display...');
    console.log('Map object:', map);
    console.log('Map center:', map.getCenter());
    console.log('Map zoom:', map.getZoom());
    
    // Clear any existing highlights first
    clearRouteHighlights();
    
    // Create a very obvious test route with larger coordinates
    const testRouteId = 999;
    const startLat = 10.55;
    const startLng = 122.91;
    const endLat = 10.56;
    const endLng = 122.92;
    
    console.log('Creating test route with coordinates:', {startLat, startLng, endLat, endLng});
    
    // Create route line directly
    const startPoint = L.latLng(startLat, startLng);
    const endPoint = L.latLng(endLat, endLng);
    
    console.log('Start point:', startPoint);
    console.log('End point:', endPoint);
    
    // Add a very obvious route line
    const testLine = L.polyline([startPoint, endPoint], {
        color: 'red',
        weight: 10,
        opacity: 1.0
    }).addTo(map);
    
    // Add obvious markers
    const startMarker = L.marker(startPoint, {
        icon: L.divIcon({
            className: 'test-marker',
            html: '<div style="background: red; width: 30px; height: 30px; border-radius: 50%; border: 5px solid white; box-shadow: 0 0 10px rgba(255,0,0,0.8);"></div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        })
    }).addTo(map).bindPopup('TEST START POINT').openPopup();
    
    const endMarker = L.marker(endPoint, {
        icon: L.divIcon({
            className: 'test-marker',
            html: '<div style="background: blue; width: 30px; height: 30px; border-radius: 50%; border: 5px solid white; box-shadow: 0 0 10px rgba(0,0,255,0.8);"></div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        })
    }).addTo(map).bindPopup('TEST END POINT');
    
    // Store for clearing later
    window.testRouteElements = {
        line: testLine,
        startMarker: startMarker,
        endMarker: endMarker
    };
    
    // Fit map to show the test route
    const bounds = L.latLngBounds([startPoint, endPoint]);
    map.fitBounds(bounds, {padding: [50, 50]});
    
    console.log('Test route created successfully');
    
    Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: 'Test route created',
        text: 'You should see a red line with red and blue markers',
        showConfirmButton: false,
        timer: 3000
    });
}

// Debug map function
function debugMap() {
    console.log('=== MAP DEBUG INFO ===');
    console.log('Map object:', map);
    console.log('Map center:', map.getCenter());
    console.log('Map zoom:', map.getZoom());
    console.log('Map bounds:', map.getBounds());
    console.log('Map size:', map.getSize());
    console.log('Map container:', map.getContainer());
    console.log('Map layers count:', map.eachLayer ? 'Available' : 'Not available');
    
    // Count layers
    let layerCount = 0;
    map.eachLayer(function(layer) {
        layerCount++;
        console.log(`Layer ${layerCount}:`, layer);
    });
    console.log('Total layers on map:', layerCount);
    
    // Test adding a simple marker
    const testMarker = L.marker([10.55, 122.91]).addTo(map);
    testMarker.bindPopup('Debug marker - if you see this, map is working!').openPopup();
    
    // Remove after 3 seconds
    setTimeout(() => {
        map.removeLayer(testMarker);
        console.log('Debug marker removed');
    }, 3000);
    
    Swal.fire({
        position: 'top-end',
        icon: 'info',
        title: 'Map Debug Info',
        text: `Zoom: ${map.getZoom()}, Layers: ${layerCount}`,
        showConfirmButton: false,
        timer: 3000
    });
}

// Simple test function
function simpleTest() {
    console.log('=== SIMPLE TEST STARTED ===');
    console.log('Map object:', map);
    
    if (typeof map === 'undefined') {
        console.error('Map is undefined!');
        Swal.fire({
            position: 'top-end',
            icon: 'error',
            title: 'Map Error',
            text: 'Map object is undefined!',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }
    
    // Clear any existing test markers
    if (window.simpleTestMarker) {
        map.removeLayer(window.simpleTestMarker);
    }
    
    // Add a simple marker
    const marker = L.marker([10.55, 122.91]).addTo(map);
    marker.bindPopup('SIMPLE TEST MARKER - Map is working!').openPopup();
    
    // Store reference
    window.simpleTestMarker = marker;
    
    // Center map on marker
    map.setView([10.55, 122.91], 15);
    
    console.log('Simple test marker added');
    
    Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: 'Simple Test',
        text: 'You should see a marker with popup!',
        showConfirmButton: false,
        timer: 3000
    });
}

// Refresh map manually
function refreshMap() {
    // Reload active routes (this will update the always-visible routes)
    loadActiveRoutes();
    
    Swal.fire({
        position: 'center',
        icon: 'success',
        title: 'Map refreshed - Active routes updated',
        showConfirmButton: false,
        timer: 1500
    });
}

// No map action buttons needed - using cards instead

// No route loading needed
</script>

<script>

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

// Logout functionality
document.addEventListener("DOMContentLoaded", function() {
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function(e) {
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
        customClass: {
          popup: 'rounded-4 shadow',
          confirmButton: 'swal-btn',
          cancelButton: 'swal-btn'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '../logout.php';
        }
      });
    });
  }
});
</script>
</body>
</html>