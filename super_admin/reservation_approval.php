<?php
session_start();
require_once '../db_connection.php';

// Access control: Super Admin only
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $reservation_id = (int) $_POST['reservation_id'];
    
    if ($action === 'approve') {
        // Get available dispatcher - use prepared statement for consistency
        $dispatcher_stmt = $conn->prepare("SELECT user_id FROM user_table WHERE role = 'dispatcher' AND role != 'super admin' LIMIT 1");
        $dispatcher_stmt->execute();
        $dispatcher_result = $dispatcher_stmt->get_result();
        $dispatcher_id = null;
        
        if ($dispatcher_result && $dispatcher_result->num_rows > 0) {
            $dispatcher = $dispatcher_result->fetch_assoc();
            $dispatcher_id = $dispatcher['user_id'];
        }
        $dispatcher_stmt->close();
        
        $stmt = $conn->prepare("UPDATE vehicle_reservations SET approval_status = 'approved', approved_by = ?, approved_at = NOW(), status = 'assigned', assigned_dispatcher_id = ? WHERE id = ?");
        $stmt->bind_param("iii", $_SESSION['user_id'], $dispatcher_id, $reservation_id);
        
        if ($stmt->execute()) {
            $message = "Reservation approved and assigned to dispatcher!";
        } else {
            $error = "Failed to approve reservation.";
        }
        $stmt->close();
        
    } elseif ($action === 'reject') {
        $rejection_reason = $_POST['rejection_reason'] ?? '';
        
        $stmt = $conn->prepare("UPDATE vehicle_reservations SET approval_status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $reservation_id);
        
        if ($stmt->execute()) {
            $message = "Reservation rejected.";
        } else {
            $error = "Failed to reject reservation.";
        }
        $stmt->close();
    }
}

// Fetch pending reservations with attachments - use prepared statement for consistency
$sql = "SELECT vr.*, ru.full_name as requester_full_name, ru.department as requester_department
        FROM vehicle_reservations vr
        LEFT JOIN reservation_users ru ON vr.created_by = ru.id
        WHERE vr.approval_status = 'pending'
        ORDER BY vr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Approval | Super Admin</title>
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

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px;
        }

        .attachment-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .attachment-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .attachment-info p {
            margin-bottom: 0.5rem;
        }

        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .modal-content {
            border-radius: 12px;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            border-radius: 12px 12px 0 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/navbar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-primary fw-bold">
                        <i class="fas fa-clipboard-check me-2"></i>Reservation Approval
                    </h2>
                    <p class="text-muted">Review and approve vehicle reservation requests with attachments</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($reservation = $result->fetch_assoc()): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-car me-2"></i>Reservation #<?= $reservation['id'] ?>
                                        </h5>
                                        <span class="badge bg-warning status-badge">Pending Approval</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-user me-1"></i>Requester Details
                                        </h6>
                                        <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($reservation['requester_name']) ?></p>
                                        <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($reservation['department']) ?></p>
                                        <p class="mb-0"><strong>Contact:</strong> <?= htmlspecialchars($reservation['contact']) ?></p>
                                    </div>

                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-route me-1"></i>Trip Details
                                        </h6>
                                        <p class="mb-1"><strong>Purpose:</strong> <?= htmlspecialchars($reservation['purpose']) ?></p>
                                        <p class="mb-1"><strong>From:</strong> <?= htmlspecialchars($reservation['origin']) ?></p>
                                        <p class="mb-1"><strong>To:</strong> <?= htmlspecialchars($reservation['destination']) ?></p>
                                        <p class="mb-1"><strong>Start:</strong> <?= date('M d, Y H:i', strtotime($reservation['start_datetime'])) ?></p>
                                        <?php if ($reservation['end_datetime']): ?>
                                            <p class="mb-1"><strong>End:</strong> <?= date('M d, Y H:i', strtotime($reservation['end_datetime'])) ?></p>
                                        <?php endif; ?>
                                        <p class="mb-0"><strong>Passengers:</strong> <?= $reservation['passengers'] ?></p>
                                    </div>

                                    <?php if ($reservation['notes']): ?>
                                        <div class="mb-3">
                                            <h6 class="text-primary mb-2">
                                                <i class="fas fa-sticky-note me-1"></i>Notes
                                            </h6>
                                            <p class="mb-0"><?= htmlspecialchars($reservation['notes']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($reservation['attachment_path']): ?>
                                        <div class="mb-3">
                                            <h6 class="text-primary mb-2">
                                                <i class="fas fa-paperclip me-1"></i>Attachment
                                            </h6>
                                            <div class="attachment-info">
                                                <p class="mb-2">
                                                    <strong>File:</strong> <?= htmlspecialchars($reservation['attachment_original_name']) ?>
                                                    <span class="text-muted">(<?= number_format($reservation['attachment_size'] / 1024, 1) ?> KB)</span>
                                                </p>
                                                
                                                <?php
                                                $file_extension = strtolower(pathinfo($reservation['attachment_original_name'], PATHINFO_EXTENSION));
                                                $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                                                ?>
                                                
                                                <?php if (in_array($file_extension, $image_extensions)): ?>
                                                    <img src="../<?= htmlspecialchars($reservation['attachment_path']) ?>" 
                                                         alt="Attachment Preview" 
                                                         class="attachment-preview img-fluid mb-2">
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <a href="../<?= htmlspecialchars($reservation['attachment_path']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-download me-1"></i>View/Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success btn-action flex-fill" 
                                                onclick="approveReservation(<?= $reservation['id'] ?>)">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        <button class="btn btn-danger btn-action flex-fill" 
                                                onclick="rejectReservation(<?= $reservation['id'] ?>)">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card text-center py-5">
                            <div class="card-body">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No pending reservations</h5>
                                <p class="text-muted">All reservation requests have been processed.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>Reject Reservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="reservation_id" id="rejectReservationId">
                        
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveReservation(reservationId) {
            if (confirm('Are you sure you want to approve this reservation?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectReservation(reservationId) {
            document.getElementById('rejectReservationId').value = reservationId;
            document.getElementById('rejection_reason').value = '';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        // Sidebar functionality
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
