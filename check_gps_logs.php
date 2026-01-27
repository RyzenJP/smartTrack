<?php
require 'db_connection.php';

// Use prepared statements for consistency (DESCRIBE is safe but best practice)
echo "GPS Logs table structure:\n";
$stmt1 = $conn->prepare('DESCRIBE gps_logs');
$stmt1->execute();
$result = $stmt1->get_result();
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
$stmt1->close();

echo "\nGPS Devices table structure:\n";
$stmt2 = $conn->prepare('DESCRIBE gps_devices');
$stmt2->execute();
$result = $stmt2->get_result();
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
$stmt2->close();

$conn->close();
?>
