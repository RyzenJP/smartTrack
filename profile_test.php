<?php
// Minimal test to find the error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting...<br>";

try {
    echo "Step 2: Including security headers...<br>";
    require_once __DIR__ . '/includes/security_headers.php';
    echo "Step 3: Security headers loaded<br>";
} catch (Exception $e) {
    die("Error in security_headers: " . $e->getMessage());
} catch (Error $e) {
    die("Fatal error in security_headers: " . $e->getMessage());
}

try {
    echo "Step 4: Including db_connection...<br>";
    require_once 'db_connection.php';
    echo "Step 5: Database connected<br>";
} catch (Exception $e) {
    die("Error in db_connection: " . $e->getMessage());
} catch (Error $e) {
    die("Fatal error in db_connection: " . $e->getMessage());
}

echo "Step 6: All includes successful!<br>";
echo "If you see this, the basic includes work.<br>";
?>



