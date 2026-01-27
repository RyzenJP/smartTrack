<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// Get all alerts, prioritize unresolved and high priority - use prepared statements for consistency
$alerts_stmt = $conn->prepare("SELECT * FROM alerts ORDER BY resolved ASC, priority DESC, created_at DESC");
$alerts_stmt->execute();
$alertsResult = $alerts_stmt->get_result();

// Get alert statistics - use prepared statements for consistency
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM alerts");
$total_stmt->execute();
$totalAlerts = $total_stmt->get_result()->fetch_row()[0];
$total_stmt->close();

$unresolved_stmt = $conn->prepare("SELECT COUNT(*) FROM alerts WHERE resolved = 0");
$unresolved_stmt->execute();
$unresolvedAlerts = $unresolved_stmt->get_result()->fetch_row()[0];
$unresolved_stmt->close();

$highPriority_stmt = $conn->prepare("SELECT COUNT(*) FROM alerts WHERE priority = 'high' AND resolved = 0");
$highPriority_stmt->execute();
$highPriorityAlerts = $highPriority_stmt->get_result()->fetch_row()[0];
$highPriority_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts - Dispatcher</title>
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

        .burger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--primary);
            margin-right: 1rem;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 20px;
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

        .page-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 53, 102, 0.15);
        }

        .page-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .alert-card {
            border-left: 4px solid;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .alert-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .alert-card.priority-high {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }

        .alert-card.priority-medium {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffbf0, #ffffff);
        }

        .alert-card.priority-low {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #f0f9fb, #ffffff);
        }

        .alert-card.resolved {
            opacity: 0.6;
            border-left-color: #6c757d;
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stats-card {
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-0">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h2>
            <p>Monitor and manage all system alerts and notifications</p>
        </div>

        <!-- Alert Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card bg-primary text-white">
                    <h6>Total Alerts</h6>
                    <h3><?= $totalAlerts ?></h3>
                    <small>All alerts in system</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card bg-danger text-white">
                    <h6>Unresolved Alerts</h6>
                    <h3><?= $unresolvedAlerts ?></h3>
                    <small>Require attention</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card bg-warning text-dark">
                    <h6>High Priority</h6>
                    <h3><?= $highPriorityAlerts ?></h3>
                    <small>Critical alerts</small>
                </div>
            </div>
        </div>

        <!-- Alerts List -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>All Alerts</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($alertsResult && $alertsResult->num_rows > 0): ?>
                    <?php while ($alert = $alertsResult->fetch_assoc()): ?>
                        <div class="alert-card priority-<?= strtolower($alert['priority']) ?> <?= $alert['resolved'] ? 'resolved' : '' ?> p-3 m-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <h6 class="mb-0 me-2"><?= htmlspecialchars($alert['title']) ?></h6>
                                        <span class="priority-badge bg-<?= $alert['priority'] === 'high' ? 'danger' : ($alert['priority'] === 'medium' ? 'warning' : 'info') ?> text-<?= $alert['priority'] === 'medium' ? 'dark' : 'white' ?>">
                                            <?= strtoupper($alert['priority']) ?>
                                        </span>
                                        <?php if ($alert['resolved']): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="fas fa-check me-1"></i>Resolved
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-2 text-muted" style="white-space: pre-line;"><?= htmlspecialchars($alert['description']) ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('M d, Y g:i A', strtotime($alert['created_at'])) ?>
                                        <?php if ($alert['resolved'] && $alert['resolved_at']): ?>
                                            | <i class="fas fa-check-circle me-1"></i>Resolved: <?= date('M d, Y g:i A', strtotime($alert['resolved_at'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="ms-3">
                                    <?php if (!$alert['resolved']): ?>
                                        <button class="btn btn-sm btn-success" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                            <i class="fas fa-check me-1"></i>Resolve
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">No Alerts</h5>
                        <p class="text-muted">There are no alerts in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function resolveAlert(alertId) {
    Swal.fire({
        title: 'Resolve Alert?',
        text: "Mark this alert as resolved?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check me-1"></i>Yes, Resolve',
        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('resolve_alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    alert_id: alertId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Resolved!',
                        text: 'Alert has been marked as resolved',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    Swal.fire('Error', 'Failed to resolve alert', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to resolve alert', 'error');
            });
        }
    });
}

// Sidebar toggle functionality
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
        
        linkTexts.forEach(text => {
            text.style.display = isCollapsed ? 'none' : 'inline';
        });
        
        dropdownToggles.forEach(toggle => {
            const chevron = toggle.querySelector('.dropdown-chevron');
            if (chevron) {
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
}

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

