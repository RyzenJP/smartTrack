<?php
session_start();
require_once __DIR__ . '/../db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Routing | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
  <style>
       #map {
      height: 500px;
      width: 100%;
      border-radius: 0.5rem;
      margin-top: 10px;
    }
    .route-controls {
      position: absolute;
      top: 10px;
      left: 10px;
      z-index: 1000;
      background: white;
      padding: 10px;
      border-radius: 5px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
  
    
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
    margin-top: -50px; /* Adjust this value as needed */
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
  </style>
</head>
<body>
<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid" style="margin-top: 70px;">
    <div class="row mt-2">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title text-primary fw-bold m-0">Routing</h5>
            </div>
            <div id="map"></div>
            
            <div class="route-controls">
              <div class="form-group mb-2">
                <label class="form-label">Start Point (A):</label>
                <select id="pointA" class="form-select form-select-sm">
                  <option value="">Select Geofence</option>
                </select>
              </div>
              <div class="form-group mb-2">
                <label class="form-label">End Point (B):</label>
                <input type="text" id="pointB" class="form-control form-control-sm" placeholder="Click on map">
              </div>
              
            
            <div class="action-buttons">
              <button class="btn btn-primary btn-sm" id="calculateRouteBtn">
                <i class="fas fa-route"></i> Calculate
              </button>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1" aria-labelledby="routeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="routeModalLabel">Route Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="routeForm">
          <input type="hidden" id="routeId">
          <div class="mb-3">
            <label for="routeName" class="form-label">Route Name</label>
            <input type="text" class="form-control" id="routeName" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Latitude</label>
              <input type="text" class="form-control" id="startLat" readonly>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Longitude</label>
              <input type="text" class="form-control" id="startLng" readonly>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">End Latitude</label>
              <input type="text" class="form-control" id="endLat" readonly>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Longitude</label>
              <input type="text" class="form-control" id="endLng" readonly>
            </div>
          </div>
          <div class="mb-3">
            <label for="routeDistance" class="form-label">Distance (km)</label>
            <input type="text" class="form-control" id="routeDistance" readonly>
          </div>
          <div class="mb-3">
            <label for="routeDuration" class="form-label">Duration</label>
            <input type="text" class="form-control" id="routeDuration" readonly>
          </div>
          <div class="mb-3">
            <label for="modalDriverId" class="form-label">Assigned Driver</label>
            <select class="form-select" id="modalDriverId" required>
              <option value="">Select Driver</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSaveRoute">Save Route</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize map centered on Bago City
  const map = L.map('map').setView([10.5388, 122.8388], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  const routeModal = new bootstrap.Modal(document.getElementById('routeModal'));
  let routingControl = null;
  let startPoint = null;
  let endPoint = null;
  let currentRouteId = null;
  let geofences = []; // Store loaded geofences
  let geofenceLayers = []; // Store geofence circles on map

  // Load geofences for Point A dropdown and display on map
  async function loadGeofences() {
    try {
      const response = await fetch('geofence_api.php?action=get_geofences');
      const { success, data } = await response.json();
      
      if (success) {
        geofences = data;
        const select = document.getElementById('pointA');
        select.innerHTML = '<option value="">Select Geofence</option>';
        
        // Clear existing geofences from map
        geofenceLayers.forEach(layer => map.removeLayer(layer));
        geofenceLayers = [];
        
        // Add each geofence to dropdown and map
        data.forEach(geofence => {
          // Add to dropdown
          const option = document.createElement('option');
          option.value = geofence.id;
          option.textContent = geofence.name;
          option.dataset.lat = geofence.latitude;
          option.dataset.lng = geofence.longitude;
          option.dataset.radius = geofence.radius;
          select.appendChild(option);
          
          // Add to map as circle
          const circle = L.circle([geofence.latitude, geofence.longitude], {
            color: '#003566',
            fillColor: '#00b4d8',
            fillOpacity: 0.2,
            radius: geofence.radius
          }).bindPopup(`<b>${geofence.name}</b><br>Radius: ${geofence.radius}m`);
          
          circle.addTo(map);
          geofenceLayers.push(circle);
        });
      }
    } catch (error) {
      console.error('Error loading geofences:', error);
    }
  }

  // Update the loadDrivers function (around line 400)
async function loadDrivers() {
  try {
    const response = await fetch('assignment_api.php?action=get_assignments');
    const { success, data } = await response.json();
    
    if (success) {
      const modalSelect = document.getElementById('modalDriverId');
      modalSelect.innerHTML = '<option value="">Select Driver</option>';
      data.forEach(driver => {
        const option = document.createElement('option');
        option.value = driver.id;
        option.textContent = `${driver.driver_name} (${driver.plate_number || 'No vehicle'})`;
        modalSelect.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Error loading drivers:', error);
  }
}

  // Handle map clicks for Point B
  map.on('click', function(e) {
    endPoint = e.latlng;
    document.getElementById('pointB').value = `${e.latlng.lat.toFixed(4)}, ${e.latlng.lng.toFixed(4)}`;
  });

  // Calculate route button
  document.getElementById('calculateRouteBtn').addEventListener('click', function() {
    const pointASelect = document.getElementById('pointA');
    const selectedOption = pointASelect.options[pointASelect.selectedIndex];
    
    if (!selectedOption.value) {
      Swal.fire('Error', 'Please select a start point (Point A)', 'error');
      return;
    }
    
    if (!endPoint) {
      Swal.fire('Error', 'Please select an end point (Point B) by clicking on the map', 'error');
      return;
    }
    
    startPoint = L.latLng(
      parseFloat(selectedOption.dataset.lat),
      parseFloat(selectedOption.dataset.lng)
    );
    
    // Remove existing route if any
    if (routingControl) {
      map.removeControl(routingControl);
    }
    
    // Calculate and display new route
    routingControl = L.Routing.control({
      waypoints: [startPoint, endPoint],
      routeWhileDragging: true,
      show: false // Hide the default instructions panel
    }).addTo(map);
    
    routingControl.on('routesfound', function(e) {
      const routes = e.routes;
      const route = routes[0];
      
      // Update modal fields with route info
      document.getElementById('routeName').value = `${selectedOption.text} to ${endPoint.lat.toFixed(4)},${endPoint.lng.toFixed(4)}`;
      document.getElementById('startLat').value = startPoint.lat.toFixed(6);
      document.getElementById('startLng').value = startPoint.lng.toFixed(6);
      document.getElementById('endLat').value = endPoint.lat.toFixed(6);
      document.getElementById('endLng').value = endPoint.lng.toFixed(6);
      document.getElementById('routeDistance').value = (route.summary.totalDistance / 1000).toFixed(2) + ' km';
      document.getElementById('routeDuration').value = (route.summary.totalTime / 60).toFixed(1) + ' mins';
      
      // Open modal to confirm/save
      routeModal.show();
    });
  });

  // Save route button
  document.getElementById('confirmSaveRoute').addEventListener('click', async function() {
    const routeData = {
      name: document.getElementById('routeName').value,
      start_lat: document.getElementById('startLat').value,
      start_lng: document.getElementById('startLng').value,
      end_lat: document.getElementById('endLat').value,
      end_lng: document.getElementById('endLng').value,
      distance: document.getElementById('routeDistance').value,
      duration: document.getElementById('routeDuration').value,
      driver_id: document.getElementById('modalDriverId').value
    };
    
    if (currentRouteId) {
      routeData.id = currentRouteId;
    }
    
    try {
      const response = await fetch('routing_api.php?action=' + (currentRouteId ? 'update_route' : 'save_route'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(routeData)
      });
      
      const result = await response.json();
      
      if (result.success) {
        routeModal.hide();
        Swal.fire('Success!', `Route ${currentRouteId ? 'updated' : 'saved'} successfully`, 'success');
        currentRouteId = result.id || currentRouteId;
      } else {
        Swal.fire('Error', result.message || 'Failed to save route', 'error');
      }
    } catch (error) {
      console.error('Error:', error);
      Swal.fire('Error', 'Network error occurred', 'error');
    }
  });

  // Initialize on page load
  loadGeofences();
  loadDrivers();
});
</script>
</script>
 <!-- JS -->
<script>
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

  // ✅ Sidebar active class handler (top-level and submenu)
  const allSidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');

  allSidebarLinks.forEach(link => {
    link.addEventListener('click', function () {
      // Remove active from all links (top-level and submenu)
      allSidebarLinks.forEach(l => l.classList.remove('active'));

      // Add active to clicked link
      this.classList.add('active');

      // Optional: Expand parent menu if collapsed
      const parentCollapse = this.closest('.collapse');
      if (parentCollapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(parentCollapse);
        if (bsCollapse) {
          bsCollapse.show();
        }
      }
    });
  });
</script>
</body>
</html>