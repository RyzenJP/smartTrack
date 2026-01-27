<?php
session_start();

// Check if user is logged in and is a mechanic
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

$mechanicId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = $_POST['request_id'] ?? '';
    
    if ($action == 'assign' && $requestId) {
        // Assign request to mechanic
        $stmt = $conn->prepare("UPDATE emergency_maintenance SET mechanic_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?");
        if ($stmt->execute([$mechanicId, $requestId])) {
            $message = "Emergency request assigned to you successfully!";
            $messageType = "success";
        } else {
            $message = "Error assigning request.";
            $messageType = "danger";
        }
    } elseif ($action == 'start' && $requestId) {
        // Start working on request
        $stmt = $conn->prepare("UPDATE emergency_maintenance SET status = 'in_progress', started_at = NOW() WHERE id = ? AND mechanic_id = ?");
        if ($stmt->execute([$requestId, $mechanicId])) {
            $message = "Started working on emergency request!";
            $messageType = "success";
        } else {
            $message = "Error starting work on request.";
            $messageType = "danger";
        }
    } elseif ($action == 'complete' && $requestId) {
        // Complete request
        $completionNotes = $_POST['completion_notes'] ?? '';
        $actualCost = $_POST['actual_cost'] ?? null;
        
        $stmt = $conn->prepare("UPDATE emergency_maintenance SET status = 'completed', completed_at = NOW(), completion_notes = ?, actual_cost = ? WHERE id = ? AND mechanic_id = ?");
        if ($stmt->execute([$completionNotes, $actualCost, $requestId, $mechanicId])) {
            $message = "Emergency request completed successfully!";
            $messageType = "success";
        } else {
            $message = "Error completing request.";
            $messageType = "danger";
        }
    } elseif ($action == 'add_notes' && $requestId) {
        // Add mechanic notes
        $mechanicNotes = $_POST['mechanic_notes'] ?? '';
        $estimatedCost = $_POST['estimated_cost'] ?? null;
        
        $stmt = $conn->prepare("UPDATE emergency_maintenance SET mechanic_notes = ?, estimated_cost = ? WHERE id = ? AND mechanic_id = ?");
        if ($stmt->execute([$mechanicNotes, $estimatedCost, $requestId, $mechanicId])) {
            $message = "Notes updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating notes.";
            $messageType = "danger";
        }
    }
}

// Get all emergency maintenance requests
$allRequestsQuery = "
    SELECT em.*, fv.article as vehicle_name, fv.plate_number, fv.unit,
           d.full_name as driver_name, d.phone as driver_phone_db,
           m.full_name as mechanic_name
    FROM emergency_maintenance em
    JOIN fleet_vehicles fv ON em.vehicle_id = fv.id
    JOIN user_table d ON em.driver_id = d.user_id
    LEFT JOIN user_table m ON em.mechanic_id = m.user_id
    ORDER BY 
        CASE em.urgency_level 
            WHEN 'CRITICAL' THEN 1 
            WHEN 'HIGH' THEN 2 
            WHEN 'MEDIUM' THEN 3 
            WHEN 'LOW' THEN 4 
        END,
        em.requested_at DESC
";
// Use prepared statement for consistency (static query but best practice)
$allRequests_stmt = $conn->prepare($allRequestsQuery);
$allRequests_stmt->execute();
$allRequests = $allRequests_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allRequests_stmt->close();

