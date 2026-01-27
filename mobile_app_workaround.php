<?php
// Mobile App Workaround - GPS data storage on your domain
// This will store GPS data on your domain while main server is down

header('Content-Type: application/json');
require_once __DIR__ . '/includes/cors_helper.php';
setCORSHeaders(true);

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Create local storage directory
$storageDir = 'mobile_gps_storage';
if (!file_exists($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'save_gps':
        // Save GPS data locally
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($input && isset($input['device_id'])) {
            $filename = $storageDir . '/gps_' . $input['device_id'] . '_' . date('Y-m-d') . '.json';
            
            // Load existing data
            $data = [];
            if (file_exists($filename)) {
                $data = json_decode(file_get_contents($filename), true) ?: [];
            }
            
            // Add new entry
            $data[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'device_id' => $input['device_id'],
                'latitude' => $input['latitude'] ?? 0,
                'longitude' => $input['longitude'] ?? 0,
                'speed' => $input['speed'] ?? 0,
                'battery_level' => $input['battery_level'] ?? 0.8
            ];
            
            // Keep only last 1000 entries
            if (count($data) > 1000) {
                $data = array_slice($data, -1000);
            }
            
            // Save to file
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'message' => 'GPS data saved locally',
                'timestamp' => date('Y-m-d H:i:s'),
                'count' => count($data)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid data'
            ]);
        }
        break;
        
    case 'get_latest':
        // Get latest GPS data
        $deviceId = $_GET['device_id'] ?? 'MOBILE-001';
        $filename = $storageDir . '/gps_' . $deviceId . '_' . date('Y-m-d') . '.json';
        
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true) ?: [];
            $latest = end($data);
            
            if ($latest) {
                echo json_encode([
                    'lat' => $latest['latitude'],
                    'lng' => $latest['longitude'],
                    'speed' => $latest['speed'],
                    'last_update' => $latest['timestamp']
                ]);
            } else {
                echo json_encode([
                    'error' => 'No data found'
                ]);
            }
        } else {
            echo json_encode([
                'error' => 'No data file found'
            ]);
        }
        break;
        
    case 'get_all':
        // Get all GPS data for today
        $deviceId = $_GET['device_id'] ?? 'MOBILE-001';
        $filename = $storageDir . '/gps_' . $deviceId . '_' . date('Y-m-d') . '.json';
        
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true) ?: [];
            echo json_encode([
                'success' => true,
                'data' => $data,
                'count' => count($data)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No data file found'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => true,
            'message' => 'Mobile App Workaround API',
            'endpoints' => [
                'save_gps' => 'POST - Save GPS data locally',
                'get_latest' => 'GET - Get latest GPS data',
                'get_all' => 'GET - Get all GPS data for today'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
}
?>
