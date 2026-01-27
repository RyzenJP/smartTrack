<?php
/**
 * Python ML Server Bridge
 * Connects PHP web application to Python ML server
 */

header('Content-Type: application/json');

// Secure CORS configuration
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true); // Allow credentials for authenticated requests

// Python ML Server configuration
// Load from environment variable if available, otherwise use Heroku endpoint
if (file_exists(__DIR__ . '/../includes/env_loader.php')) {
    require_once __DIR__ . '/../includes/env_loader.php';
    loadEnv(__DIR__ . '/../.env');
}

$PYTHON_ML_SERVER_URL = defined('PYTHON_ML_SERVER_URL') 
    ? PYTHON_ML_SERVER_URL 
    : 'https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com';

/**
 * Make HTTP request to Python ML server
 */
function callPythonMLServer($endpoint, $method = 'GET', $data = null) {
    global $PYTHON_ML_SERVER_URL;
    
    $url = $PYTHON_ML_SERVER_URL . $endpoint;
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
            'timeout' => 30
        ]
    ];
    
    if ($data && $method === 'POST') {
        $options['http']['content'] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Python ML server is not running. Please start the server first.',
            'error' => 'Connection failed to ' . $url
        ];
    }
    
    $decoded = json_decode($result, true);
    return $decoded ?: [
        'success' => false,
        'message' => 'Invalid response from Python ML server',
        'raw_response' => $result
    ];
}

/**
 * Get server status
 */
function getServerStatus() {
    $response = callPythonMLServer('/status');
    
    if (!$response['success']) {
        return [
            'success' => false,
            'message' => 'Python ML server is not available',
            'server_running' => false,
            'recommended_action' => 'Start the Python ML server using: python python_ml_server.py 8080'
        ];
    }
    
    return [
        'success' => true,
        'server_running' => true,
        'data' => $response['data']
    ];
}

/**
 * Train the ML model
 */
function trainModel() {
    $response = callPythonMLServer('/train', 'POST');
    
    if ($response['success']) {
        return [
            'success' => true,
            'message' => 'Model trained successfully using Python ML server',
            'training_stats' => $response['training_stats'] ?? null,
            'method' => 'python_ml_server'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Model training failed: ' . ($response['message'] ?? 'Unknown error'),
        'method' => 'python_ml_server'
    ];
}

/**
 * Predict maintenance for a specific vehicle
 */
function predictVehicle($vehicleId) {
    $response = callPythonMLServer("/predict?vehicle_id={$vehicleId}");
    
    if ($response['success']) {
        return [
            'success' => true,
            'data' => $response['data'],
            'method' => 'python_ml_server'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Prediction failed: ' . ($response['message'] ?? 'Unknown error'),
        'method' => 'python_ml_server'
    ];
}

/**
 * Predict maintenance for all vehicles
 */
function predictAllVehicles() {
    $response = callPythonMLServer('/predict_all');
    
    if ($response['success']) {
        return [
            'success' => true,
            'data' => $response['data'],
            'method' => 'python_ml_server'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Predictions failed: ' . ($response['message'] ?? 'Unknown error'),
        'method' => 'python_ml_server'
    ];
}

// Main request handler
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'status':
            $result = getServerStatus();
            break;
            
        case 'train':
            $result = trainModel();
            break;
            
        case 'predict':
            $vehicleId = $_GET['vehicle_id'] ?? $_POST['vehicle_id'] ?? null;
            if (!$vehicleId) {
                $result = [
                    'success' => false,
                    'message' => 'Vehicle ID is required'
                ];
            } else {
                $result = predictVehicle($vehicleId);
            }
            break;
            
        case 'predict_all':
            $result = predictAllVehicles();
            break;
            
        default:
            $result = [
                'success' => false,
                'message' => 'Invalid action. Available actions: status, train, predict, predict_all',
                'available_actions' => [
                    'status' => 'GET - Check Python ML server status',
                    'train' => 'POST - Train the ML model',
                    'predict' => 'GET - Predict maintenance for specific vehicle (requires vehicle_id)',
                    'predict_all' => 'GET - Predict maintenance for all vehicles'
                ]
            ];
            break;
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
