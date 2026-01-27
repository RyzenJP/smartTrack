<?php
// This script will help fix the GPS tracking in production
echo "=== FIXING PRODUCTION GPS TRACKING ===\n\n";

echo "The issue is that your mobile device needs to be properly linked to a vehicle in the production database.\n";
echo "Here's what we need to do:\n\n";

echo "1. Your mobile app is sending GPS data with device_id: MOBILE-001\n";
echo "2. The GPS data is being received successfully\n";
echo "3. But the vehicle locations API can't find it because the device isn't linked to a vehicle\n\n";

echo "SOLUTION:\n";
echo "We need to update the production database to link MOBILE-001 to a vehicle.\n";
echo "Since you have your mobile on, let's test this:\n\n";

// Test sending GPS data with vehicle_id
$url = 'https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php';

$testData = [
    'device_id' => 'MOBILE-001',
    'device_name' => 'Mobile Device',
    'vehicle_id' => '1',  // Link to vehicle ID 1 (Ambulance)
    'latitude' => 10.555079,
    'longitude' => 122.8826498,
    'speed' => 35.0,
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

echo "Sending GPS data with vehicle_id:\n";
echo $result . "\n\n";

echo "Now checking vehicle locations:\n";
$locations = file_get_contents('https://smarttrack.bccbsis.com/trackingv2/trackingv2/get_all_vehicle_locations.php');
echo $locations . "\n";
?>
