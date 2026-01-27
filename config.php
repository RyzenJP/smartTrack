<?php
// Environment Configuration Toggle
// Change 'local' to 'prod' to switch environments

$environment = 'prod'; // Change this to 'prod' for production

// Load the appropriate configuration
if ($environment === 'local') {
    require_once 'config.local.php';
} else {
    require_once 'config.prod.php';
}

// Display current environment (remove in production)
if (DEBUG && ENVIRONMENT === 'local') {
    echo "<!-- Running in LOCAL environment -->";
}
?>
