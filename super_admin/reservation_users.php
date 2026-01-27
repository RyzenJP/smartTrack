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

// Handle actions: edit and delete (and legacy activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (in_array($action, ['activate', 'deactivate']) && isset($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        $newStatus = $action === 'activate' ? 'Active' : 'Inactive';
        $stmt = $conn->prepare("UPDATE reservation_users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $userId);
        if ($stmt->execute()) {
            $message = $newStatus === 'Active' ? 'User activated successfully.' : 'User deactivated successfully.';
        } else {
            $error = 'Failed to update user status.';
        }
        $stmt->close();
    }
}

// Filters & search
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';
if ($statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter === 'active' ? 'Active' : 'Inactive';
    $types .= 's';
}
if ($search !== '') {
    $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, full_name, username, email, phone, status, last_login, created_at, updated_at
        FROM reservation_users $whereSql ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reservation Users | Smart Track</title>
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

    .card-icon {
      font-size: 2rem;
      color: var(--accent);
    }

    .card h6 {
      font-weight: 500;
    }

    .card h4 {
      font-weight: bold;
    }
    
    /* Ensure modal overlays navbar */
    .modal {
      z-index: 1300 !important;
    }
    .modal-backdrop {
      z-index: 1290 !important;
    }

    /* Responsive tweaks */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 260px;
        transform: translateX(-100%);
        position: fixed;
      }
      .sidebar.open { transform: translateX(0); }
      .sidebar.collapsed { width: 260px; }
      .main-content,
      .main-content.collapsed { margin-left: 0; padding: 16px; }
      .table thead th:nth-child(1), /* # */
      .table thead th:nth-child(7), /* Last Login */
      .table thead th:nth-child(8)  /* Created */ { display: none; }
      .table tbody td:nth-child(1),
      .table tbody td:nth-child(7),
      .table tbody td:nth-child(8) { display: none; }
      .d-flex.gap-2 a.btn { padding: 4px 8px; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

 <!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h3 class="mb-2 mb-lg-0"><i class="fas fa-users me-2"></i>Reservation Users</h3>
                    <div class="d-flex gap-2">
                        <a href="reservation_users.php?status=all" class="btn btn-outline-secondary btn-sm <?php echo $statusFilter==='all'?'active':''; ?>">All</a>
                        <a href="reservation_users.php?status=active" class="btn btn-outline-success btn-sm <?php echo $statusFilter==='active'?'active':''; ?>">Active</a>
                        <a href="reservation_users.php?status=inactive" class="btn btn-outline-warning btn-sm <?php echo $statusFilter==='inactive'?'active':''; ?>">Inactive</a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1300;">
                        <div id="successToast" class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                    <script>
                      document.addEventListener('DOMContentLoaded', function() {
                        const toastEl = document.getElementById('successToast');
                        if (toastEl) {
                          const t = new bootstrap.Toast(toastEl);
                          t.show();
                        }
                      });
                    </script>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-1"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form class="row g-2 mb-3" method="get">
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <input type="text" class="form-control" name="q" placeholder="Search name, username, email, phone" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                                <a class="btn btn-outline-secondary" href="reservation_users.php"><i class="fas fa-undo me-1"></i>Reset</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Full Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows === 0): ?>
                                        <tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>
                                    <?php endif; ?>
                                    <?php while ($u = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo (int)$u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                            <td><span class="fw-semibold">@<?php echo htmlspecialchars($u['username']); ?></span></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td><?php echo htmlspecialchars($u['phone'] ?? ''); ?></td>
                                            <td>
                                                <?php $badge = strtolower($u['status']) === 'active' ? 'success' : 'secondary'; ?>
                                                <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($u['status']); ?></span>
                                            </td>
                                            <td><small class="text-muted"><?php echo $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : '—'; ?></small></td>
                                            <td><small class="text-muted"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></small></td>
                                            <td class="text-nowrap">
                                                <?php if (strtolower($u['status']) !== 'active'): ?>
                                                <button type="button" class="btn btn-sm btn-success activate-btn" 
                                                        data-id="<?= $u['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($u['full_name']) ?>">
                                                    <i class="fas fa-user-check"></i> Activate
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Activate Confirmation Modal -->
<div class="modal fade" id="activateModal" tabindex="-1" aria-hidden="true" style="z-index: 1300;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <form method="POST" action="reservation_users.php">
        <input type="hidden" name="action" value="activate">
        <input type="hidden" name="user_id" id="activate_user_id">
        <div class="modal-body text-center p-5">
          <div class="mb-4">
            <div class="d-inline-flex align-items-center justify-content-center" 
                 style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #28a745, #20c997);">
              <i class="fas fa-user-check text-white" style="font-size: 2.5rem;"></i>
            </div>
          </div>
          <h4 class="fw-bold mb-3" style="color: #212529;">Activate User Account?</h4>
          <p class="text-muted mb-4" id="activateModalText" style="font-size: 1.05rem;">
            Are you sure you want to activate this user?
          </p>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success btn-lg" style="border-radius: 12px; padding: 14px; font-weight: 600;">
              <i class="fas fa-check-circle me-2"></i>Yes, Activate
            </button>
            <button type="button" class="btn btn-light btn-lg" data-bs-dismiss="modal" 
                    style="border-radius: 12px; padding: 14px; font-weight: 600; border: 2px solid #e9ecef;">
              <i class="fas fa-times me-2"></i>Cancel
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>



<!-- JS -->
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  function handleBurgerClick(){
    if (window.innerWidth < 992) { sidebar.classList.toggle('open'); return; }
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

    // 🚨 Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  }
  burgerBtn.addEventListener('click', handleBurgerClick);
  window.addEventListener('resize',()=>{ if(window.innerWidth<992){ mainContent.classList.remove('collapsed'); } });
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Activate button click handler (Bootstrap modal)
  document.querySelectorAll('.activate-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id;
      const name = this.dataset.name || 'this user';
      const modal = new bootstrap.Modal(document.getElementById('activateModal'));
      document.getElementById('activate_user_id').value = id;
      const txt = document.getElementById('activateModalText');
      if (txt) txt.textContent = `Are you sure you want to activate ${name}'s account?`;
      modal.show();
    });
  });

</script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
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
  });
</script>
</body>
</html>


