<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

$driverId = $_SESSION['user_id'];

// Get driver's assigned route (latest one) - using routes table
$routeQuery = $conn->prepare("
    SELECT r.*, gd.device_id, r.route_type
    FROM routes r
    LEFT JOIN vehicle_assignments va ON r.driver_id = va.driver_id AND va.status = 'active'
    LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id
    LEFT JOIN gps_devices gd ON fv.id = gd.vehicle_id
    WHERE r.driver_id = ? AND r.status = 'active'
    ORDER BY r.created_at DESC
    LIMIT 1
");
$routeQuery->bind_param("i", $driverId);
$routeQuery->execute();
$route = $routeQuery->get_result()->fetch_assoc();
$routeQuery->close();

// Debug: Check if route was found
if (!$route) {
    error_log("No route found for driver ID: " . $driverId);
    
    // Debug: Check what routes exist for this driver
    $debugQuery = $conn->prepare("SELECT * FROM routes WHERE driver_id = ? ORDER BY created_at DESC");
    $debugQuery->bind_param("i", $driverId);
    $debugQuery->execute();
    $debugResult = $debugQuery->get_result();
    $debugRoutes = $debugResult->fetch_all(MYSQLI_ASSOC);
    $debugQuery->close();
    
    error_log("Debug - Routes for driver $driverId: " . json_encode($debugRoutes));
    
    // Also check if driver has any vehicle assignments
    $assignmentQuery = $conn->prepare("SELECT va.*, fv.unit, fv.article FROM vehicle_assignments va LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id WHERE va.driver_id = ? AND va.status = 'active'");
    $assignmentQuery->bind_param("i", $driverId);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignments = $assignmentResult->fetch_all(MYSQLI_ASSOC);
    $assignmentQuery->close();
    
    error_log("Debug - Vehicle assignments for driver $driverId: " . json_encode($assignments));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Route | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
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
     
    #map {
      width: 100%;
       height: 70vh;
      background-color: #e9ecef;
      border-radius: 0.5rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

     /* Mobile-first responsive design */
     .main-content {
       margin-left: 250px;
       margin-top: 60px;
       padding: 10px;
       transition: margin-left 0.3s ease;
       min-height: 100vh;
       display: flex;
       flex-direction: column;
     }

     .main-content.collapsed {
       margin-left: 70px;
     }

     /* Header for mobile - hidden since we have navbar */
     .mobile-header {
       display: none;
     }

     .mobile-header h2 {
       margin: 0;
       font-size: 1.2rem;
       font-weight: 600;
     }

     /* Map container */
     .map-container {
       flex: 1;
       margin: 10px 0;
       position: relative;
     }

     /* Route info panel - mobile optimized */
     .route-info-mobile {
       background: white;
       border-radius: 15px 15px 0 0;
       box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
       position: relative;
       z-index: 10;
       overflow: hidden;
       padding: 20px;
       margin-top: -10px;
       position: relative;
       z-index: 100;
     }

     .route-info-mobile::before {
       content: '';
       position: absolute;
       top: 8px;
       left: 50%;
       transform: translateX(-50%);
       width: 40px;
       height: 4px;
       background: #ddd;
       border-radius: 2px;
     }

     .route-stats {
       display: grid;
       grid-template-columns: 1fr 1fr;
       gap: 15px;
       margin-bottom: 20px;
       width: 100%;
       position: relative;
       z-index: 1;
       box-sizing: border-box;
     }

     .route-stat {
       text-align: center;
       padding: 15px;
       background: #f8f9fa;
       border-radius: 10px;
       border-left: 4px solid var(--accent);
       position: relative;
       z-index: 1;
       min-width: 0;
       overflow: hidden;
       box-sizing: border-box;
     }

     .route-stat .label {
       font-size: 0.8rem;
       color: #666;
       margin-bottom: 5px;
       font-weight: 500;
     }

     .route-stat .value {
       font-size: 1.1rem;
       font-weight: 600;
       color: var(--primary);
     }

     .next-turn-section {
       background: var(--primary);
       color: white;
       padding: 15px;
       border-radius: 10px;
       text-align: center;
       margin-bottom: 15px;
     }

     .next-turn-section .label {
       font-size: 0.8rem;
       opacity: 0.8;
       margin-bottom: 5px;
     }

     .next-turn-section .instruction {
       font-size: 1rem;
       font-weight: 600;
     }

     .route-actions {
       display: flex;
       gap: 10px;
       justify-content: center;
     }

     .btn-mobile {
       flex: 1;
       padding: 12px;
       border: none;
       border-radius: 8px;
       font-size: 0.9rem;
       font-weight: 500;
       cursor: pointer;
       transition: all 0.2s ease;
     }

     .btn-primary-mobile {
       background: var(--accent);
       color: white;
     }

     .btn-primary-mobile:hover {
       background: #0099cc;
       transform: translateY(-1px);
     }

     .btn-outline-mobile {
       background: transparent;
       color: var(--primary);
       border: 2px solid var(--primary);
     }

     .btn-outline-mobile:hover {
       background: var(--primary);
       color: white;
     }

    /* Voice controls removed */

     /* No route message */
     .no-route-mobile {
       display: flex;
       flex-direction: column;
       align-items: center;
       justify-content: center;
       height: 70vh;
       text-align: center;
       padding: 20px;
     }

     /* Sidebar - visible on all screens */
     .sidebar {
       position: fixed;
       top: 0;
       left: 0;
       width: 250px;
       height: 100vh;
       background-color: var(--primary);
       color: #fff;
       transition: all 0.3s ease;
       z-index: 1050;
       padding-top: 60px;
       overflow-y: auto;
       display: block;
     }

     .sidebar a {
       display: block;
       padding: 14px 20px;
       color: #d9d9d9;
       text-decoration: none;
       transition: background 0.2s;
       white-space: nowrap;
     }

     .sidebar a:hover, .sidebar a.active {
       background-color: #001d3d;
       color: var(--accent);
     }

     .sidebar.collapsed {
       width: 70px;
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
       display: block;
     }

     .burger-btn {
       font-size: 1.5rem;
       background: none;
       border: none;
       color: var(--primary);
       margin-right: 1rem;
     }

     .no-route-mobile i {
       font-size: 4rem;
       color: #ccc;
       margin-bottom: 20px;
     }

     .no-route-mobile h3 {
       color: #666;
       margin-bottom: 10px;
     }

     .no-route-mobile p {
       color: #999;
       font-size: 0.9rem;
     }

           /* Tablet styles (768px - 1023px) */
      @media (min-width: 768px) and (max-width: 1023px) {
        .main-content {
          margin-left: 0;
          margin-top: 0;
          padding: 15px;
        }

        #map {
          height: 65vh;
        }

        .mobile-header {
          display: block;
          padding: 12px;
        }

        .mobile-header h2 {
          font-size: 1.1rem;
        }

        .route-info-mobile {
          border-radius: 12px 12px 0 0;
          margin-top: -8px;
          padding: 18px;
        }

        .route-stats {
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
        }

        .route-stat {
          padding: 12px;
        }

        .route-stat .value {
          font-size: 1rem;
        }

        /* voice controls removed */

        .main-content {
          margin-left: 0;
          padding: 10px;
        }
        
        .sidebar {
          transform: translateX(-100%);
          transition: transform 0.3s ease;
        }
        
        .sidebar.show {
          transform: translateX(0);
        }
      }

             /* Laptop styles (1024px - 1439px) */
       @media (min-width: 1024px) and (max-width: 1439px) {
         .main-content {
           margin-left: 250px;
           margin-top: 60px;
           padding: 20px;
           min-height: calc(100vh - 60px);
         }

         #map {
           height: 65vh;
           border-radius: 0.5rem;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

         .mobile-header {
           display: none;
         }

         .route-info-mobile {
           border-radius: 0.5rem;
           margin-top: 20px;
           padding: 25px;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

                   .route-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
          }

         .route-stat {
           padding: 18px;
         }

         .route-stat .value {
           font-size: 1.2rem;
         }

        /* voice controls removed */
       }

             /* Desktop styles (1440px and above) */
       @media (min-width: 1440px) {
         .main-content {
           margin-left: 250px;
           margin-top: 60px;
           padding: 30px;
           max-width: 1400px;
           margin-left: calc(250px + (100vw - 1400px) / 2);
           min-height: calc(100vh - 60px);
         }

         #map {
           height: 70vh;
           border-radius: 0.5rem;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

         .mobile-header {
           display: none;
         }

         .route-info-mobile {
           border-radius: 0.5rem;
           margin-top: 25px;
           padding: 30px;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

                   .route-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
          }

         .route-stat {
           padding: 20px;
         }

         .route-stat .value {
           font-size: 1.3rem;
         }

         .next-turn-section {
           padding: 20px;
         }

         .next-turn-section .instruction {
           font-size: 1.1rem;
         }

        /* voice controls removed */
       }

             /* Large desktop styles (1920px and above) */
       @media (min-width: 1920px) {
         .main-content {
           max-width: 1600px;
           margin-left: calc(250px + (100vw - 1600px) / 2);
           padding: 40px;
           min-height: calc(100vh - 60px);
         }

         #map {
           height: 75vh;
           border-radius: 0.5rem;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

         .route-info-mobile {
           padding: 35px;
           box-shadow: 0 4px 8px rgba(0,0,0,0.1);
         }

         .route-stats {
           gap: 30px;
         }

         .route-stat {
           padding: 25px;
         }

         .route-stat .value {
           font-size: 1.4rem;
         }

         .next-turn-section {
           padding: 25px;
         }

         .next-turn-section .instruction {
           font-size: 1.2rem;
         }

       }

    /* Vehicle marker styling */
    .vehicle-marker {
      background: transparent !important;
      border: none !important;
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

    /* Enhanced Layer Control Styling - Compact */
    .leaflet-control-layers {
      border-radius: 8px !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
      border: 1px solid rgba(0, 0, 0, 0.1) !important;
      background: white !important;
      padding: 10px !important;
      font-family: 'Segoe UI', sans-serif !important;
    }

    .leaflet-control-layers-toggle {
      width: 36px !important;
      height: 36px !important;
      background-color: white !important;
      background-image: none !important;
      border-radius: 6px !important;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15) !important;
      border: 1px solid rgba(0, 0, 0, 0.1) !important;
      transition: all 0.2s ease !important;
    }

    .leaflet-control-layers-toggle:hover {
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2) !important;
      transform: scale(1.05) !important;
    }

    .leaflet-control-layers-toggle::before {
      content: '\f5fd';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 16px;
      color: var(--primary);
    }

    .leaflet-control-layers-expanded {
      padding: 10px !important;
      min-width: 180px !important;
      max-width: 200px !important;
    }

    .leaflet-control-layers-base {
      margin-bottom: 0 !important;
    }

    .leaflet-control-layers-base label {
      display: flex !important;
      align-items: center !important;
      padding: 8px 10px !important;
      margin: 3px 0 !important;
      border-radius: 6px !important;
      transition: all 0.2s ease !important;
      cursor: pointer !important;
      font-size: 13px !important;
      font-weight: 500 !important;
      color: #333 !important;
      background: #f8f9fa !important;
      border: 2px solid transparent !important;
    }

    .leaflet-control-layers-base label:hover {
      background: #e9ecef !important;
      border-color: var(--accent) !important;
      transform: translateX(2px) !important;
    }

    .leaflet-control-layers-base label input[type="radio"] {
      margin-right: 8px !important;
      width: 16px !important;
      height: 16px !important;
      cursor: pointer !important;
      accent-color: var(--accent) !important;
    }

    .leaflet-control-layers-base label input[type="radio"]:checked + span {
      color: var(--primary) !important;
      font-weight: 600 !important;
    }

    .leaflet-control-layers-base label span {
      display: flex !important;
      align-items: center !important;
      gap: 6px !important;
    }

    .leaflet-control-layers-base label i {
      font-size: 14px !important;
      color: var(--accent) !important;
      min-width: 18px !important;
    }

    /* Mobile responsive layer control - More compact */
    @media (max-width: 768px) {
      .leaflet-control-layers {
        padding: 8px !important;
        max-width: 160px !important;
      }

      .leaflet-control-layers-expanded {
        min-width: 160px !important;
        max-width: 160px !important;
      }

      .leaflet-control-layers-base label {
        padding: 6px 8px !important;
        font-size: 12px !important;
        margin: 2px 0 !important;
      }

      .leaflet-control-layers-base label input[type="radio"] {
        width: 14px !important;
        height: 14px !important;
        margin-right: 6px !important;
      }

      .leaflet-control-layers-base label i {
        font-size: 12px !important;
        min-width: 16px !important;
      }

      .leaflet-control-layers-base label span {
        gap: 4px !important;
      }

      .leaflet-control-layers-toggle {
        width: 32px !important;
        height: 32px !important;
      }

      .leaflet-control-layers-toggle::before {
        font-size: 14px !important;
      }
    }

    /* Layer control header styling */
    .leaflet-control-layers-separator {
      display: none !important;
    }

  </style>
