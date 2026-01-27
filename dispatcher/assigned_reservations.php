<?php
session_start();
require_once '../db_connection.php';

// Check if user is dispatcher (at the very beginning)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $reservation_id = $_POST['reservation_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'start') {
                // Update status to active (shorter value)
                $sql = "UPDATE vehicle_reservations SET status = 'active' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                
                if ($stmt->execute()) {
                    // Redirect to active routes page with the reservation ID
                    header("Location: active-routes.php?reservation_id=" . $reservation_id . "&started=1");
                    exit();
                } else {
                    $error = "Error starting trip.";
                }
            } elseif ($action === 'complete') {
                // First, get reservation details for fuel calculation
                $reservationQuery = "SELECT vr.*, fv.id as vehicle_id, fv.article, fv.plate_number, fv.fuel_consumption_l_per_km 
                                   FROM vehicle_reservations vr 
                                   LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id 
                                   WHERE vr.id = ?";
                $reservationStmt = $conn->prepare($reservationQuery);
                $reservationStmt->bind_param("i", $reservation_id);
                $reservationStmt->execute();
                $reservation = $reservationStmt->get_result()->fetch_assoc();
                
                if ($reservation && $reservation['vehicle_id']) {
                    // Calculate trip distance from GPS data
                    $startTime = $reservation['start_datetime'];
                    $endTime = date('Y-m-d H:i:s'); // Current time
                    
                    // Call API to get trip distance and update fuel
                    $fuelUpdateData = [
                        'vehicle_id' => $reservation['vehicle_id'],
                        'trip_distance_km' => 0, // Will be calculated by API
                        'trip_notes' => "Trip completion - Reservation ID: {$reservation_id}"
                    ];
                    
                    // Get trip distance from GPS
                    $distanceUrl = "../api/trip_completion_fuel.php?action=get_trip_distance&vehicle_id=" . $reservation['vehicle_id'] . 
                                  "&start_time=" . urlencode($startTime) . "&end_time=" . urlencode($endTime);
                    
                    $distanceResponse = file_get_contents($distanceUrl);
                    $distanceData = json_decode($distanceResponse, true);
                    
                    if ($distanceData && $distanceData['success']) {
                        $fuelUpdateData['trip_distance_km'] = $distanceData['distance_km'];
                    }
                    
                    // Update fuel consumption
                    $fuelUrl = "../api/trip_completion_fuel.php?action=update_fuel_on_trip_completion";
                    $fuelContext = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => 'Content-Type: application/json',
                            'content' => json_encode($fuelUpdateData)
                        ]
                    ]);
                    
                    $fuelResponse = file_get_contents($fuelUrl, false, $fuelContext);
                    $fuelData = json_decode($fuelResponse, true);
                }
                
                // Update reservation status
                $sql = "UPDATE vehicle_reservations SET status = 'completed', completed_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                
                if ($stmt->execute()) {
                    if (isset($fuelData) && $fuelData['success']) {
                        $message = "Trip completed successfully! Fuel consumption updated: " . 
                                  number_format($fuelData['fuel_consumed'], 2) . "L consumed for " . 
                                  number_format($fuelData['trip_distance'], 2) . "km trip.";
                    } else {
                        $message = "Trip completed successfully!";
                    }
                } else {
                    $error = "Error completing trip.";
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get assigned reservations for this dispatcher
$sql = "SELECT vr.*, 
        fv.article, fv.unit, fv.plate_number, fv.status as vehicle_status,
        ru.full_name as requester_full_name, ru.department as requester_department
        FROM vehicle_reservations vr 
        LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id 
        LEFT JOIN reservation_users ru ON vr.created_by = ru.id
        WHERE vr.assigned_dispatcher_id = ? 
        ORDER BY vr.start_datetime ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Dispatcher</title>
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

        /* Prevent double carets on sidebar dropdown and style custom chevron */
        .dropdown-toggle::after { display: none; }
        .dropdown-chevron { color: #ffffff; transition: transform 0.3s ease, color 0.2s ease; }
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron { transform: rotate(90deg); }

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

        /* Modern Cards */
        .reservation-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .reservation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .reservation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .card-header h5 {
            color: white;
            font-weight: 600;
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-modern {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .info-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .info-section h6 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .info-section h6 i {
            margin-right: 0.5rem;
            color: var(--accent);
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bg-assigned { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
        .bg-in-progress { background: linear-gradient(135deg, #ffc107, #e0a800); color: white; }
        .bg-completed { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
        .bg-cancelled { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }

        .vehicle-status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .bg-vehicle-active { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
        .bg-vehicle-inactive { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            padding: 3rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 53, 102, 0.15);
            min-height: 120px;
        }

        .page-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stats-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #adb5bd;
            margin-bottom: 0;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>


<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-calendar-check me-3"></i>My Assigned Reservations</h2>
                    <p class="mb-0">Manage and track your assigned vehicle reservations</p>
                </div>
                <div class="stats-badge">
                    <i class="fas fa-list me-2"></i><?php echo $result->num_rows; ?> Reservations
                </div>
            </div>
        </div>
        <!-- Toast Notifications -->
        <?php if ($message): ?>
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1200;">
                <div id="liveToast" class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body" id="toastBody">
                        <?php echo $message; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1200;">
                <div id="liveToast" class="toast bg-danger" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-danger text-white">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong class="me-auto">Error</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body" id="toastBody">
                        <?php echo $error; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Reservations Content -->
        <?php if ($result->num_rows == 0): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No Assigned Reservations</h4>
                <p>You don't have any assigned reservations at the moment.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="col-12">
                        <div class="card reservation-card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Reservation #<?php echo $row['id']; ?></h5>
                                    <?php
                                    $status_class = [
                                        'assigned' => 'bg-assigned',
                                        'active' => 'bg-in-progress', 
                                        'in_progress' => 'bg-in-progress', 
                                        'completed' => 'bg-completed',
                                        'cancelled' => 'bg-cancelled'
                                    ];
                                    $class = $status_class[$row['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="status-badge <?php echo $class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body p-4">
                                <div class="row">
                                    <!-- Requester Information -->
                                    <div class="col-md-3">
                                        <div class="info-section h-100">
                                            <h6><i class="fas fa-user"></i>Requester</h6>
                                            <p class="mb-2"><strong><?php echo htmlspecialchars($row['requester_name']); ?></strong></p>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($row['department']); ?></p>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($row['contact']); ?></p>
                                            <p class="mb-0 text-muted small"><?php echo $row['passengers']; ?> passengers</p>
                                        </div>
                                    </div>


                                    <!-- Schedule & Actions -->
                                    <div class="col-md-6">
                                        <div class="info-section h-100">
                                            <h6><i class="fas fa-clock"></i>Schedule</h6>
                                            <p class="mb-1 text-muted small"><strong>Start:</strong></p>
                                            <p class="mb-2"><?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?></p>
                                            <p class="mb-1 text-muted small"><strong>End:</strong></p>
                                            <p class="mb-3"><?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?></p>
                                            
                                            <!-- Actions -->
                                            <div class="action-buttons">
                                                <?php if ($row['status'] === 'assigned'): ?>
                                                    <!-- Working Start button -->
                                                    <a href="active-routes.php?reservation_id=<?php echo $row['id']; ?>&started=1" class="btn btn-modern btn-success btn-sm">
                                                        <i class="fas fa-play me-1"></i>Start
                                                    </a>
                                                    
                                                <?php elseif ($row['status'] === 'active' || $row['status'] === 'in_progress'): ?>
                                                    <button type="button" class="btn btn-modern btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-check me-1"></i>Complete
                                                    </button>
                                                    <!-- Fallback direct form -->
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Complete this trip?')">
                                                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="action" value="complete">
                                                        <button type="submit" class="btn btn-modern btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i>Complete (Direct)
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-modern btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Trip Details Row -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="info-section">
                                            <h6><i class="fas fa-route"></i>Trip Details</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-2"><strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?></p>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="mb-2 text-muted small"><strong>From:</strong> <?php echo htmlspecialchars($row['origin']); ?></p>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="mb-0 text-muted small"><strong>To:</strong> <?php echo htmlspecialchars($row['destination']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes (if any) -->
                                <?php if ($row['notes']): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="info-section">
                                            <h6><i class="fas fa-sticky-note"></i>Notes</h6>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($row['notes']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Attachment (if any) -->
                                <?php if ($row['attachment_path']): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="info-section">
                                            <h6><i class="fas fa-paperclip"></i>Attachment</h6>
                                            <div class="attachment-info bg-light p-3 rounded">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <p class="mb-1">
                                                            <strong>File:</strong> <?php echo htmlspecialchars($row['attachment_original_name']); ?>
                                                            <span class="text-muted">(<?php echo number_format($row['attachment_size'] / 1024, 1); ?> KB)</span>
                                                        </p>
                                                    </div>
                                                    <a href="../<?php echo htmlspecialchars($row['attachment_path']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-download me-1"></i>View/Download
                                                    </a>
                                                </div>
                                                
                                                <?php
                                                $file_extension = strtolower(pathinfo($row['attachment_original_name'], PATHINFO_EXTENSION));
                                                $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                                                ?>
                                                
                                                <?php if (in_array($file_extension, $image_extensions)): ?>
                                                    <div class="mt-2">
                                                        <img src="../<?php echo htmlspecialchars($row['attachment_path']); ?>" 
                                                             alt="Attachment Preview" 
                                                             class="img-fluid rounded" 
                                                             style="max-height: 200px; max-width: 100%;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Start Trip Modal -->
                    <div class="modal fade" id="startModal<?php echo $row['id']; ?>" tabindex="-1" style="z-index: 1200;">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title"><i class="fas fa-play-circle me-2"></i>Start Trip</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you ready to start this trip?</p>
                                    <div class="alert alert-info">
                                        <strong>Reservation #<?php echo $row['id']; ?></strong><br>
                                        <strong>Requester:</strong> <?php echo htmlspecialchars($row['requester_name']); ?><br>
                                        <strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?><br>
                                        <strong>Route:</strong> <?php echo htmlspecialchars($row['origin']); ?> → <?php echo htmlspecialchars($row['destination']); ?><br>
                                        <strong>Vehicle:</strong> <?php echo htmlspecialchars($row['article']); ?> (<?php echo htmlspecialchars($row['plate_number']); ?>)
                                    </div>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> You will be redirected to the Active Routes page where you can manually input the vehicle's location and track the trip progress.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="start">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-play me-1"></i>Start Trip & Go to Routes
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Complete Trip Modal -->
                    <div class="modal fade" id="completeModal<?php echo $row['id']; ?>" tabindex="-1" style="z-index: 1200;">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Complete Trip</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to complete this trip?</p>
                                    <div class="alert alert-success">
                                        <strong>Reservation #<?php echo $row['id']; ?></strong><br>
                                        <strong>Requester:</strong> <?php echo htmlspecialchars($row['requester_name']); ?><br>
                                        <strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?><br>
                                        <strong>Route:</strong> <?php echo htmlspecialchars($row['origin']); ?> → <?php echo htmlspecialchars($row['destination']); ?><br>
                                        <strong>Vehicle:</strong> <?php echo htmlspecialchars($row['article']); ?> (<?php echo htmlspecialchars($row['plate_number']); ?>)
                                    </div>
                                    <p class="text-muted small">This will change the status from "In Progress" to "Completed".</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check me-1"></i>Complete Trip
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Modal -->
                    <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1" style="z-index: 1200;">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), #001d3d); color: white;">
                                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Reservation Details #<?php echo $row['id']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-section">
                                                <h6><i class="fas fa-user"></i>Requester Information</h6>
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($row['requester_name']); ?></p>
                                                <p><strong>Department:</strong> <?php echo htmlspecialchars($row['department']); ?></p>
                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($row['contact']); ?></p>
                                                <p><strong>Passengers:</strong> <?php echo $row['passengers']; ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-section">
                                                <h6><i class="fas fa-route"></i>Trip Details</h6>
                                                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?></p>
                                                <p><strong>Origin:</strong> <?php echo htmlspecialchars($row['origin']); ?></p>
                                                <p><strong>Destination:</strong> <?php echo htmlspecialchars($row['destination']); ?></p>
                                                <p><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?></p>
                                                <p><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($row['notes']): ?>
                                        <div class="info-section">
                                            <h6><i class="fas fa-sticky-note"></i>Notes</h6>
                                            <p><?php echo htmlspecialchars($row['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['attachment_path']): ?>
                                        <div class="info-section">
                                            <h6><i class="fas fa-paperclip"></i>Attachment</h6>
                                            <div class="attachment-info bg-light p-3 rounded">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <div>
                                                        <p class="mb-1">
                                                            <strong>File:</strong> <?php echo htmlspecialchars($row['attachment_original_name']); ?>
                                                            <span class="text-muted">(<?php echo number_format($row['attachment_size'] / 1024, 1); ?> KB)</span>
                                                        </p>
                                                    </div>
                                                    <a href="../<?php echo htmlspecialchars($row['attachment_path']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-download me-1"></i>View/Download
                                                    </a>
                                                </div>
                                                
                                                <?php
                                                $file_extension = strtolower(pathinfo($row['attachment_original_name'], PATHINFO_EXTENSION));
                                                $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                                                ?>
                                                
                                                <?php if (in_array($file_extension, $image_extensions)): ?>
                                                    <div class="mt-2">
                                                        <img src="../<?php echo htmlspecialchars($row['attachment_path']); ?>" 
                                                             alt="Attachment Preview" 
                                                             class="img-fluid rounded" 
                                                             style="max-height: 300px; max-width: 100%;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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

// Show toast notifications
document.addEventListener('DOMContentLoaded', function() {
    // Show success toast
    <?php if ($message): ?>
        const successToast = document.getElementById('liveToast');
        const successToastBody = document.getElementById('toastBody');
        successToastBody.innerHTML = '<i class="fas fa-check-circle me-2"></i><?= $message ?>';
        const successToastInstance = new bootstrap.Toast(successToast);
        successToastInstance.show();
    <?php endif; ?>
    
    // Show error toast
    <?php if ($error): ?>
        const errorToast = document.getElementById('liveToast');
        const errorToastBody = document.getElementById('toastBody');
        errorToast.classList.remove('bg-success');
        errorToast.classList.add('bg-danger');
        errorToastBody.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i><?= $error ?>';
        const errorToastInstance = new bootstrap.Toast(errorToast);
        errorToastInstance.show();
    <?php endif; ?>

    // Debug: Test if Bootstrap modals are working
    console.log('Bootstrap version:', bootstrap);
    console.log('Modal elements:', document.querySelectorAll('.modal'));
    
    // Test modal functionality
    const startButtons = document.querySelectorAll('[data-bs-target^="#startModal"]');
    console.log('Start buttons found:', startButtons.length);
    
    startButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            console.log('Start button clicked!');
            console.log('Target modal:', this.getAttribute('data-bs-target'));
        });
    });
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
