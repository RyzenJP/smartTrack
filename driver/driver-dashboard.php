<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

// Check if session role is set and user is a driver
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$userRole = strtolower($_SESSION['role']);
if ($userRole !== 'driver') {
    header("Location: unauthorized.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// Get driver's vehicle info
$driverId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Get notifications (same logic as working simple dashboard)
$notifications = [];
$driverIdsToCheck = [];

// Find driver_id by username
if (!empty($username)) {
    $stmt = $conn->prepare("SELECT DISTINCT driver_id FROM vehicle_assignments WHERE driver_name = ? AND status = 'active'");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($matches as $match) {
        $driverIdsToCheck[] = $match['driver_id'];
    }
}

// Always add session user_id
$driverIdsToCheck[] = $driverId;
$driverIdsToCheck = array_unique($driverIdsToCheck);

// Get notifications for all possible driver IDs
foreach ($driverIdsToCheck as $checkId) {
    $stmt = $conn->prepare("SELECT * FROM driver_messages WHERE driver_id = ? ORDER BY sent_at DESC LIMIT 10");
    $stmt->bind_param('i', $checkId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notifications = array_merge($notifications, $results);
}

// Remove duplicates
$uniqueNotifications = [];
$seenIds = [];
foreach ($notifications as $notification) {
    if (!in_array($notification['id'], $seenIds)) {
        $uniqueNotifications[] = $notification;
        $seenIds[] = $notification['id'];
    }
}

// Sort by date
usort($uniqueNotifications, function($a, $b) {
    return strtotime($b['sent_at']) - strtotime($a['sent_at']);
});

// Query to get vehicle assigned to this driver
$vehicleQuery = $conn->prepare("
    SELECT fv.* 
    FROM fleet_vehicles fv
    JOIN vehicle_assignments va ON fv.id = va.vehicle_id
    WHERE va.driver_id = ?
");

if (!$vehicleQuery) {
    die("Prepare failed: " . $conn->error);
}

$vehicleQuery->bind_param("i", $driverId);
$vehicleQuery->execute();
$vehicle = $vehicleQuery->get_result()->fetch_assoc();
$vehicleQuery->close();

// Get recent location history for the vehicle (if assigned)
$recentLocations = [];
if ($vehicle) {
    $locationQuery = $conn->prepare("
        SELECT * FROM fleet_location_history 
        WHERE vehicle_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 10
    ");
    if ($locationQuery) {
        $locationQuery->bind_param("i", $vehicle['id']);
        $locationQuery->execute();
        $recentLocations = $locationQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $locationQuery->close();
    }
}

// Get maintenance records for the vehicle (if assigned)
$maintenanceRecords = [];
if ($vehicle) {
    $maintenanceQuery = $conn->prepare("
        SELECT * FROM maintenance_schedules 
        WHERE vehicle_id = ? 
        ORDER BY scheduled_date DESC 
        LIMIT 3
    ");
    if ($maintenanceQuery) {
        $maintenanceQuery->bind_param("i", $vehicle['id']);
        $maintenanceQuery->execute();
        $maintenanceRecords = $maintenanceQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $maintenanceQuery->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Driver Dashboard | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
   :root {
      --primary: #003566;
      --primary-light: #004080;
      --accent: #00b4d8;
      --accent-light: #33c5e8;
      --bg: #f8fafc;
      --card-bg: #ffffff;
      --text-primary: #1e293b;
      --text-secondary: #64748b;
      --border-color: #e2e8f0;
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
      --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
      --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      --gradient-accent: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
    }

    /* Mobile-first responsive base */
    body {
      font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      color: var(--text-primary);
      line-height: 1.6;
      overflow-x: hidden;
    }

    * {
      box-sizing: border-box;
    }

    .main-content {
      margin-left: 250px;
      margin-top: 60px;
      padding: 30px;
      transition: margin-left 0.3s ease;
      min-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    .navbar {
      position: fixed;      
      top: 0;                
      left: 0;       
      width: 100%;
      background: var(--card-bg);
      backdrop-filter: blur(10px);
      box-shadow: var(--shadow-md);
      border-bottom: 1px solid var(--border-color);
      z-index: 1100;
    }

    /* Professional Card Styling */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 16px;
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .card-header {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 20px 24px;
      font-weight: 600;
    }

    .card-header.bg-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important;
    }

    .card-header.bg-primary {
      background: var(--gradient-primary) !important;
    }

    .card-body {
      padding: 24px;
    }

    /* Professional Typography */
    h3 {
      font-weight: 700;
      color: var(--text-primary);
      font-size: 2rem;
      margin-bottom: 2rem;
    }

    h4 {
      font-weight: 600;
      color: var(--text-primary);
      font-size: 1.5rem;
    }

    h5 {
      font-weight: 600;
      color: var(--text-primary);
      font-size: 1.25rem;
    }

    h6 {
      font-weight: 600;
      color: var(--text-primary);
      font-size: 1rem;
    }

    /* Enhanced Badges */
    .badge {
      font-weight: 500;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 0.875rem;
    }

    .badge.bg-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    }

    .badge.bg-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    }

    .badge.bg-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    }

    /* Professional Buttons */
    .btn {
      font-weight: 500;
      border-radius: 10px;
      padding: 12px 24px;
      transition: all 0.3s ease;
      border: none;
      text-transform: none;
      font-size: 0.95rem;
    }

    .btn-primary {
      background: var(--gradient-primary);
      color: white;
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: var(--gradient-primary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .btn-outline-primary {
      color: var(--primary);
      border: 2px solid var(--primary);
      background: transparent;
    }

    .btn-outline-primary:hover {
      background: var(--primary);
      border-color: var(--primary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    /* Professional Icons */
    .card-icon {
      font-size: 2.5rem;
      background: var(--gradient-accent);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Enhanced Alerts */
    .alert {
      border: none;
      border-radius: 12px;
      padding: 16px 20px;
      font-weight: 500;
    }

    .alert-warning {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      border-left: 4px solid #f59e0b;
    }

    .alert-info {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      color: #1e40af;
      border-left: 4px solid #3b82f6;
    }

    /* Professional Spacing */
    .g-4 {
      --bs-gutter-x: 2rem;
      --bs-gutter-y: 2rem;
    }

    /* Enhanced Dropdown */
    .dropdown-menu {
      border-radius: 12px;
      border: none;
      box-shadow: var(--shadow-lg);
      min-width: 220px;
      padding: 8px;
      background: var(--card-bg);
      animation: fadeIn 0.25s ease-in-out;
    }

    .dropdown-menu .dropdown-item {
      display: flex;
      align-items: center;
      padding: 12px 16px;
      font-size: 0.95rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
      border-radius: 8px;
      margin: 2px 0;
    }

    .dropdown-menu .dropdown-item:hover {
      background: var(--gradient-primary);
      color: white;
      transform: translateX(4px);
    }

    .dropdown-menu .dropdown-item i {
      margin-right: 12px;
      color: var(--text-secondary);
      transition: color 0.3s ease;
      width: 16px;
      text-align: center;
    }

    .dropdown-menu .dropdown-item:hover i {
      color: white;
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
      transition: all 0.3s ease;
      padding: 8px;
      border-radius: 8px;
    }

    .burger-btn:hover {
      background: var(--border-color);
      transform: scale(1.1);
    }

    /* Professional Vehicle Info Card */
    .vehicle-info-card {
      background: var(--gradient-primary);
      color: white;
      border-radius: 16px;
      position: relative;
      overflow: hidden;
    }

    .vehicle-info-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 100px;
      height: 100px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      transform: translate(30px, -30px);
    }

    .vehicle-info-card .card-body {
      position: relative;
      z-index: 2;
    }

    /* Professional Navigation Card */
    .navigation-card {
      background: var(--gradient-accent);
      color: white;
      border-radius: 16px;
    }

    /* Enhanced Status Indicators */
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 500;
    }

    /* Professional Scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
    }

    ::-webkit-scrollbar-track {
      background: var(--border-color);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--accent);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary);
    }

    /* Mobile Responsive Enhancements */
    @media (max-width: 991.98px) {
      .main-content {
        margin-left: 0 !important;
        padding: 16px !important;
        margin-top: 60px !important;
        width: 100% !important;
      }
      
      .main-content.collapsed {
        margin-left: 0 !important;
      }
      
      .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
      }
      
      /* Header responsive */
      .d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 1rem !important;
      }
      
      .d-flex.justify-content-between h3 {
        font-size: 1.5rem !important;
        margin-bottom: 0.5rem !important;
        text-align: center !important;
      }
      
      .d-flex.justify-content-between .d-flex.align-items-center.gap-3 {
        justify-content: center !important;
        flex-wrap: wrap !important;
        gap: 1rem !important;
      }
      
      /* Cards responsive */
      .row.g-4 > * {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      .col-md-4, .col-md-8 {
        margin-bottom: 1.5rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      .card {
        margin-bottom: 1rem !important;
        width: 100% !important;
      }
      
      .card-body {
        padding: 16px !important;
      }
      
      .card-header {
        padding: 15px 20px !important;
        font-size: 0.9rem !important;
      }
      
      /* Vehicle info card mobile */
      .vehicle-info-card .card-body {
        padding: 20px 16px !important;
      }
      
      .vehicle-info-card h4 {
        font-size: 1.25rem !important;
      }
      
      .vehicle-info-card i.fas.fa-car {
        font-size: 2.5rem !important;
      }
      
      /* Navigation card mobile */
      .navigation-card .card-body {
        padding: 20px 16px !important;
      }
      
      .navigation-card h4 {
        font-size: 1.25rem !important;
      }
      
      .navigation-card i.fas.fa-route {
        font-size: 2.5rem !important;
      }
      
      .navigation-card .btn {
        font-size: 0.9rem !important;
        padding: 12px 20px !important;
      }
      
      /* Vehicle details section mobile */
      .row.align-items-center.mb-4 {
        flex-direction: column !important;
        text-align: center !important;
        gap: 1rem !important;
      }
      
      .col-md-2, .col-md-10 {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      .row.g-4 .col-md-6 {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      /* Buttons mobile */
      .d-flex.gap-3 {
        flex-direction: column !important;
        gap: 0.75rem !important;
      }
      
      .btn {
        width: 100% !important;
        font-size: 0.9rem !important;
        padding: 12px 20px !important;
      }
      
      /* Badges mobile */
      .badge {
        font-size: 0.75rem !important;
        padding: 6px 10px !important;
      }
      
      /* Notifications mobile */
      .alert {
        padding: 12px 15px !important;
        font-size: 0.85rem !important;
      }
      
      .alert .fw-bold {
        font-size: 0.9rem !important;
      }
      
      .alert .small {
        font-size: 0.8rem !important;
      }
      
      /* Status indicators mobile */
      .status-indicator {
        font-size: 0.75rem !important;
        padding: 4px 8px !important;
      }
      
      /* Typography mobile */
      h4 {
        font-size: 1.25rem !important;
      }
      
      h5 {
        font-size: 1.1rem !important;
      }
      
      h6 {
        font-size: 0.95rem !important;
      }
      
      p {
        font-size: 0.85rem !important;
      }
      
      small {
        font-size: 0.75rem !important;
      }
    }
    
    @media (max-width: 575.98px) {
      .main-content {
        padding: 12px !important;
        margin-top: 60px !important;
      }
      
      /* Header mobile */
      .d-flex.justify-content-between h3 {
        font-size: 1.3rem !important;
      }
      
      .d-flex.justify-content-between .text-end {
        text-align: center !important;
      }
      
      /* Cards mobile */
      .card-body {
        padding: 14px !important;
      }
      
      .card-header {
        padding: 12px 16px !important;
        font-size: 0.85rem !important;
      }
      
      /* Vehicle info card mobile */
      .vehicle-info-card .card-body {
        padding: 16px 12px !important;
      }
      
      .vehicle-info-card h4 {
        font-size: 1.1rem !important;
      }
      
      .vehicle-info-card i.fas.fa-car {
        font-size: 2rem !important;
      }
      
      /* Navigation card mobile */
      .navigation-card .card-body {
        padding: 16px 12px !important;
      }
      
      .navigation-card h4 {
        font-size: 1.1rem !important;
      }
      
      .navigation-card i.fas.fa-route {
        font-size: 2rem !important;
      }
      
      .navigation-card .btn {
        font-size: 0.85rem !important;
        padding: 10px 16px !important;
      }
      
      /* Buttons mobile */
      .btn {
        font-size: 0.85rem !important;
        padding: 10px 16px !important;
      }
      
      /* Badges mobile */
      .badge {
        font-size: 0.7rem !important;
        padding: 4px 8px !important;
      }
      
      /* Notifications mobile */
      .alert {
        padding: 10px 12px !important;
        font-size: 0.8rem !important;
      }
      
      .alert .fw-bold {
        font-size: 0.85rem !important;
      }
      
      .alert .small {
        font-size: 0.75rem !important;
      }
      
      /* Status indicators mobile */
      .status-indicator {
        font-size: 0.7rem !important;
        padding: 3px 6px !important;
      }
      
      /* Typography mobile */
      h4 {
        font-size: 1.1rem !important;
      }
      
      h5 {
        font-size: 1rem !important;
      }
      
      h6 {
        font-size: 0.9rem !important;
      }
      
      p {
        font-size: 0.8rem !important;
      }
      
      small {
        font-size: 0.7rem !important;
      }
      
      /* Icon sizes mobile */
      .fas {
        font-size: 0.9em !important;
      }
      
      /* Spacing mobile */
      .mb-3 {
        margin-bottom: 0.75rem !important;
      }
      
      .mb-4 {
        margin-bottom: 1rem !important;
      }
      
      .mb-5 {
        margin-bottom: 1.5rem !important;
      }
      
      .mt-3 {
        margin-top: 0.75rem !important;
      }
      
      .mt-4 {
        margin-top: 1rem !important;
      }
    }
    
    @media (max-width: 375px) {
      .main-content {
        padding: 8px !important;
      }
      
      .card-body {
        padding: 12px !important;
      }
      
      .card-header {
        padding: 10px 14px !important;
      }
      
      .vehicle-info-card .card-body,
      .navigation-card .card-body {
        padding: 14px 10px !important;
      }
      
      .btn {
        font-size: 0.8rem !important;
        padding: 8px 14px !important;
      }
      
      h3 {
        font-size: 1.2rem !important;
      }
      
      h4 {
        font-size: 1rem !important;
      }
      
      h5 {
        font-size: 0.95rem !important;
      }
    }
    
    /* Modal Mobile Responsive */
    @media (max-width: 768px) {
      .modal-dialog {
        margin: 1rem 0.5rem !important;
        max-width: calc(100% - 1rem) !important;
        max-height: calc(100vh - 2rem) !important;
      }
      
      .modal-content {
        max-height: calc(100vh - 2rem) !important;
        overflow-y: auto !important;
        border-radius: 12px !important;
      }
      
      .modal-header {
        padding: 1rem !important;
        border-radius: 12px 12px 0 0 !important;
      }
      
      .modal-title {
        font-size: 1.1rem !important;
        flex: 1 !important;
        margin-right: 1rem !important;
      }
      
      .modal-header .btn-close {
        width: 35px !important;
        height: 35px !important;
        padding: 0.5rem !important;
        font-size: 1.2rem !important;
        background: rgba(255,255,255,0.2) !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
        border-radius: 50% !important;
        opacity: 1 !important;
        color: white !important;
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
      }
      
      .modal-header .btn-close:hover { 
        background: rgba(255,255,255,0.3) !important;
        color: white !important;
        opacity: 1 !important; 
      }
      
      .modal-header .btn-close:focus { 
        outline: 2px solid rgba(255,255,255,0.5) !important; 
        outline-offset: 2px !important; 
      }
      
      .modal-body {
        padding: 1rem !important;
        font-size: 0.9rem;
      }
      
      .modal-body .text-center {
        margin-bottom: 1rem !important;
      }
      
      .modal-body .alert {
        padding: 12px 15px !important;
        font-size: 0.85rem !important;
      }
      
      .modal-body .d-flex.justify-content-center.gap-3 {
        flex-direction: column !important;
        gap: 0.75rem !important;
      }
      
      .modal-body .btn {
        width: 100% !important;
        margin: 0 !important;
        padding: 12px 16px !important;
        font-size: 0.9rem !important;
      }
      
      .modal-footer {
        padding: 1rem !important;
        flex-direction: column;
        gap: 0.5rem;
      }
      
      .modal-footer .btn {
        width: 100%;
        margin: 0 !important;
        padding: 10px 15px !important;
        font-size: 0.9rem !important;
      }
    }
    
    @media (max-width: 576px) {
      .modal-dialog {
        margin: 0.5rem 0.25rem !important;
        max-width: calc(100% - 0.5rem) !important;
        max-height: calc(100vh - 1rem) !important;
      }
      
      .modal-content {
        max-height: calc(100vh - 1rem) !important;
        border-radius: 8px !important;
      }
      
      .modal-title {
        font-size: 1rem !important;
      }
      
      .modal-header .btn-close {
        width: 30px !important;
        height: 30px !important;
        padding: 0.3rem !important;
        font-size: 1rem !important;
      }
      
      .modal-body {
        padding: 0.75rem !important;
        font-size: 0.85rem;
      }
      
      .modal-body .alert {
        padding: 10px 12px !important;
        font-size: 0.8rem !important;
      }
      
      .modal-body .btn {
        padding: 10px 14px !important;
        font-size: 0.85rem !important;
      }
      
      .modal-footer {
        padding: 0.75rem !important;
      }
      
      .modal-footer .btn {
        padding: 8px 12px !important;
        font-size: 0.85rem !important;
      }
    }

    /* Professional Animations */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card {
      animation: slideInUp 0.5s ease-out;
    }

    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }

    /* Enhanced Text Styling */
    .text-muted {
      color: var(--text-secondary) !important;
    }

    .fw-bold {
      font-weight: 600 !important;
    }

    /* Professional Link Styling */
    .btn-link {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn-link:hover {
      color: var(--primary);
      transform: translateX(4px);
    }

  </style>
  <style>
    .modal-backdrop {
      z-index: 9998 !important;
    }
    .modal {
      z-index: 9999 !important;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <!-- Professional Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
      <div>
        <h3 class="mb-2">
          <i class="fas fa-tachometer-alt me-3" style="background: var(--gradient-accent); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
          Driver Dashboard
        </h3>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Driver') ?>!</p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="text-end">
          <small class="text-muted d-block">Last Login</small>
          <small class="fw-medium"><?= date('M j, Y H:i') ?></small>
        </div>
        <div class="vr" style="height: 40px;"></div>
        <button class="btn btn-outline-primary" onclick="location.reload()">
          <i class="fas fa-sync-alt me-2"></i>Refresh
        </button>
      </div>
    </div>

    <!-- Professional Action Cards Row -->
    <div class="row g-4 mt-3">
      <!-- Vehicle Details -->
      <div class="col-md-8">
        <div class="card shadow-sm h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Vehicle Information</h5>
            <?php if($vehicle): ?>
              <span class="badge bg-<?= 
                $vehicle['status'] == 'active' ? 'success' : 
                ($vehicle['status'] == 'maintenance' ? 'warning' : 'danger') 
              ?>">
                <i class="fas fa-circle me-1"></i><?= ucfirst($vehicle['status']) ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if($vehicle): ?>
              <div class="row align-items-center mb-4">
                <div class="col-md-2 text-center">
                  <div class="p-3 bg-light rounded-circle d-inline-block">
                    <i class="fas fa-car" style="font-size: 2rem; color: var(--primary);"></i>
                  </div>
                </div>
                <div class="col-md-10">
                  <h4 class="mb-1"><?= htmlspecialchars($vehicle['article']) ?></h4>
                  <p class="text-muted mb-0">Your assigned vehicle for daily operations</p>
                </div>
              </div>
              
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded-3">
                    <div class="me-3">
                      <i class="fas fa-id-card text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                      <small class="text-muted d-block">Plate Number</small>
                      <strong><?= htmlspecialchars($vehicle['plate_number']) ?></strong>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded-3">
                    <div class="me-3">
                      <i class="fas fa-tag text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                      <small class="text-muted d-block">Unit</small>
                      <strong><?= htmlspecialchars($vehicle['unit']) ?></strong>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded-3">
                    <div class="me-3">
                      <i class="fas fa-calendar-plus text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                      <small class="text-muted d-block">Date Added</small>
                      <strong><?= date('M j, Y', strtotime($vehicle['created_at'])) ?></strong>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded-3">
                    <div class="me-3">
                      <i class="fas fa-map-marker-alt text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                      <small class="text-muted d-block">GPS Status</small>
                      <strong class="<?= $vehicle['current_latitude'] && $vehicle['current_longitude'] ? 'text-success' : 'text-warning' ?>">
                        <?= $vehicle['current_latitude'] && $vehicle['current_longitude'] ? 'Active' : 'Inactive' ?>
                      </strong>
                    </div>
                  </div>
                </div>
              </div>
              
              <?php if($vehicle['current_latitude'] && $vehicle['current_longitude']): ?>
                <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-3">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-satellite-dish text-primary me-3" style="font-size: 1.5rem;"></i>
                    <div>
                      <small class="text-muted d-block">Last Known Coordinates</small>
                      <code class="text-primary"><?= $vehicle['current_latitude'] ?>, <?= $vehicle['current_longitude'] ?></code>
                      <small class="text-muted d-block mt-1">Updated: <?= date('M j, Y H:i', strtotime($vehicle['last_updated'])) ?></small>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              
              <div class="mt-4 d-flex gap-3">
                <a href="maintenance-request.php" class="btn btn-primary flex-fill">
                  <i class="fas fa-wrench me-2"></i>Request Maintenance
                </a>
                <a href="emergency-maintenance.php" class="btn btn-danger flex-fill">
                  <i class="fas fa-exclamation-triangle me-2"></i>Emergency Request
                </a>
              </div>
            <?php else: ?>
              <div class="text-center py-5">
                <div class="mb-4">
                  <i class="fas fa-car-crash" style="font-size: 4rem; color: var(--text-secondary); opacity: 0.5;"></i>
                </div>
                <h5 class="mb-3">No Vehicle Assigned</h5>
                <p class="text-muted mb-4">You don't currently have a vehicle assigned to you.</p>
                <button class="btn btn-outline-primary" onclick="alert('Please contact your fleet manager for vehicle assignment.')">
                  <i class="fas fa-phone me-2"></i>Contact Fleet Manager
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Recent Maintenance -->
      <div class="col-md-4">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Recent Maintenance</h5>
          </div>
          <div class="card-body">
            <?php if(!empty($maintenanceRecords)): ?>
              <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach($maintenanceRecords as $record): ?>
                  <div class="border-bottom pb-2 mb-2">
                    <h6 class="mb-1"><?= ucfirst(str_replace('_', ' ', $record['maintenance_type'])) ?></h6>
                    <small class="text-muted"><?= date('M j, Y', strtotime($record['scheduled_date'])) ?></small>
                    <p class="mb-0">
                      Status: 
                      <span class="badge bg-<?= 
                        $record['status'] == 'completed' ? 'success' : 
                        ($record['status'] == 'in_progress' ? 'primary' : 'warning') 
                      ?>">
                        <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                      </span>
                    </p>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="mt-3">
                <a href="maintenance-history.php" class="btn btn-link p-0">
                  <i class="fas fa-history me-1"></i>View All Maintenance
                </a>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No maintenance records found
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

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
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');

  if (burgerBtn) {
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
      }
    });
  }
</script>

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

<script>
  // Notification Management Functions
  function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    return `${Math.floor(diffInSeconds / 86400)}d ago`;
  }

  // Global variable to store notification ID
  let currentNotificationId = null;

  // Show dismiss modal
  function showDismissModal(notificationId) {
    currentNotificationId = notificationId;
    
    // Reset modal states
    document.getElementById('dismissModalContent').style.display = 'block';
    document.getElementById('dismissLoadingState').style.display = 'none';
    document.getElementById('dismissSuccessState').style.display = 'none';
    document.getElementById('dismissErrorState').style.display = 'none';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('dismissModal'));
    modal.show();
  }

  // Dismiss notification
  function dismissNotification() {
    if (!currentNotificationId) return;
    
    // Show loading state
    document.getElementById('dismissModalContent').style.display = 'none';
    document.getElementById('dismissLoadingState').style.display = 'block';
    document.getElementById('dismissSuccessState').style.display = 'none';
    document.getElementById('dismissErrorState').style.display = 'none';
    
    // Send AJAX request to dismiss notification
    const formData = new FormData();
    formData.append('notification_id', currentNotificationId);
    
    fetch('../api/dismiss_notification.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        // Show success state
        document.getElementById('dismissLoadingState').style.display = 'none';
        document.getElementById('dismissSuccessState').style.display = 'block';
        
        // Reload notifications after a short delay
        setTimeout(() => {
          loadNotifications();
          // Close modal after 2 seconds
          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('dismissModal'));
            modal.hide();
          }, 2000);
        }, 1000);
      } else {
        // Show error state
        document.getElementById('dismissLoadingState').style.display = 'none';
        document.getElementById('dismissErrorState').style.display = 'block';
        document.getElementById('dismissErrorMessage').textContent = result.error || 'Failed to dismiss notification. Please try again.';
      }
    })
    .catch(error => {
      console.error('Error dismissing notification:', error);
      // Show error state
      document.getElementById('dismissLoadingState').style.display = 'none';
      document.getElementById('dismissErrorState').style.display = 'block';
      document.getElementById('dismissErrorMessage').textContent = 'Failed to dismiss notification. Please try again.';
    });
  }

  // Load notifications via AJAX
  function loadNotifications() {
    fetch('../api/get_driver_notifications.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateNotificationDisplay(data.data);
        } else {
          console.log('No notifications found');
          updateNotificationDisplay([]);
        }
      })
      .catch(error => {
        console.error('Error loading notifications:', error);
      });
  }

  // Update notification display
  function updateNotificationDisplay(notifications) {
    const container = document.getElementById('notificationsList');
    const countBadge = document.getElementById('notificationCount');
    
    // Update count badge
    countBadge.textContent = notifications.length;
    
    if (notifications.length === 0) {
      container.innerHTML = `
        <div class="text-center text-muted">
          <i class="fas fa-check-circle"></i>
          No new notifications
        </div>
      `;
      return;
    }

    let html = '';
    notifications.forEach(notification => {
      const timeAgo = getTimeAgo(notification.sent_at);
      html += `
        <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
          <div class="d-flex align-items-start">
            <i class="fas fa-tools me-2 mt-1"></i>
            <div class="flex-grow-1">
              <div class="fw-bold">Maintenance Alert</div>
              <div class="small">${notification.message_text.replace(/\n/g, '<br>')}</div>
              <div class="text-muted small mt-1">
                <i class="fas fa-clock me-1"></i>${timeAgo}
              </div>
            </div>
          </div>
          <button type="button" class="btn-close" onclick="showDismissModal(${notification.id})"></button>
        </div>
      `;
    });
    
    container.innerHTML = html;
  }

  // Load notifications on page load
  document.addEventListener('DOMContentLoaded', function() {
    // Load notifications immediately
    loadNotifications();
    
    // Refresh notifications every 30 seconds via AJAX
    setInterval(loadNotifications, 30000);
  });