</head>
<body>

 <!-- Mobile Header -->
 <div class="mobile-header">
   <h2><i class="fas fa-route"></i> My Route</h2>
 </div>

 <!-- Desktop Sidebar and Navbar -->
<?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <?php if (!$route): ?>
     <div class="no-route-mobile">
       <i class="fas fa-map-marked-alt"></i>
       <h3>No Route Assigned</h3>
       <p>You don't have any active routes at the moment.</p>
       <p class="text-muted small">Driver ID: <?= $driverId ?></p>
       <?php if (isset($debugRoutes) && count($debugRoutes) > 0): ?>
         <p class="text-info small">Found <?= count($debugRoutes) ?> route(s) but none are active.</p>
       <?php endif; ?>
       <?php if (isset($assignments) && count($assignments) > 0): ?>
         <p class="text-success small">You have <?= count($assignments) ?> active vehicle assignment(s).</p>
       <?php endif; ?>
      </div>
    <?php else: ?>
     <!-- Map Container -->
     <div class="map-container">
      <div id="map"></div>
     </div>
     
           <!-- Mobile Route Info Panel -->
      <div class="route-info-mobile">
        <div class="route-stats">
          <div class="route-stat">
            <div class="label">Distance</div>
            <div class="value" id="routeDistance">Loading...</div>
          </div>
          <div class="route-stat">
            <div class="label">Time</div>
            <div class="value" id="routeTime">Loading...</div>
          </div>
        </div>
        
        <?php if (isset($route['route_type']) && $route['route_type'] === 'round_trip'): ?>
        <div class="alert alert-info text-center mb-3" style="padding: 10px; border-radius: 8px; background: #e3f2fd; border: 1px solid #2196f3;">
          <i class="fas fa-sync-alt me-2"></i>
          <strong>Round Trip Route</strong> - You will return to the starting point after reaching the destination
        </div>
        <?php endif; ?>
        
        <div class="next-turn-section">
          <div class="label">Next Turn</div>
          <div class="instruction" id="nextTurn">Head northeast</div>
        </div>
        
        <div class="route-actions">
          <button class="btn-mobile btn-outline-mobile" onclick="toggleRouteInfo()">
            <i class="fas fa-info-circle"></i> Details
          </button>
          <button class="btn-mobile btn-primary-mobile" onclick="speakCurrentInstruction()">
            <i class="fas fa-volume-up"></i> Repeat
          </button>
        </div>
      </div>
    <?php endif; ?>
   
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script>
// Make map accessible to helper functions
let map = null;
// Destination detection state must be initialized BEFORE any calls
let tripCompleted = false;
const DESTINATION_RADIUS = 30; // meters - stricter to avoid premature completion
let destinationProximityHits = 0;
const REQUIRED_PROXIMITY_HITS = 3; // must be inside radius for 3 checks
// Post-completion monitoring
let postCompletionMonitoringEnabled = false;
let lastPostCompletionAlert = 0;
let completionLocation = null;
document.addEventListener("DOMContentLoaded", function () {
  <?php if ($route): ?>
    let startLat = parseFloat("<?= $route['start_lat'] ?>");
    let startLng = parseFloat("<?= $route['start_lng'] ?>");
    let endLat = parseFloat("<?= $route['end_lat'] ?>");
    let endLng = parseFloat("<?= $route['end_lng'] ?>");
    let deviceId = "<?= $route['device_id'] ?>";

    if (!isNaN(startLat) && !isNaN(startLng) && !isNaN(endLat) && !isNaN(endLng)) {
      // Calculate center point between start and end
      const centerLat = (startLat + endLat) / 2;
      const centerLng = (startLng + endLng) / 2;
      
      map = L.map('map', {
        maxZoom: 20,
        zoomControl: true
      }).setView([centerLat, centerLng], 12);
      
      // Define base layers with reliable tile sources and error handling
      const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors',
        errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
      });
      
      const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 20,
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics',
        errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
      });
      
      // Use a combination of satellite imagery with road overlay for hybrid
      const hybridLayer = L.layerGroup([
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
          maxZoom: 20,
          attribution: 'Tiles &copy; Esri',
          errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
        }),
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
          maxZoom: 20,
          attribution: 'Tiles &copy; Esri',
          errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
        })
      ]);
      
      // Add default layer (street view)
      streetLayer.addTo(map);
      
      // Create base layer object for layer control
      const baseLayers = {
        '<i class="fas fa-map me-2"></i> Street Map': streetLayer,
        '<i class="fas fa-satellite me-2"></i> Satellite': satelliteLayer,
        '<i class="fas fa-layer-group me-2"></i> Hybrid': hybridLayer
      };
      
      // Add layer control to map (collapsed to minimize map coverage)
      L.control.layers(baseLayers, null, {
        position: 'topright',
        collapsed: true
      }).addTo(map);

      // Create routing control but hide the panel
      const routingControl = L.Routing.control({
        waypoints: [
          L.latLng(startLat, startLng),
          L.latLng(endLat, endLng)
        ],
        routeWhileDragging: false,
        addWaypoints: false,
        draggableWaypoints: false,
        showAlternatives: false,
        fitSelectedRoutes: false,
        lineOptions: {
          styles: [{ color: '#00b4d8', weight: 4, opacity: 0.8 }]
        }
      }).addTo(map);
      
      // Hide the routing panel but keep the route line
      setTimeout(() => {
        const routingPanel = document.querySelector('.leaflet-routing-container');
        if (routingPanel) {
          routingPanel.style.display = 'none';
        }
      }, 100);
      
             // Update route information when routing is calculated
       routingControl.on('routesfound', function(e) {
         const routes = e.routes;
         if (routes && routes.length > 0) {
           const route = routes[0];
           let distance = (route.summary.totalDistance / 1000).toFixed(1);
           let time = Math.round(route.summary.totalTime / 60);
           
           // Check if this is a round trip route
           const isRoundTrip = <?= isset($route['route_type']) && $route['route_type'] === 'round_trip' ? 'true' : 'false' ?>;
           
           if (isRoundTrip) {
             // Double the distance and time for round trip
             distance = (parseFloat(distance) * 2).toFixed(1);
             time = time * 2;
             
             // Update the display to show round trip info
             document.getElementById('routeDistance').textContent = distance + ' km (Round Trip)';
             document.getElementById('routeTime').textContent = time + ' min (Round Trip)';
           } else {
             document.getElementById('routeDistance').textContent = distance + ' km';
             document.getElementById('routeTime').textContent = time + ' min';
           }
           
           // Store route instructions for voice navigation
           routeInstructions = route.instructions.map(instruction => ({
             text: instruction.text,
             distance: instruction.distance ? `${instruction.distance} meters` : null
           }));
           currentInstructionIndex = 0;
           
           // Get first instruction for next turn
           if (route.instructions && route.instructions.length > 0) {
             const firstInstruction = route.instructions[0];
             document.getElementById('nextTurn').textContent = firstInstruction.text;
           }
           
           // Store route polyline for deviation detection
           if (route.coordinates && route.coordinates.length > 0) {
             routePolyline = L.polyline(route.coordinates, {
               color: '#00b4d8',
               weight: 3,
               opacity: 0.7
             }).addTo(map);
           }
         }
       });

      // Vehicle marker (current location) - green pulsing, will be updated with live GPS
      let vehicleMarker = L.marker([startLat, startLng], {
        icon: L.divIcon({
          className: 'vehicle-marker',
          html: `<div style="background: #28a745; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4); animation: pulse 2s infinite;" title="Vehicle Location"></div>`,
          iconSize: [26, 26]
        })
      }).addTo(map).bindPopup("<b>Vehicle Location</b>");
      
      console.log('Vehicle marker created at:', startLat, startLng);
      
      // End marker (destination) - regular pin
      L.marker([endLat, endLng]).addTo(map).bindPopup("<b>Destination</b>");
      
      // Load and display all geofences after map is fully initialized
      setTimeout(() => {
        loadGeofences();
        loadVehiclesWithGPS();
      }, 500);

      // Fit map to show both points
      const bounds = L.latLngBounds([[startLat, startLng], [endLat, endLng]]);
      map.fitBounds(bounds, { padding: [20, 20] });

      // Vehicle circle removed - using green pulsing markers for vehicles with GPS instead

                       // Route deviation detection variables
         let lastDeviationAlert = 0;
         let isOnRoute = true;
         let routePolyline = null;
        let deviationThreshold = 200; // meters from route - increased for GPS accuracy
        let esp32Online = false; // Track ESP32 online status
        let deviationDetectionEnabled = true; // Route deviation detection enabled

               // Function to check if vehicle is on the designated route
        function checkRouteDeviation(vehicleLat, vehicleLng, routeCoordinates) {
          if (!routeCoordinates || routeCoordinates.length < 2) return true;
          
          let minDistance = Infinity;
          
          // Find the closest point on the route to the vehicle
          for (let i = 0; i < routeCoordinates.length - 1; i++) {
            const routePoint1 = routeCoordinates[i];
            const routePoint2 = routeCoordinates[i + 1];
            
            // Calculate distance from vehicle to line segment
            const distance = distanceToLineSegment(
              vehicleLat, vehicleLng,
              routePoint1.lat, routePoint1.lng,
              routePoint2.lat, routePoint2.lng
            );
            
            if (distance < minDistance) {
              minDistance = distance;
            }
          }
          
          // Debug: Log the distance for troubleshooting
          console.log(`Vehicle distance from route: ${minDistance.toFixed(1)} meters (threshold: ${deviationThreshold}m)`);
          
          return minDistance <= deviationThreshold;
        }

       // Function to calculate distance from point to line segment
       function distanceToLineSegment(px, py, x1, y1, x2, y2) {
         const A = px - x1;
         const B = py - y1;
         const C = x2 - x1;
         const D = y2 - y1;

         const dot = A * C + B * D;
         const lenSq = C * C + D * D;
         let param = -1;

         if (lenSq !== 0) param = dot / lenSq;

         let xx, yy;

         if (param < 0) {
           xx = x1;
           yy = y1;
         } else if (param > 1) {
           xx = x2;
           yy = y2;
         } else {
           xx = x1 + param * C;
           yy = y1 + param * D;
         }

         const dx = px - xx;
         const dy = py - yy;

         return Math.sqrt(dx * dx + dy * dy) * 111000; // Convert to meters (roughly)
       }

                       // Function to alert driver about route deviation
         async function alertRouteDeviation() {
           // Don't alert if ESP32 is offline
           if (!esp32Online) {
             console.log('Skipping deviation alert - ESP32 is offline');
             return;
           }
           
           const now = Date.now();
           // Only alert once every 60 seconds to avoid spam
           if (now - lastDeviationAlert > 60000) {
             lastDeviationAlert = now;
             
             // Visual alert - vehicle circle removed
             
             // Audio alert
             speak('Warning! Return to designated route immediately!');
             
             // Show visual notification
             showDeviationAlert();
             
             // Alert dispatcher about route deviation during active trip
             try {
               const gpsRes = await fetch(`../get_latest_location.php?device_id=${deviceId}`);
               const gpsData = await gpsRes.json();
               if (gpsData.lat && gpsData.lng) {
                 alertDispatcherRouteDeviation(parseFloat(gpsData.lat), parseFloat(gpsData.lng));
               }
             } catch (error) {
               console.error('Error alerting dispatcher about deviation:', error);
             }
             
             console.log('Route deviation detected!');
           }
         }

       // Function to show visual deviation alert
       function showDeviationAlert() {
         // Create or update deviation alert element
         let alertElement = document.getElementById('deviationAlert');
         if (!alertElement) {
           alertElement = document.createElement('div');
           alertElement.id = 'deviationAlert';
           alertElement.style.cssText = `
             position: fixed;
             top: 50%;
             left: 50%;
             transform: translate(-50%, -50%);
             background: #dc3545;
             color: white;
             padding: 20px;
             border-radius: 10px;
             z-index: 10000;
             text-align: center;
             box-shadow: 0 4px 20px rgba(220, 53, 69, 0.3);
             animation: pulse 1s infinite;
           `;
           document.body.appendChild(alertElement);
           
           // Add CSS animation
           const style = document.createElement('style');
           style.textContent = `
             @keyframes pulse {
               0% { transform: translate(-50%, -50%) scale(1); }
               50% { transform: translate(-50%, -50%) scale(1.05); }
               100% { transform: translate(-50%, -50%) scale(1); }
             }
           `;
           document.head.appendChild(style);
         }
         
         alertElement.innerHTML = `
           <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
           <div style="font-weight: bold; font-size: 1.2rem;">ROUTE DEVIATION DETECTED!</div>
           <div style="margin-top: 5px;">Return to designated route immediately</div>
         `;
         
         // Remove alert after 5 seconds
         setTimeout(() => {
           if (alertElement) {
             alertElement.remove();
           }
         }, 5000);
       }

       // Function to clear deviation alert
       function clearDeviationAlert() {
         const alertElement = document.getElementById('deviationAlert');
         if (alertElement) {
           alertElement.remove();
         }
         
         // Vehicle circle removed
       }

      async function updateVehiclePosition() {
        try {
          const res = await fetch(`../get_latest_location.php?device_id=${deviceId}`);
          const data = await res.json();
            
            // Check if ESP32 device is online and sending data
            if (data.error) {
              console.log('ESP32 device offline or no data available');
              esp32Online = false;
              // Clear any existing deviation alerts when device goes offline
              if (!isOnRoute) {
                isOnRoute = true;
                clearDeviationAlert();
              }
              return;
            }
            
          if (data.lat && data.lng) {
            let lat = parseFloat(data.lat);
            let lng = parseFloat(data.lng);
            if (!isNaN(lat) && !isNaN(lng)) {
                esp32Online = true;
                console.log('Updating vehicle marker to:', lat, lng);
                // Move the live vehicle marker to the latest GPS position
                if (vehicleMarker && typeof vehicleMarker.setLatLng === 'function') {
                  vehicleMarker.setLatLng([lat, lng]);
                  console.log('Vehicle marker updated successfully');
                } else {
                  console.error('Vehicle marker not found, creating new one');
                  // Create vehicle marker if it doesn't exist
                  vehicleMarker = L.marker([lat, lng], {
                    icon: L.divIcon({
                      className: 'vehicle-marker',
                      html: `<div style="background: #28a745; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4); animation: pulse 2s infinite;" title="Vehicle Location"></div>`,
                      iconSize: [26, 26]
                    })
                  }).addTo(map).bindPopup("<b>Vehicle Location</b>");
                  console.log('New vehicle marker created at:', lat, lng);
                }
                // Optionally keep map view reasonable if vehicle moves far away
                // map.panTo([lat, lng], { animate: true });
                
                // Check for post-completion unauthorized movement
                if (postCompletionMonitoringEnabled && completionLocation) {
                  const distanceFromCompletion = calculateDistance(
                    lat, lng,
                    completionLocation.lat, completionLocation.lng
                  );
                  
                  // If vehicle moves more than 100m from completion location
                  if (distanceFromCompletion > 100) {
                    const now = Date.now();
                    // Alert dispatcher once every 5 minutes to avoid spam
                    if (now - lastPostCompletionAlert > 300000) {
                      lastPostCompletionAlert = now;
                      alertDispatcherUnauthorizedMovement(lat, lng, distanceFromCompletion);
                    }
                  }
                }
                
                                 // Only check route deviation if ESP32 is online AND deviation detection is enabled
                 if (deviationDetectionEnabled && esp32Online && routePolyline && routePolyline.getLatLngs) {
                  const routeCoordinates = routePolyline.getLatLngs();
                  const isOnDesignatedRoute = checkRouteDeviation(lat, lng, routeCoordinates);
                  
                  // Only alert if we have valid route coordinates and the vehicle is significantly off route
                  if (routeCoordinates.length > 2) {
                    if (!isOnDesignatedRoute && isOnRoute) {
                      // Vehicle just deviated from route
                      isOnRoute = false;
                      alertRouteDeviation();
                    } else if (isOnDesignatedRoute && !isOnRoute) {
                      // Vehicle returned to route
                      isOnRoute = true;
                      clearDeviationAlert();
                      speak('Route resumed. Continue following designated directions.');
                    }
                  }
                }
            }
          }
        } catch (err) {
          console.error("Error updating vehicle position:", err);
            esp32Online = false;
          }
        }

                    // Immediate cleanup - remove any existing deviation alerts
       clearDeviationAlert();
       console.log('Immediately cleared any existing deviation alerts');
       
       // Initial check - clear any existing alerts if ESP32 is offline
        setTimeout(() => {
          if (!esp32Online) {
            clearDeviationAlert();
            console.log('Cleared any existing deviation alerts - ESP32 is offline');
          }
        }, 1000);

      updateVehiclePosition();
      setInterval(updateVehiclePosition, 5000); // refresh every 5 seconds
      
      // Update vehicle positions on map every 5 seconds
      setInterval(loadVehiclesWithGPS, 5000);
      
      // Add automatic destination detection (variables pre-initialized above)
      checkDestinationProximity();
      setInterval(checkDestinationProximity, 10000); // check every 10 seconds
    }
  <?php endif; ?>
  
  // Automatic Turn-by-Turn Voice Navigation
  let currentInstructionIndex = 0;
  let routeInstructions = [];
  let isVoiceEnabled = true;
  
     // Voice synthesis function
   function speak(text) {
     if (!isVoiceEnabled) return;
     
     if ('speechSynthesis' in window) {
       const utterance = new SpeechSynthesisUtterance(text);
       utterance.rate = 0.9;
       utterance.pitch = 0.8; // Lower pitch for male voice
       utterance.volume = 0.8;
       
       // Try to get a male voice
       const voices = speechSynthesis.getVoices();
       const maleVoice = voices.find(voice => 
         voice.name.toLowerCase().includes('male') || 
         voice.name.toLowerCase().includes('david') ||
         voice.name.toLowerCase().includes('james') ||
         voice.name.toLowerCase().includes('john') ||
         voice.name.toLowerCase().includes('mike') ||
         voice.name.toLowerCase().includes('steve') ||
         voice.name.toLowerCase().includes('tom') ||
         voice.name.toLowerCase().includes('paul') ||
         voice.name.toLowerCase().includes('mark') ||
         voice.name.toLowerCase().includes('chris')
       );
       
       if (maleVoice) {
         utterance.voice = maleVoice;
       }
       
       speechSynthesis.speak(utterance);
     }
   }
  
  // Function to speak the next instruction
  function speakNextInstruction() {
    if (routeInstructions.length > 0 && currentInstructionIndex < routeInstructions.length) {
      const instruction = routeInstructions[currentInstructionIndex];
      const distance = instruction.distance ? ` in ${instruction.distance}` : '';
      const text = `${instruction.text}${distance}`;
      speak(text);
      console.log('Speaking:', text);
    }
  }
  
  // Function to speak all instructions
  function speakAllInstructions() {
    if (routeInstructions.length > 0) {
      let allText = 'Route instructions: ';
      routeInstructions.forEach((instruction, index) => {
        const distance = instruction.distance ? ` in ${instruction.distance}` : '';
        allText += `${index + 1}. ${instruction.text}${distance}. `;
      });
      speak(allText);
    }
  }
  
  // Function to speak current instruction
  function speakCurrentInstruction() {
    if (routeInstructions.length > 0 && currentInstructionIndex < routeInstructions.length) {
      const instruction = routeInstructions[currentInstructionIndex];
      const distance = instruction.distance ? ` in ${instruction.distance}` : '';
      const text = `Current instruction: ${instruction.text}${distance}`;
      speak(text);
    }
  }
  // Ensure availability for inline onclick handler
  window.speakCurrentInstruction = speakCurrentInstruction;
  
  // Function to advance to next instruction
  function nextInstruction() {
    if (currentInstructionIndex < routeInstructions.length - 1) {
      currentInstructionIndex++;
      speakNextInstruction();
      return true;
    } else {
      speak('You have reached your destination');
      // Automatically complete the trip
      autoCompleteTrip();
      return false;
    }
  }
  
  // Function to go to previous instruction
  function previousInstruction() {
    if (currentInstructionIndex > 0) {
      currentInstructionIndex--;
      speakCurrentInstruction();
      return true;
    }
    return false;
  }
  
  // External navigation integrations removed by request
  
  // Automatic destination detection and trip completion
  // Function to check if vehicle is near destination
  async function checkDestinationProximity() {
    if (tripCompleted) return;
    
    try {
      const res = await fetch(`../get_latest_location.php?device_id=${deviceId}`);
      const data = await res.json();
      
      if (data.error || !data.lat || !data.lng) {
        console.log('Cannot check destination proximity - no GPS data');
        return;
      }
      
      const vehicleLat = parseFloat(data.lat);
      const vehicleLng = parseFloat(data.lng);
      const vehicleSpeed = data.speed ? parseFloat(data.speed) : 0;
      
      // Check if we're on return journey
      let destinationLat, destinationLng;
      if (window.returnDestination) {
        // Return journey - destination is the starting point
        destinationLat = window.returnDestination.lat;
        destinationLng = window.returnDestination.lng;
      } else {
        // Outbound journey - destination is the end point
        destinationLat = parseFloat("<?= $route['end_lat'] ?>");
        destinationLng = parseFloat("<?= $route['end_lng'] ?>");
      }
      
      if (isNaN(vehicleLat) || isNaN(vehicleLng) || isNaN(destinationLat) || isNaN(destinationLng)) {
        return;
      }
      
      // Calculate distance to destination
      const distance = calculateDistance(vehicleLat, vehicleLng, destinationLat, destinationLng);
      
      console.log(`Distance to destination: ${distance.toFixed(1)} meters`);
      
      // Require consecutive proximity confirmations and low speed to avoid false completion
      if (distance <= DESTINATION_RADIUS && vehicleSpeed <= 5) {
        destinationProximityHits++;
        console.log(`Proximity hit ${destinationProximityHits}/${REQUIRED_PROXIMITY_HITS} (distance ${distance.toFixed(1)}m, speed ${vehicleSpeed} km/h)`);
        if (destinationProximityHits >= REQUIRED_PROXIMITY_HITS) {
          if (window.returnDestination) {
            console.log('Vehicle reached return destination (starting point). Round trip completed!');
            // Complete the round trip
            completeRoundTrip();
          } else {
            console.log('Vehicle reached destination with stable proximity. Auto-completing trip...');
            autoCompleteTrip();
          }
        }
      } else {
        // Reset streak if we move away or speed high
        if (destinationProximityHits !== 0) {
          console.log('Proximity streak reset');
        }
        destinationProximityHits = 0;
      }
      
    } catch (error) {
      console.error('Error checking destination proximity:', error);
    }
  }
  
  // Function to calculate distance between two points in meters
  function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000; // Earth's radius in meters
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }
  
  // Function to automatically complete the trip
  async function autoCompleteTrip() {
    if (tripCompleted) return;
    
    tripCompleted = true;
    
    try {
      // Check if this is a round trip
      const isRoundTrip = <?= isset($route['route_type']) && $route['route_type'] === 'round_trip' ? 'true' : 'false' ?>;
      
      if (isRoundTrip) {
        // For round trips, show intermediate completion message
        speak('Destination reached! You will now return to the starting point.');
        
        // Show intermediate completion notification
        Swal.fire({
          title: 'Destination Reached!',
          html: 'You have reached your destination.<br><small class="text-info">Round trip: You will now return to the starting point.</small>',
          icon: 'success',
          iconColor: '#28a745',
          confirmButtonColor: '#28a745',
          confirmButtonText: 'Continue Return Journey',
          showCancelButton: false,
          allowOutsideClick: false,
          allowEscapeKey: false,
          background: '#f8f9fa',
          color: '#212529',
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn'
          }
        }).then(() => {
          // Reset trip completion for return journey
          tripCompleted = false;
          destinationProximityHits = 0;
          
          // Update route to show return journey
          const startLat = parseFloat("<?= $route['start_lat'] ?>");
          const startLng = parseFloat("<?= $route['start_lng'] ?>");
          const endLat = parseFloat("<?= $route['end_lat'] ?>");
          const endLng = parseFloat("<?= $route['end_lng'] ?>");
          
          // Create return route (B to A)
          if (routingControl) {
            routingControl.setWaypoints([
              L.latLng(endLat, endLng), // Current location (destination)
              L.latLng(startLat, startLng) // Return to start
            ]);
          }
          
          // Update destination for return journey
          window.returnDestination = { lat: startLat, lng: startLng };
          
          speak('Return journey navigation started. Follow the route back to the starting point.');
        });
        
        return; // Don't complete the trip yet, just reached destination
      }
      
      // For single trips, complete as normal
      // Remove route UI and polyline so assigned route disappears
      try {
        if (routingControl && typeof routingControl.remove === 'function') {
          routingControl.remove();
        }
      } catch (e) { /* no-op */ }
      try {
        if (routePolyline && map && map.removeLayer) {
          map.removeLayer(routePolyline);
        }
      } catch (e) { /* no-op */ }
      const routePanel = document.querySelector('.route-info-mobile');
      if (routePanel) routePanel.style.display = 'none';

      // Store completion location for post-completion monitoring
      const gpsRes = await fetch(`../get_latest_location.php?device_id=${deviceId}`);
      const gpsData = await gpsRes.json();
      if (gpsData.lat && gpsData.lng) {
        completionLocation = {
          lat: parseFloat(gpsData.lat),
          lng: parseFloat(gpsData.lng)
        };
        // Enable post-completion monitoring
        postCompletionMonitoringEnabled = true;
        console.log('Post-completion monitoring enabled at location:', completionLocation);
      }
      
      // Show completion notification
      speak('Trip completed successfully! Vehicle movement is now being monitored.');
      
      // Show visual notification
      Swal.fire({
        title: 'Trip Completed!',
        html: 'You have successfully reached your destination.<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Note: Vehicle movement after completion is being monitored.</small>',
        icon: 'success',
        iconColor: '#28a745',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'View Trip Logs',
        showCancelButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: '#f8f9fa',
        color: '#212529',
        customClass: {
          popup: 'rounded-4 shadow',
          confirmButton: 'swal-btn'
        },
        didRender: () => {
          const confirmBtn = document.querySelector('.swal-btn');
          if (confirmBtn) {
            confirmBtn.style.minWidth = '150px';
            confirmBtn.style.padding = '12px 20px';
            confirmBtn.style.fontSize = '16px';
          }
        }
      }).then(() => {
        // Redirect to trip logs
        window.location.href = 'trip-logs.php';
      });
      
      // Update route status in database (will also create a trip log)
      await updateRouteStatus('completed');
      
    } catch (error) {
      console.error('Error completing trip:', error);
      // Still redirect even if database update fails
      setTimeout(() => {
        window.location.href = 'trip-logs.php';
      }, 2000);
    }
  }
  
  // Function to complete round trip
  async function completeRoundTrip() {
    if (tripCompleted) return;
    
    tripCompleted = true;
    
    try {
      // Remove route UI and polyline
      try {
        if (routingControl && typeof routingControl.remove === 'function') {
          routingControl.remove();
        }
      } catch (e) { /* no-op */ }
      try {
        if (routePolyline && map && map.removeLayer) {
          map.removeLayer(routePolyline);
        }
      } catch (e) { /* no-op */ }
      const routePanel = document.querySelector('.route-info-mobile');
      if (routePanel) routePanel.style.display = 'none';

      // Store completion location for post-completion monitoring
      const gpsRes = await fetch(`../get_latest_location.php?device_id=${deviceId}`);
      const gpsData = await gpsRes.json();
      if (gpsData.lat && gpsData.lng) {
        completionLocation = {
          lat: parseFloat(gpsData.lat),
          lng: parseFloat(gpsData.lng)
        };
        postCompletionMonitoringEnabled = true;
        console.log('Post-completion monitoring enabled at location:', completionLocation);
      }
      
      // Show completion notification
      speak('Round trip completed successfully! You have returned to the starting point.');
      
      // Show visual notification
      Swal.fire({
        title: 'Round Trip Completed!',
        html: 'You have successfully completed the round trip and returned to the starting point.<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Note: Vehicle movement after completion is being monitored.</small>',
        icon: 'success',
        iconColor: '#28a745',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'View Trip Logs',
        showCancelButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: '#f8f9fa',
        color: '#212529',
        customClass: {
          popup: 'rounded-4 shadow',
          confirmButton: 'swal-btn'
        },
        didRender: () => {
          const confirmBtn = document.querySelector('.swal-btn');
          if (confirmBtn) {
            confirmBtn.style.minWidth = '150px';
            confirmBtn.style.padding = '12px 20px';
            confirmBtn.style.fontSize = '16px';
          }
        }
      }).then(() => {
        // Redirect to trip logs
        window.location.href = 'trip-logs.php';
      });
      
      // Update route status in database
      await updateRouteStatus('completed');
      
    } catch (error) {
      console.error('Error completing round trip:', error);
      // Still redirect even if database update fails
      setTimeout(() => {
        window.location.href = 'trip-logs.php';
      }, 2000);
    }
  }
  
  // Function to update route status in database
  async function updateRouteStatus(status) {
    try {
      const response = await fetch('../api/update_route_status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          route_id: <?= $route['id'] ?>,
          status: status,
          completed_at: new Date().toISOString()
        })
      });
      
      const result = await response.json();
      if (result.success) {
        console.log('Route status updated successfully');
      } else {
        console.error('Failed to update route status:', result.message);
      }
    } catch (error) {
      console.error('Error updating route status:', error);
    }
  }
  
  // Function to alert dispatcher about route deviation during active trip
  async function alertDispatcherRouteDeviation(currentLat, currentLng) {
    try {
      console.log('Alerting dispatcher about route deviation during trip');
      
      const response = await fetch('../api/alert_route_deviation.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          driver_id: <?= $_SESSION['user_id'] ?>,
          driver_name: '<?= $_SESSION['full_name'] ?>',
          route_id: <?= $route['id'] ?>,
          vehicle_unit: '<?= $route['unit'] ?? 'N/A' ?>',
          current_lat: currentLat,
          current_lng: currentLng,
          route_start_lat: <?= $route['start_lat'] ?>,
          route_start_lng: <?= $route['start_lng'] ?>,
          route_end_lat: <?= $route['end_lat'] ?>,
          route_end_lng: <?= $route['end_lng'] ?>,
          timestamp: new Date().toISOString()
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        console.log('Dispatcher alerted successfully via SMS and system alert');
      } else {
        console.error('Failed to alert dispatcher:', result.message);
      }
    } catch (error) {
      console.error('Error alerting dispatcher about deviation:', error);
    }
  }
  
  // Function to alert dispatcher about unauthorized vehicle movement after trip completion
  async function alertDispatcherUnauthorizedMovement(currentLat, currentLng, distance) {
    try {
      console.log('Alerting dispatcher about unauthorized movement');
      
      const response = await fetch('../api/alert_post_trip_movement.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          driver_id: <?= $_SESSION['user_id'] ?>,
          driver_name: '<?= $_SESSION['full_name'] ?>',
          route_id: <?= $route['id'] ?>,
          vehicle_unit: '<?= $route['unit'] ?? 'N/A' ?>',
          current_lat: currentLat,
          current_lng: currentLng,
          completion_lat: completionLocation.lat,
          completion_lng: completionLocation.lng,
          distance_moved: Math.round(distance),
          timestamp: new Date().toISOString()
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        console.log('Dispatcher alerted successfully via SMS and system alert');
        
        // Show warning to driver
        Swal.fire({
          title: 'Unauthorized Movement Detected',
          html: `<p>Dispatcher has been notified of vehicle movement after trip completion.</p>
                 <p class="text-danger"><strong>Distance from completion point: ${Math.round(distance)}m</strong></p>
                 <p class="text-muted">Please return the vehicle to the designated area.</p>`,
          icon: 'warning',
          iconColor: '#ffc107',
          confirmButtonColor: '#003566',
          confirmButtonText: 'Understood',
          background: '#f8f9fa'
        });
      } else {
        console.error('Failed to alert dispatcher:', result.message);
      }
    } catch (error) {
      console.error('Error alerting dispatcher:', error);
    }
  }
  
     // Initial voice prompt
   setTimeout(() => {
     const isRoundTrip = <?= isset($route['route_type']) && $route['route_type'] === 'round_trip' ? 'true' : 'false' ?>;
     const routeType = isRoundTrip ? 'Round trip navigation ready' : 'Turn-by-turn navigation ready';
     speak(routeType);
     // Speak the first direction after navigation is ready
     setTimeout(() => {
       if (routeInstructions.length > 0) {
         speakNextInstruction();
       }
     }, 1500);
   }, 2000);
  
     // Toggle voice function for mobile button
  // toggleVoice UI removed
   
       // Keyboard controls for manual navigation (laptop and desktop only)
    if (window.innerWidth >= 1024) {
      document.addEventListener('keydown', function(event) {
        switch(event.key) {
          case 'ArrowRight':
          case 'n':
            nextInstruction();
            break;
          case 'ArrowLeft':
          case 'p':
            previousInstruction();
            break;
          case 'c':
            speakCurrentInstruction();
            break;
          case 'a':
            speakAllInstructions();
            break;
        }
      });
    }
   
  // Sidebar toggle functionality
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
      // Check if we're on mobile (sidebar is hidden off-screen)
      if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
      } else {
        // Desktop behavior - collapse/expand sidebar
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
      }
    });
  }

  // Sidebar active class handler
  const allSidebarLinks = document.querySelectorAll('.sidebar a:not(.dropdown-toggle)');
  allSidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
      allSidebarLinks.forEach(l => l.classList.remove('active'));
      this.classList.add('active');
      const parentCollapse = this.closest('.collapse');
      if (parentCollapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(parentCollapse);
        if (bsCollapse) {
          bsCollapse.show();
        }
      }
    });
  });

  // Logout functionality
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
              window.location.href = '/tracking/logout.php';
            }
          });
        }
      });
    });
  }

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});

