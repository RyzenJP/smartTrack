<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

require_once '../db_connection.php';

// Check if user is super admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: ../index.php");
    exit;
}

// Include security class for input sanitization
require_once __DIR__ . '/../config/security.php';
$security = Security::getInstance();

// Sanitize GET inputs
$message = isset($_GET['success']) ? $security->sanitizeInput($_GET['success'], 'string') : '';
$error = isset($_GET['error']) ? $security->sanitizeInput($_GET['error'], 'string') : '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please try again.';
        } else {
            $reservation_id = (int)$security->sanitizeInput($_POST['reservation_id'] ?? '', 'int');
            $action = $security->sanitizeInput($_POST['action'], 'string');
            
            try {
            if ($action === 'approve') {
                // Get the single dispatcher (excluding super_admin) - use prepared statement for consistency
                $dispatcher_stmt = $conn->prepare("SELECT user_id FROM user_table WHERE role = 'dispatcher' AND role != 'super admin' LIMIT 1");
                $dispatcher_stmt->execute();
                $dispatcher_result = $dispatcher_stmt->get_result();
                
                if ($dispatcher_result && $dispatcher_result->num_rows > 0) {
                    $dispatcher = $dispatcher_result->fetch_assoc();
                    $dispatcher_id = $dispatcher['user_id'];
                    $dispatcher_stmt->close();
                    
                    // Auto-assign to dispatcher and set status to 'assigned'
                    $sql = "UPDATE vehicle_reservations SET status = 'assigned', assigned_dispatcher_id = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $dispatcher_id, $reservation_id);
                    $message = "Reservation approved and automatically assigned to dispatcher!";
                } else {
                    // No dispatcher found, just approve
                    $sql = "UPDATE vehicle_reservations SET status = 'approved' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $reservation_id);
                    $message = "Reservation approved successfully!";
                }
            } elseif ($action === 'reject') {
                $sql = "UPDATE vehicle_reservations SET status = 'cancelled' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $message = "Reservation rejected.";
            }
            
            if ($stmt->execute()) {
                // Success message already set above
            } else {
                $error = "Error updating reservation status.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
        }
    }
}

// Get all reservations with status filter - use prepared statement for security
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$sql = "SELECT vr.*, fv.article, fv.unit, fv.plate_number, 
               ut.full_name as dispatcher_name, ut.email as dispatcher_email
        FROM vehicle_reservations vr 
        LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id 
        LEFT JOIN user_table ut ON vr.assigned_dispatcher_id = ut.user_id";
        
if ($status_filter !== 'all') {
    $sql .= " WHERE vr.status = ?";
}
$sql .= " ORDER BY vr.created_at DESC";

