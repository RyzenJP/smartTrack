<?php
session_start();

// Check if user is a driver
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

$driverId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $notes = $_POST['notes'];
    
    // Handle multiple maintenance types or single type
    $maintenance_types = [];
    if (isset($_POST['maintenance_types']) && is_array($_POST['maintenance_types'])) {
        $maintenance_types = $_POST['maintenance_types'];
    } elseif (isset($_POST['maintenance_type'])) {
        $maintenance_types = [$_POST['maintenance_type']];
    }
    
    // Insert each maintenance type as a separate request but with a common identifier
    $request_id = uniqid('req_', true); // Generate unique request ID
    $success_count = 0;
    $error_count = 0;
    
    foreach ($maintenance_types as $maintenance_type) {
        // Add request ID to notes to group related requests
        $grouped_notes = "[Request ID: {$request_id}] " . $notes;
        if (count($maintenance_types) > 1) {
            $grouped_notes .= " (Part of multi-service request: " . implode(', ', $maintenance_types) . ")";
        }
        
        $stmt = $conn->prepare("
            INSERT INTO maintenance_requests (vehicle_id, driver_id, maintenance_type, notes, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iiss", $vehicle_id, $driverId, $maintenance_type, $grouped_notes);

        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        if ($success_count == 1) {
            $_SESSION['success'] = "Maintenance request submitted successfully.";
        } else {
            $_SESSION['success'] = "Maintenance request submitted successfully for " . $success_count . " services: " . implode(', ', $maintenance_types);
        }
    }
    
    if ($error_count > 0) {
        $_SESSION['error'] = "Failed to submit {$error_count} maintenance request(s). Please try again.";
    }

    header("Location: maintenance-request.php");
    exit();
}

// Fetch the single vehicle assigned to this driver (1:1 ratio) - use prepared statement for security
$vehicle_stmt = $conn->prepare("
    SELECT fv.id, fv.article, fv.plate_number, fv.current_mileage
    FROM fleet_vehicles fv
    JOIN vehicle_assignments va ON fv.id = va.vehicle_id
    WHERE va.driver_id = ? AND va.status = 'active'
    LIMIT 1
");
$vehicle_stmt->bind_param("i", $driverId);
$vehicle_stmt->execute();
$vehicle_result = $vehicle_stmt->get_result();
$assigned_vehicle = $vehicle_result->fetch_assoc();
$vehicle_stmt->close();

// Function to get required maintenance based on mileage schedule
function getRequiredMaintenance($current_mileage) {
    $schedule = [
        5000 => ['oil_change'],
        10000 => ['oil_change', 'tire_rotation'],
        15000 => ['oil_change'],
        20000 => ['oil_change', 'tire_rotation', 'wheel_alignment', 'brake_inspection'],
        25000 => ['oil_change'],
        30000 => ['oil_change', 'tire_rotation'],
        35000 => ['oil_change'],
        40000 => ['oil_change', 'tire_rotation', 'wheel_alignment', 'brake_inspection', 'ac_maintenance'],
        45000 => ['oil_change', 'transmission_fluid'],
        50000 => ['oil_change', 'tire_rotation'],
        55000 => ['oil_change'],
        60000 => ['oil_change', 'tire_rotation', 'wheel_alignment', 'brake_inspection'],
        65000 => ['oil_change'],
        70000 => ['oil_change', 'tire_rotation'],
        75000 => ['oil_change'],
        80000 => ['oil_change', 'tire_rotation', 'wheel_alignment', 'brake_inspection', 'ac_maintenance'],
        85000 => ['oil_change', 'transmission_fluid'],
        90000 => ['oil_change', 'tire_rotation'],
        95000 => ['oil_change'],
        100000 => ['oil_change', 'tire_rotation', 'wheel_alignment', 'brake_inspection']
    ];
    
    // Find the next milestone
    $next_milestone = null;
    $required_maintenance = [];
    
    foreach ($schedule as $mileage => $maintenance_types) {
        if ($current_mileage < $mileage) {
            $next_milestone = $mileage;
            $required_maintenance = $maintenance_types;
            break;
        }
    }
    
    return [
        'next_milestone' => $next_milestone,
        'required_maintenance' => $required_maintenance,
        'km_remaining' => $next_milestone ? ($next_milestone - $current_mileage) : 0
    ];
}

// Get required maintenance for the assigned vehicle
$maintenance_info = null;
if ($assigned_vehicle) {
    $maintenance_info = getRequiredMaintenance($assigned_vehicle['current_mileage']);
}

// Maintenance type options
$maintenance_types = [
    'oil_change' => 'Oil Change',
    'tire_rotation' => 'Tire Rotation',
    'wheel_alignment' => 'Wheel Alignment',
    'brake_inspection' => 'Brake Inspection',
    'ac_maintenance' => 'AC Maintenance',
    'battery_check' => 'Battery Check',
    'transmission_fluid' => 'Transmission Fluid',
    'timing_belt' => 'Timing Belt'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Request | Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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

        /* Card styling */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
        }

        .card-header {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }

        .form-control, .form-select {
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #002855;
            border-color: #002855;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Maintenance Request
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <!-- Display messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($assigned_vehicle): ?>
                        <form method="POST">
                            <!-- Hidden field for vehicle ID -->
                            <input type="hidden" name="vehicle_id" value="<?= $assigned_vehicle['id'] ?>">
                            
                            <!-- Display assigned vehicle info -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6 class="mb-2">
                                            <i class="fas fa-car me-2"></i>Your Assigned Vehicle
                                        </h6>
                                        <p class="mb-0">
                                            <strong><?= htmlspecialchars($assigned_vehicle['article']) ?></strong> - 
                                            <span class="badge bg-primary"><?= htmlspecialchars($assigned_vehicle['plate_number']) ?></span>
                                            <span class="ms-3">
                                                <i class="fas fa-tachometer-alt me-1"></i>
                                                Current Mileage: <strong><?= number_format($assigned_vehicle['current_mileage']) ?> KM</strong>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <?php if ($maintenance_info && $maintenance_info['next_milestone']): ?>
                            <!-- Display required maintenance based on schedule -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <h6 class="mb-2">
                                            <i class="fas fa-calendar-check me-2"></i>Next Maintenance Due
                                        </h6>
                                        <p class="mb-2">
                                            <strong>Next Milestone:</strong> <?= number_format($maintenance_info['next_milestone']) ?> KM 
                                            <span class="badge bg-warning text-dark ms-2">
                                                <?= $maintenance_info['km_remaining'] ?> KM remaining
                                            </span>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Required Services:</strong>
                                        </p>
                                        <div class="mt-2">
                                            <?php 
                                            $display_names = [];
                                            foreach ($maintenance_info['required_maintenance'] as $maintenance_type) {
                                                $display_names[] = $maintenance_types[$maintenance_type];
                                            }
                                            ?>
                                            <span class="badge bg-primary me-2 mb-1">
                                                <i class="fas fa-tools me-1"></i><?= implode(' + ', $display_names) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden fields for required maintenance -->
                            <?php foreach ($maintenance_info['required_maintenance'] as $maintenance_type): ?>
                                <input type="hidden" name="maintenance_types[]" value="<?= $maintenance_type ?>">
                            <?php endforeach; ?>
                            <?php else: ?>
                            <!-- Fallback for vehicles past 100k or no schedule match -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-secondary">
                                        <h6 class="mb-2">
                                            <i class="fas fa-info-circle me-2"></i>Maintenance Schedule
                                        </h6>
                                        <p class="mb-0">Your vehicle has exceeded the standard maintenance schedule. Please select the maintenance type manually or contact your administrator.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-wrench me-2"></i>Maintenance Type
                                    </label>
                                    <select name="maintenance_type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <?php foreach ($maintenance_types as $val => $label): ?>
                                            <option value="<?= $val ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <h6 class="mb-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>No Vehicle Assigned
                            </h6>
                            <p class="mb-0">You don't have any vehicle assigned to you. Please contact your administrator to get a vehicle assigned.</p>
                        </div>
                        <div class="d-flex justify-content-start">
                            <a href="driver-dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
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

        // Collapse all sidebar dropdowns when sidebar is collapsed
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
        // Handle logout from sidebar
        const logoutBtn = document.getElementById("logoutBtn");
        if (logoutBtn) {
            logoutBtn.addEventListener("click", function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }

        // Handle logout from navbar dropdown
        const logoutBtnDropdown = document.getElementById("logoutBtnDropdown");
        if (logoutBtnDropdown) {
            logoutBtnDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }

        function showLogoutConfirmation() {
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
        }
    });
</script>

</body>
</html>
