<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

// ✅ Check if session role is set and user is a mechanic
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// ✅ Mechanic ID from session
$mechanicId = (int) $_SESSION['user_id'];

// ✅ Fetch maintenance jobs assigned to this mechanic
$jobs = [];
$jobsQuery = $conn->prepare("
    SELECT ms.*, fv.article, fv.plate_number, fv.unit 
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    WHERE ms.assigned_mechanic = ?
    ORDER BY ms.start_time DESC
    LIMIT 5
");
if ($jobsQuery) {
    $jobsQuery->bind_param("i", $mechanicId);
    $jobsQuery->execute();
    $jobs = $jobsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $jobsQuery->close();
}

// ✅ Fetch pending requests (not yet assigned) - use prepared statement for consistency
$pendingRequests = [];
$pending_stmt = $conn->prepare("
    SELECT ms.*, fv.article, fv.plate_number 
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    WHERE ms.status = 'pending'
    ORDER BY ms.start_time ASC
    LIMIT 5
");
if ($pending_stmt) {
    $pending_stmt->execute();
    $pendingQuery = $pending_stmt->get_result();
    $pendingRequests = $pendingQuery->fetch_all(MYSQLI_ASSOC);
    $pending_stmt->close();
}

// ✅ Dashboard Statistics
$stats = [];

// Total jobs assigned to this mechanic
$totalJobsQuery = $conn->prepare("SELECT COUNT(*) as total FROM maintenance_schedules WHERE assigned_mechanic = ?");
$totalJobsQuery->bind_param("i", $mechanicId);
$totalJobsQuery->execute();
$stats['total_jobs'] = $totalJobsQuery->get_result()->fetch_assoc()['total'];
$totalJobsQuery->close();

// Completed jobs this month
$completedQuery = $conn->prepare("
    SELECT COUNT(*) as completed 
    FROM maintenance_schedules 
    WHERE assigned_mechanic = ? AND status = 'completed' 
    AND MONTH(updated_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(updated_at) = YEAR(CURRENT_DATE())
");
$completedQuery->bind_param("i", $mechanicId);
$completedQuery->execute();
$stats['completed_this_month'] = $completedQuery->get_result()->fetch_assoc()['completed'];
$completedQuery->close();

// In progress jobs
$inProgressQuery = $conn->prepare("SELECT COUNT(*) as in_progress FROM maintenance_schedules WHERE assigned_mechanic = ? AND status = 'in_progress'");
$inProgressQuery->bind_param("i", $mechanicId);
$inProgressQuery->execute();
$stats['in_progress'] = $inProgressQuery->get_result()->fetch_assoc()['in_progress'];
$inProgressQuery->close();

// Pending requests (all mechanics) - use prepared statement for consistency
$pendingCountQuery = $conn->prepare("SELECT COUNT(*) as pending FROM maintenance_schedules WHERE status = 'pending'");
$pendingCountQuery->execute();
$stats['pending_requests'] = $pendingCountQuery->get_result()->fetch_assoc()['pending'];
$pendingCountQuery->close();

// Completed jobs today
$completedTodayQuery = $conn->prepare("
    SELECT COUNT(*) as completed 
    FROM maintenance_schedules 
    WHERE assigned_mechanic = ? AND status = 'completed' 
    AND DATE(updated_at) = CURRENT_DATE()
");
$completedTodayQuery->bind_param("i", $mechanicId);
$completedTodayQuery->execute();
$stats['completed_today'] = $completedTodayQuery->get_result()->fetch_assoc()['completed'];
$completedTodayQuery->close();

// Completed jobs this week
$completedWeekQuery = $conn->prepare("
    SELECT COUNT(*) as completed 
    FROM maintenance_schedules 
    WHERE assigned_mechanic = ? AND status = 'completed' 
    AND updated_at >= DATE_SUB(CURRENT_DATE(), INTERVAL WEEKDAY(CURRENT_DATE()) DAY)
");
$completedWeekQuery->bind_param("i", $mechanicId);
$completedWeekQuery->execute();
$stats['completed_this_week'] = $completedWeekQuery->get_result()->fetch_assoc()['completed'];
$completedWeekQuery->close();

// Average completion time (in days)
$avgTimeQuery = $conn->prepare("
    SELECT AVG(DATEDIFF(updated_at, start_time)) as avg_days 
    FROM maintenance_schedules 
    WHERE assigned_mechanic = ? AND status = 'completed' 
    AND start_time IS NOT NULL AND updated_at IS NOT NULL
");
$avgTimeQuery->bind_param("i", $mechanicId);
$avgTimeQuery->execute();
$avgResult = $avgTimeQuery->get_result()->fetch_assoc();
$stats['avg_completion_time'] = $avgResult['avg_days'] ? round($avgResult['avg_days'], 1) : 0;
$avgTimeQuery->close();

// Recent activity (last 3 days, limit to 5 items)
$recentActivityQuery = $conn->prepare("
    SELECT ms.*, fv.article, fv.plate_number, fv.unit
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    WHERE ms.assigned_mechanic = ? 
    AND ms.updated_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ORDER BY ms.updated_at DESC
    LIMIT 5
");
$recentActivityQuery->bind_param("i", $mechanicId);
$recentActivityQuery->execute();
$recentActivity = $recentActivityQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$recentActivityQuery->close();

// ✅ Fetch monthly performance data for the last 6 months
$monthlyData = [];
$monthlyQuery = $conn->prepare("
    SELECT 
        MONTH(updated_at) as month_num,
        MONTHNAME(updated_at) as month_name,
        COUNT(*) as completed_jobs
    FROM maintenance_schedules 
    WHERE assigned_mechanic = ? 
    AND status = 'completed' 
    AND updated_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(updated_at), MONTH(updated_at)
    ORDER BY YEAR(updated_at), MONTH(updated_at)
");
if ($monthlyQuery) {
    $monthlyQuery->bind_param("i", $mechanicId);
    $monthlyQuery->execute();
    $monthlyResults = $monthlyQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $monthlyQuery->close();
    
    // Create array with month names as keys
    foreach ($monthlyResults as $row) {
        $monthlyData[$row['month_name']] = $row['completed_jobs'];
    }
}

// ✅ Get last 6 months for display (based on current date)
$last6Months = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('M', strtotime("-$i months"));
    $last6Months[] = $date;
}

// ✅ Debug: Let's see what data we're getting
echo "<!-- DEBUG: Monthly Data: " . json_encode($monthlyData) . " -->";
echo "<!-- DEBUG: Monthly Results: " . json_encode($monthlyResults ?? []) . " -->";
echo "<!-- DEBUG: Completed This Month: " . $stats['completed_this_month'] . " -->";
echo "<!-- DEBUG: Current Date: " . date('Y-m-d') . " -->";
echo "<!-- DEBUG: Mechanic ID: " . $mechanicId . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Mechanic Dashboard | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
      overflow-x: hidden;
    }
    
    /* Force mobile-first approach */
    * {
      box-sizing: border-box;
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

    /* Sidebar backdrop for mobile */
    .sidebar-backdrop {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      background: rgba(0,0,0,0.25) !important;
      z-index: 1100 !important;
    }

/* Ensure dropdown toggle is clickable */
.sidebar .dropdown-toggle:hover {
  background-color: rgba(255, 255, 255, 0.1) !important;
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

    /* Dashboard specific styles */
    .main-content {
      margin-left: 250px;
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
      min-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    /* Statistics Cards - Simplified */
    .stat-card {
      background: #667eea;
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .stat-card.success {
      background: #4facfe;
    }

    .stat-card.warning {
      background: #fa709a;
    }

    .stat-card.info {
      background: #a8edea;
      color: #333;
    }

    .stat-card.danger {
      background: #ff9a9e;
      color: #333;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 0.9rem;
      opacity: 0.9;
    }

    .stat-icon {
      font-size: 2rem;
      opacity: 0.8;
    }

    /* Chart Container */
    .chart-container {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }

    /* Activity Feed */
    .activity-item {
      padding: 15px;
      border-left: 4px solid var(--accent);
      background: white;
      margin-bottom: 10px;
      border-radius: 0 10px 10px 0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .activity-time {
      font-size: 0.8rem;
      color: #666;
    }


    /* Progress Bars */
    .progress-custom {
      height: 8px;
      border-radius: 10px;
      background: #e9ecef;
    }

    .progress-bar-custom {
      background: linear-gradient(90deg, var(--accent), #4facfe);
      border-radius: 10px;
    }

    /* Performance Items - Lightweight */
    .performance-item {
      padding: 10px;
      border-radius: 8px;
      background: #f8f9fa;
      margin-bottom: 10px;
    }

    .performance-number {
      font-size: 1.8rem;
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 5px;
    }

    .performance-label {
      font-size: 0.9rem;
      color: #666;
      font-weight: 500;
    }

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
      
      h2.mb-0 { font-size: 1.25rem !important; }
      .stat-card { 
        padding: 14px !important; 
        margin-bottom: 1rem !important;
        width: 100% !important;
      }
      .stat-number { font-size: 1.8rem !important; }
      .stat-icon { font-size: 1.4rem !important; }
      .card-body { padding: 14px !important; }
      
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
      
      /* Force mobile layout for current work status */
      .row .col-md-6 {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
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

    /* Phone tweaks for more native feel */
    @media (max-width: 575.98px) {
      .main-content { 
        padding: 12px !important; 
        margin-top: 60px !important;
      }
      .stat-card { 
        padding: 12px !important; 
        border-radius: 12px !important; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.06) !important; 
        margin-bottom: 1rem !important;
      }
      .stat-number { font-size: 1.6rem !important; }
      .stat-label { font-size: 0.85rem !important; }
      .card .card-title { font-size: 1rem !important; margin-bottom: 8px !important; }
      .card-body { padding: 12px !important; }
      .progress-custom { height: 6px !important; }
      .activity-item { 
        padding: 12px !important; 
        border-radius: 10px !important; 
        margin-bottom: 0.75rem !important;
      }
      
      /* Header adjustments for mobile */
      .d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
      }
      
      .d-flex.justify-content-between .text-muted {
        font-size: 0.85rem !important;
      }
      
      /* Better spacing for dashboard cards */
      .row.g-4 > * {
        margin-bottom: 1.5rem !important;
      }
      
      /* Current work status mobile layout */
      .row .col-md-6 {
        margin-bottom: 1rem !important;
      }
      
      /* Activity and jobs sections */
      .col-lg-6 {
        margin-bottom: 1.5rem !important;
      }
      
      /* Button adjustments */
      .btn {
        font-size: 0.9rem !important;
        padding: 8px 16px !important;
      }
      
      /* Badge adjustments */
      .badge {
        font-size: 0.75rem !important;
      }
      
      /* Text size adjustments */
      h6 { font-size: 0.9rem !important; }
      small { font-size: 0.75rem !important; }
      p { font-size: 0.85rem !important; }
      
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
    }
    
    /* Extra small devices (phones, less than 576px) */
    @media (max-width: 375px) {
      .main-content {
        padding: 8px !important;
      }
      
      .stat-card {
        padding: 10px !important;
      }
      
      .stat-number {
        font-size: 1.4rem !important;
      }
      
      h2.mb-0 {
        font-size: 1.1rem !important;
      }
      
      .card-body {
        padding: 10px !important;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Mechanic Dashboard</h2>
      <div class="text-muted">
        <i class="fas fa-calendar me-1"></i>
        <?= date('F j, Y') ?>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="stat-card success">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['total_jobs'] ?></div>
              <div class="stat-label">Total Jobs</div>
            </div>
            <div class="stat-icon">
              <i class="fas fa-tools"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card info">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['completed_this_month'] ?></div>
              <div class="stat-label">Completed This Month</div>
            </div>
            <div class="stat-icon">
              <i class="fas fa-check-circle"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card warning">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['in_progress'] ?></div>
              <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-icon">
              <i class="fas fa-clock"></i>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card danger">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['pending_requests'] ?></div>
              <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
          </div>
        </div>
      </div>
    </div>


    <div class="row g-4">
      <!-- Current Work Status -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-tools me-2"></i>Current Work Status</h5>
            <div class="row">
              <div class="col-md-6 mb-3">
                <div class="d-flex justify-content-between align-items-center p-3 bg-success bg-opacity-10 rounded">
                  <div>
                    <h6 class="mb-0 text-success">Completed Today</h6>
                    <small class="text-muted">Jobs finished</small>
                  </div>
                  <div class="text-end">
                    <h4 class="mb-0 text-success"><?= $stats['completed_today'] ?></h4>
                  </div>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="d-flex justify-content-between align-items-center p-3 bg-info bg-opacity-10 rounded">
                  <div>
                    <h6 class="mb-0 text-info">This Week</h6>
                    <small class="text-muted">Total completed</small>
                  </div>
                  <div class="text-end">
                    <h4 class="mb-0 text-info"><?= $stats['completed_this_week'] ?></h4>
                  </div>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="d-flex justify-content-between align-items-center p-3 bg-primary bg-opacity-10 rounded">
                  <div>
                    <h6 class="mb-0 text-primary">Avg. Time</h6>
                    <small class="text-muted">Days per job</small>
                  </div>
                  <div class="text-end">
                    <h4 class="mb-0 text-primary"><?= round($stats['avg_completion_time'], 1) ?></h4>
                  </div>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="d-flex justify-content-between align-items-center p-3 bg-secondary bg-opacity-10 rounded">
                  <div>
                    <h6 class="mb-0 text-secondary">Efficiency</h6>
                    <small class="text-muted">Completion rate</small>
                  </div>
                  <div class="text-end">
                    <h4 class="mb-0 text-secondary"><?= $stats['total_jobs'] > 0 ? round(($stats['completed_this_month'] / $stats['total_jobs']) * 100, 1) : 0 ?>%</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Workload Summary -->
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-chart-pie me-2"></i>Workload Summary</h5>
            
            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>Completed</span>
                <span><?= $stats['completed_this_month'] ?></span>
              </div>
              <div class="progress progress-custom">
                <div class="progress-bar progress-bar-custom" style="width: <?= $stats['total_jobs'] > 0 ? ($stats['completed_this_month'] / $stats['total_jobs']) * 100 : 0 ?>%"></div>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>In Progress</span>
                <span><?= $stats['in_progress'] ?></span>
              </div>
              <div class="progress progress-custom">
                <div class="progress-bar bg-warning" style="width: <?= $stats['total_jobs'] > 0 ? ($stats['in_progress'] / $stats['total_jobs']) * 100 : 0 ?>%"></div>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>Pending</span>
                <span><?= $stats['pending_requests'] ?></span>
              </div>
              <div class="progress progress-custom">
                <div class="progress-bar bg-danger" style="width: <?= $stats['total_jobs'] > 0 ? ($stats['pending_requests'] / $stats['total_jobs']) * 100 : 0 ?>%"></div>
              </div>
            </div>

            <hr>
            <div class="text-center">
              <h6>Average Completion Time</h6>
              <h4 class="text-primary"><?= $stats['avg_completion_time'] ?> days</h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-2">
      <!-- Recent Activity -->
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-history me-2"></i>Recent Activity</h5>
            <div style="max-height: 250px; overflow-y: auto;">
              <?php if (!empty($recentActivity)): ?>
                <?php foreach($recentActivity as $activity): ?>
                  <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($activity['article']) ?> (<?= $activity['plate_number'] ?>)</h6>
                        <p class="mb-1"><?= ucfirst(str_replace('_', ' ', $activity['maintenance_type'])) ?></p>
                        <span class="badge bg-<?= 
                          $activity['status']=='completed' ? 'success' : 
                          ($activity['status']=='in_progress' ? 'primary' : 'warning') ?>">
                          <?= ucfirst($activity['status']) ?>
                        </span>
                      </div>
                      <small class="activity-time">
                        <?= date('M j, H:i', strtotime($activity['updated_at'])) ?>
                      </small>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-muted text-center py-4">No recent activity</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Jobs -->
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-tools me-2"></i>Current Jobs</h5>
            <div style="max-height: 250px; overflow-y: auto;">
              <?php if (!empty($jobs)): ?>
                <?php foreach($jobs as $job): ?>
                  <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($job['article']) ?> (<?= $job['plate_number'] ?>)</h6>
                        <p class="mb-1 text-muted"><?= ucfirst(str_replace('_', ' ', $job['maintenance_type'])) ?></p>
                        <small class="text-muted">
                          <i class="fas fa-calendar me-1"></i>
                          <?= date("M j, Y H:i", strtotime($job['start_time'])) ?>
                        </small>
                      </div>
                      <span class="badge bg-<?= 
                        $job['status']=='completed' ? 'success' : 
                        ($job['status']=='in_progress' ? 'primary' : 'warning') ?>">
                        <?= ucfirst($job['status']) ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="text-center">
                  <a href="maintenance.php" class="btn btn-outline-primary">View All Jobs</a>
                </div>
              <?php else: ?>
                <p class="text-muted text-center py-4">No jobs assigned yet</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ✅ Dynamic Monthly Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyChart');
    if (ctx) {
        const monthlyData = <?= json_encode($monthlyData) ?>;
        const last6Months = <?= json_encode($last6Months) ?>;
        
        // Prepare data for chart
        const chartData = last6Months.map(month => monthlyData[month] || 0);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: last6Months,
                datasets: [{
                    label: 'Jobs Completed',
                    data: chartData,
                    borderColor: '#00b4d8',
                    backgroundColor: 'rgba(0, 180, 216, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#00b4d8',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#666'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#666'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#003566'
                    }
                }
            }
        });
    }
});

// Sidebar toggle functionality - Updated to work with navbar implementation
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

// Function to check if we're on mobile
function isMobile() {
    return window.innerWidth <= 991.98;
}

// Desktop sidebar collapse/expand functionality
function handleDesktopSidebar() {
    if (isMobile()) return;
    
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

// Enhanced burger button functionality - works with navbar script
if (burgerBtn) {
    // Clone the burger button to remove existing event listeners from navbar script
    const newBurgerBtn = burgerBtn.cloneNode(true);
    burgerBtn.parentNode.replaceChild(newBurgerBtn, burgerBtn);
    
    // Add comprehensive click handler that works for both mobile and desktop
    newBurgerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (isMobile()) {
            // Mobile behavior - toggle sidebar with backdrop
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                // Remove backdrop if it exists
                const backdrop = document.querySelector('.sidebar-backdrop');
                if (backdrop) backdrop.remove();
            } else {
                sidebar.classList.add('open');
                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                backdrop.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.25);
                    z-index: 1100;
                `;
                backdrop.addEventListener('click', () => {
                    sidebar.classList.remove('open');
                    backdrop.remove();
                });
                document.body.appendChild(backdrop);
            }
        } else {
            // Desktop behavior - handle sidebar collapse
            handleDesktopSidebar();
        }
    });
    
    // Add touch support for mobile
    newBurgerBtn.addEventListener('touchstart', (e) => {
        if (isMobile()) {
            // Prevent default touch behavior
            e.preventDefault();
            e.stopPropagation();
            // Trigger the same logic as click
            newBurgerBtn.click();
        }
    }, { passive: false });
    
    // Update reference to new button
    const updatedBurgerBtn = newBurgerBtn;
}

// Handle window resize
window.addEventListener('resize', () => {
    if (window.innerWidth > 991.98) {
        // Desktop view - clean up mobile states
        sidebar.classList.remove('show', 'open');
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        
        // Remove any mobile backdrops
        const backdrops = document.querySelectorAll('.sidebar-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        linkTexts.forEach(text => {
            text.style.display = 'inline';
        });
    } else {
        // Mobile view - clean up desktop states
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        
        linkTexts.forEach(text => {
            text.style.display = 'inline';
        });
    }
});

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
        if (window.innerWidth <= 991.98) {
            // On mobile, prevent default Bootstrap behavior and handle manually
            e.preventDefault();
            e.stopPropagation();
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

// Prevent sub-menu clicks from interfering with dropdown toggle
const subMenuLinks = document.querySelectorAll('.collapse a');
subMenuLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        // Don't prevent navigation, but ensure dropdown state is maintained
        e.stopPropagation();
    });
});

    // Comprehensive dropdown toggle fix
    dropdownToggles.forEach(toggle => {
      // Skip if this is the logout button (not a dropdown toggle)
      if (toggle.id === 'logoutBtn') {
        return;
      }
      
      // Remove any existing event listeners by cloning the element
      const newToggle = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(newToggle, toggle);
      
      // Add comprehensive click handler
      newToggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const targetId = newToggle.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
        const chevron = newToggle.querySelector('.dropdown-chevron');
        
        if (targetElement && chevron) {
          const isCurrentlyOpen = targetElement.classList.contains('show');
          
          // Close all other dropdowns first
          const allDropdowns = document.querySelectorAll('.collapse');
          allDropdowns.forEach(dropdown => {
            if (dropdown !== targetElement && dropdown.classList.contains('show')) {
              dropdown.classList.remove('show');
              // Update other chevrons
              const otherToggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
              if (otherToggle) {
                const otherChevron = otherToggle.querySelector('.dropdown-chevron');
                if (otherChevron) {
                  otherChevron.style.transform = 'rotate(0deg)';
                  otherToggle.setAttribute('aria-expanded', 'false');
                }
              }
            }
          });
          
          // Toggle current dropdown with smooth animation
          if (isCurrentlyOpen) {
            // Close dropdown
            targetElement.classList.remove('show');
            targetElement.style.height = '0px';
            targetElement.style.overflow = 'hidden';
            chevron.style.transform = 'rotate(0deg)';
            newToggle.setAttribute('aria-expanded', 'false');
          } else {
            // Open dropdown
            targetElement.style.height = 'auto';
            targetElement.style.overflow = 'visible';
            targetElement.classList.add('show');
            chevron.style.transform = 'rotate(90deg)';
            newToggle.setAttribute('aria-expanded', 'true');
          }
        }
      });
    
    // Ensure chevron starts in correct position
    const targetId = newToggle.getAttribute('data-bs-target');
    const targetElement = document.querySelector(targetId);
    const chevron = newToggle.querySelector('.dropdown-chevron');
    
    if (targetElement && chevron) {
        const isOpen = targetElement.classList.contains('show');
        chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
        newToggle.setAttribute('aria-expanded', isOpen);
    }
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

// Enhanced logout functionality - works reliably
function initializeLogout() {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn && !logoutBtn.dataset.initialized) {
        // Mark as initialized to prevent multiple event listeners
        logoutBtn.dataset.initialized = 'true';
        
        logoutBtn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
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
}

// Initialize logout when DOM is ready
document.addEventListener("DOMContentLoaded", initializeLogout);

// Also try to initialize after a short delay to ensure all scripts are loaded
setTimeout(initializeLogout, 500);

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

<?php if (isset($_SESSION['login_success'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Login Successful',
    text: '<?= $_SESSION['login_success'] ?>',
    timer: 3000,
    showConfirmButton: false
});
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>
</script>

</body>
</html>
