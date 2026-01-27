<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Ensure only admin or super admin can access
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'super admin'])) {
    header("Location: ../index.php");
    exit();
}

// Handle status change
if (isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT driver_id FROM maintenance_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    $driver_id = $driver['driver_id'];
    $stmt->close();
    
    // Update status
    $stmt = $conn->prepare("UPDATE maintenance_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    $update_success = $stmt->execute();
    $stmt->close();
    
    if ($update_success) {
        // Insert notification for driver using a prepared statement
        $msg = "Your maintenance request #$request_id has been $status.";
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notif->bind_param("is", $driver_id, $msg);
        $stmt_notif->execute();
        $stmt_notif->close();
    }
}

// Fetch all maintenance requests
$requests_query = "
    SELECT mr.*,
           fv.article AS vehicle_name,
           fv.plate_number,
           u.full_name AS driver_name
    FROM maintenance_requests mr
    JOIN fleet_vehicles fv ON mr.vehicle_id = fv.id
    JOIN user_table u ON mr.driver_id = u.user_id
    ORDER BY mr.request_date DESC
";

// Use prepared statement for consistency (static query but best practice)
$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->execute();
$requests = $requests_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests | Admin</title>
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
        
        /* Main content specific styles */
        .table th {
            background-color: #003566;
            color: white;
        }
        .btn-sm {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../pages/sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/navbar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <h3 class="mb-4">Maintenance Requests</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Request Date</th>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Plate</th>
                            <th>Type</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests && $requests->num_rows > 0): ?>
                            <?php while ($row = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= date("Y-m-d H:i", strtotime($row['request_date'])) ?></td>
                                    <td><?= htmlspecialchars($row['driver_name']) ?></td>
                                    <td><?= htmlspecialchars($row['vehicle_name']) ?></td>
                                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                                    <td><?= ucwords(str_replace('_', ' ', $row['maintenance_type'])) ?></td>
                                    <td><?= date("Y-m-d H:i", strtotime($row['start_time'])) ?></td>
                                    <td><?= date("Y-m-d H:i", strtotime($row['end_time'])) ?></td>
                                    <td><?= htmlspecialchars($row['notes']) ?></td>
                                    <td>
                                        <?php
                                            $status_class = '';
                                            switch ($row['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-warning text-dark';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'bg-success';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-danger';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'bg-primary';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge <?= $status_class ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline-block">
                                            <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto">
                                                <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Pending</option>
                                                <option value="approved" <?= $row['status']=='approved'?'selected':'' ?>>Approved</option>
                                                <option value="rejected" <?= $row['status']=='rejected'?'selected':'' ?>>Rejected</option>
                                                <option value="completed" <?= $row['status']=='completed'?'selected':'' ?>>Completed</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No maintenance requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Sidebar and Navbar interaction logic
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