</script>
<!-- Dismiss Notification Modal -->
<div class="modal fade" id="dismissModal" tabindex="-1" aria-labelledby="dismissModalLabel" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #001d3d; color: white;">
                <h5 class="modal-title" id="dismissModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Dismiss Notification
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="dismissModalContent">
                    <div class="text-center mb-4">
                        <i class="fas fa-question-circle" style="font-size: 3rem; color: #001d3d;"></i>
                        <h4 class="mt-3">Dismiss Notification?</h4>
                        <p class="text-muted">Are you sure you want to dismiss this maintenance notification?</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Once dismissed, this notification will be removed from your dashboard and cannot be recovered.
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>
                            Cancel
                        </button>
                        <button type="button" class="btn" style="background-color: #001d3d; color: white; border: none;" onclick="dismissNotification()">
                            <i class="fas fa-check me-2"></i>
                            Yes, Dismiss
                        </button>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="dismissLoadingState" style="display: none;">
                    <div class="text-center">
                        <div class="spinner-border mb-3" style="color: #001d3d;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5>Dismissing Notification...</h5>
                        <p class="text-muted">Please wait while we remove this notification.</p>
                    </div>
                </div>
                
                <!-- Success State -->
                <div id="dismissSuccessState" style="display: none;">
                    <div class="text-center">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #001d3d;"></i>
                        <h4 class="mt-3" style="color: #001d3d;">Notification Dismissed!</h4>
                        <p class="text-muted">The notification has been successfully removed from your dashboard.</p>
                        <button type="button" class="btn" style="background-color: #001d3d; color: white; border: none;" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>
                            Close
                        </button>
                    </div>
                </div>
                
                <!-- Error State -->
                <div id="dismissErrorState" style="display: none;">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h4 class="mt-3 text-danger">Error</h4>
                        <p id="dismissErrorMessage" class="text-muted"></p>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>