// Toggle route details panel - moved outside DOMContentLoaded to be globally accessible
function toggleRouteInfo() {
  const routingPanel = document.querySelector('.leaflet-routing-container');
  if (routingPanel) {
    if (routingPanel.style.display === 'none' || routingPanel.style.display === '') {
      routingPanel.style.display = 'block';
      routingPanel.style.maxHeight = '300px';
      routingPanel.style.overflowY = 'auto';
      routingPanel.style.fontSize = '12px';
      routingPanel.style.position = 'absolute';
      routingPanel.style.top = '10px';
      routingPanel.style.right = '10px';
      routingPanel.style.zIndex = '1000';
      routingPanel.style.backgroundColor = 'white';
      routingPanel.style.border = '1px solid #ccc';
      routingPanel.style.borderRadius = '5px';
      routingPanel.style.padding = '10px';
    } else {
      routingPanel.style.display = 'none';
    }
  }
}
// Expose for inline handler
window.toggleRouteInfo = toggleRouteInfo;

// Load and display all geofences
async function loadGeofences() {
  try {
    console.log('Loading geofences for driver navigation...');
    const response = await fetch('../super_admin/geofence_api.php?action=get_geofences');
    const { success, data } = await response.json();
    
    console.log('Geofences API response:', { success, data });
    
    if (success && data && data.length > 0) {
      console.log('Geofences loaded:', data);
      
      data.forEach(geofence => {
        console.log('Adding geofence to map:', geofence);
        addGeofenceToMap(geofence);
      });
    } else {
      console.log('No geofences found or error loading geofences');
    }
  } catch (error) {
    console.error('Error loading geofences:', error);
  }
}

