<?php
session_start();
// Allow Super Admin to access geofencing
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Geofencing | Smart Track</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
#map { height: 500px; width: 100%; border-radius: 0.5rem; margin-top: 10px; }
.action-buttons { margin-top: 10px; display: flex; gap: 5px; }

/* Polygon point styling */
.polygon-point {
  background: #dc3545;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* Rectangle corner and handle styling */
.rectangle-corner {
  cursor: pointer;
  transition: transform 0.2s ease;
}

.rectangle-corner:hover {
  transform: scale(1.2);
}

.rectangle-handle {
  cursor: move;
  transition: transform 0.2s ease;
}

.rectangle-handle:hover {
  transform: scale(1.1);
}

/* Vehicle marker styling */
.vehicle-marker {
  cursor: pointer;
  transition: transform 0.2s ease;
}

.vehicle-marker:hover {
  transform: scale(1.3);
}

/* Offset SweetAlert2 toast below the header */
.swal2-container.swal2-top-end {
  top: 80px !important;
  right: 16px !important;
}

/* Geofence list styling */
#geofenceList .border-bottom:hover {
  background-color: #f8f9fa;
  transition: background-color 0.2s;
}

/* Tool button active state */
.btn-group .btn.active {
  background-color: var(--primary);
  border-color: var(--primary);
  color: white;
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
    margin-top: -80px; /* Move content up more */
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
      position : sticky;
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
</style>
</head>
<body>
<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid" style="margin-top: 70px;">
    <div class="row mt-2">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title text-primary fw-bold m-0">Geofencing</h5>
              <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                  <button class="btn btn-outline-primary btn-sm" id="circleTool" title="Draw Circle - Click once to create">
                    <i class="fas fa-circle"></i>
                  </button>
                  <button class="btn btn-outline-primary btn-sm" id="rectangleTool" title="Draw Rectangle - Click twice to create">
                    <i class="fas fa-square"></i>
                  </button>
                  <button class="btn btn-outline-primary btn-sm" id="polygonTool" title="Draw Polygon - Click multiple points, double-click to finish">
                    <i class="fas fa-draw-polygon"></i>
                  </button>
                  <button class="btn btn-outline-secondary btn-sm" id="selectTool" title="Select Mode">
                    <i class="fas fa-mouse-pointer"></i>
                  </button>
                </div>
                <div class="btn-group" role="group">
                  <a href="geofence_analytics.php" class="btn btn-outline-warning btn-sm" title="View Statistics">
                    <i class="fas fa-chart-bar"></i> Statistics
                  </a>
                </div>
              </div>
            </div>
            
            <div class="alert alert-info alert-dismissible fade show" role="alert">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Geofencing Features:</strong>
              <ul class="mb-0 mt-1">
                <li><strong>Circle:</strong> Click once on the map to create a circular geofence</li>
                <li><strong>Rectangle:</strong> Click twice to create, then drag corners/handles to customize</li>
                <li><strong>Polygon:</strong> Click multiple points, then double-click or right-click to finish</li>
                <li><strong>Auto-Monitoring:</strong> Real-time vehicle tracking is always active</li>
                <li><strong>Auto-Alerts:</strong> Geofence entry/exit notifications are automatic</li>
                <li><strong>Statistics:</strong> View detailed analytics and reports</li>
              </ul>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <div class="row">
              <div class="col-md-8">
            <div id="map"></div>
                <div class="action-buttons mt-2">
              <button class="btn btn-primary btn-sm" id="saveGeofenceBtn"><i class="fas fa-save"></i> Save</button>
                  <button class="btn btn-success btn-sm" id="finalizeRectangleBtn" style="display: none;"><i class="fas fa-check"></i> Finalize Rectangle</button>
                  <button class="btn btn-warning btn-sm" id="clearDrawingBtn"><i class="fas fa-eraser"></i> Clear</button>
            </div>
          </div>
              <div class="col-md-4">
                <!-- Monitoring Status Card -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-satellite-dish"></i> MOBILE-001 Device Status</h6>
        </div>
                  <div class="card-body p-2">
                    <div class="monitoring-status mb-3">
                      <div class="d-flex align-items-center">
                        <div class="me-2">
                          <i class="fas fa-satellite-dish text-success"></i>
                        </div>
                        <div>
                          <div class="fw-bold">Tracking MOBILE-001 Device</div>
                          <small class="text-muted">Real-time GPS monitoring active</small>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span>Status:</span>
                      <span id="monitoringStatus" class="badge bg-secondary">Inactive</span>
      </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span>Device Tracked:</span>
                      <span id="vehiclesTracked" class="badge bg-info">0</span>
    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span>Active Alerts:</span>
                      <span id="activeAlerts" class="badge bg-warning">0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                      <span>Last Update:</span>
                      <small id="lastUpdate" class="text-muted">Never</small>
                    </div>
                  </div>
                </div>

                <!-- Geofences Card -->
                <div class="card">
                  <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list"></i> Geofences</h6>
                  </div>
                  <div class="card-body p-2">
                    <div id="geofenceList" style="max-height: 300px; overflow-y: auto;">
                      <div class="text-muted text-center">No geofences yet</div>
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
</div>

<!-- Save Geofence Modal -->
<div class="modal fade" id="saveGeofenceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Save Geofence</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="geofenceForm">
          <div class="mb-3">
            <label for="geofenceName" class="form-label">Geofence Name</label>
            <input type="text" class="form-control" id="geofenceName" name="name" required value="Unnamed Geofence">
          </div>
          <div class="mb-3">
            <label for="geofenceLatitude" class="form-label">Latitude</label>
            <input type="text" class="form-control" id="geofenceLatitude" readonly>
          </div>
          <div class="mb-3">
            <label for="geofenceLongitude" class="form-label">Longitude</label>
            <input type="text" class="form-control" id="geofenceLongitude" readonly>
          </div>
          <div class="mb-3">
            <label for="geofenceRadius" class="form-label">Radius (meters)</label>
            <input type="number" class="form-control" id="geofenceRadius" name="radius" required value="500">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSaveGeofence">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const map = L.map('map').setView([10.5388, 122.8388], 14);

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

  const saveGeofenceModal = new bootstrap.Modal(document.getElementById('saveGeofenceModal'));
  const geofenceForm = document.getElementById('geofenceForm');

  // Drawing tools state
  let currentTool = 'select';
  let drawingMode = false;
  let currentGeofence = null;
  let geofences = [];
  let geofenceLayers = new Map();

  // Auto-monitoring state (always active)
  let monitoringActive = true;
  let monitoringInterval = null;
  let vehicles = new Map();
  let vehicleMarkers = new Map();
  let activeAlerts = new Map();
  let alertCount = 0;
  
  // Hardcoded device ID for easy focus
  const deviceID = "MOBILE-001";

  // Drawing elements
  let tempMarker = null;
  let tempCircle = null;
  let tempRectangle = null;
  let tempPolygon = null;
  let polygonPoints = [];
  let rectangleStart = null;
  let rectangleEnd = null;
  let rectangleCorners = [];
  let rectangleHandles = [];

  // Tool buttons
  const tools = ['circleTool', 'rectangleTool', 'polygonTool', 'selectTool'];
  
  function setActiveTool(toolName) {
    currentTool = toolName;
    drawingMode = toolName !== 'select';
    
    // Update button states
    tools.forEach(tool => {
      const btn = document.getElementById(tool);
      if (tool === toolName + 'Tool') {
        btn.classList.remove('btn-outline-primary', 'btn-outline-secondary');
        btn.classList.add('btn-primary');
      } else {
        btn.classList.remove('btn-primary');
        btn.classList.add(tool === 'selectTool' ? 'btn-outline-secondary' : 'btn-outline-primary');
      }
    });

    // Clear temporary drawing
    clearTempDrawing();
    
    // Update cursor
    if (drawingMode) {
      map.getContainer().style.cursor = 'crosshair';
    } else {
      map.getContainer().style.cursor = 'grab';
    }
  }

  function clearTempDrawing() {
    if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
    if (tempCircle) { map.removeLayer(tempCircle); tempCircle = null; }
    if (tempRectangle) { map.removeLayer(tempRectangle); tempRectangle = null; }
    if (tempPolygon) { map.removeLayer(tempPolygon); tempPolygon = null; }
    polygonPoints = [];
    rectangleStart = null;
    rectangleEnd = null;
    rectangleCorners = [];
    rectangleHandles.forEach(handle => map.removeLayer(handle));
    rectangleHandles = [];
    
    // Hide finalize button
    document.getElementById('finalizeRectangleBtn').style.display = 'none';
  }

  // Tool event listeners
  document.getElementById('circleTool').addEventListener('click', () => setActiveTool('circle'));
  document.getElementById('rectangleTool').addEventListener('click', () => setActiveTool('rectangle'));
  document.getElementById('polygonTool').addEventListener('click', () => setActiveTool('polygon'));
  document.getElementById('selectTool').addEventListener('click', () => setActiveTool('select'));

  // Action button event listeners
  document.getElementById('finalizeRectangleBtn').addEventListener('click', finalizeRectangle);
  document.getElementById('clearDrawingBtn').addEventListener('click', () => {
    clearTempDrawing();
    document.getElementById('finalizeRectangleBtn').style.display = 'none';
  });

  // Auto-start monitoring on page load
  startAutoMonitoring();

  // Map click handler
  map.on('click', function(e) {
    if (!drawingMode) return;

    switch (currentTool) {
      case 'circle':
        createCircleGeofence(e.latlng);
        break;
      case 'rectangle':
        if (!rectangleStart) {
          // First click - start rectangle
          rectangleStart = e.latlng;
          tempMarker = L.marker(e.latlng, {
            icon: L.divIcon({
              className: 'rectangle-corner',
              html: '<div style="background: #28a745; width: 12px; height: 12px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
              iconSize: [18, 18]
            })
          }).addTo(map);
        } else if (!rectangleEnd) {
          // Second click - create initial rectangle
          rectangleEnd = e.latlng;
          createRectangleGeofence(rectangleStart, e.latlng);
        } else {
          // Additional clicks - add corner points for customization
          addRectangleCorner(e.latlng);
        }
        break;
      case 'polygon':
        addPolygonPoint(e.latlng);
        break;
    }
  });

  // Double-click to finish polygon
  map.on('dblclick', function(e) {
    if (currentTool === 'polygon' && polygonPoints.length >= 3) {
      finishPolygon();
    }
  });

  // Right-click to finish polygon (alternative)
  map.on('contextmenu', function(e) {
    e.originalEvent.preventDefault();
    if (currentTool === 'polygon' && polygonPoints.length >= 3) {
      finishPolygon();
    }
  });

  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && currentTool === 'rectangle' && rectangleCorners.length >= 3) {
      finalizeRectangle();
    }
  });

  function createCircleGeofence(center) {
    clearTempDrawing();
    const radius = 500; // Default radius
    tempCircle = L.circle(center, {
      color: '#003566',
      fillColor: '#00b4d8',
      fillOpacity: 0.3,
      radius: radius
    }).addTo(map);
    
    // Show save modal
    showSaveModal({
      type: 'circle',
      center: center,
      radius: radius
    });
  }

  function createRectangleGeofence(start, end) {
    // Don't clear temp drawing yet - we want to keep the corner markers
    if (tempRectangle) { map.removeLayer(tempRectangle); }
    
    const bounds = L.latLngBounds(start, end);
    tempRectangle = L.rectangle(bounds, {
      color: '#003566',
      fillColor: '#00b4d8',
      fillOpacity: 0.3,
      weight: 3
    }).addTo(map);
    
    // Store corners for customization
    rectangleCorners = [
      bounds.getNorthWest(), // Top-left
      bounds.getNorthEast(), // Top-right
      bounds.getSouthEast(), // Bottom-right
      bounds.getSouthWest()  // Bottom-left
    ];
    
    // Add corner markers for customization
    addRectangleCornerMarkers();
    
    // Add resize handles
    addRectangleHandles();
    
    // Show instructions
    showRectangleInstructions();
    
    // Show finalize button
    document.getElementById('finalizeRectangleBtn').style.display = 'inline-block';
  }

  function addRectangleCorner(latlng) {
    // Add a new corner point for custom rectangle
    rectangleCorners.push(latlng);
    
    // Update rectangle with custom corners
    updateCustomRectangle();
    
    // Add corner marker
    addCornerMarker(latlng, rectangleCorners.length - 1);
  }

  function addRectangleCornerMarkers() {
    rectangleCorners.forEach((corner, index) => {
      addCornerMarker(corner, index);
    });
  }

  function addCornerMarker(latlng, index) {
    const marker = L.marker(latlng, {
      icon: L.divIcon({
        className: 'rectangle-corner',
        html: `<div style="background: #ffc107; width: 12px; height: 12px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer;" title="Corner ${index + 1}"></div>`,
        iconSize: [18, 18]
      }),
      draggable: true
    }).addTo(map);
    
    // Store reference to marker
    rectangleHandles.push(marker);
    
    // Add drag event
    marker.on('drag', function(e) {
      rectangleCorners[index] = e.target.getLatLng();
      updateCustomRectangle();
    });
  }

  function addRectangleHandles() {
    // Add resize handles at midpoints of each side
    const bounds = L.latLngBounds(rectangleCorners);
    const center = bounds.getCenter();
    
    // Midpoints of each side
    const midpoints = [
      L.latLng(bounds.getNorth(), center.lng), // Top
      L.latLng(center.lat, bounds.getEast()),  // Right
      L.latLng(bounds.getSouth(), center.lng), // Bottom
      L.latLng(center.lat, bounds.getWest())   // Left
    ];
    
    midpoints.forEach((midpoint, index) => {
      const handle = L.marker(midpoint, {
        icon: L.divIcon({
          className: 'rectangle-handle',
          html: `<div style="background: #dc3545; width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: move;" title="Resize handle ${index + 1}"></div>`,
          iconSize: [14, 14]
        }),
        draggable: true
      }).addTo(map);
      
      rectangleHandles.push(handle);
      
      // Add drag event for resizing
      handle.on('drag', function(e) {
        const newPos = e.target.getLatLng();
        resizeRectangleFromHandle(index, newPos);
      });
    });
  }

  function resizeRectangleFromHandle(handleIndex, newPos) {
    const bounds = L.latLngBounds(rectangleCorners);
    const center = bounds.getCenter();
    
    switch(handleIndex) {
      case 0: // Top
        rectangleCorners[0] = L.latLng(newPos.lat, bounds.getWest());
        rectangleCorners[1] = L.latLng(newPos.lat, bounds.getEast());
        break;
      case 1: // Right
        rectangleCorners[1] = L.latLng(bounds.getNorth(), newPos.lng);
        rectangleCorners[2] = L.latLng(bounds.getSouth(), newPos.lng);
        break;
      case 2: // Bottom
        rectangleCorners[2] = L.latLng(newPos.lat, bounds.getEast());
        rectangleCorners[3] = L.latLng(newPos.lat, bounds.getWest());
        break;
      case 3: // Left
        rectangleCorners[0] = L.latLng(bounds.getNorth(), newPos.lng);
        rectangleCorners[3] = L.latLng(bounds.getSouth(), newPos.lng);
        break;
    }
    
    updateCustomRectangle();
  }

  function updateCustomRectangle() {
    if (tempRectangle) {
      map.removeLayer(tempRectangle);
    }
    
    // Create polygon from corners
    tempRectangle = L.polygon(rectangleCorners, {
      color: '#003566',
      fillColor: '#00b4d8',
      fillOpacity: 0.3,
      weight: 3
    }).addTo(map);
  }

  function showRectangleInstructions() {
    // Show instructions for rectangle customization
    const instructions = document.createElement('div');
    instructions.className = 'alert alert-info alert-dismissible fade show';
    instructions.innerHTML = `
      <i class="fas fa-info-circle me-2"></i>
      <strong>Rectangle Customization:</strong>
      <ul class="mb-0 mt-1">
        <li>Drag corner markers to adjust the shape</li>
        <li>Drag red handles to resize sides</li>
        <li>Click "Save" when satisfied with the shape</li>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert after the existing alert
    const existingAlert = document.querySelector('.alert-info');
    if (existingAlert) {
      existingAlert.parentNode.insertBefore(instructions, existingAlert.nextSibling);
    }
  }

  function finalizeRectangle() {
    if (rectangleCorners.length >= 3) {
      // Calculate center and dimensions
      const bounds = L.latLngBounds(rectangleCorners);
      const center = bounds.getCenter();
      const width = bounds.getEast() - bounds.getWest();
      const height = bounds.getNorth() - bounds.getSouth();
      
      showSaveModal({
        type: 'rectangle',
        bounds: bounds,
        center: center,
        width: Math.abs(width),
        height: Math.abs(height),
        corners: rectangleCorners.map(corner => [corner.lat, corner.lng])
      });
    }
  }

  function addPolygonPoint(latlng) {
    polygonPoints.push(latlng);
    
    if (tempPolygon) {
      map.removeLayer(tempPolygon);
    }
    
    if (polygonPoints.length >= 3) {
      tempPolygon = L.polygon(polygonPoints, {
        color: '#003566',
        fillColor: '#00b4d8',
        fillOpacity: 0.3
      }).addTo(map);
    }
    
    // Add point marker
    L.marker(latlng, {
      icon: L.divIcon({
        className: 'polygon-point',
        html: '<div style="background: #dc3545; width: 8px; height: 8px; border-radius: 50%; border: 2px solid white;"></div>',
        iconSize: [12, 12]
      })
    }).addTo(map);
  }

  function finishPolygon() {
    if (polygonPoints.length >= 3) {
      showSaveModal({
        type: 'polygon',
        polygon: polygonPoints.map(p => [p.lat, p.lng])
      });
    }
  }

  function showSaveModal(geofenceData) {
    currentGeofence = geofenceData;
    
    // Update modal fields based on geofence type
    if (geofenceData.type === 'circle') {
      document.getElementById('geofenceLatitude').value = geofenceData.center.lat.toFixed(6);
      document.getElementById('geofenceLongitude').value = geofenceData.center.lng.toFixed(6);
      document.getElementById('geofenceRadius').value = geofenceData.radius;
    } else if (geofenceData.type === 'rectangle') {
      const center = geofenceData.center || geofenceData.bounds.getCenter();
      document.getElementById('geofenceLatitude').value = center.lat.toFixed(6);
      document.getElementById('geofenceLongitude').value = center.lng.toFixed(6);
      document.getElementById('geofenceRadius').value = Math.round((geofenceData.width + geofenceData.height) / 2);
    } else if (geofenceData.type === 'polygon') {
      // Calculate center of polygon
      const center = calculatePolygonCenter(geofenceData.polygon);
      document.getElementById('geofenceLatitude').value = center.lat.toFixed(6);
      document.getElementById('geofenceLongitude').value = center.lng.toFixed(6);
      document.getElementById('geofenceRadius').value = calculatePolygonRadius(geofenceData.polygon, center);
    }
    
    saveGeofenceModal.show();
  }

  function calculatePolygonCenter(polygon) {
    let lat = 0, lng = 0;
    polygon.forEach(point => {
      lat += point[0];
      lng += point[1];
    });
    return L.latLng(lat / polygon.length, lng / polygon.length);
  }

  function calculatePolygonRadius(polygon, center) {
    let maxDistance = 0;
    polygon.forEach(point => {
      const distance = center.distanceTo(L.latLng(point[0], point[1]));
      if (distance > maxDistance) maxDistance = distance;
    });
    return Math.round(maxDistance);
  }

  // Save geofence
  document.getElementById('confirmSaveGeofence').addEventListener('click', async function(){
    if(!geofenceForm.checkValidity()){ geofenceForm.classList.add('was-validated'); return; }
    
    let data = {
      id: currentGeofence?.id || null,
      name: document.getElementById('geofenceName').value,
      color: '#00b4d8'
    };

    // Format data based on geofence type to match existing API
    if (currentGeofence?.type === 'circle') {
      data.latitude = parseFloat(document.getElementById('geofenceLatitude').value);
      data.longitude = parseFloat(document.getElementById('geofenceLongitude').value);
      data.radius = parseInt(document.getElementById('geofenceRadius').value);
      data.type = 'circle';
      // For circles, we don't need polygon field
    } else if (currentGeofence?.type === 'rectangle') {
      data.latitude = parseFloat(document.getElementById('geofenceLatitude').value);
      data.longitude = parseFloat(document.getElementById('geofenceLongitude').value);
      data.width = currentGeofence.width;
      data.height = currentGeofence.height;
      data.radius = parseInt(document.getElementById('geofenceRadius').value);
      data.type = 'rectangle';
      
      // Debug logging
      console.log('Current geofence object:', currentGeofence);
      console.log('Has corners:', !!currentGeofence.corners);
      console.log('Has bounds:', !!currentGeofence.bounds);
      
      // Add corners if available (for custom rectangles)
      if (currentGeofence.corners && currentGeofence.corners.length > 0) {
        data.polygon = currentGeofence.corners;
        console.log('Using corners for polygon:', data.polygon);
      } else if (currentGeofence.bounds) {
        // For regular rectangles, create a polygon from the bounds
        const bounds = currentGeofence.bounds;
        data.polygon = [
          [bounds.getNorthWest().lat, bounds.getNorthWest().lng],
          [bounds.getNorthEast().lat, bounds.getNorthEast().lng],
          [bounds.getSouthEast().lat, bounds.getSouthEast().lng],
          [bounds.getSouthWest().lat, bounds.getSouthWest().lng]
        ];
        console.log('Using bounds for polygon:', data.polygon);
      } else {
        console.error('No corners or bounds available for rectangle!');
        throw new Error('Invalid rectangle data - no corners or bounds available');
      }
    } else if (currentGeofence?.type === 'polygon') {
      data.polygon = polygonPoints.map(p => [p.lat, p.lng]);
      data.radius = parseInt(document.getElementById('geofenceRadius').value);
      data.type = 'polygon';
    }

    try{
      const resp = await fetch('geofence_api.php?action=save_geofence',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(data)
      });
      const result = await resp.json();
      if(result.success){
        saveGeofenceModal.hide();
        Swal.fire('Saved','Geofence saved successfully','success');
        
        // Reset drawing mode
        setActiveTool('select');
        clearTempDrawing();
        
        // Reload geofence list and map
        loadGeofences();
      } else {
        throw new Error(result.message || 'Failed to save geofence');
      }
    }catch(e){ 
      console.error(e); 
      Swal.fire('Error', e.message || 'Failed to save geofence', 'error'); 
    }
  });

  function addGeofenceToMap(geofence) {
    let layer;
    
    console.log('Adding geofence to map:', geofence); // Debug log
    
    if (geofence.type === 'circle') {
      layer = L.circle([geofence.latitude, geofence.longitude], {
        color: geofence.color || '#00b4d8',
        fillColor: geofence.color || '#00b4d8',
        fillOpacity: 0.3,
        radius: geofence.radius
      });
    } else if (geofence.type === 'rectangle') {
      // Check if we have polygon coordinates (custom rectangle)
      if (geofence.polygon && geofence.polygon.length >= 3) {
        layer = L.polygon(geofence.polygon, {
          color: geofence.color || '#00b4d8',
          fillColor: geofence.color || '#00b4d8',
          fillOpacity: 0.3
        });
      } else if (geofence.width && geofence.height) {
        // For regular rectangles, create bounds from center and dimensions
        const lat = parseFloat(geofence.latitude);
        const lng = parseFloat(geofence.longitude);
        const latOffset = (geofence.height / 2) / 111320; // Convert meters to degrees
        const lngOffset = (geofence.width / 2) / (111320 * Math.cos(lat * Math.PI / 180));
        
        const bounds = [
          [lat - latOffset, lng - lngOffset],
          [lat + latOffset, lng + lngOffset]
        ];
        
        layer = L.rectangle(bounds, {
          color: geofence.color || '#00b4d8',
          fillColor: geofence.color || '#00b4d8',
          fillOpacity: 0.3
        });
      }
    } else if (geofence.type === 'polygon' && geofence.polygon) {
      layer = L.polygon(geofence.polygon, {
        color: geofence.color || '#00b4d8',
        fillColor: geofence.color || '#00b4d8',
        fillOpacity: 0.3
      });
    }
    
    if (layer) {
      layer.addTo(map);
      layer.bindPopup(`<b>${geofence.name}</b><br>Type: ${geofence.type}<br>Radius: ${geofence.radius}m`);
      geofenceLayers.set(geofence.id, layer);
      console.log('Geofence layer added successfully:', geofence.id);
    } else {
      console.error('Failed to create layer for geofence:', geofence);
    }
  }

  // Clear drawing button
  document.getElementById('clearDrawingBtn').addEventListener('click', function() {
    clearTempDrawing();
    setActiveTool('select');
  });

  function updateGeofenceList() {
    const listContainer = document.getElementById('geofenceList');
    
    if (geofences.length === 0) {
      listContainer.innerHTML = '<div class="text-muted text-center">No geofences yet</div>';
      return;
    }
    
    listContainer.innerHTML = geofences.map(geofence => `
      <div class="d-flex justify-content-between align-items-center p-2 border-bottom" 
           onclick="selectGeofence(${geofence.id})" 
           style="cursor: pointer; ${currentGeofence?.id === geofence.id ? 'background-color: #e3f2fd;' : ''}">
        <div>
          <div class="fw-bold">${geofence.name}</div>
          <small class="text-muted">${geofence.type} • ${geofence.radius}m</small>
        </div>
      </div>
    `).join('');
  }

  async function loadGeofences(){
    try{
      console.log('Loading geofences...');
      const resp = await fetch('geofence_api.php?action=get_geofences');
      const {success,data} = await resp.json();
      console.log('Geofences response:', {success, data});
      
      if(success){
        // Process geofence data
        const processedGeofences = data.map(geofence => {
          // Ensure polygon is properly decoded
          if (typeof geofence.polygon === 'string') {
            try {
              geofence.polygon = JSON.parse(geofence.polygon);
            } catch (e) {
              console.error('Failed to parse polygon:', e);
              geofence.polygon = null;
            }
          }
          return geofence;
        });
        
        geofences = processedGeofences;
        console.log('Loaded geofences:', geofences);
        updateGeofenceList();
        
        // Clear existing layers
        geofenceLayers.forEach(layer => map.removeLayer(layer));
        geofenceLayers.clear();
        
        console.log('Adding', processedGeofences.length, 'geofences to map');
        // Add all geofences to map
        processedGeofences.forEach(geofence => {
          addGeofenceToMap(geofence);
        });
      }
    }catch(e){ 
      console.error('Error loading geofences:', e); 
    }
  }

  // Global functions for geofence list
  window.selectGeofence = function(id) {
    const geofence = geofences.find(g => g.id == id);
    if (geofence) {
      currentGeofence = geofence;
      updateGeofenceList();
      
      // Pan to geofence
      const layer = geofenceLayers.get(geofence.id);
      if (layer) {
        map.fitBounds(layer.getBounds());
        layer.openPopup();
      }
    }
  };

  // Initialize
  setActiveTool('select');
  loadGeofences();

  // ===== MONITORING FUNCTIONS =====
  
  function startAutoMonitoring() {
    const status = document.getElementById('monitoringStatus');
    status.textContent = 'Auto-Active';
    status.className = 'badge bg-success';
    startMonitoring();
  }

  function startMonitoring() {
    // Clear any existing interval
    if (monitoringInterval) {
      clearInterval(monitoringInterval);
    }
    
    // Start monitoring every 10 seconds
    monitoringInterval = setInterval(async () => {
      await updateVehicleLocations();
      checkGeofenceStatus();
      updateMonitoringUI();
    }, 10000);
    
    // Initial update
    updateVehicleLocations();
  }

  function stopMonitoring() {
    if (monitoringInterval) {
      clearInterval(monitoringInterval);
      monitoringInterval = null;
    }
    
    // Clear vehicle markers
    vehicleMarkers.forEach(marker => map.removeLayer(marker));
    vehicleMarkers.clear();
    vehicles.clear();
    
    updateMonitoringUI();
  }

  async function updateVehicleLocations() {
    try {
      console.log('Fetching vehicle location for device:', deviceID);
      const response = await fetch(`../get_latest_location.php?device_id=${deviceID}`);
      const data = await response.json();
      
      console.log('Vehicle location response:', data);
      
      if (data.lat && data.lng) {
        console.log(`Found location for ${deviceID}:`, data);
        
        // Clear old vehicles
        vehicles.clear();
        vehicleMarkers.forEach(marker => {
          map.removeLayer(marker);
        });
        vehicleMarkers.clear();
        
        // Create vehicle object in the expected format
        const vehicle = {
          vehicle_id: null,
          vehicle_name: 'MOBILE-001 Device',
          plate_number: 'N/A',
          device_id: deviceID,
          driver_name: 'Unassigned',
          driver_phone: 'N/A',
          latitude: parseFloat(data.lat),
          longitude: parseFloat(data.lng),
          speed: parseFloat(data.speed || 0),
          timestamp: data.last_update || new Date().toISOString(),
          last_update: data.last_update || new Date().toISOString()
        };
        
        // Update vehicle marker
        vehicles.set(vehicle.device_id, vehicle);
        updateVehicleMarker(vehicle);
        
        // Update monitoring UI
        updateMonitoringUI(1);
        
        console.log(`Updated ${deviceID} location`);
      } else {
        console.log('No location data found for device:', deviceID);
        updateMonitoringUI(0);
      }
    } catch (error) {
      console.error('Error updating vehicle locations:', error);
      updateMonitoringUI(0);
    }
  }

  function updateVehicleMarker(vehicle) {
    const deviceId = vehicle.device_id;
    console.log('Creating marker for vehicle:', vehicle);
    console.log('Coordinates:', vehicle.latitude, vehicle.longitude);
    
    // Remove existing marker
    if (vehicleMarkers.has(deviceId)) {
      console.log('Removing existing marker for:', deviceId);
      map.removeLayer(vehicleMarkers.get(deviceId));
    }
    
    // Create new marker
    const marker = L.marker([vehicle.latitude, vehicle.longitude], {
      icon: L.divIcon({
        className: 'vehicle-marker',
        html: `<div style="background: #28a745; width: 14px; height: 14px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);" title="${vehicle.vehicle_name || 'Vehicle'}"></div>`,
        iconSize: [20, 20]
      })
    }).addTo(map);
    
    console.log('Marker created and added to map');
    
    // Format timestamp
    const timestamp = vehicle.timestamp ? new Date(vehicle.timestamp).toLocaleString() : 'Unknown';
    
    marker.bindPopup(`
      <div style="min-width: 200px;">
        <b>🚗 ${vehicle.vehicle_name || 'Unknown Vehicle'}</b><br>
        <b>Plate:</b> ${vehicle.plate_number || 'N/A'}<br>
        <b>Driver:</b> ${vehicle.driver_name || 'Unassigned'}<br>
        <b>Speed:</b> ${vehicle.speed || '0'} km/h<br>
        <b>Device ID:</b> ${vehicle.device_id}<br>
        <b>Last Update:</b> ${timestamp}<br>
        <small class="text-muted">ESP32 GPS Device</small>
      </div>
    `);
    
    vehicleMarkers.set(deviceId, marker);
    console.log('Vehicle marker stored:', deviceId);
  }

  function checkGeofenceStatus() {
    console.log('Checking geofence status for', vehicles.size, 'vehicles and', geofences.length, 'geofences');
    vehicles.forEach((vehicle, deviceId) => {
      console.log('Checking vehicle:', deviceId, 'at', vehicle.latitude, vehicle.longitude);
      geofences.forEach(geofence => {
        const isInside = isVehicleInGeofence(vehicle, geofence);
        const alertKey = `${deviceId}-${geofence.id}`;
        console.log('Geofence', geofence.id, geofence.name, 'type:', geofence.type, 'isInside:', isInside);
        
        if (isInside && !activeAlerts.has(alertKey)) {
          // Vehicle entered geofence
          activeAlerts.set(alertKey, {
            type: 'entry',
            vehicle: vehicle,
            geofence: geofence,
            timestamp: new Date()
          });
          alertCount++;
          showGeofenceAlert('entry', vehicle, geofence);
          
        } else if (!isInside && activeAlerts.has(alertKey)) {
          // Vehicle exited geofence
          activeAlerts.set(alertKey, {
            type: 'exit',
            vehicle: vehicle,
            geofence: geofence,
            timestamp: new Date()
          });
          showGeofenceAlert('exit', vehicle, geofence);
        }
      });
    });
  }

  function isVehicleInGeofence(vehicle, geofence) {
    const vehicleLat = parseFloat(vehicle.latitude);
    const vehicleLng = parseFloat(vehicle.longitude);
    
    console.log('Checking geofence detection:', {
      vehicle: { lat: vehicleLat, lng: vehicleLng },
      geofence: { id: geofence.id, type: geofence.type, polygon: geofence.polygon }
    });
    
    if (geofence.type === 'circle') {
      const distance = L.latLng(vehicleLat, vehicleLng).distanceTo(
        L.latLng(geofence.latitude, geofence.longitude)
      );
      return distance <= geofence.radius;
      
    } else if ((geofence.type === 'polygon' || geofence.type === 'rectangle') && geofence.polygon) {
      console.log('Using polygon detection for geofence', geofence.id, 'polygon:', geofence.polygon);
      const result = isPointInPolygon([vehicleLat, vehicleLng], geofence.polygon);
      console.log('Polygon detection result:', result);
      return result;
      
    } else if (geofence.type === 'rectangle') {
      // Calculate rectangle bounds and check if point is inside
      const lat = parseFloat(geofence.latitude);
      const lng = parseFloat(geofence.longitude);
      const latOffset = (geofence.height / 2) / 111320;
      const lngOffset = (geofence.width / 2) / (111320 * Math.cos(lat * Math.PI / 180));
      
      return vehicleLat >= lat - latOffset && vehicleLat <= lat + latOffset &&
             vehicleLng >= lng - lngOffset && vehicleLng <= lng + lngOffset;
    }
    
    return false;
  }

  function isPointInPolygon(point, polygon) {
    const x = point[0], y = point[1];
    let inside = false;
    
    console.log('Point in polygon check:', { point, polygon });
    
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
      const xi = polygon[i][0], yi = polygon[i][1];
      const xj = polygon[j][0], yj = polygon[j][1];
      
      if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
        inside = !inside;
      }
    }
    
    console.log('Point in polygon result:', inside);
    return inside;
  }

  function showGeofenceAlert(type, vehicle, geofence) {
    // Auto-alerts are always enabled
    
    const isExit = type !== 'entry';
    const bg = isExit ? '#f8d7da' : '#d1e7dd';
    const border = isExit ? '#f5c2c7' : '#badbcc';
    const color = isExit ? '#842029' : '#0f5132';
    const title = isExit ? 'Vehicle went out of its designated area' : 'Vehicle returned to its designated area';
    
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.style.background = bg;
        toast.style.border = `1px solid ${border}`;
        toast.style.color = color;
        toast.style.boxShadow = '0 6px 16px rgba(0,0,0,0.15)';
      }
    });
    
    Toast.fire({
      title: title,
      html: `
        <div style="text-align:left;">
          <div><b>Vehicle:</b> ${vehicle.vehicle_name || 'Unknown'}</div>
          <div><b>Driver:</b> ${vehicle.driver_name || 'Unknown'}</div>
          <div><b>Geofence:</b> ${geofence.name}</div>
          <div><b>Time:</b> ${new Date().toLocaleTimeString()}</div>
        </div>
      `
    });
    
    // Send server-side alert
    sendGeofenceAlert(type, vehicle, geofence);
  }

  async function sendGeofenceAlert(type, vehicle, geofence) {
    try {
      const response = await fetch('../geofence_alert_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_geofence_alert',
          device_id: vehicle.device_id,
          geofence_id: geofence.id,
          event_type: type,
          vehicle_data: vehicle
        })
      });
      
      const result = await response.json();
      if (!result.success) {
        console.error('Failed to send geofence alert:', result.error);
      }
    } catch (error) {
      console.error('Error sending geofence alert:', error);
    }
  }

  function updateMonitoringUI(vehicleCount = null) {
    const count = vehicleCount !== null ? vehicleCount : vehicles.size;
    document.getElementById('vehiclesTracked').textContent = count;
    document.getElementById('activeAlerts').textContent = alertCount;
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
    
    // Update the monitoring status text to show MOBILE-001 focus
    const statusElement = document.querySelector('.monitoring-status');
    if (statusElement) {
      statusElement.innerHTML = `
        <div class="d-flex align-items-center">
          <div class="me-2">
            <i class="fas fa-satellite-dish text-success"></i>
          </div>
          <div>
            <div class="fw-bold">Tracking MOBILE-001 Device</div>
            <small class="text-muted">Real-time GPS monitoring active</small>
          </div>
        </div>
      `;
    }
  }
});
</script>
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
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
  });
</script>
</body>
</html>
