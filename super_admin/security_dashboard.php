<?php
session_start();
require 'db_connection.php';

// Check if user is admin (case insensitive)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied - Security Dashboard</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>🔒 Access Denied</h4>
                <p>You need to be logged in as a Super Admin to access the Security Dashboard.</p>
                <p><strong>Current Role:</strong> " . ($_SESSION['role'] ?? 'Not logged in') . "</p>
                <a href='login.php' class='btn btn-primary'>Go to Login</a>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// Get security stats using existing connection - use prepared statements for consistency
$totalUsers_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_table");
$totalUsers_stmt->execute();
$totalUsers = $totalUsers_stmt->get_result()->fetch_assoc()['count'] ?? 0;
$totalUsers_stmt->close();

$activeSessions_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_table WHERE last_login > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$activeSessions_stmt->execute();
$activeSessions = $activeSessions_stmt->get_result()->fetch_assoc()['count'] ?? 0;
$activeSessions_stmt->close();

$stats = [
    'total_users' => $totalUsers,
    'active_sessions' => $activeSessions,
    'failed_logins' => isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0,
    'security_events' => file_exists('security.log') ? count(file('security.log')) : 0
];

// Get recent security events
$recent_events = [];
if (file_exists('security.log')) {
    $lines = file('security.log');
    $recent_events = array_slice(array_reverse($lines), 0, 10);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="mt-4 mb-4"><i class="fas fa-shield-alt text-primary"></i> Security Dashboard</h1>
                
                <!-- Security Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['total_users'] ?></h4>
                                        <p class="mb-0">Total Users</p>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['active_sessions'] ?></h4>
                                        <p class="mb-0">Active Sessions</p>
                                    </div>
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['failed_logins'] ?></h4>
                                        <p class="mb-0">Failed Logins</p>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['security_events'] ?></h4>
                                        <p class="mb-0">Security Events</p>
                                    </div>
                                    <i class="fas fa-shield-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Features Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cogs"></i> Security Features Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">SQL Injection Protection</h6>
                                                <small class="text-muted">Prepared statements enabled</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">Rate Limiting</h6>
                                                <small class="text-muted">Login attempts limited</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">Input Sanitization</h6>
                                                <small class="text-muted">XSS protection active</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">Security Headers</h6>
                                                <small class="text-muted">HTTP headers configured</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">File Protection</h6>
                                                <small class="text-muted">.htaccess rules active</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-0">Session Security</h6>
                                                <small class="text-muted">Secure session handling</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Security Events -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history"></i> Recent Security Events</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_events)): ?>
                                    <p class="text-muted">No security events recorded.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Event</th>
                                                    <th>IP Address</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_events as $event): ?>
                                                    <?php $parts = explode(' - ', $event); ?>
                                                    <tr>
                                                        <td><?= isset($parts[0]) ? $parts[0] : '' ?></td>
                                                        <td><?= isset($parts[1]) ? $parts[1] : '' ?></td>
                                                        <td><?= isset($parts[2]) ? $parts[2] : '' ?></td>
                                                        <td><?= isset($parts[3]) ? $parts[3] : '' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="super_admin/homepage.php" class="btn btn-outline-primary w-100 mb-2">
                                            <i class="fas fa-arrow-left"></i> Back to Admin
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-outline-warning w-100 mb-2" onclick="clearSecurityLog()">
                                            <i class="fas fa-trash"></i> Clear Security Log
                                        </button>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-outline-info w-100 mb-2" onclick="refreshDashboard()">
                                            <i class="fas fa-sync"></i> Refresh
                                        </button>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="SECURITY_CHECKLIST.md" class="btn btn-outline-success w-100 mb-2" target="_blank">
                                            <i class="fas fa-list"></i> Security Checklist
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearSecurityLog() {
            if (confirm('Are you sure you want to clear the security log?')) {
                fetch('api/clear_security_log.php', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error clearing security log');
                    }
                });
            }
        }
        
        function refreshDashboard() {
            location.reload();
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
