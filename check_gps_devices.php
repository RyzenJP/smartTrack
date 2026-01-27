<?php
// Use local database for testing
$conn = new mysqli('localhost', 'root', '', 'trackingv2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== GPS DEVICES STATUS ===\n";

// Check GPS devices - use prepared statements for consistency
$stmt1 = $conn->prepare("SELECT device_id, vehicle_id, status, last_update, lat, lng FROM gps_devices ORDER BY last_update DESC LIMIT 10");
$stmt1->execute();
$result = $stmt1->get_result();
echo "GPS Devices:\n";
while($row = $result->fetch_assoc()) {
    echo "Device: {$row['device_id']}, Vehicle: {$row['vehicle_id']}, Status: {$row['status']}, Last Update: {$row['last_update']}, Lat: {$row['lat']}, Lng: {$row['lng']}\n";
}
$stmt1->close();

echo "\n=== FLEET VEHICLES ===\n";
$stmt2 = $conn->prepare("SELECT id, article, plate_number, status FROM fleet_vehicles WHERE status = 'active' AND article NOT LIKE '%Synthetic%' LIMIT 10");
$stmt2->execute();
$result = $stmt2->get_result();
echo "Active Vehicles:\n";
while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['article']}, Plate: {$row['plate_number']}, Status: {$row['status']}\n";
}
$stmt2->close();

echo "\n=== VEHICLE ASSIGNMENTS ===\n";
$stmt3 = $conn->prepare("SELECT va.vehicle_id, va.driver_id, va.status, v.article, u.full_name FROM vehicle_assignments va LEFT JOIN fleet_vehicles v ON va.vehicle_id = v.id LEFT JOIN user_table u ON va.driver_id = u.user_id WHERE va.status = 'active' LIMIT 10");
$stmt3->execute();
$result = $stmt3->get_result();
echo "Active Assignments:\n";
while($row = $result->fetch_assoc()) {
    echo "Vehicle: {$row['article']}, Driver: {$row['full_name']}, Status: {$row['status']}\n";
}
$stmt3->close();
?>