// Add geofence to map
function addGeofenceToMap(geofence) {
  try {
    // Check if map exists and is properly initialized
    if (!map || typeof map.addLayer !== 'function') {
      console.error('Map not properly initialized, cannot add geofence');
      return;
    }
    
    let layer;
    
    if (geofence.type === 'circle' && geofence.latitude && geofence.longitude && geofence.radius) {
      // Circle geofence
      layer = L.circle([geofence.latitude, geofence.longitude], {
        radius: geofence.radius,
        color: geofence.color || '#ff6b6b',
        fillColor: geofence.color || '#ff6b6b',
        fillOpacity: 0.2,
        weight: 2
      });
    } else if (geofence.type === 'polygon' && geofence.polygon && Array.isArray(geofence.polygon) && geofence.polygon.length >= 3) {
      // Polygon geofence
      const coordinates = geofence.polygon
        .map(coord => {
          // Handle both array format [lat, lng] and object format {lat, lng}
          let lat, lng;
          if (Array.isArray(coord) && coord.length >= 2) {
            // Array format: [lat, lng]
            lat = parseFloat(coord[0]);
            lng = parseFloat(coord[1]);
          } else if (coord && typeof coord === 'object') {
            // Object format: {lat, lng} or {latitude, longitude}
            lat = parseFloat(coord.lat ?? coord.latitude);
            lng = parseFloat(coord.lng ?? coord.longitude);
          } else {
            return null;
          }
          return (isNaN(lat) || isNaN(lng)) ? null : [lat, lng];
        })
        .filter(Boolean);
      if (coordinates.length < 3) {
        console.warn('Skipping invalid polygon geofence, not enough valid points:', geofence);
        return;
      }
      layer = L.polygon(coordinates, {
        color: geofence.color || '#ff6b6b',
        fillColor: geofence.color || '#ff6b6b',
        fillOpacity: 0.2,
        weight: 2
      });
    } else if (geofence.type === 'rectangle' && geofence.polygon && Array.isArray(geofence.polygon) && geofence.polygon.length >= 4) {
      // Rectangle geofence
      const coordinates = geofence.polygon
        .map(coord => {
          // Handle both array format [lat, lng] and object format {lat, lng}
          let lat, lng;
          if (Array.isArray(coord) && coord.length >= 2) {
            // Array format: [lat, lng]
            lat = parseFloat(coord[0]);
            lng = parseFloat(coord[1]);
          } else if (coord && typeof coord === 'object') {
            // Object format: {lat, lng} or {latitude, longitude}
            lat = parseFloat(coord.lat ?? coord.latitude);
            lng = parseFloat(coord.lng ?? coord.longitude);
          } else {
            return null;
          }
          return (isNaN(lat) || isNaN(lng)) ? null : [lat, lng];
        })
        .filter(Boolean)
        .slice(0, 4);
      if (coordinates.length < 4) {
        console.warn('Skipping invalid rectangle geofence, need 4 valid points:', geofence);
        return;
      }
      layer = L.polygon(coordinates, {
        color: geofence.color || '#ff6b6b',
        fillColor: geofence.color || '#ff6b6b',
        fillOpacity: 0.2,
        weight: 2
      });
    }
    
    if (layer) {
      layer.addTo(map);
      
      // Add popup with geofence info
      layer.bindPopup(`
        <div>
          <h6><strong>${geofence.name || 'Unnamed Geofence'}</strong></h6>
          <p><strong>Type:</strong> ${geofence.type}</p>
          ${geofence.radius ? `<p><strong>Radius:</strong> ${geofence.radius}m</p>` : ''}
          <p><strong>Created:</strong> ${new Date(geofence.created_at).toLocaleDateString()}</p>
        </div>
      `);
      
      console.log('Geofence added to map:', geofence.name);
    }
  } catch (error) {
    console.error('Error adding geofence to map:', error);
  }
}

