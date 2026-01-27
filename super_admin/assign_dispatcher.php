<?php
session_start();
require_once '../db_connection.php';

// Check if user is super admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header('Location: ../login.php');
    exit();
}

$reservation_id = $_GET['id'] ?? null;
$message = '';
$error = '';

if (!$reservation_id) {
    header('Location: reservation_management.php');
    exit();
}

// Get reservation details
$reservation_sql = "SELECT * FROM vehicle_reservations WHERE id = ?";
$stmt = $conn->prepare($reservation_sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();

if (!$reservation) {
    header('Location: reservation_management.php');
    exit();
}

// Get available dispatchers - use prepared statement for consistency
$dispatchers_stmt = $conn->prepare("SELECT user_id, full_name, email, phone FROM user_table WHERE role = 'Dispatcher'");
$dispatchers_stmt->execute();
$dispatchers_result = $dispatchers_stmt->get_result();

// Get available vehicles - use prepared statement for consistency
$vehicles_stmt = $conn->prepare("SELECT id, article, unit, plate_number, status FROM fleet_vehicles WHERE status = 'active'");
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assigned_dispatcher_id = $_POST['dispatcher_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $notes = $_POST['notes'];
    
    try {
        $update_sql = "UPDATE vehicle_reservations SET 
                       assigned_dispatcher_id = ?, 
                       vehicle_id = ?, 
                       status = 'assigned',
                       notes = CONCAT(IFNULL(notes, ''), '\nDispatcher Assignment: ', ?)
                       WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iisi", $assigned_dispatcher_id, $vehicle_id, $notes, $reservation_id);
        
        if ($stmt->execute()) {
            $message = "Reservation assigned to dispatcher successfully!";
            // Redirect with success message
            header("Location: reservation_management.php?success=" . urlencode($message));
            exit();
        } else {
            $error = "Error assigning reservation.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Dispatcher - Super Admin</title>
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

        .sidebar a i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        .link-text {
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .link-text {
            opacity: 0;
            width: 0;
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

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary);
            margin-right: 1rem;
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
            transition: all 0.3s ease;
        }

        .dropdown-menu .dropdown-item:hover i {
            color: var(--accent);
            transform: scale(1.1);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            border: none;
            border-radius: 12px 12px 0 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 51, 102, 0.3);
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
        }

        /* Toast styling */
        .toast-container {
            z-index: 1300;
        }

        /* Make Reservation Details header match main header */
        .card-header.reservation-details-header {
            background: linear-gradient(135deg, var(--primary), #001d3d) !important;
            color: white !important;
            border: none !important;
        }

        .card-header.reservation-details-header h5 {
            color: white !important;
        }

        .card-header.reservation-details-header i {
            color: white !important;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>
<!-- Main Content -->
<div class="main-content" id="mainContent">
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

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0 text-white"><i class="fas fa-user-tie me-2"></i>Assign Dispatcher to Reservation #<?php echo $reservation['id']; ?></h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Reservation Details -->
                        <div class="card mb-4">
                            <div class="card-header reservation-details-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Reservation Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Requester:</strong> <?php echo htmlspecialchars($reservation['requester_name']); ?></p>
                                        <p><strong>Department:</strong> <?php echo htmlspecialchars($reservation['department']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($reservation['contact']); ?></p>
                                        <p><strong>Passengers:</strong> <?php echo $reservation['passengers']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($reservation['purpose']); ?></p>
                                        <p><strong>Route:</strong> <?php echo htmlspecialchars($reservation['origin']); ?> → <?php echo htmlspecialchars($reservation['destination']); ?></p>
                                        <p><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($reservation['start_datetime'])); ?></p>
                                        <p><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($reservation['end_datetime'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assignment Form -->
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="dispatcher_id" class="form-label">Select Dispatcher *</label>
                                    <select class="form-select" id="dispatcher_id" name="dispatcher_id" required>
                                        <option value="">Choose a dispatcher...</option>
                                        <?php while ($dispatcher = $dispatchers_result->fetch_assoc()): ?>
                                            <option value="<?php echo $dispatcher['user_id']; ?>">
                                                <?php echo htmlspecialchars($dispatcher['full_name']); ?> 
                                                (<?php echo htmlspecialchars($dispatcher['email']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_id" class="form-label">Select Vehicle *</label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Choose a vehicle...</option>
                                        <?php while ($vehicle = $vehicles_result->fetch_assoc()): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['article']); ?> - 
                                                <?php echo htmlspecialchars($vehicle['unit']); ?> 
                                                (<?php echo htmlspecialchars($vehicle['plate_number']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Assignment Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special instructions for the dispatcher..."></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="reservation_management.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left"></i> Back to Reservations
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Assign Dispatcher
                                </button>
                            </div>
                    </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const linkTexts = document.querySelectorAll('.link-text');
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

        if (burgerBtn && sidebar) {
            burgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                
                // Toggle link texts
                linkTexts.forEach(linkText => {
                    if (sidebar.classList.contains('collapsed')) {
                        linkText.style.opacity = '0';
                        linkText.style.width = '0';
                    } else {
                        linkText.style.opacity = '1';
                        linkText.style.width = 'auto';
                    }
                });
                
                // Close dropdowns when sidebar collapses
                if (sidebar.classList.contains('collapsed')) {
                    dropdownToggles.forEach(toggle => {
                        const menu = toggle.nextElementSibling;
                        if (menu && menu.classList.contains('show')) {
                            const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
                            collapseInstance.hide();
                        }
                    });
                }
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
        
        // Redirect after showing toast
        setTimeout(function() {
            window.location.href = 'reservation_management.php';
        }, 2000);
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
