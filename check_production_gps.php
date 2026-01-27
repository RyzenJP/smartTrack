<?php
// Check production GPS devices via API
$url = 'https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php';

// Send a request to get GPS devices (we'll need to modify the API to support this)
// For now, let's test with your actual mobile device

echo "=== PRODUCTION GPS CHECK ===\n";
echo "1. Testing mobile GPS API connection...\n";

// Test with a real device ID that might be in your system
$testData = [
    'device_id' => 'MOBILE-001',  // This was in your local database
    'device_name' => 'Mobile Device',
    'latitude' => 10.555079,
    'longitude' => 122.8826498,
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

echo "GPS API Response:\n";
echo $result . "\n\n";

echo "2. Checking vehicle locations API...\n";
$locations = file_get_contents('https://smarttrack.bccbsis.com/trackingv2/trackingv2/get_all_vehicle_locations.php');
echo "Locations API Response:\n";
echo $locations . "\n";
?>
