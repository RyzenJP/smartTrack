<?php
session_start();
require 'db_connection.php';

// Simple role check
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    echo "Access denied. You need to be a super admin to access this page.";
    echo "<br>Current role: " . ($_SESSION['role'] ?? 'Not set');
    echo "<br><a href='login.php'>Go to Login</a>";
    exit;
}

// Get basic stats
$totalUsers = 0;
$activeSessions = 0;

try {
    // Use prepared statements for consistency
    $totalUsers_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_table");
    $totalUsers_stmt->execute();
    $totalUsers = $totalUsers_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $totalUsers_stmt->close();
    
    $activeSessions_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_table WHERE last_login > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $activeSessions_stmt->execute();
    $activeSessions = $activeSessions_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $activeSessions_stmt->close();
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - Simple</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>🔒 Security Dashboard (Simple Version)</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Total Users</h5>
                        <h2><?= $totalUsers ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Active Sessions</h5>
                        <h2><?= $activeSessions ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="super_admin/homepage.php" class="btn btn-primary">Back to Admin</a>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>
