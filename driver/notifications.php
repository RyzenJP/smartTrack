<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Ensure driver is logged in
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

$driver_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Mark all as read when page is loaded - use prepared statement for security
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$update_stmt->bind_param("i", $driver_id);
$update_stmt->execute();
$update_stmt->close();

// Fetch notifications - use prepared statement for security
$stmt = $conn->prepare("
    SELECT id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | Driver</title>
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
    
    /* Sidebar styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        height: 100vh;
        background-color: var(--primary);
        color: #fff;
        z-index: 1000;
        overflow-y: auto;
        padding-top: 60px; /* Add padding to prevent content being hidden by the top navbar */
        transition: all 0.3s ease;
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

    /* Top navbar styles */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border-bottom: 1px solid #dee2e6;
        z-index: 1100;
        display: block;
    }

    .burger-btn {
        font-size: 1.5rem;
        background: none;
        border: none;
        color: var(--primary);
        margin-right: 1rem;
    }

    /* Main content area */
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

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }
    
    /* Notification specific styles */
    .notification-item {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
        background-color: white;
        border-radius: 6px;
        margin-bottom: 8px;
    }
    .notification-item.unread {
        background-color: #e9f7ff;
        border-left: 4px solid #0d6efd;
    }
    .date-text {
        font-size: 0.85rem;
        color: gray;
    }
</style>
</head>
<body>

<?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <h3 class="mb-4"><i class="fas fa-bell"></i> My Notifications</h3>
    
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="notification-item <?= $row['is_read'] ? '' : 'unread' ?>">
                    <p class="mb-1"><?= htmlspecialchars($row['message']) ?></p>
                    <span class="date-text"><?= date("Y-m-d H:i", strtotime($row['created_at'])) ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">You have no notifications yet.</div>
        <?php endif; ?>
        <?php $stmt->close(); ?>
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

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>