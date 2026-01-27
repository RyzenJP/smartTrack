<?php
session_start();
// Check if user is logged in and is a dispatcher
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}

// Include the database connection file
require_once __DIR__ . '/../db_connection.php';

// Prepare message variables for SweetAlert
$message = '';
$isSuccess = false;

// Function to fetch available drivers from the database - use prepared statement for consistency
function getAvailableDrivers($conn) {
    $sql = "SELECT user_id, full_name FROM user_table WHERE role = 'driver' AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $drivers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $drivers[] = $row;
        }
    }
    $stmt->close();
    return $drivers;
}

// Function to fetch available vehicles from the database (filter out synthetic vehicles) - use prepared statement for consistency
function getAvailableVehicles($conn) {
    $sql = "SELECT id, unit, plate_number FROM fleet_vehicles WHERE status = 'active' AND article NOT LIKE '%Synthetic%' AND plate_number NOT LIKE 'SYN-%' AND plate_number NOT LIKE '%SYN%'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicles = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $vehicles[] = $row;
        }
    }
    $stmt->close();
    return $vehicles;
}

// Fetch the data from the database
$availableDrivers = getAvailableDrivers($conn);
$availableVehicles = getAvailableVehicles($conn);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $driverId = $conn->real_escape_string($_POST['driver_id']);
    $vehicleId = $conn->real_escape_string($_POST['vehicle_id']);
    $routeName = $conn->real_escape_string($_POST['route_name']);
    $startLat = $conn->real_escape_string($_POST['start_lat']);
    $startLng = $conn->real_escape_string($_POST['start_lng']);
    $endLat = $conn->real_escape_string($_POST['end_lat']);
    $endLng = $conn->real_escape_string($_POST['end_lng']);

    // SQL to insert a new trip into the `driver_routes` table
    $sql = "INSERT INTO driver_routes (driver_id, vehicle_id, route_name, start_lat, start_lng, end_lat, end_lng) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // Check if the statement was prepared successfully
    if ($stmt === false) {
        $message = "Error preparing statement: " . $conn->error;
        $isSuccess = false;
    } else {
        // Bind parameters and execute
        $stmt->bind_param("iisdidi", $driverId, $vehicleId, $routeName, $startLat, $startLng, $endLat, $endLng);
        
        if ($stmt->execute()) {
            $message = "Trip successfully scheduled for Driver ID: " . $driverId;
            $isSuccess = true;
        } else {
            $message = "Error scheduling trip: " . $stmt->error;
            $isSuccess = false;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Schedule Trips | Smart Track</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
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

        .sidebar.collapsed .link-text,
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
        
        /* Main content */
        .main-content {
            margin-left: 250px;
            margin-top: 80px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Card styles */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .form-control, .form-select {
            border-radius: 0.3rem;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="d-flex align-items-center justify-content-center p-3">
        <span class="fs-4 fw-bold text-white text-uppercase link-text">Smart Track</span>
    </div>
    <ul class="list-unstyled">
        <li>
            <a href="dashboard.php">
                <i class="fas fa-home me-3"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="#tripSubmenu" data-bs-toggle="collapse" class="dropdown-toggle" aria-expanded="true">
                <i class="fas fa-road me-3"></i>
                <span class="link-text">Trips</span>
                <i class="fas fa-chevron-right float-end mt-1 dropdown-chevron"></i>
            </a>
            <ul class="collapse list-unstyled show" id="tripSubmenu">
                <li><a href="schedule-trips.php" class="active">Schedule Trips</a></li>
                <li><a href="trip-history.php">Trip History</a></li>
            </ul>
        </li>
        <li>
            <a href="#fleetSubmenu" data-bs-toggle="collapse" class="dropdown-toggle" aria-expanded="false">
                <i class="fas fa-car me-3"></i>
                <span class="link-text">Fleet</span>
                <i class="fas fa-chevron-right float-end mt-1 dropdown-chevron"></i>
            </a>
            <ul class="collapse list-unstyled" id="fleetSubmenu">
                <li><a href="vehicle-list.php">Vehicle List</a></li>
                <li><a href="add-vehicle.php">Add New Vehicle</a></li>
                <li><a href="vehicle-maintenance.php">Maintenance Log</a></li>
            </ul>
        </li>
        <li>
            <a href="#driverSubmenu" data-bs-toggle="collapse" class="dropdown-toggle" aria-expanded="false">
                <i class="fas fa-users me-3"></i>
                <span class="link-text">Drivers</span>
                <i class="fas fa-chevron-right float-end mt-1 dropdown-chevron"></i>
            </a>
            <ul class="collapse list-unstyled" id="driverSubmenu">
                <li><a href="driver-list.php">Driver List</a></li>
                <li><a href="add-driver.php">Add New Driver</a></li>
            </ul>
        </li>
        <li>
            <a href="reports.php">
                <i class="fas fa-chart-line me-3"></i>
                <span class="link-text">Reports</span>
            </a>
        </li>
        <li>
            <a href="settings.php">
                <i class="fas fa-cogs me-3"></i>
                <span class="link-text">Settings</span>
            </a>
        </li>
    </ul>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <button class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand d-none d-lg-block" href="#">
            <span class="text-primary fw-bold">Schedule Trip</span>
        </a>
        <div class="ms-auto me-3 d-flex align-items-center">
            <div class="dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://via.placeholder.com/40" alt="User" class="rounded-circle me-2" />
                    <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Dispatcher'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user-circle"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Account Settings</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item text-danger" href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">Schedule New Trip</h4>
                    </div>
                    <div class="card-body">
                        <form id="scheduleTripForm" method="POST" action="">
                            <div class="mb-3">
                                <label for="driverSelect" class="form-label">Select Driver</label>
                                <select class="form-select" id="driverSelect" name="driver_id" required>
                                    <option value="" disabled selected>Choose a driver</option>
                                    <?php foreach ($availableDrivers as $driver): ?>
                                        <option value="<?= $driver['user_id'] ?>"><?= htmlspecialchars($driver['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="vehicleSelect" class="form-label">Select Vehicle</label>
                                <select class="form-select" id="vehicleSelect" name="vehicle_id" required>
                                    <option value="" disabled selected>Choose a vehicle</option>
                                    <?php foreach ($availableVehicles as $vehicle): ?>
                                        <option value="<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['unit'] . ' (' . $vehicle['plate_number'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="routeName" class="form-label">Route Name</label>
                                <input type="text" class="form-control" id="routeName" name="route_name" placeholder="e.g., North End Delivery" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="startLat" class="form-label">Start Latitude</label>
                                    <input type="text" class="form-control" id="startLat" name="start_lat" placeholder="e.g., 10.553387" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="startLng" class="form-label">Start Longitude</label>
                                    <input type="text" class="form-control" id="startLng" name="start_lng" placeholder="e.g., 122.913818" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="endLat" class="form-label">End Latitude</label>
                                    <input type="text" class="form-control" id="endLat" name="end_lat" placeholder="e.g., 10.553387" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="endLng" class="form-label">End Longitude</label>
                                    <input type="text" class="form-control" id="endLng" name="end_lng" placeholder="e.g., 122.913818" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-calendar-alt me-2"></i>Schedule Trip
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Check if a message from the form submission exists and show a SweetAlert
    <?php if ($message && $isSuccess): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($message) ?>',
            showConfirmButton: false,
            timer: 2000
        });
    <?php elseif ($message): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?= addslashes($message) ?>',
            confirmButtonColor: '#003566',
        });
    <?php endif; ?>

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