// Load and display MOBILE-001 device (hardcoded focus)
async function loadVehiclesWithGPS() {
  try {
    console.log('Loading MOBILE-001 device...');
    const response = await fetch('../get_latest_location.php?device_id=MOBILE-001');
    const data = await response.json();
    
    console.log('MOBILE-001 device loaded:', data);
    
    // Clear existing vehicle markers
    if (window.vehicleMarkers) {
      window.vehicleMarkers.forEach(marker => {
        if (map.hasLayer(marker)) {
          map.removeLayer(marker);
        }
      });
    }
    window.vehicleMarkers = [];
    
    if (data.lat && data.lng) {
      console.log('Found MOBILE-001 device:', data);
      
      // Convert to the format expected by addVehicleToMap
      const vehicleData = {
        latitude: data.lat,
        longitude: data.lng,
        vehicle_name: 'MOBILE-001 Device',
        plate_number: 'N/A',
        driver_name: 'Unassigned',
        speed: data.speed || 0,
        last_update: data.last_update
      };
      addVehicleToMap(vehicleData);
      console.log('MOBILE-001 device marker added to map');
    } else {
      console.log('MOBILE-001 device not found or no GPS data');
    }
  } catch (error) {
    console.error('Error loading assigned vehicle:', error);
  }
}

