<?php
// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header FIRST to ensure all output is JSON
header('Content-Type: application/json');

// CORS headers for mobile apps - Allow all origins for mobile apps
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
// For mobile apps, allow all origins
if ($origin === '*' || empty($origin)) {
    header('Access-Control-Allow-Origin: *');
} else {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load database constants - check multiple locations
$rootDir = __DIR__ . '/../'; // trackingv2/trackingv2/
$rootDir2 = __DIR__ . '/../../'; // trackingv2/ (one level up)

// Load environment variables if available
$envLoaderPaths = [
    $rootDir . 'includes/env_loader.php',
    $rootDir2 . 'includes/env_loader.php'
];

foreach ($envLoaderPaths as $envLoaderPath) {
    if (file_exists($envLoaderPath)) {
        require_once $envLoaderPath;
        $envDir = dirname($envLoaderPath);
        if (file_exists($envDir . '/../.env')) {
            loadEnv($envDir . '/../.env');
        } elseif (file_exists($envDir . '/.env')) {
            loadEnv($envDir . '/.env');
        }
        break;
    }
}

if (!defined('DB_HOST')) {
    // Try to load from config files (check multiple locations)
    $configPaths = [
        $rootDir . 'config.local.php',
        $rootDir . 'config.prod.php',
        $rootDir2 . 'config.local.php',
        $rootDir2 . 'config.prod.php',
        $rootDir . 'config.php',
        $rootDir2 . 'config.php'
    ];
    
    $configLoaded = false;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            $configLoaded = true;
            break;
        }
    }
    
    // If no config file found, use fallback
    if (!$configLoaded) {
        // Production fallback (Hostinger)
        $isProduction = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'smarttrack.bccbsis.com') !== false);
        
        if ($isProduction) {
            // Production database credentials (Hostinger)
            define('DB_HOST', 'localhost');
            define('DB_NAME', 'u520834156_dbSmartTrack');
            define('DB_USER', 'u520834156_uSmartTrck25');
            define('DB_PASS', 'xjOzav~2V');
        } else {
            // Local development defaults
            define('DB_HOST', 'localhost');
            define('DB_NAME', 'trackingv2');
            define('DB_USER', 'root');
            define('DB_PASS', '');
        }
    }
}

// Test database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test query
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();
    
    ob_clean(); // Clear any unexpected output
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful',
        'database' => DB_NAME,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    ob_end_flush();
} catch (PDOException $e) {
    ob_clean(); // Clear any unexpected output
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => 'Please check your database configuration',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    ob_end_flush();
}
?>

