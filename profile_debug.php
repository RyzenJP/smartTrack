<?php
// Debug version of profile.php to find the error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- Debug: Starting profile.php -->\n";

try {
    echo "<!-- Step 1: Including security headers -->\n";
    require_once __DIR__ . '/includes/security_headers.php';
    echo "<!-- Step 1: OK -->\n";
} catch (Throwable $e) {
    die("FATAL ERROR in security_headers: " . $e->getMessage() . " on line " . $e->getLine());
}

try {
    echo "<!-- Step 2: Including db_connection -->\n";
    require_once 'db_connection.php';
    echo "<!-- Step 2: OK -->\n";
} catch (Throwable $e) {
    die("FATAL ERROR in db_connection: " . $e->getMessage() . " on line " . $e->getLine());
}

try {
    echo "<!-- Step 3: Checking session -->\n";
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: index.php");
        exit();
    }
    echo "<!-- Step 3: OK - User logged in -->\n";
} catch (Throwable $e) {
    die("FATAL ERROR checking session: " . $e->getMessage() . " on line " . $e->getLine());
}

try {
    echo "<!-- Step 4: Getting user data -->\n";
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $message = '';
    $messageType = '';
    
    // Determine which table to use based on role
    $isRequester = (strtolower($role) === 'requester');
    $table = $isRequester ? 'reservation_users' : 'user_table';
    $idColumn = $isRequester ? 'id' : 'user_id';
    
    echo "<!-- Step 4a: Table = $table, ID Column = $idColumn -->\n";
    
    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
    if (!$stmt) {
        die("FATAL ERROR preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }
    
    echo "<!-- Step 4: OK - User data fetched -->\n";
} catch (Throwable $e) {
    die("FATAL ERROR fetching user: " . $e->getMessage() . " on line " . $e->getLine());
}

echo "<!-- All checks passed! -->\n";
echo "<h1>Profile Debug - All Checks Passed!</h1>";
echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";
echo "<p>Role: " . htmlspecialchars($role) . "</p>";
echo "<p>User Name: " . htmlspecialchars($user['full_name'] ?? 'N/A') . "</p>";
echo "<p>If you see this, the basic profile.php logic works!</p>";
?>



