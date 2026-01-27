<?php
// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header FIRST to ensure all output is JSON
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);

// CORS headers for mobile apps (allow all origins for mobile apps)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load database constants - check multiple locations
// Try root directory first (where db_connection.php usually is)
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

// Define database constants if not already defined
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
        // Check if we're on production server by checking the domain
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

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log detailed error server-side only
    error_log("Mobile GPS API - Database connection failed: " . $e->getMessage());
    error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME);
    
    // ALWAYS return JSON, never HTML/text
    ob_clean(); // Clear any unexpected output
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error. Please contact the administrator.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    ob_end_flush();
    exit();
}

// Function to validate API key
function validateApiKey($apiKey) {
    // You can implement your own API key validation logic here
    // For now, we'll use a simple validation
    return !empty($apiKey) && strlen($apiKey) >= 10;
}

// Function to log GPS data
function logGpsData($pdo, $data) {
    try {
        $sql = "INSERT INTO gps_logs (
            device_id, 
            latitude, 
            longitude
        ) VALUES (?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['device_id'],
            $data['latitude'],
            $data['longitude']
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("GPS logging error: " . $e->getMessage());
        error_log("GPS logging SQL: " . $sql);
        error_log("GPS logging data: " . json_encode($data));
        return false;
    }
}

// Function to update device status using your existing gps_devices table structure
function updateDeviceStatus($pdo, $deviceId, $deviceName, $latitude, $longitude, $speed, $batteryLevel = null, $vehicleId = null) {
    try {
        // Check if device exists
        $checkSql = "SELECT id FROM gps_devices WHERE device_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$deviceId]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing device using your table structure
            $updateSql = "UPDATE gps_devices SET 
                vehicle_id = ?, 
                status = 'active',
                last_update = NOW(),
                battery_level = ?,
                lat = ?, 
                lng = ?, 
                speed = ?
                WHERE device_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $vehicleId, // vehicle_id from request
                $batteryLevel,
                $latitude,
                $longitude,
                $speed,
                $deviceId
            ]);
        } else {
            // Insert new device using your table structure
            $insertSql = "INSERT INTO gps_devices (
                device_id, 
                vehicle_id, 
                status, 
                last_update, 
                battery_level, 
                lat, 
                lng, 
                speed
            ) VALUES (?, ?, 'active', NOW(), ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $deviceId,
                $vehicleId, // vehicle_id from request
                $batteryLevel,
                $latitude,
                $longitude,
                $speed
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Device status update error: " . $e->getMessage());
        return false;
    }
}

// Main API logic
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $requiredFields = ['device_id', 'device_name', 'latitude', 'longitude', 'api_key'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate API key
    if (!validateApiKey($input['api_key'])) {
        throw new Exception('Invalid API key');
    }

    // Validate coordinates
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid coordinates');
    }

    // Prepare data for database
    $gpsData = [
        'device_id' => $input['device_id'],
        'device_name' => $input['device_name'],
        'vehicle_id' => $input['vehicle_id'] ?? null,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'speed' => floatval($input['speed'] ?? 0),
        'battery_level' => isset($input['battery_level']) ? intval($input['battery_level']) : null,
        'status' => $input['status'] ?? 'active',
        'timestamp' => $input['timestamp'] ?? date('Y-m-d H:i:s')
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Log GPS data
        $logResult = logGpsData($pdo, $gpsData);
        
        // Update device status
        $statusResult = updateDeviceStatus(
            $pdo, 
            $gpsData['device_id'], 
            $gpsData['device_name'], 
            $gpsData['latitude'], 
            $gpsData['longitude'], 
            $gpsData['speed'],
            $gpsData['battery_level'],
            $gpsData['vehicle_id']
        );

        if (!$logResult || !$statusResult) {
            error_log("GPS Save Failed - Log: " . ($logResult ? 'SUCCESS' : 'FAILED') . ", Status: " . ($statusResult ? 'SUCCESS' : 'FAILED'));
            throw new Exception('Failed to save GPS data - Log: ' . ($logResult ? 'OK' : 'FAILED') . ', Status: ' . ($statusResult ? 'OK' : 'FAILED'));
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        ob_clean(); // Clear any unexpected output
        echo json_encode([
            'success' => true,
            'message' => 'GPS data received successfully',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'device_id' => $gpsData['device_id'],
                'coordinates' => [
                    'latitude' => $gpsData['latitude'],
                    'longitude' => $gpsData['longitude']
                ],
                'speed' => $gpsData['speed']
            ]
        ]);
        ob_end_flush();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    ob_clean(); // Clear any unexpected output
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    ob_end_flush();
}
?>
