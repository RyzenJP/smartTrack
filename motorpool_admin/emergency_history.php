<?php
session_start();

// Only allow motor pool admin or super admin roles
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['motor_pool_admin','admin','super_admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// Filters
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$urgency = isset($_GET['urgency']) ? $_GET['urgency'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'completed';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = [];
$params = [];
$types = '';

if ($from !== '') { $where[] = "DATE(em.requested_at) >= ?"; $params[] = $from; $types .= 's'; }
if ($to !== '') { $where[] = "DATE(em.requested_at) <= ?"; $params[] = $to; $types .= 's'; }
if ($urgency !== '') { $where[] = "em.urgency_level = ?"; $params[] = $urgency; $types .= 's'; }
if ($status !== '') { $where[] = "em.status = ?"; $params[] = $status; $types .= 's'; }
if ($q !== '') {
    $where[] = "(fv.article LIKE CONCAT('%', ?, '%') OR fv.plate_number LIKE CONCAT('%', ?, '%') OR d.full_name LIKE CONCAT('%', ?, '%') OR m.full_name LIKE CONCAT('%', ?, '%') OR em.issue_title LIKE CONCAT('%', ?, '%'))";
    array_push($params, $q, $q, $q, $q, $q);
    $types .= 'sssss';
}

$sql = "SELECT em.*, fv.article AS vehicle_name, fv.plate_number, d.full_name AS driver_name, m.full_name AS mechanic_name
        FROM emergency_maintenance em
        JOIN fleet_vehicles fv ON em.vehicle_id = fv.id
        JOIN user_table d ON em.driver_id = d.user_id
        LEFT JOIN user_table m ON em.mechanic_id = m.user_id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY em.requested_at DESC LIMIT 500";

$rows = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Use prepared statement for consistency even when no params
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Maintenance History | Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary:#003566; --accent:#00b4d8; --bg:#f8fafc; --border:#e2e8f0; }
        body { background: var(--bg); font-family: 'Segoe UI', sans-serif; }
        /* Navbar */
        .navbar { position: fixed; top:0; left:0; width:100%; background:#fff; border-bottom:1px solid var(--border); z-index:1100; }
        .burger-btn{ font-size:1.5rem; background:none; border:none; color:var(--primary); margin-right:1rem; }
        /* Sidebar */
        .sidebar{ position:fixed; top:0; left:0; width:250px; height:100vh; background-color:var(--primary); color:#fff; transition:all .3s ease; z-index:1000; padding-top:60px; overflow-y:auto; }
        .sidebar a{ display:block; padding:14px 20px; color:#d9d9d9; text-decoration:none; white-space:nowrap; }
        .sidebar a:hover, .sidebar a.active{ background:#001d3d; color:var(--accent); }
        .sidebar .dropdown-toggle{ color:#d9d9d9; }
        .sidebar .dropdown-toggle::after{ display:none; }
        .dropdown-chevron{ color:#fff; transition:transform .3s ease; }
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron{ transform:rotate(90deg); }
        .sidebar.collapsed{ width:70px; }
        .sidebar.collapsed .link-text{ display:none; }
        .sidebar.collapsed .collapse{ display:none !important; }
        .sidebar.collapsed .dropdown-chevron{ display:none !important; }
        .sidebar.collapsed a{ text-align:center; padding:14px 8px; }
        .sidebar.collapsed a i{ margin-right:0 !important; }

        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; transition:margin-left .3s ease; }
        .main-content.collapsed{ margin-left:70px; }

        .card { border:1px solid var(--border); border-radius: 12px; }
        .badge-urgency { border-radius: 999px; font-weight: 600; }
        .table thead th { background:#f1f5f9; }
        
        /* Mobile Responsive Table */
        @media (max-width: 768px) { 
            .main-content{ margin-left:0; }
            
            .table-responsive {
                border: none !important;
                box-shadow: none !important;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .table thead th {
                padding: 8px 4px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            .table tbody td {
                padding: 8px 4px;
                vertical-align: middle;
            }
            
            /* Hide less important columns on mobile */
            .table th:nth-child(1), /* ID */
            .table td:nth-child(1) {
                display: none;
            }
            
            .table th:nth-child(5), /* Mechanic */
            .table td:nth-child(5) {
                display: none;
            }
            
            .table th:nth-child(9), /* Est. Cost */
            .table td:nth-child(9) {
                display: none;
            }
            
            /* Make Vehicle column wider */
            .table th:nth-child(3),
            .table td:nth-child(3) {
                min-width: 120px;
            }
            
            /* Compact urgency badges */
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
            .table {
                font-size: 0.75rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 6px 2px;
            }
            
            /* Hide Driver column on very small screens */
            .table th:nth-child(4),
            .table td:nth-child(4) {
                display: none;
            }
            
            /* Make remaining columns more compact */
            .table th:nth-child(3),
            .table td:nth-child(3) {
                min-width: 100px;
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            /* Make urgency badges smaller */
            .badge {
                font-size: 0.6rem;
                padding: 2px 4px;
            }
        }

        /* Additional mobile improvements */
        @media (max-width: 768px) {
            /* Make table scroll horizontally if needed */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Ensure table doesn't break layout */
            .table-responsive table {
                min-width: 600px;
            }
        }
        
        .modal { z-index:1199 !important; } .modal-backdrop{ z-index:1198 !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/admin_navbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-4">
                <h3 class="mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>Emergency Maintenance History</h3>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-sm-3">
                            <label class="form-label">From</label>
                            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">To</label>
                            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Urgency</label>
                            <select name="urgency" class="form-select">
                                <option value="">All</option>
                                <?php foreach(['CRITICAL','HIGH','MEDIUM','LOW'] as $u): ?>
                                    <option value="<?= $u ?>" <?= $urgency===$u?'selected':'' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach(['pending','assigned','in_progress','completed','cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="q" class="form-control" placeholder="Vehicle/Driver/Issue" value="<?= htmlspecialchars($q) ?>">
                        </div>
                        <div class="col-sm-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
                            <a href="emergency_history.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Mechanic</th>
                                <th>Issue</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th class="text-end">Est. Cost</th>
                                <th class="text-end">Actual Cost</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="11" class="text-center text-muted py-4">No records found</td></tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= $r['id'] ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($r['requested_at'])) ?></td>
                                    <td><?= htmlspecialchars($r['vehicle_name']) ?> <small class="text-muted">(<?= htmlspecialchars($r['plate_number']) ?>)</small></td>
                                    <td><?= htmlspecialchars($r['driver_name']) ?></td>
                                    <td><?= htmlspecialchars($r['mechanic_name'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($r['issue_title']) ?></td>
                                    <td><span class="badge bg-<?= $r['urgency_level']=='CRITICAL'?'danger':($r['urgency_level']=='HIGH'?'warning':($r['urgency_level']=='MEDIUM'?'info':'success')) ?> badge-urgency"><?= $r['urgency_level'] ?></span></td>
                                    <td><span class="badge bg-<?= $r['status']=='completed'?'success':($r['status']=='in_progress'?'primary':($r['status']=='assigned'?'info':($r['status']=='cancelled'?'danger':'warning'))) ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                                    <td class="text-end"><?= $r['estimated_cost'] !== null ? '₱' . number_format((float)$r['estimated_cost'], 2) : '—' ?></td>
                                    <td class="text-end"><?= $r['actual_cost'] !== null ? '₱' . number_format((float)$r['actual_cost'], 2) : '—' ?></td>
                                    <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#details<?= $r['id'] ?>"><i class="fas fa-eye me-1"></i>Details</button></td>
                                </tr>

                                <!-- Details Modal -->
                                <div class="modal fade" id="details<?= $r['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Emergency Request Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Request</h6>
                                                        <p><strong>Issue:</strong> <?= htmlspecialchars($r['issue_title']) ?></p>
                                                        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($r['issue_description'])) ?></p>
                                                        <p><strong>Urgency:</strong> <?= htmlspecialchars($r['urgency_level']) ?></p>
                                                        <p><strong>Requested:</strong> <?= date('M j, Y H:i', strtotime($r['requested_at'])) ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Vehicle & Driver</h6>
                                                        <p><strong>Vehicle:</strong> <?= htmlspecialchars($r['vehicle_name']) ?></p>
                                                        <p><strong>Plate:</strong> <?= htmlspecialchars($r['plate_number']) ?></p>
                                                        <p><strong>Driver:</strong> <?= htmlspecialchars($r['driver_name']) ?></p>
                                                        <p><strong>Mechanic:</strong> <?= htmlspecialchars($r['mechanic_name'] ?: '—') ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle & dropdown behavior
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const linkTexts = document.querySelectorAll('.link-text');

        if (burgerBtn) {
            burgerBtn.addEventListener('click', () => {
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    sidebar.classList.toggle('show');
                } else {
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    linkTexts.forEach(t => t.style.display = isCollapsed ? 'none' : 'inline');
                    if (isCollapsed) {
                        const openMenus = sidebar.querySelectorAll('.collapse.show');
                        openMenus.forEach(menu => {
                            const ci = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, {toggle:false});
                            ci.hide();
                        });
                    }
                }
            });
        }

        // lift modals below fixed navbar
        document.querySelectorAll('.modal').forEach(m => m.addEventListener('shown.bs.modal', () => {
            const nav = document.querySelector('.navbar');
            const dialog = m.querySelector('.modal-dialog');
            if (nav && dialog) dialog.style.marginTop = (nav.offsetHeight + 10) + 'px';
        }));
    </script>
</body>
</html>


