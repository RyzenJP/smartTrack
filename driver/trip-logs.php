<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

$driverId = $_SESSION['user_id'];

// Get completed routes for this driver
$routesQuery = $conn->prepare("
    SELECT r.*, fv.article, fv.unit, fv.plate_number
    FROM routes r
    LEFT JOIN vehicle_assignments va ON r.driver_id = va.driver_id AND va.status = 'active'
    LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id
    WHERE r.driver_id = ? AND r.status = 'completed'
    ORDER BY r.id DESC
");
$routesQuery->bind_param("i", $driverId);
$routesQuery->execute();
$routes = $routesQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$routesQuery->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Logs | Smart Track</title>
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
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), #001d3d);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .trip-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--accent);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .trip-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .trip-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .trip-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }
        
        .trip-status {
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .trip-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            background: var(--accent);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .detail-content h6 {
            margin: 0;
            font-weight: 600;
            color: var(--primary);
        }
        
        .detail-content p {
            margin: 2px 0 0 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .route-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .route-info h6 {
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .route-coords {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .coord-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .no-trips {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-trips i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .no-trips h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-trips p {
            color: #999;
            font-size: 0.9rem;
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
            z-index: 1050;
            padding-top: 60px;
            overflow-y: auto;
        }
        
        .sidebar a {
            display: block;
            padding: 14px 20px;
            color: #d9d9d9;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background-color: #001d3d;
            color: var(--accent);
        }
        
        .sidebar.collapsed {
            width: 70px;
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
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-top: 0;
                padding: 15px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .trip-details {
                grid-template-columns: 1fr;
            }
            
            .route-coords {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-route me-3"></i>Trip Logs</h1>
            <p>View your completed trips and route history</p>
        </div>

        <?php if (empty($routes)): ?>
            <div class="no-trips">
                <i class="fas fa-route"></i>
                <h3>No Completed Trips</h3>
                <p>You haven't completed any trips yet. Your completed trips will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($routes as $route): ?>
                <div class="trip-card">
                    <div class="trip-header">
                        <h3 class="trip-title">
                            <i class="fas fa-route me-2"></i>
                            Trip #<?= $route['id'] ?>
                        </h3>
                        <span class="trip-status">
                            <i class="fas fa-check-circle me-1"></i>Completed
                        </span>
                    </div>
                    
                    <div class="trip-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="detail-content">
                                <h6>Vehicle</h6>
                                <p><?= htmlspecialchars($route['article'] ?? 'N/A') ?> - <?= htmlspecialchars($route['plate_number'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="detail-content">
                                <h6>Completed</h6>
                                <p><?= date('M j, Y g:i A', strtotime($route['completed_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="detail-content">
                                <h6>Duration</h6>
                                <p>
                                    <?php
                                    $start = strtotime($route['created_at']);
                                    $end = strtotime($route['completed_at']);
                                    $duration = $end - $start;
                                    $hours = floor($duration / 3600);
                                    $minutes = floor(($duration % 3600) / 60);
                                    echo $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="detail-content">
                                <h6>Route</h6>
                                <p>From start to destination</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="route-info">
                        <h6><i class="fas fa-route me-2"></i>Route Details</h6>
                        <div class="route-coords">
                            <div class="coord-item">
                                <i class="fas fa-play text-success"></i>
                                <span><strong>Start:</strong> <?= number_format($route['start_lat'], 6) ?>, <?= number_format($route['start_lng'], 6) ?></span>
                            </div>
                            <div class="coord-item">
                                <i class="fas fa-flag-checkered text-danger"></i>
                                <span><strong>End:</strong> <?= number_format($route['end_lat'], 6) ?>, <?= number_format($route['end_lng'], 6) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Sidebar toggle functionality
            const burgerBtn = document.getElementById('burgerBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const linkTexts = document.querySelectorAll('.link-text');
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

            if (burgerBtn) {
                burgerBtn.addEventListener('click', () => {
                    // Check if we're on mobile (sidebar is hidden off-screen)
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('show');
                    } else {
                        // Desktop behavior - collapse/expand sidebar
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
                });
            }

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

            // Logout functionality
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
                                    window.location.href = '/tracking/logout.php';
                                }
                            });
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>