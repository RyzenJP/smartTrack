<?php
// Check GPS device assignments in production
echo "=== CHECKING GPS DEVICE ASSIGNMENTS ===\n\n";

// Test the mobile GPS API with different vehicle IDs
$vehicleIds = [1, 7]; // Common vehicle IDs from your local database

foreach ($vehicleIds as $vehicleId) {
    echo "Testing with Vehicle ID: $vehicleId\n";
    
    $url = 'https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php';
    
    $testData = [
        'device_id' => 'MOBILE-001',
        'device_name' => 'Mobile Device',
        'vehicle_id' => $vehicleId,
        'latitude' => 10.555079 + (rand(-100, 100) / 10000), // Slight variation
        'longitude' => 122.8826498 + (rand(-100, 100) / 10000),
        'speed' => 30.0,
        'api_key' => 'test123456789'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($testData)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    echo "GPS API Response: " . $result . "\n";
    
    // Check vehicle locations after each test
    $locations = file_get_contents('https://smarttrack.bccbsis.com/trackingv2/trackingv2/get_all_vehicle_locations.php');
    echo "Locations API Response: " . $locations . "\n\n";
    
    if (strpos($locations, '"count":0') === false) {
        echo "SUCCESS! Found vehicles with GPS data!\n";
        break;
    }
}
?>