// Get my assigned requests
$myRequestsQuery = $conn->prepare("
    SELECT em.*, fv.article as vehicle_name, fv.plate_number, fv.unit,
           d.full_name as driver_name, d.phone as driver_phone_db
    FROM emergency_maintenance em
    JOIN fleet_vehicles fv ON em.vehicle_id = fv.id
    JOIN user_table d ON em.driver_id = d.user_id
    WHERE em.mechanic_id = ?
    ORDER BY em.requested_at DESC
");
$myRequestsQuery->bind_param("i", $mechanicId);
$myRequestsQuery->execute();
$myRequests = $myRequestsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$myRequestsQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Emergency Maintenance - Mechanic | Smart Track</title>
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
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            color: var(--text-primary);
            overflow-x: hidden;
        }
        
        /* Force mobile-first approach */
        * {
            box-sizing: border-box;
        }

        /* Navbar */
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

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary);
            margin-right: 1rem;
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

        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-thumb { background-color: #ffffffcc; border-radius: 10px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }

        .sidebar a { display: block; padding: 14px 20px; color: #d9d9d9; text-decoration: none; white-space: nowrap; }
        .sidebar a:hover, .sidebar a.active { background-color: #001d3d; color: var(--accent); }

        .sidebar .dropdown-toggle { color: #d9d9d9; }
        /* Hide Bootstrap default caret to avoid double arrows */
        .sidebar .dropdown-toggle::after { display: none; }
        .dropdown-chevron { color: #ffffff; transition: transform 0.3s ease; }
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron { transform: rotate(90deg); }

        /* Fix dropdown toggle layout - position chevron at far right edge */
        .sidebar .dropdown-toggle {
          justify-content: space-between !important;
          align-items: center !important;
          cursor: pointer !important;
          pointer-events: auto !important;
        }

        .sidebar .dropdown-toggle > div {
          display: flex;
          align-items: center;
          flex: 1;
        }

        .sidebar .dropdown-toggle .link-text {
          margin-left: 12px;
        }

        .sidebar .dropdown-chevron {
          margin-left: auto;
          flex-shrink: 0;
          position: absolute;
          right: 15px;
        }

        /* Smooth collapse animation */
        .sidebar .collapse {
          transition: height 0.3s ease, opacity 0.3s ease;
          overflow: hidden;
        }

        .sidebar .collapse:not(.show) {
          height: 0 !important;
          opacity: 0;
        }

        .sidebar .collapse.show {
          height: auto !important;
          opacity: 1;
        }

        .sidebar.collapsed { width: 70px; }
        .sidebar.collapsed .link-text { display: none; }
        .sidebar.collapsed .collapse { display: none !important; }
        .sidebar.collapsed .dropdown-chevron { display: none !important; }
        .sidebar.collapsed a { text-align: center; padding: 14px 8px; }
        .sidebar.collapsed a i { margin-right: 0 !important; }

        /* Main content */
        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; transition: margin-left 0.3s ease; }
        .main-content.collapsed { margin-left: 70px; }

        /* Cards */
        .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: var(--shadow-sm); }
        .card:hover { box-shadow: var(--shadow-lg); }
        .card-body { padding: 24px; }

        /* Stat cards - gradient, bold numbers */
        .card.bg-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important; border: none; border-radius: 16px; }
        .card.bg-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important; border: none; border-radius: 16px; }
        .card.bg-info { background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%) !important; border: none; border-radius: 16px; }
        .card.bg-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important; border: none; border-radius: 16px; }
        .card.bg-danger h5,
        .card.bg-warning h5,
        .card.bg-info h5,
        .card.bg-success h5 { font-size: 2rem; font-weight: 700; margin: 0; }
        .card.bg-danger p,
        .card.bg-warning p,
        .card.bg-info p,
        .card.bg-success p { margin: 4px 0 0 0; font-weight: 500; }

        /* Tabs */
        .nav-tabs { border: 0; }
        .nav-tabs .nav-link { border: 0; font-weight: 600; color: var(--text-secondary); }
        .nav-tabs .nav-link.active { color: var(--primary); position: relative; }
        .nav-tabs .nav-link.active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -8px; height: 3px; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%); border-radius: 2px; }

        .status-pending { background-color: #fff3cd; }
        .status-assigned { background-color: #cff4fc; }
        .status-in_progress { background-color: #e2e3e5; }
        .status-completed { background-color: #d1e7dd; }
        .status-cancelled { background-color: #f8d7da; }

        /* Request card */
        .request-card { transition: transform 0.2s ease; border: 1px solid var(--border-color); border-radius: 14px; }

        .request-card:hover { transform: translateY(-2px); }
        .request-card .card-body { position: relative; }
        .badge-urgency { position: absolute; right: 16px; top: 12px; border-radius: 999px; font-weight: 700; letter-spacing: .3px; }

        .badge-urgency {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }

        /* Buttons */
        .btn { border-radius: 10px; padding: 10px 16px; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border: none; }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); color: #fff; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 10px; }
        }

        /* Ensure modals appear over fixed navbar */
        .modal-backdrop { z-index: 1198 !important; }
        .modal { z-index: 1199 !important; }

        /* Mobile responsive tweaks */
        @media (max-width: 991.98px) {
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
            .sidebar.show, .sidebar.open { transform: translateX(0) !important; }
            .main-content, .main-content.collapsed { 
                margin-left: 0 !important; 
                padding: 16px !important; 
                margin-top: 60px !important;
                width: 100% !important;
            }
            
            /* Force mobile layout */
            .container-fluid {
                padding-left: 0 !important;
                padding-right: 0 !important;
                max-width: 100% !important;
            }
            
            /* Force single column layout */
            .row.g-4 > * {
                margin-bottom: 1rem !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            
            /* Stack all columns on mobile */
            .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4 {
                margin-bottom: 1.5rem !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
        }

        /* Phone tweaks for more native feel */
        @media (max-width: 575.98px) {
            .main-content { 
                padding: 12px !important; 
                margin-top: 60px !important;
            }
            
            /* Header adjustments for mobile */
            .d-flex.justify-content-between {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }
            
            /* Force mobile layout for all elements */
            .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4,
            .col-md-3, .col-md-6, .col-md-8, .col-md-4 {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            
            /* Ensure cards are full width on mobile */
            .card {
                width: 100% !important;
                margin-bottom: 1rem !important;
            }
            
            /* Force mobile navbar */
            .navbar {
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
            
            /* Table responsive */
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 8px 4px;
                font-size: 0.75rem;
            }
            
            /* Button adjustments */
            .btn {
                font-size: 0.8rem !important;
                padding: 6px 12px !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-tools text-primary me-2"></i>Emergency Maintenance</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="mechanic-dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Emergency Maintenance</li>
                    </ol>
                </nav>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5><?= count(array_filter($allRequests, fn($r) => $r['urgency_level'] == 'CRITICAL' && $r['status'] != 'completed')) ?></h5>
                            <p class="mb-0">Critical Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5><?= count(array_filter($allRequests, fn($r) => $r['status'] == 'pending')) ?></h5>
                            <p class="mb-0">Pending Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5><?= count(array_filter($myRequests, fn($r) => $r['status'] == 'assigned' || $r['status'] == 'in_progress')) ?></h5>
                            <p class="mb-0">My Active Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5><?= count(array_filter($myRequests, fn($r) => $r['status'] == 'completed')) ?></h5>
                            <p class="mb-0">Completed by Me</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-requests-tab" data-bs-toggle="tab" data-bs-target="#all-requests" type="button" role="tab">
                        <i class="fas fa-list me-2"></i>All Requests (<?= count($allRequests) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-requests-tab" data-bs-toggle="tab" data-bs-target="#my-requests" type="button" role="tab">
                        <i class="fas fa-user-cog me-2"></i>My Requests (<?= count($myRequests) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="mainTabContent">
                <!-- All Requests Tab -->
                <div class="tab-pane fade show active" id="all-requests" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">All Emergency Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($allRequests)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                    <p>No emergency maintenance requests yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($allRequests as $request): ?>
                                        <div class="col-lg-6 mb-3">
                                            <div class="card request-card urgency-<?= strtolower($request['urgency_level']) ?>">
                                                <div class="card-body status-<?= $request['status'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-0"><?= htmlspecialchars($request['issue_title']) ?></h6>
                                                        <span class="badge badge-urgency bg-<?= 
                                                            $request['urgency_level'] == 'CRITICAL' ? 'danger' : 
                                                            ($request['urgency_level'] == 'HIGH' ? 'warning' : 
                                                            ($request['urgency_level'] == 'MEDIUM' ? 'info' : 'success')) 
                                                        ?>">
                                                            <?= $request['urgency_level'] ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <p class="small text-muted mb-2">
                                                        <i class="fas fa-car me-1"></i><?= htmlspecialchars($request['vehicle_name']) ?> 
                                                        (<?= htmlspecialchars($request['plate_number']) ?>)
                                                    </p>
                                                    
                                                    <p class="small text-muted mb-2">
                                                        <i class="fas fa-user me-1"></i>Driver: <?= htmlspecialchars($request['driver_name']) ?>
                                                        <?php if ($request['driver_phone'] || $request['driver_phone_db']): ?>
                                                            | <i class="fas fa-phone me-1"></i><?= htmlspecialchars($request['driver_phone'] ?: $request['driver_phone_db']) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    
                                                    <?php if ($request['location']): ?>
                                                        <p class="small text-muted mb-2">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($request['location']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <p class="small mb-3"><?= htmlspecialchars($request['issue_description']) ?></p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?= date('M j, Y H:i', strtotime($request['requested_at'])) ?>
                                                        </small>
                                                        <span class="badge bg-<?= 
                                                            $request['status'] == 'completed' ? 'success' : 
                                                            ($request['status'] == 'in_progress' ? 'primary' : 
                                                            ($request['status'] == 'assigned' ? 'info' : 'warning')) 
                                                        ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <?php if ($request['mechanic_name']): ?>
                                                        <div class="mb-2">
                                                            <small class="text-success">
                                                                <i class="fas fa-user-cog me-1"></i>Assigned to: <?= htmlspecialchars($request['mechanic_name']) ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Action Buttons -->
                                                    <div class="d-flex gap-2">
                                                        <?php if ($request['status'] == 'pending'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="assign">
                                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-hand-paper me-1"></i>Assign to Me
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                data-bs-toggle="modal" data-bs-target="#detailsModal<?= $request['id'] ?>">
                                                            <i class="fas fa-eye me-1"></i>Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?= $request['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Emergency Request Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Request Information</h6>
                                                                <p><strong>Issue:</strong> <?= htmlspecialchars($request['issue_title']) ?></p>
                                                                <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($request['issue_description'])) ?></p>
                                                                <p><strong>Urgency:</strong> 
                                                                    <span class="badge bg-<?= 
                                                                        $request['urgency_level'] == 'CRITICAL' ? 'danger' : 
                                                                        ($request['urgency_level'] == 'HIGH' ? 'warning' : 
                                                                        ($request['urgency_level'] == 'MEDIUM' ? 'info' : 'success')) 
                                                                    ?>"><?= $request['urgency_level'] ?></span>
                                                                </p>
                                                                <?php if ($request['location']): ?>
                                                                    <p><strong>Location:</strong> <?= htmlspecialchars($request['location']) ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Vehicle & Driver</h6>
                                                                <p><strong>Vehicle:</strong> <?= htmlspecialchars($request['vehicle_name']) ?></p>
                                                                <p><strong>Plate:</strong> <?= htmlspecialchars($request['plate_number']) ?></p>
                                                                <p><strong>Driver:</strong> <?= htmlspecialchars($request['driver_name']) ?></p>
                                                                <?php if ($request['driver_phone'] || $request['driver_phone_db']): ?>
                                                                    <p><strong>Phone:</strong> <?= htmlspecialchars($request['driver_phone'] ?: $request['driver_phone_db']) ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($request['mechanic_notes'] || $request['completion_notes']): ?>
                                                            <hr>
                                                            <h6>Mechanic Notes</h6>
                                                            <?php if ($request['mechanic_notes']): ?>
                                                                <p><strong>Work Notes:</strong><br><?= nl2br(htmlspecialchars($request['mechanic_notes'])) ?></p>
                                                            <?php endif; ?>
                                                            <?php if ($request['completion_notes']): ?>
                                                                <p><strong>Completion Notes:</strong><br><?= nl2br(htmlspecialchars($request['completion_notes'])) ?></p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($request['estimated_cost'] || $request['actual_cost']): ?>
                                                            <hr>
                                                            <h6>Cost Information</h6>
                                                            <?php if ($request['estimated_cost']): ?>
                                                                <p><strong>Estimated Cost:</strong> ₱<?= number_format($request['estimated_cost'], 2) ?></p>
                                                            <?php endif; ?>
                                                            <?php if ($request['actual_cost']): ?>
                                                                <p><strong>Actual Cost:</strong> ₱<?= number_format($request['actual_cost'], 2) ?></p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- My Requests Tab -->
                <div class="tab-pane fade" id="my-requests" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">My Assigned Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($myRequests)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-user-cog fa-3x mb-3"></i>
                                    <p>No requests assigned to you yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($myRequests as $request): ?>
                                        <div class="col-lg-6 mb-3">
                                            <div class="card request-card urgency-<?= strtolower($request['urgency_level']) ?>">
                                                <div class="card-body status-<?= $request['status'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-0"><?= htmlspecialchars($request['issue_title']) ?></h6>
                                                        <span class="badge badge-urgency bg-<?= 
                                                            $request['urgency_level'] == 'CRITICAL' ? 'danger' : 
                                                            ($request['urgency_level'] == 'HIGH' ? 'warning' : 
                                                            ($request['urgency_level'] == 'MEDIUM' ? 'info' : 'success')) 
                                                        ?>">
                                                            <?= $request['urgency_level'] ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <p class="small text-muted mb-2">
                                                        <i class="fas fa-car me-1"></i><?= htmlspecialchars($request['vehicle_name']) ?> 
                                                        (<?= htmlspecialchars($request['plate_number']) ?>)
                                                    </p>
                                                    
                                                    <p class="small mb-3"><?= htmlspecialchars(substr($request['issue_description'], 0, 100)) ?>...</p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?= date('M j, Y H:i', strtotime($request['requested_at'])) ?>
                                                        </small>
                                                        <span class="badge bg-<?= 
                                                            $request['status'] == 'completed' ? 'success' : 
                                                            ($request['status'] == 'in_progress' ? 'primary' : 'info') 
                                                        ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Action Buttons for My Requests -->
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <?php if ($request['status'] == 'assigned'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="start">
                                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-play me-1"></i>Start Work
                                                                </button>
                                                            </form>
                                                        <?php elseif ($request['status'] == 'in_progress'): ?>
                                                            <button type="button" class="btn btn-sm btn-warning" 
                                                                    data-bs-toggle="modal" data-bs-target="#completeModal<?= $request['id'] ?>">
                                                                <i class="fas fa-check me-1"></i>Complete
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($request['status'] != 'completed'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                    data-bs-toggle="modal" data-bs-target="#notesModal<?= $request['id'] ?>">
                                                                <i class="fas fa-sticky-note me-1"></i>Notes
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Complete Modal -->
                                        <?php if ($request['status'] == 'in_progress'): ?>
                                            <div class="modal fade" id="completeModal<?= $request['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Complete Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="complete">
                                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="completion_notes<?= $request['id'] ?>" class="form-label">Completion Notes</label>
                                                                    <textarea class="form-control" id="completion_notes<?= $request['id'] ?>" 
                                                                              name="completion_notes" rows="3" 
                                                                              placeholder="Describe what was done to fix the issue..."></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="actual_cost<?= $request['id'] ?>" class="form-label">Actual Cost (₱)</label>
                                                                    <input type="number" class="form-control" id="actual_cost<?= $request['id'] ?>" 
                                                                           name="actual_cost" step="0.01" min="0" 
                                                                           placeholder="0.00">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">Complete Request</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Notes Modal -->
                                        <?php if ($request['status'] != 'completed'): ?>
                                            <div class="modal fade" id="notesModal<?= $request['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Add/Update Notes</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="add_notes">
                                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="mechanic_notes<?= $request['id'] ?>" class="form-label">Work Notes</label>
                                                                    <textarea class="form-control" id="mechanic_notes<?= $request['id'] ?>" 
                                                                              name="mechanic_notes" rows="4" 
                                                                              placeholder="Add notes about diagnosis, parts needed, work progress..."><?= htmlspecialchars($request['mechanic_notes'] ?? '') ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="estimated_cost<?= $request['id'] ?>" class="form-label">Estimated Cost (₱)</label>
                                                                    <input type="text" class="form-control numeric-amount" id="estimated_cost<?= $request['id'] ?>" 
                                                                           name="estimated_cost" inputmode="decimal" pattern="^\\d*(\\.\\d{0,2})?$" 
                                                                           value="<?= $request['estimated_cost'] ?? '' ?>"
                                                                           placeholder="0.00" aria-describedby="costHelp<?= $request['id'] ?>">
                                                                    <small id="costHelp<?= $request['id'] ?>" class="text-muted">Numbers only, up to 2 decimal places.</small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Save Notes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const linkTexts = document.querySelectorAll('.link-text');

        if (burgerBtn) {
            burgerBtn.addEventListener('click', () => {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile) {
                    sidebar.classList.toggle('show');
                } else {
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');

                    linkTexts.forEach(text => {
                        text.style.display = isCollapsed ? 'none' : 'inline';
                    });

                    // Close any open submenus when collapsing the sidebar
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

        // Adjust modal top spacing to avoid navbar overlap
        document.querySelectorAll('.modal').forEach(m => {
            m.addEventListener('shown.bs.modal', () => {
                const nav = document.querySelector('.navbar');
                const dialog = m.querySelector('.modal-dialog');
                if (nav && dialog) {
                    const h = nav.offsetHeight || 60;
                    dialog.style.marginTop = (h + 10) + 'px';
                }
            });
            m.addEventListener('hidden.bs.modal', () => {
                const dialog = m.querySelector('.modal-dialog');
                if (dialog) dialog.style.marginTop = '';
            });
        });
        // Enforce numeric-only typing with 2 decimal places
        function attachNumericHandlers() {
            document.querySelectorAll('.numeric-amount').forEach(inp => {
                // block non numeric keys
                inp.addEventListener('keydown', (e) => {
                    const allowed = ['Backspace','Tab','ArrowLeft','ArrowRight','Delete','Home','End'];
                    const isCtrl = e.ctrlKey || e.metaKey;
                    if (isCtrl && (e.key === 'a' || e.key === 'c' || e.key === 'v' || e.key === 'x')) return; // allow shortcuts
                    if (allowed.includes(e.key)) return;
                    if (e.key === '.' && !inp.value.includes('.')) return;
                    if (/^[0-9]$/.test(e.key)) return;
                    e.preventDefault();
                });
                // sanitize on input
                inp.addEventListener('input', () => {
                    let v = inp.value.replace(/[^0-9.]/g, '');
                    const parts = v.split('.');
                    if (parts.length > 2) {
                        v = parts[0] + '.' + parts.slice(1).join('');
                    }
                    const [intPart, decPart] = v.split('.');
                    v = intPart || '';
                    if (typeof decPart !== 'undefined') v += '.' + decPart.slice(0,2);
                    inp.value = v;
                });
            });
        }
        attachNumericHandlers();
        // when modals are created dynamically, re-attach after shown
        document.querySelectorAll('.modal').forEach(m => m.addEventListener('shown.bs.modal', attachNumericHandlers));
    </script>
    <script>
        // Sidebar toggle functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const linkTexts = document.querySelectorAll('.link-text');

        if (burgerBtn) {
            burgerBtn.addEventListener('click', () => {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile) {
                    sidebar.classList.toggle('show');
                } else {
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');

                    linkTexts.forEach(text => {
                        text.style.display = isCollapsed ? 'none' : 'inline';
                    });
                }
            });
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
