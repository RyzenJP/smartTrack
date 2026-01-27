<?php
// Minimal profile.php to find where it breaks
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting...<br>";

try {
    require_once __DIR__ . '/includes/security_headers.php';
    echo "Step 2: Security headers OK<br>";
} catch (Throwable $e) {
    die("ERROR: " . $e->getMessage());
}

try {
    require_once 'db_connection.php';
    echo "Step 3: DB connection OK<br>";
} catch (Throwable $e) {
    die("ERROR: " . $e->getMessage());
}

// Check session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    die("ERROR: Not logged in. User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
echo "Step 4: Session OK - User ID: $user_id, Role: $role<br>";

// Get table info
$isRequester = (strtolower($role) === 'requester');
$table = $isRequester ? 'reservation_users' : 'user_table';
$idColumn = $isRequester ? 'id' : 'user_id';
echo "Step 5: Table: $table, ID Column: $idColumn<br>";

// Fetch user
try {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
    if (!$stmt) {
        die("ERROR preparing: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        die("ERROR: User not found in database");
    }
    echo "Step 6: User data fetched OK<br>";
    echo "User: " . htmlspecialchars($user['full_name'] ?? 'N/A') . "<br>";
} catch (Throwable $e) {
    die("ERROR fetching user: " . $e->getMessage());
}

// Test dashboard links
$dashboardLinks = [
    'super admin' => 'super_admin/homepage.php',
    'admin' => 'motorpool_admin/admin_homepage.php',
    'dispatcher' => 'dispatcher/dispatcher-dashboard.php',
    'driver' => 'driver/driver-dashboard.php',
    'mechanic' => 'mechanic/mechanic_homepage.php',
    'requester' => 'user/user_dashboard.php'
];
$dashboardLink = $dashboardLinks[strtolower($role)] ?? 'index.php';
echo "Step 7: Dashboard link: $dashboardLink<br>";

// Test sidebar/navbar paths
$roleLower = strtolower($role);
$sidebarFile = '';
$navbarFile = '';

switch ($roleLower) {
    case 'super admin':
        $sidebarFile = 'pages/sidebar.php';
        $navbarFile = 'pages/navbar.php';
        break;
    case 'admin':
        $sidebarFile = 'pages/admin_sidebar.php';
        $navbarFile = 'pages/admin_navbar.php';
        break;
    case 'dispatcher':
        $sidebarFile = 'pages/dispatcher_sidebar.php';
        $navbarFile = 'pages/dispatcher_navbar.php';
        break;
    case 'driver':
        $sidebarFile = 'pages/driver_sidebar.php';
        $navbarFile = 'pages/driver_navbar.php';
        break;
    case 'mechanic':
        $sidebarFile = 'pages/mechanic_sidebar.php';
        $navbarFile = 'pages/mechanic_navbar.php';
        break;
    case 'requester':
        $sidebarFile = 'pages/user_sidebar.php';
        $navbarFile = 'pages/user_navbar.php';
        break;
    default:
        $sidebarFile = 'pages/sidebar.php';
        $navbarFile = 'pages/navbar.php';
        break;
}

echo "Step 8: Sidebar file: $sidebarFile (exists: " . (file_exists($sidebarFile) ? 'YES' : 'NO') . ")<br>";
echo "Step 9: Navbar file: $navbarFile (exists: " . (file_exists($navbarFile) ? 'YES' : 'NO') . ")<br>";

echo "<h2>All steps completed successfully!</h2>";
echo "<p>If you see this, the basic profile.php logic works.</p>";
echo "<p>Try including the sidebar/navbar files now:</p>";

if (file_exists($sidebarFile)) {
    try {
        include $sidebarFile;
        echo "<p>Sidebar included OK</p>";
    } catch (Throwable $e) {
        echo "<p>ERROR including sidebar: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Sidebar file not found: $sidebarFile</p>";
}

if (file_exists($navbarFile)) {
    try {
        include $navbarFile;
        echo "<p>Navbar included OK</p>";
    } catch (Throwable $e) {
        echo "<p>ERROR including navbar: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Navbar file not found: $navbarFile</p>";
}
?>



