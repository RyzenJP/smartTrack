<?php
session_start();

// Check if user is logged in and is a driver
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

$driverId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicleId = $_POST['vehicle_id'] ?? '';
    $issueTitle = $_POST['issue_title'] ?? '';
    $issueDescription = $_POST['issue_description'] ?? '';
    $urgencyLevel = $_POST['urgency_level'] ?? 'MEDIUM';
    $location = $_POST['location'] ?? '';
    $driverPhone = $_POST['driver_phone'] ?? '';
    
    if ($vehicleId && $issueTitle && $issueDescription) {
        $stmt = $conn->prepare("
            INSERT INTO emergency_maintenance 
            (vehicle_id, driver_id, issue_title, issue_description, urgency_level, location, driver_phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->bind_param("iisssss", $vehicleId, $driverId, $issueTitle, $issueDescription, $urgencyLevel, $location, $driverPhone)) {
            if ($stmt->execute()) {
                $message = "Emergency maintenance request submitted successfully!";
                $messageType = "success";
            } else {
                $message = "Error submitting request: " . $stmt->error;
                $messageType = "danger";
            }
        } else {
            $message = "Error preparing request.";
            $messageType = "danger";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all required fields.";
        $messageType = "warning";
    }
}

// Get driver's assigned vehicle (1:1 ratio)
$vehicleQuery = $conn->prepare("
    SELECT fv.id, fv.article, fv.plate_number, fv.unit,
           fv.current_latitude, fv.current_longitude
    FROM fleet_vehicles fv
    JOIN vehicle_assignments va ON fv.id = va.vehicle_id
    WHERE va.driver_id = ?
    LIMIT 1
");
$vehicleQuery->bind_param("i", $driverId);
$vehicleQuery->execute();
$vehicle = $vehicleQuery->get_result()->fetch_assoc();
$vehicleQuery->close();

// Get driver's contact info from user_table
$driverQuery = $conn->prepare("SELECT phone FROM user_table WHERE user_id = ?");
$driverQuery->bind_param("i", $driverId);
$driverQuery->execute();
$driverInfo = $driverQuery->get_result()->fetch_assoc();
$driverQuery->close();

// Get current location from GPS device if available
$currentLocation = '';
if ($vehicle) {
    if ($vehicle['current_latitude'] && $vehicle['current_longitude']) {
        $currentLocation = "Lat: {$vehicle['current_latitude']}, Lng: {$vehicle['current_longitude']}";
    } else {
        // Try to get latest GPS location from gps_logs
        $gpsQuery = $conn->prepare("
            SELECT gl.latitude, gl.longitude, gl.timestamp
            FROM gps_devices gd
            JOIN gps_logs gl ON gd.device_id = gl.device_id
            WHERE gd.vehicle_id = ?
            ORDER BY gl.timestamp DESC
            LIMIT 1
        ");
        $gpsQuery->bind_param("i", $vehicle['id']);
        $gpsQuery->execute();
        $gpsData = $gpsQuery->get_result()->fetch_assoc();
        $gpsQuery->close();
        
        if ($gpsData) {
            $currentLocation = "Lat: {$gpsData['latitude']}, Lng: {$gpsData['longitude']} (GPS: " . date('M j, H:i', strtotime($gpsData['timestamp'])) . ")";
        }
    }
}

// Get driver's emergency maintenance history
$historyQuery = $conn->prepare("
    SELECT em.*, fv.article as vehicle_name, fv.plate_number,
           u.full_name as mechanic_name
    FROM emergency_maintenance em
    JOIN fleet_vehicles fv ON em.vehicle_id = fv.id
    LEFT JOIN user_table u ON em.mechanic_id = u.user_id
    WHERE em.driver_id = ?
    ORDER BY em.requested_at DESC
    LIMIT 10
");
$historyQuery->bind_param("i", $driverId);
$historyQuery->execute();
$history = $historyQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$historyQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Maintenance | Smart Track</title>
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

        .sidebar.collapsed .link-text {
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

        .urgency-critical { border-left: 5px solid #dc3545; }
        .urgency-high { border-left: 5px solid #fd7e14; }
        .urgency-medium { border-left: 5px solid #ffc107; }
        .urgency-low { border-left: 5px solid #198754; }

        .status-pending { background-color: #fff3cd; }
        .status-assigned { background-color: #cff4fc; }
        .status-in_progress { background-color: #e2e3e5; }
        .status-completed { background-color: #d1e7dd; }
        .status-cancelled { background-color: #f8d7da; }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-exclamation-triangle text-danger me-2"></i>Emergency Maintenance</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="driver-dashboard.php">Dashboard</a></li>
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

            <div class="row">
                <!-- Request Form -->
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Submit Emergency Request</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($vehicle): ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="vehicle_info" class="form-label">Your Assigned Vehicle</label>
                                        <div class="form-control-plaintext bg-light p-3 rounded">
                                            <strong><?= htmlspecialchars($vehicle['article']) ?></strong><br>
                                            <small class="text-muted">
                                                Plate: <?= htmlspecialchars($vehicle['plate_number']) ?> | 
                                                Unit: <?= htmlspecialchars($vehicle['unit']) ?>
                                            </small>
                                        </div>
                                        <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                                    </div>

                                <div class="mb-3">
                                    <label for="issue_title" class="form-label">Issue Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="issue_title" name="issue_title" 
                                           placeholder="e.g., Engine Overheating, Flat Tire, Battery Dead" required>
                                </div>

                                <div class="mb-3">
                                    <label for="issue_description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="issue_description" name="issue_description" rows="4" 
                                              placeholder="Describe the problem in detail, including symptoms, when it started, and current situation..." required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="urgency_level" class="form-label">Urgency Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="urgency_level" name="urgency_level" required>
                                        <option value="LOW">🟢 Low - Can wait, not affecting operations</option>
                                        <option value="MEDIUM" selected>🟡 Medium - Needs attention soon</option>
                                        <option value="HIGH">🟠 High - Urgent, affecting operations</option>
                                        <option value="CRITICAL">🔴 Critical - Immediate attention required</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Current Location</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?= htmlspecialchars($currentLocation) ?>"
                                           placeholder="e.g., Highway 101 Mile 45, Office Parking Lot, Downtown Main Street">
                                    <?php if ($currentLocation): ?>
                                        <div class="form-text text-success">
                                            <i class="fas fa-map-marker-alt me-1"></i>Auto-detected from GPS
                                        </div>
                                    <?php else: ?>
                                        <div class="form-text text-muted">
                                            <i class="fas fa-info-circle me-1"></i>GPS location not available, please enter manually
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="driver_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="driver_phone" name="driver_phone" 
                                           value="<?= htmlspecialchars($driverInfo['phone'] ?? '') ?>"
                                           placeholder="Your phone number for mechanic contact">
                                    <?php if ($driverInfo['phone']): ?>
                                        <div class="form-text text-success">
                                            <i class="fas fa-phone me-1"></i>Auto-filled from your profile
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Emergency Request
                                    </button>
                                </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>No Vehicle Assigned</h5>
                                    <p class="mb-0">You don't currently have a vehicle assigned to you. Please contact your fleet manager to get a vehicle assignment before submitting emergency maintenance requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Request History -->
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Emergency Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($history)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                    <p>No emergency maintenance requests yet.</p>
                                </div>
                            <?php else: ?>
                                <div style="max-height: 600px; overflow-y: auto;">
                                    <?php foreach ($history as $request): ?>
                                        <div class="card mb-3 urgency-<?= strtolower($request['urgency_level']) ?>">
                                            <div class="card-body status-<?= $request['status'] ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0"><?= htmlspecialchars($request['issue_title']) ?></h6>
                                                    <span class="badge bg-<?= 
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
                                                
                                                <p class="small mb-2"><?= htmlspecialchars(substr($request['issue_description'], 0, 100)) ?>...</p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
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
                                                    <div class="mt-2">
                                                        <small class="text-success">
                                                            <i class="fas fa-user-cog me-1"></i>Assigned to: <?= htmlspecialchars($request['mechanic_name']) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
