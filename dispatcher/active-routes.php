<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// If coming from assigned_reservations start action, fetch reservation details
$autoRoute = null;
if (isset($_GET['reservation_id']) && ctype_digit($_GET['reservation_id'])) {
    $resId = intval($_GET['reservation_id']);
    $stmt = $conn->prepare("SELECT vr.id, vr.origin, vr.destination, vr.vehicle_id, fv.unit, fv.plate_number, gd.device_id
                             FROM vehicle_reservations vr
                             LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id
                             LEFT JOIN gps_devices gd ON gd.vehicle_id = fv.id AND gd.status = 'active'
                             WHERE vr.id = ? LIMIT 1");
    $stmt->bind_param('i', $resId);
    if ($stmt->execute()) {
        $routeRes = $stmt->get_result();
        if ($routeRes && $routeRes->num_rows > 0) {
            $row = $routeRes->fetch_assoc();
            $autoRoute = [
                'id' => $row['id'],
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'unit' => $row['unit'],
                'plate' => $row['plate_number'],
                'device_id' => $row['device_id']
            ];
        }
    }
    $stmt->close();
}

$currentPage = 'active_routes.php'; 

// Fetch active routes
$routesQuery = "
    SELECT 
        r.id AS route_id,
        r.driver_id,
        r.unit,
        d.full_name AS driver_name,
        v.plate_number AS vehicle_plate,
        r.start_lat,
        r.start_lng,
        r.end_lat,
        r.end_lng,
        r.created_at AS start_time,
        r.status,
        r.distance,
        r.duration
    FROM routes r
    JOIN user_table d ON r.driver_id = d.user_id
    JOIN fleet_vehicles v ON r.unit = v.unit
    WHERE r.is_deleted = 0
    ORDER BY r.created_at DESC
";
// Use prepared statements for consistency (static queries but best practice)
$routes_stmt = $conn->prepare($routesQuery);
$routes_stmt->execute();
$routesResult = $routes_stmt->get_result();

// Fetch drivers and vehicles for modals
$drivers_stmt = $conn->prepare("SELECT user_id, full_name FROM user_table WHERE role = 'driver'");
$drivers_stmt->execute();
$driversResult = $drivers_stmt->get_result();

$vehicles_stmt = $conn->prepare("SELECT unit, plate_number FROM fleet_vehicles WHERE status = 'active'");
$vehicles_stmt->execute();
$vehiclesResult = $vehicles_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Planning | Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
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
      margin-top: 10px;
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
    #routingMap {
      height: 500px;
      width: 100%;
      border-radius: 0.5rem;
      margin-bottom: 20px;
    }
    .route-controls {
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 1000;
      width: 320px;
    }
    
    /* Responsive adjustments for route controls */
    @media (max-width: 1200px) {
      .route-controls {
        width: 280px;
      }
    }
    
    @media (max-width: 768px) {
      .route-controls {
        position: relative;
        top: auto;
        right: auto;
        width: 100%;
        margin-bottom: 20px;
      }
    }
    
    .route-controls .card {
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      border: none;
    }
    
    .route-controls .card-header {
      border-bottom: none;
    }
    
    
    /* Map layer control styling */
    .leaflet-control-layers {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border: 1px solid #e9ecef;
    }
    
    .leaflet-control-layers-toggle {
      background: white;
      border: 1px solid #e9ecef;
      border-radius: 4px;
      padding: 8px;
      font-size: 16px;
    }
    
    .leaflet-control-layers-toggle:hover {
      background: #f8f9fa;
      border-color: #00b4d8;
    }
    
    .leaflet-control-layers-expanded {
      padding: 12px;
      min-width: 150px;
    }
    
    .leaflet-control-layers-expanded label {
      margin-bottom: 8px;
      font-weight: 500;
      color: #333;
    }
    
    .leaflet-control-layers-expanded input[type="radio"] {
      margin-right: 8px;
    }
      .table-responsive {
        max-height: 500px;
        overflow-y: auto;
      }

     /* Address search styling */
     .input-group .btn {
       border-left: 0;
     }

     .input-group .form-control:focus {
       border-right: 0;
       box-shadow: none;
     }

     .input-group .form-control:focus + .btn {
       border-color: var(--accent);
       box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
     }

     /* Fix modal z-index to appear above navbar */
     .modal { z-index: 1300 !important; }
     .modal-backdrop { z-index: 1299 !important; }
     
     /* Custom delete modal styling */
     #deleteRouteModal .modal-content {
       border-radius: 1rem;
       overflow: hidden;
     }
     
     #deleteRouteModal .btn {
       border-radius: 0.5rem;
       font-weight: 500;
       transition: all 0.3s ease;
     }
     
     #deleteRouteModal .btn:hover {
       transform: translateY(-1px);
       box-shadow: 0 4px 12px rgba(0,0,0,0.15);
     }
     
     #deleteRouteModal .btn-danger:hover {
       background-color: #dc3545;
       border-color: #dc3545;
     }
     
     /* Modern Alert Styling */
     .modern-alert {
       border: none;
       border-radius: 0.75rem;
       padding: 1rem 1.25rem;
       margin-bottom: 1.5rem;
       box-shadow: 0 4px 12px rgba(0,0,0,0.1);
       position: relative;
       overflow: hidden;
     }
     
     .modern-alert::before {
       content: '';
       position: absolute;
       left: 0;
       top: 0;
       bottom: 0;
       width: 4px;
       background: currentColor;
     }
     
     .modern-alert.alert-success {
       background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
       color: #155724;
       border-left: 4px solid #28a745;
     }
     
     .modern-alert.alert-danger {
       background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
       color: #721c24;
       border-left: 4px solid #dc3545;
     }
     
     .alert-icon {
       font-size: 1.5rem;
       display: flex;
       align-items: center;
       justify-content: center;
       width: 40px;
       height: 40px;
       border-radius: 50%;
       background: rgba(255,255,255,0.3);
       backdrop-filter: blur(10px);
     }
     
     .modern-alert.alert-success .alert-icon {
       color: #28a745;
     }
     
     .modern-alert.alert-danger .alert-icon {
       color: #dc3545;
     }
     
     .btn-close-modern {
       background: none;
       border: none;
       font-size: 1.1rem;
       color: inherit;
       opacity: 0.7;
       transition: all 0.3s ease;
       padding: 0.5rem;
       border-radius: 50%;
       width: 32px;
       height: 32px;
       display: flex;
       align-items: center;
       justify-content: center;
       cursor: pointer;
       z-index: 10;
       position: relative;
     }
     
     .btn-close-modern:hover {
       opacity: 1;
       background: rgba(0,0,0,0.1);
       transform: scale(1.1);
     }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <main class="content px-3 py-4">
            <div class="container-fluid">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="modern-alert alert-<?= $_SESSION['message']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <div class="alert-icon me-3">
                                <?php if ($_SESSION['message']['type'] === 'success'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-triangle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= $_SESSION['message']['type'] === 'success' ? 'Success!' : 'Error!' ?></strong>
                                <span class="ms-2"><?= htmlspecialchars($_SESSION['message']['text']) ?></span>
                            </div>
                            <button type="button" class="btn-close-modern" onclick="this.closest('.modern-alert').style.display='none'" aria-label="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <!-- Route Planning Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-3">
                <h5 class="card-title text-primary fw-bold mb-3">
                    <i class="fas fa-sync-alt me-2"></i>Round Trip Route Planning
                </h5>
                
                <!-- Route Options Panel removed by request -->
                
                                 <div id="routingMap"></div>
                                 
                                 <!-- Map Layers Info -->
                                 <div class="alert alert-info alert-sm mt-2 mb-0">
                                     <i class="fas fa-info-circle me-1"></i>
                                     <small>
                                         <strong>Round Trip Planning:</strong> All routes are calculated as round trips (A to B to A). 
                                         Use the layer control (🗺️) to switch between <strong>Street</strong>, <strong>Satellite</strong>, and <strong>Hybrid</strong> views.
                                     </small>
                                 </div>
                 
                 <!-- Route Controls Toggle Button -->
                 <button class="btn btn-primary btn-sm position-absolute" id="toggleRouteControls" style="top: 20px; right: 20px; z-index: 1001;">
                     <i class="fas fa-cog"></i> Route Controls
                 </button>
                 
                 <!-- Enhanced Route Controls -->
                 <div class="route-controls" id="routeControlsPanel">
                    <div class="card">
                                                 <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                             <h6 class="mb-0"><i class="fas fa-map-marker-alt me-1"></i>Route Configuration</h6>
                             <button type="button" class="btn-close btn-close-white" id="closeRouteControls" style="font-size: 0.8rem;"></button>
                         </div>
                        <div class="card-body p-3">
                                                         <div class="form-group mb-2">
                                 <label class="form-label small fw-bold">Start Point (A):</label>
                                 <input type="text" id="pointA" class="form-control form-control-sm" placeholder="Vehicle Current Location" readonly>
                                 <small class="text-muted">Click on map to set manual start point if ESP32 location is unavailable</small>
                             </div>
                            <div class="form-group mb-2">
                                <label class="form-label small fw-bold">End Point (B):</label>
                                <div class="input-group">
                                    <input type="text" id="addressSearch" class="form-control form-control-sm" placeholder="Type any Philippine address (e.g., Manila, Cebu, Davao, Bago City...)">
                                    <button class="btn btn-outline-primary btn-sm" type="button" id="searchAddressBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <input type="text" id="pointB" class="form-control form-control-sm mt-2" placeholder="Or click on map to set destination" readonly>
                                <small class="text-muted">Type an address above or click on the map to set destination</small>
                            </div>
                            
                            <!-- Route Summary -->
                            <div id="routeSummary" class="d-none">
                                <hr class="my-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <small class="text-muted">Distance</small>
                                        <div class="fw-bold text-primary" id="summaryDistance">-</div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Duration</small>
                                        <div class="fw-bold text-success" id="summaryDuration">-</div>
                                    </div>
                                </div>
                                 <div class="text-center mt-2">
                                     <small class="text-muted">Round trip routes calculated automatically (A to B to A)</small>
                                 </div>
                            </div>
                            
                                                         <div class="d-flex justify-content-between mt-3">
                                 <button class="btn btn-secondary btn-sm" id="resetPointsBtn">
                                     <i class="fas fa-undo"></i> Reset Points
                                 </button>
                                 <div>
                                      <button class="btn btn-info btn-sm me-2" id="recalculateRoutesBtn" style="display: none;">
                                          <i class="fas fa-sync-alt"></i> Recalculate Routes
                                     </button>
                                     <button class="btn btn-success btn-sm" id="saveRouteBtn" data-bs-toggle="modal" data-bs-target="#saveRouteModal">
                                         <i class="fas fa-save"></i> Assign Round Trip
                                     </button>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
                
                <!-- Active Routes Table -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title text-primary fw-bold m-0">Active Routes</h5>
                                    <div class="d-flex">
                                        <button class="btn btn-primary btn-sm me-2" onclick="window.location.reload()">
                                            <i class="fas fa-sync-alt me-1"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Route ID</th>
                                                <th>Driver</th>
                                                <th>Vehicle</th>
                                                <th>Start Location</th>
                                                <th>End Location</th>
                                                <th>Status</th>
                                                <th>Start Time</th>
                                                <th>Distance</th>
                                                <th>Duration</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($routesResult && $routesResult->num_rows > 0): ?>
                                                <?php while ($row = $routesResult->fetch_assoc()): ?>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($row['status']) {
                                                        case 'active': $statusClass = 'bg-success'; break;
                                                        case 'ended': $statusClass = 'bg-secondary'; break;
                                                        default: $statusClass = 'bg-primary'; break;
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['route_id']) ?></td>
                                                        <td><?= htmlspecialchars($row['driver_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['unit'] . ' (' . $row['vehicle_plate'] . ')') ?></td>
                                                        <td><?= htmlspecialchars($row['start_lat'] . ', ' . $row['start_lng']) ?></td>
                                                        <td><?= htmlspecialchars($row['end_lat'] . ', ' . $row['end_lng']) ?></td>
                                                        <td><span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                                                        <td><?= date('F j, Y, h:i A', strtotime($row['start_time'])) ?></td>
                                                        <td><?= htmlspecialchars($row['distance']) ?></td>
                                                        <td><?= htmlspecialchars($row['duration']) ?></td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-info view-route-btn" 
                                                                data-startlat="<?= htmlspecialchars($row['start_lat']) ?>"
                                                                data-startlng="<?= htmlspecialchars($row['start_lng']) ?>"
                                                                data-endlat="<?= htmlspecialchars($row['end_lat']) ?>"
                                                                data-endlng="<?= htmlspecialchars($row['end_lng']) ?>">
                                                                <i class="fas fa-map-marked-alt"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger delete-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteRouteModal"
                                                                data-id="<?= htmlspecialchars($row['route_id']) ?>">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center">No active routes found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Save Route Modal -->
<div class="modal fade" id="saveRouteModal" tabindex="-1" aria-labelledby="saveRouteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saveRouteModalLabel">Save Round Trip Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="routeForm" action="save_route.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_route">
                    <div class="mb-3">
                        <label for="routeName" class="form-label">Round Trip Name</label>
                        <input type="text" class="form-control" id="routeName" name="name" placeholder="e.g., Manila to Cebu Round Trip" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Latitude</label>
                            <input type="text" class="form-control" id="startLat" name="start_lat" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Longitude</label>
                            <input type="text" class="form-control" id="startLng" name="start_lng" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Latitude</label>
                            <input type="text" class="form-control" id="endLat" name="end_lat" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Longitude</label>
                            <input type="text" class="form-control" id="endLng" name="end_lng" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="routeDistance" class="form-label">Distance</label>
                            <input type="text" class="form-control" id="routeDistance" name="distance" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="routeDuration" class="form-label">Duration</label>
                            <input type="text" class="form-control" id="routeDuration" name="duration" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="routeType" class="form-label">Route Type</label>
                            <select class="form-select" id="modalRouteType" name="route_type" required>
                                <option value="round_trip" selected>Round Trip (A to B to A)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="returnTime" class="form-label">Return Time</label>
                            <input type="datetime-local" class="form-control" id="returnTime" name="return_time">
                            <small class="text-muted">When should the driver return? Leave empty for immediate return</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modalDriverId" class="form-label">Driver</label>
                        <select class="form-select" id="modalDriverId" name="driver_id" required>
                            <option value="">Select Driver</option>
                            <?php while($driver = $driversResult->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($driver['user_id']) ?>"><?= htmlspecialchars($driver['full_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modalUnitId" class="form-label">Vehicle Unit</label>
                        <select class="form-select" id="modalUnitId" name="unit" required>
                            <option value="">Select Unit</option>
                            <?php
                            $vehiclesResult->data_seek(0);
                            while($vehicle = $vehiclesResult->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($vehicle['unit']) ?>"><?= htmlspecialchars($vehicle['unit'] . ' - ' . $vehicle['plate_number']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Round Trip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Route Modal - New Design -->
<div class="modal fade" id="deleteRouteModal" tabindex="-1" aria-labelledby="deleteRouteModalLabel" aria-hidden="true" style="z-index: 1300;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center py-4">
                <form action="delete_route.php" method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="route_id" id="deleteRouteId">
                    
                    <!-- Icon -->
                    <div class="mb-3">
                        <div class="mx-auto d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10" style="width: 80px; height: 80px;">
                            <i class="fas fa-trash-alt text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    
                    <!-- Title -->
                    <h4 class="modal-title text-dark mb-3">Delete Route?</h4>
                    
                    <!-- Message -->
                    <p class="text-muted mb-4">Are you sure you want to delete this route? This action cannot be undone.</p>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-trash-alt me-2"></i>Delete Route
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script>
let routingMap;
let routingControl = null;
let startPoint = null;
let endPoint = null;
let selectedRouteIndex = 0;
// Default device; will be overridden by reservation device if present
let deviceID = "MOBILE-001";
<?php if ($autoRoute && !empty($autoRoute['device_id'])): ?>
deviceID = <?= json_encode($autoRoute['device_id']) ?>;
<?php endif; ?>

// Expose auto-route details from reservation to JS
const AUTO_ROUTE = <?php echo json_encode($autoRoute); ?>;

// Route configuration
let routeConfig = {
    type: 'fastest',
    timeOfDay: 'now',
    // Show main route + one improved alternative below
    alternatives: 2
};

document.addEventListener('DOMContentLoaded', async function() {
    // Initialize map with default Philippines location
    routingMap = L.map('routingMap').setView([10.417387, 122.955502], 13);
    
    // --- Base Layers ---
    let streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(routingMap);

    let satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: "Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics"
        }
    );

    let hybridLayer = L.tileLayer(
        'https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
        {
            subdomains: ["mt0", "mt1", "mt2", "mt3"],
            attribution: "Map data &copy; Google",
            maxZoom: 20
        }
    );

    // --- Layer Control ---
    let baseMaps = {
        "🗺️ Street": streetLayer,
        "🛰️ Satellite": satelliteLayer,
        "🌍 Hybrid": hybridLayer
    };

    // Create custom layer control with better styling
    let layerControl = L.control.layers(baseMaps, null, {
        collapsed: false,
        position: 'topright'
    }).addTo(routingMap);
    
    // Customize the layer control button
    setTimeout(() => {
        const layerButton = document.querySelector('.leaflet-control-layers-toggle');
        if (layerButton) {
            layerButton.innerHTML = '<i class="fas fa-layer-group"></i>';
            layerButton.title = 'Switch Map Layers';
        }
    }, 100);
    
    // Try to fetch ESP32 location
    startPoint = await getLatestVehicleLocation();
    
    if (startPoint) {
        // Valid ESP32 location found
        routingMap.setView(startPoint, 14);
        L.marker(startPoint).addTo(routingMap).bindPopup("Vehicle Location (Point A)").openPopup();
        document.getElementById('pointA').value = `${startPoint.lat.toFixed(4)}, ${startPoint.lng.toFixed(4)}`;
        document.getElementById('startLat').value = startPoint.lat.toFixed(6);
        document.getElementById('startLng').value = startPoint.lng.toFixed(6);
    } else {
        // No valid ESP32 location - allow manual point setting
        console.log('ESP32 location not available - manual point setting enabled');
        document.getElementById('pointA').placeholder = 'Click on map to set start point';
    }

    // If we have a destination from reservation, auto-search and set it
    if (AUTO_ROUTE && AUTO_ROUTE.destination) {
        const addressInput = document.getElementById('addressSearch');
        const destination = AUTO_ROUTE.destination.trim();
        
        // Debug: Log the destination
        console.log('Auto-filling destination:', destination);
        console.log('Destination length:', destination.length);
        console.log('Reservation ID:', AUTO_ROUTE.id);
        
        // Check if destination is valid
        if (!destination || destination.length === 0) {
            console.error('Empty destination for reservation #' + AUTO_ROUTE.id);
            Swal.fire({
                title: 'No Destination Set',
                text: 'This reservation does not have a destination address. Please enter one manually or click on the map.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        addressInput.value = destination;
        // Allow some time for startPoint to set, then search
        setTimeout(() => {
            try { 
                console.log('Starting auto-search for:', destination);
                searchAddress(); 
            } catch (e) { 
                console.error('Auto search error', e);
                Swal.fire({
                    title: 'Auto-Search Failed',
                    text: 'Could not automatically search for the destination. Please search manually or click on the map.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        }, 800);
        // Pre-fill route name/unit in modal for quick save with timestamp to ensure uniqueness
        const timestamp = new Date().toLocaleString('en-PH', { 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        const routeName = `Round Trip #${AUTO_ROUTE.id} - ${AUTO_ROUTE.unit || ''} (${timestamp})`.trim();
        const routeNameInput = document.getElementById('routeName');
        if (routeNameInput) routeNameInput.value = routeName;
        const unitSelect = document.getElementById('modalUnitId');
        if (unitSelect && AUTO_ROUTE.unit) {
            Array.from(unitSelect.options).forEach(opt => {
                if (opt.value === AUTO_ROUTE.unit) opt.selected = true;
            });
        }
    }

    // Handle map click for setting points
    routingMap.on('click', function(e) {
        const clickedPoint = e.latlng;
        
        // If no start point is set, set it as start point
        if (!startPoint) {
            startPoint = clickedPoint;
            document.getElementById('pointA').value = `${startPoint.lat.toFixed(4)}, ${startPoint.lng.toFixed(4)}`;
            document.getElementById('startLat').value = startPoint.lat.toFixed(6);
            document.getElementById('startLng').value = startPoint.lng.toFixed(6);
            
            // Add marker for start point
            L.marker(startPoint).addTo(routingMap).bindPopup("Start Point (A)").openPopup();
            
            Swal.fire({
                title: 'Start Point Set!',
                text: 'Now click on the map to set your destination for the round trip',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            // Set as end point
            endPoint = clickedPoint;
            document.getElementById('pointB').value = `${endPoint.lat.toFixed(4)}, ${endPoint.lng.toFixed(4)}`;
            document.getElementById('endLat').value = endPoint.lat.toFixed(6);
            document.getElementById('endLng').value = endPoint.lng.toFixed(6);
            
            // Add marker for end point
            L.marker(endPoint).addTo(routingMap).bindPopup("End Point (B)");
            
            Swal.fire({
                title: 'End Point Set!',
                 text: 'Both points are now set. Calculating round trip routes automatically...',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
             
             // Auto-calculate routes after a short delay
             setTimeout(() => {
                 calculateIntelligentRoutes();
             }, 1500);
        }
    });

    // Route configuration change handlers
    // Route options UI removed


    // Route options UI removed

    // Route options UI removed

         // Auto-calculate routes when both points are set
     // This is now handled automatically in the map click and address search functions

    // Reset points button
    document.getElementById('resetPointsBtn').addEventListener('click', function() {
        resetPoints();
    });

    // Recalculate routes button
    document.getElementById('recalculateRoutesBtn').addEventListener('click', function() {
        if (startPoint && endPoint) {
            calculateIntelligentRoutes();
        } else {
            Swal.fire('Info', 'Please set both start and end points first.', 'info');
        }
    });

    // Toggle route controls
    document.getElementById('toggleRouteControls').addEventListener('click', function() {
        const panel = document.getElementById('routeControlsPanel');
        const button = this;
        
        if (panel.style.display === 'none') {
            panel.style.display = 'block';
            button.innerHTML = '<i class="fas fa-times"></i> Hide Controls';
            button.classList.remove('btn-primary');
            button.classList.add('btn-secondary');
        } else {
            panel.style.display = 'none';
            button.innerHTML = '<i class="fas fa-cog"></i> Route Controls';
            button.classList.remove('btn-secondary');
            button.classList.add('btn-primary');
        }
    });

    // Close route controls
    document.getElementById('closeRouteControls').addEventListener('click', function() {
        const panel = document.getElementById('routeControlsPanel');
        const toggleButton = document.getElementById('toggleRouteControls');
        
        panel.style.display = 'none';
        toggleButton.innerHTML = '<i class="fas fa-cog"></i> Route Controls';
        toggleButton.classList.remove('btn-secondary');
        toggleButton.classList.add('btn-primary');
    });

    // Address search functionality
    document.getElementById('searchAddressBtn').addEventListener('click', function() {
        console.log('Search button clicked');
        searchAddress();
    });

    // Return time field is always visible (round trip only)
    const returnTimeField = document.getElementById('returnTime').closest('.mb-3');
    returnTimeField.style.display = 'block';

     // Allow Enter key to trigger search
     document.getElementById('addressSearch').addEventListener('keypress', function(e) {
         if (e.key === 'Enter') {
             e.preventDefault();
             console.log('Enter key pressed');
             searchAddress();
         }
     });

    // View route buttons in table
    document.querySelectorAll('.view-route-btn').forEach(button => {
        button.addEventListener('click', function() {
            const startLat = parseFloat(this.getAttribute('data-startlat'));
            const startLng = parseFloat(this.getAttribute('data-startlng'));
            const endLat = parseFloat(this.getAttribute('data-endlat'));
            const endLng = parseFloat(this.getAttribute('data-endlng'));

            if (routingControl) routingMap.removeControl(routingControl);

            const osrmRouter = L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                profile: 'car',
                timeout: 15000,
                routingOptions: { alternatives: true, steps: true, annotations: true, geometries: 'polyline6', overview: 'full' },
                urlParameters: { geometries: 'polyline6', overview: 'full' }
            });

            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(startLat, startLng),
                    L.latLng(endLat, endLng)
                ],
                router: osrmRouter,
                routeWhileDragging: false,
                show: false,
                lineOptions: { styles: [{ color: '#00b4d8', weight: 4, opacity: 0.9 }] }
            }).addTo(routingMap);

            routingMap.fitBounds([
                [startLat, startLng],
                [endLat, endLng]
            ]);
        });
    });
});

// Intelligent route calculation
async function calculateIntelligentRoutes() {
    try {
        // Always round trip - no single trip option
        const isRoundTrip = true;
        
        // Show loading
        Swal.fire({
            title: 'Calculating Round Trip Routes...',
            text: 'Finding optimal round trip routes (A to B to A)',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Clear existing routes
        if (routingControl) {
            routingMap.removeControl(routingControl);
            routingControl = null;
        }
        // Clear any conceptual route line if it exists from previous state
        if (window.currentRouteLine) {
            routingMap.removeLayer(window.currentRouteLine);
            window.currentRouteLine = null;
        }

        console.log('Starting route calculation...');
        console.log('Start point:', startPoint);
        console.log('End point:', endPoint);

        // Calculate single optimal route
        const calculatedRoute = await calculateSingleRoute(true); // Always round trip
        
        console.log('Calculated route:', calculatedRoute);
        
        if (!calculatedRoute) {
            Swal.close();
            Swal.fire('Error', 'Could not calculate route. Please try different points.', 'error');
            return;
        }
            
        // Select the calculated route and display it on map
        selectRoute(calculatedRoute);

        // Show the route summary
        document.getElementById('routeSummary').classList.remove('d-none');

        console.log('Route calculation completed.');
        Swal.close();

    } catch (error) {
        console.error('Error calculating intelligent routes:', error);
        Swal.fire('Error', 'An unexpected error occurred during route calculation.', 'error');
    }
}

// Calculate multiple routes using different OSRM profiles and parameters
async function calculateSingleRoute(isRoundTrip = true) {
    try {
        // Use fastest route strategy
        const strategy = {
            name: '🚗 Fastest Route',
            description: 'Optimized for speed, typically uses highways and main roads.',
            profile: 'driving',
            avoid: [],
            color: '#00b4d8'
        };
        
        // Calculate round trip route (A to B to A)
        const route = await calculateRoundTripRoute(strategy);
        
        if (route && route.summary && isRoutePlausible(route)) {
            return {
                routeName: `🔄 ${strategy.name} (Round Trip)`,
                summary: route.summary,
                routeDescription: `${strategy.description} - Round trip (A to B to A)`,
                actualRoute: route,
                color: strategy.color,
                isRoundTrip: true
            };
        } else {
            console.log('Primary route not plausible, trying fallback...');
            // Try fallback route
            const fallbackRoute = await calculateSingleRouteWithFallback(strategy);
            if (fallbackRoute && fallbackRoute.summary) {
                return {
                    routeName: strategy.name,
                    summary: fallbackRoute.summary,
                    routeDescription: strategy.description,
                    actualRoute: fallbackRoute,
                    color: strategy.color,
                    isRoundTrip: isRoundTrip
                };
            }
        }
        
        return null;
    } catch (error) {
        console.error('Error calculating single route:', error);
        return null;
    }
}

// Basic sanity checks to avoid broken/implausible routes
function isRoutePlausible(route) {
    try {
        if (!route || !route.summary) return false;
        const d = route.summary.totalDistance; // meters
        const t = route.summary.totalTime;     // seconds
        if (!Number.isFinite(d) || !Number.isFinite(t)) return false;
        if (d <= 0 || t <= 0) return false;
        // Cap at 300 km and 8 hours for local city trips; adjust as needed
        if (d > 300000 || t > 8 * 3600) return false;
        // Average speed sanity (3 km/h to 120 km/h)
        const avgSpeedKmh = (d / 1000) / (t / 3600);
        if (avgSpeedKmh < 3 || avgSpeedKmh > 120) return false;
        // Must have at least a handful of coordinates
        const coords = route.coordinates || [];
        if (!Array.isArray(coords) || coords.length < 5) return false;
        
        // Additional check for defense: ensure route doesn't have too many unnecessary turns
        if (coords.length > 100) { // Relaxed from 50 to 100
            // If route has too many coordinate points, it might be overly circuitous
            const straightLineDistance = calculateDistance(startPoint.lat, startPoint.lng, endPoint.lat, endPoint.lng);
            const detourRatio = d / (straightLineDistance * 1000);
            if (detourRatio > 5.0) { // Relaxed from 3.0 to 5.0 for defense
                console.log('Route rejected: excessive detour ratio', detourRatio);
                return false;
            }
        }
        
        return true;
    } catch (_) {
        return false;
    }
}

// Calculate round trip route (A to B to A)
async function calculateRoundTripRoute(strategy) {
    try {
        // Calculate A to B route
        const routeAB = await calculateSingleRouteWithFallback(strategy);
        
        if (!routeAB || !routeAB.summary) {
            throw new Error('Failed to calculate A to B route');
        }
        
        // Calculate B to A route (return journey) with proper start/end points
        const returnStrategy = {
            ...strategy,
            name: `${strategy.name} (Return)`
        };
        
        const routeBA = await calculateSingleRouteWithFallback(returnStrategy, endPoint, startPoint);
        
        if (!routeBA || !routeBA.summary) {
            throw new Error('Failed to calculate B to A route');
        }
        
        // Ensure the return route starts exactly at the destination point
        const adjustedReturnCoords = [
            [endPoint.lat, endPoint.lng], // Start exactly at B
            ...routeBA.coordinates.slice(1) // Rest of the return route
        ];
        
        // Ensure the return route ends exactly at the starting point
        const finalCoords = [
            ...adjustedReturnCoords,
            [startPoint.lat, startPoint.lng] // End exactly at A
        ];
        
        // Combine both routes with proper connection
        const combinedRoute = {
            summary: {
                totalDistance: routeAB.summary.totalDistance + routeBA.summary.totalDistance,
                totalTime: routeAB.summary.totalTime + routeBA.summary.totalTime
            },
            coordinates: finalCoords,
            instructions: [
                ...routeAB.instructions,
                { text: 'Return journey begins', distance: 0, time: 0 },
                ...routeBA.instructions,
                { text: 'Arrived back at starting point', distance: 0, time: 0 }
            ],
            isRoundTrip: true
        };
        
        return combinedRoute;
    } catch (error) {
        console.error('Round trip calculation failed:', error);
        throw error;
    }
}

// Calculate single route with fallback to alternative OSRM servers
async function calculateSingleRouteWithFallback(strategy, customStartPoint = null, customEndPoint = null) {
    // Use custom points if provided, otherwise use global startPoint and endPoint
    const fromPoint = customStartPoint || startPoint;
    const toPoint = customEndPoint || endPoint;
    // Try multiple OSRM servers (more reliable ones first)
    const osrmServers = [
        'https://router.project-osrm.org',
        'https://routing.openstreetmap.de',
        'https://osrm-routing.herokuapp.com',
        'https://router.project-osrm.org/route/v1'
    ];
    
    for (const server of osrmServers) {
        try {
            const route = await calculateSingleRouteFromServer(strategy, server, fromPoint, toPoint);
            if (route) {
                console.log(`Successfully got route from ${server}`);
                return route;
            }
        } catch (error) {
            console.log(`Failed to get route from ${server}:`, error.message);
            continue;
        }
    }
    
    throw new Error('All OSRM servers failed');
}

// Calculate single route from specific OSRM server
async function calculateSingleRouteFromServer(strategy, server, fromPoint = null, toPoint = null) {
    // Use provided points or fall back to global points
    const start = fromPoint || startPoint;
    const end = toPoint || endPoint;
    return new Promise((resolve, reject) => {
        const avoidParams = strategy.avoid.length > 0 ? `&avoid=${strategy.avoid.join(',')}` : '';
        // Add alternatives parameter to get multiple route options from OSRM
        const alternativesParam = strategy.name.includes('Alternative') ? '&alternatives=3' : '';
        const url = `${server}/route/v1/${strategy.profile}/${start.lng},${start.lat};${end.lng},${end.lat}?overview=full&steps=true&geometries=geojson${avoidParams}${alternativesParam}`;
        
        console.log(`Trying OSRM URL: ${url}`);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'SmartTrack-RoutePlanner/1.0'
            },
            timeout: 10000 // 10 second timeout
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('OSRM Response:', data);
                
                if (data.routes && data.routes.length > 0) {
                    const route = data.routes[0];
                    
                    // Check if we have valid geometry
                    if (route.geometry && route.geometry.coordinates && route.geometry.coordinates.length > 0) {
                        // Convert OSRM response to Leaflet Routing Machine format
                        const leafletRoute = {
                            summary: {
                                totalDistance: route.distance,
                                totalTime: route.duration
                            },
                            coordinates: route.geometry.coordinates.map(coord => [coord[1], coord[0]]),
                            instructions: route.legs[0].steps.map(step => ({
                                text: step.maneuver.instruction,
                                distance: step.distance,
                                time: step.duration
                            }))
                        };
                        resolve(leafletRoute);
                    } else {
                        reject(new Error('No valid geometry in OSRM response'));
                    }
                } else if (data.message) {
                    reject(new Error(`OSRM Error: ${data.message}`));
                } else {
                    reject(new Error('No route found in OSRM response'));
                }
            })
            .catch(error => {
                console.error(`OSRM request failed for ${strategy.name}:`, error);
                reject(error);
            });
    });
}

// Create fallback routes when OSRM is unavailable
function createFallbackRoutes() {
    const fallbackRoutes = [];
    const baseDistance = startPoint.distanceTo(endPoint) / 1000; // km
    const baseDuration = baseDistance * 1.5 * 60; // seconds (1.5 min per km)
    
    // For round trips, double the distance and duration
    const roundTripDistance = baseDistance * 2;
    const roundTripDuration = baseDuration * 2;
    
    // Create 3 different fallback routes
    const fallbackStrategies = [
        {
            name: '🚗 Fastest Route',
            description: 'Optimized for speed using main roads.',
            distanceFactor: 1.0,
            durationFactor: 1.0,
            color: '#00b4d8'
        },
        {
            name: '📏 Shortest Distance',
            description: 'Minimizes total distance.',
            distanceFactor: 0.95,
            durationFactor: 1.2,
            color: '#28a745'
        },
        {
            name: '🌄 Scenic Route',
            description: 'Beautiful path with scenic views.',
            distanceFactor: 1.2,
            durationFactor: 1.5,
            color: '#fd7e14'
        }
    ];

    fallbackStrategies.forEach((strategy, index) => {
        // Create a simple round trip route with waypoints
        const waypoints = createWaypointsForFallback(strategy, index);
        
        fallbackRoutes.push({
            routeName: `🔄 ${strategy.name} (Round Trip)`,
            summary: {
                totalDistance: roundTripDistance * strategy.distanceFactor * 1000, // meters (round trip)
                totalTime: roundTripDuration * strategy.durationFactor // seconds (round trip)
            },
            routeDescription: strategy.description + ' - Round trip (A to B to A) (Estimated route)',
            actualRoute: {
                coordinates: waypoints,
                instructions: [
                    { text: 'Start at origin (A)', distance: 0, time: 0 },
                    { text: 'Travel to destination (B)', distance: baseDistance * strategy.distanceFactor * 1000, time: baseDuration * strategy.durationFactor },
                    { text: 'Return journey begins', distance: 0, time: 0 },
                    { text: 'Return to origin (A)', distance: baseDistance * strategy.distanceFactor * 1000, time: baseDuration * strategy.durationFactor }
                ]
            },
            color: strategy.color,
            isRoundTrip: true
        });
    });

    return fallbackRoutes;
}

// Create waypoints for fallback routes (round trip)
function createWaypointsForFallback(strategy, index) {
    const waypoints = [startPoint];
    
    // Calculate distance between points
    const distance = startPoint.distanceTo(endPoint);
    const numWaypoints = Math.max(3, Math.floor(distance / 1000)); // More waypoints for longer distances
    
    // Create outbound waypoints (A to B)
    const outboundWaypoints = [];
    if (strategy.name.includes('Fastest')) {
        // Highway-like route with fewer turns
        for (let i = 1; i < numWaypoints; i++) {
            const progress = i / numWaypoints;
            const waypoint = L.latLng(
                startPoint.lat + (endPoint.lat - startPoint.lat) * progress + (Math.random() - 0.5) * 0.0005,
                startPoint.lng + (endPoint.lng - startPoint.lng) * progress + (Math.random() - 0.5) * 0.0005
            );
            outboundWaypoints.push(waypoint);
        }
    } else if (strategy.name.includes('Shortest')) {
        // More direct path with minimal waypoints
        const midPoint = L.latLng(
            (startPoint.lat + endPoint.lat) / 2 + (Math.random() - 0.5) * 0.0003,
            (startPoint.lng + endPoint.lng) / 2 + (Math.random() - 0.5) * 0.0003
        );
        outboundWaypoints.push(midPoint);
    } else if (strategy.name.includes('Scenic')) {
        // Scenic route with more waypoints and curves
        for (let i = 1; i < numWaypoints + 2; i++) {
            const progress = i / (numWaypoints + 2);
            const curve = Math.sin(progress * Math.PI * 2) * 0.001; // Add some curves
            const waypoint = L.latLng(
                startPoint.lat + (endPoint.lat - startPoint.lat) * progress + curve + (Math.random() - 0.5) * 0.0008,
                startPoint.lng + (endPoint.lng - startPoint.lng) * progress + curve + (Math.random() - 0.5) * 0.0008
            );
            outboundWaypoints.push(waypoint);
        }
    }
    
    // Add outbound waypoints
    waypoints.push(...outboundWaypoints);
    waypoints.push(endPoint); // Destination (B)
    
    // Create return waypoints (B to A) - reverse the outbound path with slight variation
    const returnWaypoints = [];
    for (let i = outboundWaypoints.length - 1; i >= 0; i--) {
        const originalWaypoint = outboundWaypoints[i];
        // Add slight variation to make it look like a different path
        const variation = (Math.random() - 0.5) * 0.0002;
        const returnWaypoint = L.latLng(
            originalWaypoint.lat + variation,
            originalWaypoint.lng + variation
        );
        returnWaypoints.push(returnWaypoint);
    }
    
    // Add return waypoints
    waypoints.push(...returnWaypoints);
    waypoints.push(startPoint); // Return to start (A)
    
    return waypoints;
}



// Calculate route with an intermediate waypoint to get different path
async function calculateRouteWithWaypoint(customWaypoint = null) {
    let waypoint;
    
    if (customWaypoint) {
        waypoint = `${customWaypoint[0]},${customWaypoint[1]}`;
    } else {
        // Calculate midpoint and offset it slightly
        const midLat = (startPoint.lat + endPoint.lat) / 2;
        const midLng = (startPoint.lng + endPoint.lng) / 2;
        
        // Offset the midpoint by a small amount to create a different route
        const offsetLat = midLat + (Math.random() - 0.5) * 0.01; // ±0.005 degrees
        const offsetLng = midLng + (Math.random() - 0.5) * 0.01;
        
        waypoint = `${offsetLng},${offsetLat}`;
    }
    
    // Try multiple OSRM servers
    const osrmServers = [
        'https://router.project-osrm.org',
        'https://osrm-routing.herokuapp.com',
        'https://routing.openstreetmap.de'
    ];
    
    for (const server of osrmServers) {
        try {
            const url = `${server}/route/v1/driving/${startPoint.lng},${startPoint.lat};${waypoint};${endPoint.lng},${endPoint.lat}?overview=full&steps=true&geometries=geojson`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'User-Agent': 'SmartTrack-RoutePlanner/1.0'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                
                if (route.geometry && route.geometry.coordinates && route.geometry.coordinates.length > 0) {
                    const leafletRoute = {
                        summary: {
                            totalDistance: route.distance,
                            totalTime: route.duration
                        },
                        coordinates: route.geometry.coordinates.map(coord => [coord[1], coord[0]]),
                        instructions: route.legs[0].steps.map(step => ({
                            text: step.maneuver.instruction,
                            distance: step.distance,
                            time: step.duration
                        }))
                    };
                    return leafletRoute;
                }
            }
        } catch (error) {
            console.log(`Waypoint route failed from ${server}:`, error.message);
            continue;
        }
    }
    
    throw new Error('All OSRM servers failed for waypoint route');
}

// calculateRouteAlternatives function removed - logic now integrated into calculateIntelligentRoutes

// Calculate distance between two points using Haversine formula
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the Earth in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distance = R * c; // Distance in kilometers
    return distance;
}

// Helper functions removed - using simplified route calculation

// Route configuration is now handled in calculateRouteAlternatives function

// Update route summary display
function updateRouteSummary(route) {
    const distance = (route.summary.totalDistance / 1000).toFixed(2);
    const duration = Math.round(route.summary.totalTime / 60);
    
    document.getElementById('summaryDistance').textContent = `${distance} km`;
    document.getElementById('summaryDuration').textContent = `${duration} min`;
    
    
    // Update modal fields
    document.getElementById('routeDistance').value = `${distance} km`;
    document.getElementById('routeDuration').value = `${duration} min`;
    
    // Update intelligent routing fields
    const routeTypeLabels = {
        'fastest': '🚗 Fastest Route',
        'shortest': '📏 Shortest Distance',
        'avoid_tolls': '💰 Avoid Tolls',
        'avoid_highways': '🛣️ Avoid Highways',
        'scenic': '🌄 Scenic Route'
    };
    
    document.getElementById('modalRouteType').value = routeTypeLabels[routeConfig.type] || 'Fastest Route';
    
    // Show summary
    document.getElementById('routeSummary').classList.remove('d-none');
}

// Get traffic level based on time of day

// Display route alternatives

// Select a specific route
function selectRoute(route) {
    selectedRouteIndex = 0;
    
    const selectedRoute = route;
    
    // Remove existing routing control and route lines
    if (routingControl) {
        routingMap.removeControl(routingControl);
        routingControl = null;
    }
    if (window.currentRouteLine) {
        routingMap.removeLayer(window.currentRouteLine);
        window.currentRouteLine = null;
    }
    
    // Display the selected route on the map
    if (selectedRoute.actualRoute && selectedRoute.actualRoute.coordinates) {
        // This is an actual route - display the actual path
        const routeColor = selectedRoute.color || '#00b4d8';
        
        const routeLine = L.polyline(selectedRoute.actualRoute.coordinates, {
            color: routeColor,
            weight: 6,
            opacity: 0.8
        }).addTo(routingMap);
        
        // Add direction arrows for round trip
        if (selectedRoute.isRoundTrip && selectedRoute.actualRoute.coordinates.length > 10) {
            const coords = selectedRoute.actualRoute.coordinates;
            const midPoint = Math.floor(coords.length / 2);
            
            // Add arrow at midpoint to show direction
            const midCoord = coords[midPoint];
            const nextCoord = coords[midPoint + 1];
            
            if (midCoord && nextCoord) {
                const angle = Math.atan2(nextCoord[1] - midCoord[1], nextCoord[0] - midCoord[0]) * 180 / Math.PI;
                
                const arrowIcon = L.divIcon({
                    className: 'direction-arrow',
                    html: `<div style="transform: rotate(${angle}deg); font-size: 20px; color: ${routeColor}; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">→</div>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                
                L.marker([midCoord[0], midCoord[1]], { icon: arrowIcon }).addTo(routingMap);
            }
        }
        
        // Store the route line
        window.currentRouteLine = routeLine;
        
        // Add markers for key points in round trip
        if (selectedRoute.isRoundTrip) {
            // Clear existing markers first
            routingMap.eachLayer((layer) => {
                if (layer instanceof L.Marker) {
                    routingMap.removeLayer(layer);
                }
            });
            
            // Add start point marker (A)
            const startMarker = L.marker([startPoint.lat, startPoint.lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: #28a745; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">A</div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(routingMap);
            startMarker.bindPopup('Start Point (A)').openPopup();
            
            // Add destination point marker (B)
            const endMarker = L.marker([endPoint.lat, endPoint.lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: #dc3545; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">B</div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(routingMap);
            endMarker.bindPopup('Destination Point (B)');
            
            // Add return point marker (A again) at the end
            const returnMarker = L.marker([startPoint.lat, startPoint.lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: #17a2b8; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">A</div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(routingMap);
            returnMarker.bindPopup('Return Point (A) - End of Round Trip');
        }
        
        // Fit map to show the entire route
        const bounds = L.latLngBounds(selectedRoute.actualRoute.coordinates);
        routingMap.fitBounds(bounds, { padding: [20, 20] });
    
    // Show success message
    Swal.fire({
        title: 'Round Trip Selected!',
            text: `${selectedRoute.routeName} has been selected for assignment`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
    } else if (selectedRoute.isSimulated) {
        // For simulated routes, show a dashed line to indicate it's not a real route
        const routeColor = selectedRoute.color || '#00b4d8';
        
        const routeLine = L.polyline([startPoint, endPoint], {
            color: routeColor,
            weight: 6,
            opacity: 0.8,
            dashArray: '15, 10' // Longer dashes for simulated routes
        }).addTo(routingMap);
        
        // Store the route line
        window.currentRouteLine = routeLine;
        
        // Show info message for simulated route
        Swal.fire({
            title: 'Simulated Route Selected',
            text: `${selectedRoute.routeName} is a simulated alternative for demonstration purposes`,
            icon: 'info',
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        // Fallback to straight line if no coordinates available
        const routeColor = selectedRoute.color || '#00b4d8';
        
        const routeLine = L.polyline([startPoint, endPoint], {
            color: routeColor,
            weight: 6,
            opacity: 0.8,
            dashArray: '10, 10'
        }).addTo(routingMap);
        
        // Store the route line
        window.currentRouteLine = routeLine;
        
        // Show info message
        Swal.fire({
            title: 'Round Trip Selected!',
            text: `${selectedRoute.routeName} has been selected for assignment`,
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    // Update summary and modal fields
    updateRouteSummary(selectedRoute);
    
    // Update route type in modal (always round trip)
    document.getElementById('modalRouteType').value = 'round_trip';
    
    // Always show return time field (round trip only)
    const returnTimeField = document.getElementById('returnTime').closest('.mb-3');
    returnTimeField.style.display = 'block';
    
    // Generate unique route name if not already set
    const routeNameInput = document.getElementById('routeName');
    if (routeNameInput && !routeNameInput.value) {
        const timestamp = new Date().toLocaleString('en-PH', { 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        const startName = startPoint ? `${startPoint.lat.toFixed(4)}, ${startPoint.lng.toFixed(4)}` : 'Start';
        const endName = endPoint ? `${endPoint.lat.toFixed(4)}, ${endPoint.lng.toFixed(4)}` : 'End';
        routeNameInput.value = `Round Trip: ${startName} to ${endName} (${timestamp})`;
    }
}

// Reset all points and markers
function resetPoints() {
    // Clear points
    startPoint = null;
    endPoint = null;
    
    // Clear form fields
    document.getElementById('pointA').value = '';
    document.getElementById('pointB').value = '';
     document.getElementById('addressSearch').value = '';
    document.getElementById('startLat').value = '';
    document.getElementById('startLng').value = '';
    document.getElementById('endLat').value = '';
    document.getElementById('endLng').value = '';
    
    // Clear markers
    routingMap.eachLayer((layer) => {
        if (layer instanceof L.Marker) {
            routingMap.removeLayer(layer);
        }
    });
    
    // Clear routes
    if (routingControl) {
        routingMap.removeControl(routingControl);
        routingControl = null;
    }
    
    // Clear route line (for conceptual routes)
    if (window.currentRouteLine) {
        routingMap.removeLayer(window.currentRouteLine);
        window.currentRouteLine = null;
    }
    
    selectedRouteIndex = 0;
    
    // Hide route summary
    document.getElementById('routeSummary').classList.add('d-none');
    
    // Reset to Philippines view
    routingMap.setView([10.417387, 122.955502], 13);
    
    Swal.fire({
        title: 'Points Reset!',
        text: 'All points have been cleared. Click on the map to set new points.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

// Fetch latest vehicle location
async function getLatestVehicleLocation() {
    try {
        const response = await fetch(`../get_latest_location.php?device_id=${deviceID}`);
        const data = await response.json();
        
        if (!data.lat || !data.lng) {
            throw new Error('No location data available');
        }
        
        const lat = parseFloat(data.lat);
        const lng = parseFloat(data.lng);
        
        // Validate coordinates are reasonable (not in the middle of the ocean)
        if (isNaN(lat) || isNaN(lng)) {
            throw new Error('Invalid coordinate data');
        }
        
        // Check if coordinates are within reasonable bounds
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            throw new Error('Coordinates out of valid range');
        }
        
        // Check if coordinates are in the middle of the ocean (likely invalid)
        if (lat > 45 && lat < 50 && lng > 150 && lng < 160) {
            console.warn('Coordinates appear to be in the Pacific Ocean - likely invalid GPS data');
            console.log('GPS Coordinates:', lat, lng);
            throw new Error('Invalid GPS coordinates - device may need GPS signal');
        }
        
        // Check if coordinates are in Philippines region (valid)
        if (lat >= 4 && lat <= 21 && lng >= 116 && lng <= 127) {
            console.log('Valid Philippines coordinates detected:', lat, lng);
        }
        
        console.log('GPS Coordinates received:', lat, lng);
        return L.latLng(lat, lng);
    } catch (err) {
        console.error('Location fetch error:', err);
        
        // Show user-friendly error message
        if (err.message.includes('Invalid GPS coordinates')) {
            Swal.fire({
                title: 'GPS Signal Issue',
                text: 'Your ESP32 device has invalid GPS coordinates (Pacific Ocean). This usually means the GPS module needs time to acquire satellite signal or there\'s a GPS hardware issue. You can still plan routes by clicking on the map.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else if (err.message.includes('No location data available')) {
            Swal.fire({
                title: 'No GPS Data',
                text: 'No GPS coordinates found for ESP32-01. The device may need time to acquire GPS signal.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
        } else {
            Swal.fire({
                title: 'Location Unavailable',
                text: 'Could not fetch vehicle location. The ESP32 device may be offline.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }
        
        return null;
    }
   }
 
  // Helper function to set location on map
  function setLocationOnMap(location, originalAddress) {
      const lat = parseFloat(location.lat);
      const lng = parseFloat(location.lon);
      
      // Set end point
      endPoint = L.latLng(lat, lng);
      
      // Update form fields
      document.getElementById('pointB').value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
      document.getElementById('endLat').value = lat.toFixed(6);
      document.getElementById('endLng').value = lng.toFixed(6);
      
      // Clear any existing end point markers
      routingMap.eachLayer((layer) => {
          if (layer instanceof L.Marker && layer._popup && layer._popup.getContent().includes('End Point')) {
              routingMap.removeLayer(layer);
          }
      });
      
      // Add marker for end point
      const marker = L.marker(endPoint).addTo(routingMap);
      marker.bindPopup(`End Point (B): ${location.display_name || originalAddress}`).openPopup();
      
      // Fit map to show both points if start point exists
      if (startPoint) {
          const bounds = L.latLngBounds([startPoint, endPoint]);
          routingMap.fitBounds(bounds, { padding: [20, 20] });
      } else {
          routingMap.setView(endPoint, 15);
      }
      
             Swal.fire({
           title: 'Address Found!',
           text: `Location set to: ${location.display_name || originalAddress}`,
           icon: 'success',
           timer: 2000,
           showConfirmButton: false
       });
       
       // Auto-calculate routes if both points are set
       if (startPoint && endPoint) {
           setTimeout(() => {
               calculateIntelligentRoutes();
           }, 1500);
       }
  }
 
    // Address search function using Nominatim (OpenStreetMap)
  // Helper: fetch JSON with timeout and retries
  async function fetchJsonWithRetry(urls, options = {}, retries = 2, timeoutMs = 8000) {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);
      try {
          for (let i = 0; i < urls.length; i++) {
              try {
                  const res = await fetch(urls[i], {
                      ...options,
                      signal: controller.signal,
                      headers: {
                          'Accept': 'application/json',
                          'User-Agent': 'SmartTrack/1.0 (+https://example.com)'
                      }
                  });
                  if (!res.ok) throw new Error(`HTTP ${res.status}`);
                  const json = await res.json();
                  clearTimeout(timer);
                  return json;
              } catch (e) {
                  if (i === urls.length - 1) throw e;
              }
          }
      } catch (err) {
          if (retries > 0) {
              await new Promise(r => setTimeout(r, 1000));
              return fetchJsonWithRetry(urls, options, retries - 1, timeoutMs);
          }
          clearTimeout(timer);
          throw err;
      }
  }

  async function searchAddress() {
      console.log('searchAddress function called');
      const addressInput = document.getElementById('addressSearch');
      const address = addressInput.value.trim();
      
      console.log('Searching for address:', address);
      
      if (!address) {
          Swal.fire('Error', 'Please enter an address to search', 'error');
          return;
      }
     
     try {
         // Show loading
         Swal.fire({
             title: 'Searching Address...',
             text: 'Finding location on map',
             allowOutsideClick: false,
             didOpen: () => {
                 Swal.showLoading();
             }
         });
         
         // Use server-side proxy to avoid browser CORS and rate limits
         const data = await fetchJsonWithRetry([
             `geocode.php?q=${encodeURIComponent(address + ' Philippines')}&limit=5`
         ], {}, 1, 10000);
         
                   if (data && data.length > 0) {
              // If multiple results, show selection dialog
              if (data.length > 1) {
                  const options = data.map((item, index) => 
                      `${index + 1}. ${item.display_name}`
                  ).join('\n');
                  
                                     // Create a custom selection dialog
                   const result = await Swal.fire({
                       title: 'Multiple Locations Found',
                       html: `
                           <div style="text-align: left; max-height: 300px; overflow-y: auto;">
                               <p style="margin-bottom: 15px; color: #666;">Please select the correct location:</p>
                                                               ${data.map((item, index) => 
                                    `<div class="location-option" data-index="${index}" style="margin: 8px 0; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.2s; background-color: white;" onmouseover="if(!this.classList.contains('selected')) { this.style.borderColor='#00b4d8'; this.style.backgroundColor='#f8f9fa' }" onmouseout="if(!this.classList.contains('selected')) { this.style.borderColor='#e9ecef'; this.style.backgroundColor='white' }">
                                        <div style="font-weight: 600; color: #003566; display: flex; align-items: center;">
                                            <span style="margin-right: 8px;">${index + 1}.</span>
                                            <span>${item.display_name.split(',')[0]}</span>
                                        </div>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 4px; margin-left: 20px;">${item.display_name}</div>
                                    </div>`
                                ).join('')}
                           </div>
                       `,
                       showCancelButton: true,
                       confirmButtonText: 'Select Location',
                       cancelButtonText: 'Cancel',
                       width: '600px',
                                               didOpen: () => {
                            // Add click handlers to location options
                            const locationOptions = document.querySelectorAll('.location-option');
                            locationOptions.forEach((option, index) => {
                                option.addEventListener('click', function() {
                                    console.log('Location option clicked:', index);
                                    
                                    // Remove previous selections
                                    locationOptions.forEach(opt => {
                                        opt.style.borderColor = '#e9ecef';
                                        opt.style.backgroundColor = 'white';
                                        opt.classList.remove('selected');
                                    });
                                    
                                    // Highlight selected option
                                    this.style.borderColor = '#00b4d8';
                                    this.style.backgroundColor = '#e3f2fd';
                                    this.classList.add('selected');
                                    
                                    // Store selected index
                                    window.selectedLocationIndex = index;
                                    console.log('Selected index set to:', window.selectedLocationIndex);
                                });
                            });
                            
                            // Set default selection to first option
                            if (locationOptions.length > 0) {
                                window.selectedLocationIndex = 0;
                                locationOptions[0].style.borderColor = '#00b4d8';
                                locationOptions[0].style.backgroundColor = '#e3f2fd';
                                locationOptions[0].classList.add('selected');
                            }
                        },
                       preConfirm: () => {
                           return window.selectedLocationIndex !== undefined ? window.selectedLocationIndex : 0;
                       }
                   });
                   
                   if (result.isDismissed || result.isDenied) {
                       return; // User cancelled
                   }
                   
                   // Get the selected location
                   const location = data[result.value || 0];
                  setLocationOnMap(location, address);
                  
              } else {
                  // Single result
                  const location = data[0];
                  setLocationOnMap(location, address);
              }
              
          } else {
              // Try a broader search without Philippines suffix
              const broaderSearchUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=3&addressdetails=1&viewbox=116.0,4.0,127.0,21.0&bounded=1`;
              
              const broaderData = await fetchJsonWithRetry([
                  `geocode.php?q=${encodeURIComponent(address)}&limit=3`
              ], {}, 1, 10000);
              
              if (broaderData && broaderData.length > 0) {
                  // Filter results to ensure they're in Philippines
                  const philippinesResults = broaderData.filter(item => {
                      const displayName = item.display_name.toLowerCase();
                      return displayName.includes('philippines') || 
                             displayName.includes('ph') ||
                             (item.address && (
                                 item.address.country === 'Philippines' ||
                                 item.address.state?.toLowerCase().includes('philippines')
                             ));
                  });
                  
                  if (philippinesResults.length > 0) {
                      const location = philippinesResults[0];
                      setLocationOnMap(location, address);
                  } else {
                      Swal.fire('Error', 'Address not found in Philippines. Please try a different address or be more specific.', 'error');
                  }
              } else {
                  Swal.fire('Error', 'Address not found. Please try a different address or be more specific.', 'error');
              }
          }
         
     } catch (error) {
         console.error('Address search error:', error);
         console.error('Error details:', error.message);
         console.error('Searched address:', address);
         
         // Provide more helpful error message
         Swal.fire({
             title: 'Address Search Failed',
             html: `
                 <p class="mb-3">Could not find the address: <strong>"${address}"</strong></p>
                 <div class="alert alert-info text-start">
                     <strong>What you can do:</strong>
                     <ul class="mb-0 mt-2">
                         <li>Try a more specific address (e.g., "Bago City Hall, Bago City")</li>
                         <li>Check for typos in the address</li>
                         <li>Click directly on the map to set your destination</li>
                     </ul>
                 </div>
             `,
             icon: 'error',
             confirmButtonText: 'OK',
             width: '500px'
         });
     }
}
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
</script>
<script>
  document.addEventListener("DOMContentLoaded", function() {
    // Handle delete button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-btn')) {
            const deleteBtn = e.target.closest('.delete-btn');
            const routeId = deleteBtn.getAttribute('data-id');
            
            // Set the route ID in the modal
            document.getElementById('deleteRouteId').value = routeId;
            
            console.log('Delete button clicked for route ID:', routeId);
        }
        
        // Handle modern alert close button clicks
        if (e.target.closest('.btn-close-modern')) {
            const closeBtn = e.target.closest('.btn-close-modern');
            const alert = closeBtn.closest('.modern-alert');
            if (alert) {
                alert.style.display = 'none';
                console.log('Alert closed');
            }
        }
    });

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