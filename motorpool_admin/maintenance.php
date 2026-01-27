<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

require_once __DIR__ . '/../db_connection.php';

// Allow Super Admin and Motor Pool Admin to access maintenance history
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'motor_pool_admin'])) {
    header("Location: ../index.php");
    exit();
}


// Include security class for input sanitization
require_once __DIR__ . '/../config/security.php';
$security = Security::getInstance();

// Pagination and Filtering - sanitize all inputs
$page = isset($_GET['page']) ? max(1, (int)$security->sanitizeInput($_GET['page'], 'int')) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$security->sanitizeInput($_GET['per_page'], 'int'))) : 10;
$offset = ($page - 1) * $perPage;

// Filters - sanitize all inputs
$statusFilter = isset($_GET['status']) ? $security->sanitizeInput(trim($_GET['status']), 'string') : 'all';
$vehicleFilter = isset($_GET['vehicle']) ? (int)$security->sanitizeInput($_GET['vehicle'], 'int') : 0;
$mechanicFilter = isset($_GET['mechanic']) ? (int)$security->sanitizeInput($_GET['mechanic'], 'int') : 0;
$searchQuery = isset($_GET['search']) ? $security->sanitizeInput(trim($_GET['search']), 'string') : '';
$dateFrom = isset($_GET['date_from']) ? $security->sanitizeInput(trim($_GET['date_from']), 'string') : '';
$dateTo = isset($_GET['date_to']) ? $security->sanitizeInput(trim($_GET['date_to']), 'string') : '';

// Build WHERE conditions
$whereConditions = ["ms.status IN ('completed', 'cancelled')"];
$params = [];
$paramTypes = '';

// Status filter
if ($statusFilter !== 'all') {
    $whereConditions[] = "ms.status = ?";
    $params[] = $statusFilter;
    $paramTypes .= 's';
}

// Vehicle filter
if ($vehicleFilter > 0) {
    $whereConditions[] = "ms.vehicle_id = ?";
    $params[] = $vehicleFilter;
    $paramTypes .= 'i';
}

// Mechanic filter
if ($mechanicFilter > 0) {
    $whereConditions[] = "ms.assigned_mechanic = ?";
    $params[] = $mechanicFilter;
    $paramTypes .= 'i';
}

// Date range filters
if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(ms.start_time) >= ?";
    $params[] = $dateFrom;
    $paramTypes .= 's';
}
if (!empty($dateTo)) {
    $whereConditions[] = "DATE(ms.start_time) <= ?";
    $params[] = $dateTo;
    $paramTypes .= 's';
}