// Add vehicle with GPS device to map using green pulsing marker
function addVehicleToMap(vehicle) {
  try {
    // Check if map exists and is properly initialized
    if (!map || typeof map.addLayer !== 'function') {
      console.error('Map not properly initialized, cannot add vehicle');
      return;
    }
    
    const marker = L.marker([vehicle.latitude, vehicle.longitude], {
      icon: L.divIcon({
        className: 'vehicle-marker',
        html: `<div style="background: #28a745; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4); animation: pulse 2s infinite;" title="${vehicle.vehicle_name || 'Vehicle'}"></div>`,
        iconSize: [22, 22]
      })
    }).addTo(map);
    
    // Add popup with vehicle info
    marker.bindPopup(`
      <div>
        <h6><strong>${vehicle.vehicle_name || 'Vehicle'}</strong></h6>
        <p><strong>Plate:</strong> ${vehicle.plate_number || 'N/A'}</p>
        <p><strong>Driver:</strong> ${vehicle.driver_name || 'Unassigned'}</p>
        <p><strong>Speed:</strong> ${vehicle.speed || 0} km/h</p>
        <p><strong>Last Update:</strong> ${vehicle.last_update ? new Date(vehicle.last_update).toLocaleString() : 'N/A'}</p>
      </div>
    `);
    
    // Store marker in global array for cleanup
    if (!window.vehicleMarkers) {
      window.vehicleMarkers = [];
    }
    window.vehicleMarkers.push(marker);
    
    console.log('Vehicle with GPS added to map:', vehicle.vehicle_name);
  } catch (error) {
    console.error('Error adding vehicle to map:', error);
  }
}
</script>
</body>
</html>