$stmt = $conn->prepare($sql);
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }

        .sidebar.collapsed {
            width: 70px;
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

        /* Hide chevrons when sidebar is collapsed */
        .sidebar.collapsed .dropdown-chevron {
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

        .navbar-brand {
            color: #000 !important;
        }

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary);
            margin-right: 1rem;
        }

        /* Modal z-index fixes - Ensure modal appears above navbar */
        .modal {
            z-index: 9999 !important;
        }
        .modal-backdrop {
            z-index: 9998 !important;
        }

        .modal-dialog {
            z-index: 10000 !important;
        }

        /* Ensure modal appears above all other elements */
        .modal.show {
            z-index: 9999 !important;
        }

        .modal.show .modal-dialog {
            z-index: 10000 !important;
        }

        .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .modal-header .btn-close {
            color: white;
            opacity: 1;
        }


        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
        }

        .table-modern {
            margin: 0;
            border: none;
        }

        .table-modern thead th {
            background: #f8f9fa;
            border: none;
            color: var(--primary);
            font-weight: 600;
            padding: 16px 20px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-modern tbody td {
            border: none;
            padding: 16px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f4;
        }

        .table-modern tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-modern {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .btn-modern {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 991.98px) {
            /* Mobile sidebar behavior */
            .sidebar {
                width: 260px !important;
                transform: translateX(-100%) !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                height: 100vh !important;
                z-index: 1101 !important;
                transition: transform 0.3s ease !important;
            }

            .sidebar.open {
                transform: translateX(0) !important;
            }

            /* Main content adjustments for mobile */
            .main-content {
                padding: 15px !important;
                margin-left: 0 !important;
                margin-top: 60px !important;
            }

            .main-content.collapsed {
                margin-left: 0 !important;
            }

            /* Mobile dropdown animations */
            .sidebar .collapse {
                transition: height 0.3s ease !important;
            }

            .sidebar .collapse.show {
                display: block !important;
            }

            .sidebar .dropdown-chevron {
                transition: transform 0.3s ease !important;
            }

            .sidebar .dropdown-chevron.rotated {
                transform: rotate(90deg) !important;
            }
        }

        @media (max-width: 768px) {
            /* Main content adjustments */
            .main-content {
                padding: 15px !important;
                margin-top: 60px !important;
            }

            /* Page header responsive */
            .d-flex.justify-content-between {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 1rem;
            }

            .d-flex.justify-content-between h2 {
                font-size: 1.5rem !important;
                margin-bottom: 0.5rem !important;
                text-align: center !important;
            }

            .btn-group {
                justify-content: center !important;
                flex-wrap: wrap !important;
                gap: 0.5rem !important;
            }

            .btn-group .btn {
                flex: 1 !important;
                min-width: 80px !important;
                font-size: 0.8rem !important;
                padding: 8px 12px !important;
            }
            /* Table container responsive */
            .table-container {
                border-radius: 12px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
                margin: 0 !important;
            }

            .table-header {
                padding: 15px 20px !important;
            }

            .table-header h5 {
                font-size: 1.1rem !important;
            }

            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 0 12px 12px !important;
            }

            .table-modern {
                font-size: 0.85rem;
                min-width: 800px !important;
            }

            .table-modern thead th {
                padding: 12px 8px;
                font-size: 0.75rem;
                white-space: nowrap;
            }

            .table-modern tbody td {
                padding: 12px 8px;
                vertical-align: middle;
                white-space: nowrap;
            }

            /* Hide less important columns on mobile */
            .table-modern th:nth-child(1),
            .table-modern td:nth-child(1) {
                display: none;
            }

            /* Set minimum widths for remaining columns */
            .table-modern th:nth-child(2),
            .table-modern td:nth-child(2) {
                min-width: 140px; /* Requester */
            }

            .table-modern th:nth-child(3),
            .table-modern td:nth-child(3) {
                min-width: 120px; /* Vehicle */
            }

            .table-modern th:nth-child(4),
            .table-modern td:nth-child(4) {
                min-width: 130px; /* Purpose */
            }

            .table-modern th:nth-child(5),
            .table-modern td:nth-child(5) {
                min-width: 160px; /* Start Date */
            }

            .table-modern th:nth-child(6),
            .table-modern td:nth-child(6) {
                min-width: 100px; /* Status */
            }

            .table-modern th:nth-child(7),
            .table-modern td:nth-child(7) {
                min-width: 140px; /* Actions */
            }

            /* Action buttons - stack vertically on mobile */
            .btn-modern {
                font-size: 0.7rem;
                padding: 8px 12px;
                margin: 2px 0;
                display: block;
                width: 100%;
                border-radius: 6px !important;
            }

            /* Stack action buttons vertically */
            .d-flex.flex-column.flex-md-row.gap-2 {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            /* Modal responsive styles */
            .modal-dialog {
                margin: 1rem 0.5rem !important;
                max-width: calc(100% - 1rem) !important;
                max-height: calc(100vh - 2rem) !important;
            }

            .modal-lg {
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
                position: relative !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                border-radius: 12px 12px 0 0 !important;
            }

            .modal-title {
                font-size: 1.1rem !important;
                flex: 1 !important;
                margin-right: 1rem !important;
            }

            .modal-header .btn-close {
                position: static !important;
                margin: 0 !important;
                padding: 0.5rem !important;
                font-size: 1.2rem !important;
                line-height: 1 !important;
                background: rgba(255,255,255,0.2) !important;
                border: 1px solid rgba(255,255,255,0.3) !important;
                border-radius: 50% !important;
                opacity: 1 !important;
                color: white !important;
                flex-shrink: 0 !important;
                width: 35px !important;
                height: 35px !important;
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

            .modal-body .row {
                margin-bottom: 1rem !important;
            }

            .modal-body .col-md-6 {
                margin-bottom: 0.75rem !important;
            }

            .modal-body h6 {
                font-size: 0.9rem !important;
                margin-bottom: 0.5rem !important;
            }

            .modal-body p {
                font-size: 0.85rem !important;
                margin-bottom: 0.5rem !important;
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

            /* Form elements in modal */
            .modal-body .row {
                margin: 0 !important;
            }

            .modal-body .col-md-6 {
                padding: 0 !important;
                margin-bottom: 1rem;
            }

            .modal-body .alert {
                font-size: 0.85rem;
                padding: 0.75rem;
            }

            /* Compact modal content for mobile */
            .modal-body .row.mt-3 {
                margin-top: 1rem !important;
            }

            .modal-body h6 {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
                font-weight: 600;
            }

            .modal-body p {
                margin-bottom: 0.5rem;
                font-size: 0.85rem;
            }

            /* Status badges */
            .badge {
                font-size: 0.7rem;
                padding: 4px 6px;
            }
        }

        @media (max-width: 576px) {
            /* Extra small mobile adjustments */
            .main-content {
                padding: 10px !important;
            }

            .d-flex.justify-content-between h2 {
                font-size: 1.3rem !important;
            }

            .btn-group .btn {
                font-size: 0.75rem !important;
                padding: 6px 10px !important;
            }

            .table-container {
                border-radius: 8px !important;
            }

            .table-header {
                padding: 12px 15px !important;
            }

            .table-header h5 {
                font-size: 1rem !important;
            }

            .table-modern {
                font-size: 0.8rem;
                min-width: 700px !important;
            }

            .table-modern thead th {
                padding: 10px 6px;
                font-size: 0.7rem;
            }

            .table-modern tbody td {
                padding: 10px 6px;
            }

            .btn-modern {
                font-size: 0.65rem;
                padding: 6px 8px;
            }

            /* Extra small mobile modal adjustments */
            .modal-dialog {
                margin: 0.5rem 0.25rem !important;
                max-width: calc(100% - 0.5rem) !important;
                max-height: calc(100vh - 1rem) !important;
            }

            .modal-lg {
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

            .modal-body h6 {
                font-size: 0.85rem !important;
            }

            .modal-body p {
                font-size: 0.8rem !important;
            }

            .modal-footer {
                padding: 0.75rem !important;
            }

            .modal-footer .btn {
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
            }

            .table-modern thead th,
            .table-modern tbody td {
                padding: 6px 2px;
            }

            .btn-modern {
                font-size: 0.7rem;
                padding: 4px 8px;
            }

            /* Extra small mobile modal adjustments */
            .modal-dialog {
                margin: 1rem 0.25rem !important;
                max-width: calc(100% - 0.5rem) !important;
                max-height: calc(100vh - 2rem) !important;
            }

            .modal-lg {
                max-width: calc(100% - 0.5rem) !important;
                max-height: calc(100vh - 2rem) !important;
            }

            .modal-content {
                max-height: calc(100vh - 2rem) !important;
            }

            .modal-title {
                font-size: 0.95rem !important;
            }

            .modal-body {
                font-size: 0.8rem;
                padding: 0.75rem !important;
            }

            .modal-header {
                padding: 0.75rem !important;
                position: relative !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }

            .modal-header .btn-close {
                padding: 0.4rem !important;
                font-size: 1.1rem !important;
            }

            .modal-footer {
                padding: 0.75rem !important;
            }

            .modal-footer .btn {
                padding: 6px 10px;
                font-size: 0.85rem;
            }
        }

        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <!-- Include standard Super Admin sidebar and navbar -->
    <?php include __DIR__ . '/../pages/sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-calendar-check"></i> Reservation Management</h2>
                    <div class="btn-group" role="group">
                        <a href="?status=all" class="btn btn-outline-primary <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=assigned" class="btn btn-outline-info <?php echo $status_filter === 'assigned' ? 'active' : ''; ?>">Assigned</a>
                        <a href="?status=cancelled" class="btn btn-outline-danger <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                    </div>
                </div>
                
                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1300;">
                    <div id="liveToast" class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header bg-success text-white border-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong class="me-auto">Success</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body text-white" id="toastBody">
                            <!-- Toast message will be inserted here -->
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Reservation Requests</h5>
                        <p class="mb-0 mt-1 opacity-75">Manage and approve vehicle reservation requests</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Requester</th>
                                        <th>Department</th>
                                        <th>Purpose</th>
                                        <th>Route</th>
                                        <th>Date/Time</th>
                                        <th>Article</th>
                                        <th>Unit</th>
                                        <th>Plate No.</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['requester_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['contact']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td>
                                                <div style="max-width: 200px;">
                                                    <?php echo htmlspecialchars(substr($row['purpose'], 0, 50)) . (strlen($row['purpose']) > 50 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong>From:</strong> <?php echo htmlspecialchars($row['origin']); ?><br>
                                                    <strong>To:</strong> <?php echo htmlspecialchars($row['destination']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?><br>
                                                    <strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($row['vehicle_id']): ?>
                                                    <?php echo htmlspecialchars($row['article']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['vehicle_id']): ?>
                                                    <?php echo htmlspecialchars($row['unit']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['vehicle_id']): ?>
                                                    <?php echo htmlspecialchars($row['plate_number']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'assigned' => 'bg-info',
                                                    'completed' => 'bg-primary',
                                                    'cancelled' => 'bg-danger'
                                                ];
                                                $color = $status_colors[$row['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge-modern <?php echo $color; ?> text-white text-uppercase">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column flex-md-row gap-2">
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-modern btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-modern btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    <?php elseif ($row['status'] === 'approved'): ?>
                                                        <a href="assign_dispatcher.php?id=<?php echo $row['id']; ?>" class="btn btn-modern btn-primary">
                                                            <i class="fas fa-user-tie me-1"></i> Assign Dispatcher
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-modern btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye me-1"></i> Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reservation Details #<?php echo $row['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Requester Information</h6>
                                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($row['requester_name']); ?></p>
                                                                <p><strong>Department:</strong> <?php echo htmlspecialchars($row['department']); ?></p>
                                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($row['contact']); ?></p>
                                                                <p><strong>Passengers:</strong> <?php echo $row['passengers']; ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Trip Details</h6>
                                                                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?></p>
                                                                <p><strong>Origin:</strong> <?php echo htmlspecialchars($row['origin']); ?></p>
                                                                <p><strong>Destination:</strong> <?php echo htmlspecialchars($row['destination']); ?></p>
                                                                <p><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?></p>
                                                                <p><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?></p>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-3">
                                                            <div class="col-12">
                                                                <h6>Vehicle Assignment</h6>
                                                                <div class="alert alert-success">
                                                                    <?php if ($row['vehicle_id']): ?>
                                                                        <p><strong>Article:</strong> <?php echo htmlspecialchars($row['article']); ?></p>
                                                                        <p><strong>Unit:</strong> <?php echo htmlspecialchars($row['unit']); ?></p>
                                                                        <p><strong>Plate Number:</strong> <?php echo htmlspecialchars($row['plate_number']); ?></p>
                                                                    <?php else: ?>
                                                                        <p><strong>No vehicle assigned yet</strong></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($row['assigned_dispatcher_id']): ?>
                                                        <div class="row mt-3">
                                                            <div class="col-12">
                                                                <h6>Dispatcher Assignment</h6>
                                                                <div class="alert alert-info">
                                                                    <p><strong>Assigned Dispatcher:</strong> <?php echo htmlspecialchars($row['dispatcher_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($row['dispatcher_email']); ?></p>
                                                                    <p><strong>Assignment Date:</strong> <?php echo isset($row['assigned_at']) ? date('M j, Y g:i A', strtotime($row['assigned_at'])) : 'Not available'; ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="row mt-3">
                                                            <div class="col-12">
                                                                <h6>Dispatcher Assignment</h6>
                                                                <div class="alert alert-warning">
                                                                    <p><strong>No Dispatcher Assigned</strong></p>
                                                                    <p>This reservation has not been assigned to a dispatcher yet.</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if ($row['notes']): ?>
                                                            <h6>Additional Notes</h6>
                                                            <p><?php echo htmlspecialchars($row['notes']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="approveModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Reservation</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve this reservation?</p>
                                                        <div class="alert alert-info">
                                                            <strong>Reservation #<?php echo $row['id']; ?></strong><br>
                                                            <strong>Requester:</strong> <?php echo htmlspecialchars($row['requester_name']); ?><br>
                                                            <strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?><br>
                                                            <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="fas fa-check me-1"></i>Approve Reservation
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Reservation</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reject this reservation?</p>
                                                        <div class="alert alert-warning">
                                                            <strong>Reservation #<?php echo $row['id']; ?></strong><br>
                                                            <strong>Requester:</strong> <?php echo htmlspecialchars($row['requester_name']); ?><br>
                                                            <strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?><br>
                                                            <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?>
                                                        </div>
                                                        <p class="text-muted">This action cannot be undone.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-times me-1"></i>Reject Reservation
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const burgerBtn = document.getElementById('burgerBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const linkTexts = document.querySelectorAll('.link-text');
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

            function isMobile() {
                return window.innerWidth < 992;
            }

            if (burgerBtn && sidebar && mainContent) {
                burgerBtn.addEventListener('click', () => {
                    if (isMobile()) {
                        // Mobile behavior: toggle sidebar open/closed
                        sidebar.classList.toggle('open');
                    } else {
                        // Desktop behavior: toggle sidebar collapsed/expanded
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
                });

                // Handle window resize
                window.addEventListener('resize', () => {
                    if (isMobile()) {
                        sidebar.classList.remove('collapsed');
                        sidebar.classList.remove('open');
                        mainContent.classList.remove('collapsed');
                        // Show all text on mobile
                        linkTexts.forEach(text => {
                            text.style.display = 'inline';
                        });
                    } else {
                        sidebar.classList.remove('open');
                        // Restore desktop behavior
                        if (!sidebar.classList.contains('collapsed')) {
                            linkTexts.forEach(text => {
                                text.style.display = 'inline';
                            });
                        }
                    }
                });

                // Close mobile sidebar when clicking on main content (but not on sidebar links)
                if (isMobile()) {
                    mainContent.addEventListener('click', (e) => {
                        // Don't close if clicking on sidebar or its children
                        if (!sidebar.contains(e.target) && sidebar.classList.contains('open')) {
                            sidebar.classList.remove('open');
                        }
                    });

                    // Prevent sidebar from closing when clicking dropdown links
                    const sidebarLinks = sidebar.querySelectorAll('a');
                    sidebarLinks.forEach(link => {
                        link.addEventListener('click', (e) => {
                            // Allow the link to work normally, don't close sidebar
                            e.stopPropagation();
                        });
                    });
                }

                // Initialize chevron rotation based on current state
                const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
                dropdownToggles.forEach(toggle => {
                    const targetId = toggle.getAttribute('data-bs-target');
                    const targetElement = document.querySelector(targetId);
                    const chevron = toggle.querySelector('.dropdown-chevron');
                    
                    if (targetElement && chevron) {
                        const isOpen = targetElement.classList.contains('show');
                        chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
                    }
                });

                // Handle mobile dropdown behavior
                dropdownToggles.forEach(toggle => {
                    toggle.addEventListener('click', (e) => {
                        if (isMobile()) {
                            // On mobile, prevent default Bootstrap behavior and handle manually
                            e.preventDefault();
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
                                
                                // Rotate chevron - point down when open, right when closed
                                const chevron = toggle.querySelector('.dropdown-chevron');
                                if (chevron) {
                                    chevron.style.transform = !isCollapsed ? 'rotate(90deg)' : 'rotate(0deg)';
                                }
                            }
                        }
                    });
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
            }
        });

        // Show toast message if available
        <?php if ($message): ?>
            const toastLiveExample = document.getElementById('liveToast');
            const toastBody = document.getElementById('toastBody');
            toastBody.innerHTML = '<i class="fas fa-check-circle me-2"></i><?= $message ?>';
            const toast = new bootstrap.Toast(toastLiveExample);
            toast.show();
        <?php endif; ?>
        
        <?php if ($error): ?>
            const toastLiveExample = document.getElementById('liveToast');
            const toastBody = document.getElementById('toastBody');
            toastLiveExample.classList.remove('bg-success');
            toastLiveExample.classList.add('bg-danger');
            toastBody.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i><?= $error ?>';
            const toast = new bootstrap.Toast(toastLiveExample);
            toast.show();
        <?php endif; ?>
    </script>
</body>
</html>