// Search filter
if (!empty($searchQuery)) {
    $whereConditions[] = "(fv.article LIKE ? OR fv.plate_number LIKE ? OR fv.unit LIKE ? OR u.username LIKE ? OR ms.maintenance_type LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= 'sssss';
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    LEFT JOIN user_table u ON ms.assigned_mechanic = u.user_id
    WHERE {$whereClause}
";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$totalCount = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = ceil($totalCount / $perPage);

// Fetch mechanic work orders with pagination
$mechanic_work_query = "
    SELECT ms.*,
           fv.article AS vehicle_name,
           fv.plate_number,
           fv.unit,
           u.username AS mechanic_name
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    LEFT JOIN user_table u ON ms.assigned_mechanic = u.user_id
    WHERE {$whereClause}
    ORDER BY ms.end_time DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$paramTypes .= 'ii';

$mechanic_work_stmt = $conn->prepare($mechanic_work_query);
if (!empty($params)) {
    $mechanic_work_stmt->bind_param($paramTypes, ...$params);
}
$mechanic_work_stmt->execute();
$mechanic_work_orders = $mechanic_work_stmt->get_result();

// Get vehicles for filter dropdown
$vehicles_stmt = $conn->prepare("SELECT id, article, plate_number FROM fleet_vehicles WHERE article NOT LIKE '%Synthetic%' AND plate_number NOT LIKE 'SYN-%' ORDER BY article ASC");
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles_list = [];
while ($v = $vehicles_result->fetch_assoc()) {
    $vehicles_list[] = $v;
}
$vehicles_stmt->close();

// Get mechanics for filter dropdown
$mechanics_stmt = $conn->prepare("SELECT user_id, username FROM user_table WHERE role = 'Mechanic' ORDER BY username ASC");
$mechanics_stmt->execute();
$mechanics_result = $mechanics_stmt->get_result();
$mechanics_list = [];
while ($m = $mechanics_result->fetch_assoc()) {
    $mechanics_list[] = $m;
}
$mechanics_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History | Motor Pool Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #003566;
            --accent: #00b4d8;
            --bg: #f8f9fa;
        }

        body {
            background-color: var(--bg);
            font-family: 'Segoe UI', sans-serif;
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

        .sidebar.collapsed .dropdown-chevron,
        .sidebar.collapsed .link-text {
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

        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
        }

        .dropdown-chevron:hover {
            color: var(--accent);
        }

        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(90deg);
        }

        .dropdown-toggle::after {
            display: none;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        /* Navbar */
        .navbar {
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

        .dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 0.95rem;
            color: #343a40;
            transition: all 0.3s ease;
            border-radius: 0.35rem;
        }

        .dropdown-menu .dropdown-item:hover {
            background-color: #001d3d;
            color: var(--accent);
            box-shadow: inset 2px 0 0 var(--accent);
        }

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
        
        /* Main content specific styles */
        .table th {
            background-color: #003566;
            color: white;
        }
        .btn-sm {
            font-size: 0.8rem;
        }
        
        /* Professional table styling */
        #maintenanceTable {
            font-size: 0.9rem;
        }
        #maintenanceTable tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        #maintenanceTable tbody tr {
            border-bottom: 1px solid #e9ecef;
        }
        .table-responsive {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table th, .table td {
            white-space: nowrap;
        }

        /* Filter and Pagination Styles */
        .pagination {
            margin-top: 1rem;
        }
        .pagination .page-link {
            color: var(--primary);
            border-color: #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .pagination .page-link:hover {
            background-color: #f8f9fa;
            color: var(--accent);
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
        
        .filter-controls .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-controls .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        /* Mobile Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                border: none !important;
                box-shadow: none !important;
                overflow-x: auto !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
                width: 100%;
            }
            
            #maintenanceTable {
                font-size: 0.85rem;
                table-layout: fixed !important;
                min-width: 1000px !important; /* Force horizontal scroll */
                width: 1000px !important;
            }
            
            #maintenanceTable thead th {
                padding: 8px 4px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            #maintenanceTable tbody td {
                padding: 8px 4px;
                vertical-align: middle;
                white-space: nowrap;
            }
            
            /* Compact status badges */
            .badge {
                font-size: 0.7rem;
                padding: 4px 6px;
            }
            
            /* Better spacing for mobile */
            .card-body {
                padding: 1rem 0.5rem;
            }
        }

        @media (max-width: 576px) {
            /* Even more compact for very small screens */
            #maintenanceTable {
                font-size: 0.75rem;
                min-width: 900px !important;
                width: 900px !important;
            }
            
            #maintenanceTable thead th,
            #maintenanceTable tbody td {
                padding: 6px 2px;
            }
            
            /* Make status badges smaller */
            .badge {
                font-size: 0.6rem;
                padding: 2px 4px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/navbar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-primary fw-bold">Mechanic Work Orders</h2>
                    <p class="text-muted">Completed and cancelled work orders with days taken tracking</p>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters & Search</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" id="statusFilter">
                                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Vehicle</label>
                                        <select class="form-select" name="vehicle" id="vehicleFilter">
                                            <option value="0">All Vehicles</option>
                                            <?php foreach ($vehicles_list as $vehicle): ?>
                                                <option value="<?= $vehicle['id'] ?>" <?= $vehicleFilter == $vehicle['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($vehicle['article']) ?> (<?= htmlspecialchars($vehicle['plate_number']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Mechanic</label>
                                        <select class="form-select" name="mechanic" id="mechanicFilter">
                                            <option value="0">All Mechanics</option>
                                            <?php foreach ($mechanics_list as $mechanic): ?>
                                                <option value="<?= $mechanic['user_id'] ?>" <?= $mechanicFilter == $mechanic['user_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($mechanic['username']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Results Per Page</label>
                                        <select class="form-select" name="per_page" id="perPageSelect">
                                            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                            <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Date From</label>
                                        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Date To</label>
                                        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" placeholder="Search vehicle, mechanic, type..." value="<?= htmlspecialchars($searchQuery) ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Apply Filters
                                        </button>
                                        <a href="maintenance.php" class="btn btn-secondary">
                                            <i class="fas fa-redo me-2"></i>Reset
                                        </a>
                                        <span class="ms-3 text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Showing <?= $totalCount > 0 ? ($offset + 1) : 0 ?> - <?= min($offset + $perPage, $totalCount) ?> of <?= $totalCount ?> results
                                        </span>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Work Orders</h6>
                                    <h3 class="card-title mb-0"><?= $mechanic_work_orders ? $mechanic_work_orders->num_rows : 0 ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-tools fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Completed</h6>
                                    <h3 class="card-title mb-0">
                                        <?php
                                        $completed_count = 0;
                                        if ($mechanic_work_orders) {
                                            $temp_stmt = $conn->prepare("SELECT COUNT(*) as count FROM maintenance_schedules ms WHERE ms.status = 'completed'");
                                            $temp_stmt->execute();
                                            $temp_result = $temp_stmt->get_result();
                                            $completed_count = $temp_result ? $temp_result->fetch_assoc()['count'] : 0;
                                            $temp_stmt->close();
                                        }
                                        echo $completed_count;
                                        ?>
                                    </h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Same Day</h6>
                                    <h3 class="card-title mb-0">
                                        <?php
                                        $same_day_count = 0;
                                        if ($mechanic_work_orders) {
                                            $temp_stmt = $conn->prepare("SELECT COUNT(*) as count FROM maintenance_schedules ms WHERE ms.status = 'completed' AND ms.days_taken = 0");
                                            $temp_stmt->execute();
                                            $temp_result = $temp_stmt->get_result();
                                            $same_day_count = $temp_result ? $temp_result->fetch_assoc()['count'] : 0;
                                            $temp_stmt->close();
                                        }
                                        echo $same_day_count;
                                        ?>
                                    </h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bolt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Cancelled</h6>
                                    <h3 class="card-title mb-0">
                                        <?php
                                        $cancelled_count = 0;
                                        if ($mechanic_work_orders) {
                                            $temp_stmt = $conn->prepare("SELECT COUNT(*) as count FROM maintenance_schedules ms WHERE ms.status = 'cancelled'");
                                            $temp_stmt->execute();
                                            $temp_result = $temp_stmt->get_result();
                                            $cancelled_count = $temp_result ? $temp_result->fetch_assoc()['count'] : 0;
                                            $temp_stmt->close();
                                        }
                                        echo $cancelled_count;
                                        ?>
                                    </h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-ban fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mechanic Work Orders Integration -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-tools me-2"></i>Mechanic Work Orders
                                <small class="ms-2">(Completed & Cancelled with Days Taken)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($mechanic_work_orders && $mechanic_work_orders->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="maintenanceTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Vehicle</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Start Date</th>
                                                <th>Completed At</th>
                                                <th>Days Taken</th>
                                                <th>Mechanic</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($work_order = $mechanic_work_orders->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($work_order['id']) ?></strong></td>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($work_order['vehicle_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($work_order['plate_number']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?= ucwords(str_replace('_', ' ', $work_order['maintenance_type'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($work_order['status'] === 'completed'): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check-circle me-1"></i>Completed
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-ban me-1"></i>Cancelled
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('M d, Y', strtotime($work_order['start_time'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?= $work_order['end_time'] ? date('M d, Y', strtotime($work_order['end_time'])) : 'N/A' ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($work_order['status'] === 'completed'): ?>
                                                            <?php if ($work_order['days_taken'] && $work_order['days_taken'] > 0): ?>
                                                                <span class="badge bg-success"><?= $work_order['days_taken'] ?> days</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Same day</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Cancelled</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars($work_order['mechanic_name'] ?? 'Unknown') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <!-- First Page -->
                                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        
                                        <!-- Previous Page -->
                                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        if ($startPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                                            </li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                                    <?= $totalPages ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Next Page -->
                                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        
                                        <!-- Last Page -->
                                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No mechanic work orders found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Sidebar and Navbar interaction logic
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (burgerBtn && sidebar && mainContent) {
            burgerBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });
        }

    </script>
</body>
</html>