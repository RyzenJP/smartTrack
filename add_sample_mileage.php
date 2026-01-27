<?php
require_once 'db_connection.php';

echo "<h2>Adding Sample Mileage Data</h2>";

// Update vehicles with sample mileage data
$sampleData = [
    ['id' => 1, 'current_mileage' => 15000, 'last_maintenance_mileage' => 10000, 'last_maintenance_date' => '2025-01-13'],
    ['id' => 7, 'current_mileage' => 8500, 'last_maintenance_mileage' => 5000, 'last_maintenance_date' => '2025-07-15'],
    ['id' => 4, 'current_mileage' => 25000, 'last_maintenance_mileage' => 20000, 'last_maintenance_date' => '2025-06-01'],
    ['id' => 6, 'current_mileage' => 3500, 'last_maintenance_mileage' => 0, 'last_maintenance_date' => null],
    ['id' => 9, 'current_mileage' => 12000, 'last_maintenance_mileage' => 10000, 'last_maintenance_date' => '2025-05-15']
];

foreach ($sampleData as $data) {
    $stmt = $conn->prepare("
        UPDATE fleet_vehicles 
        SET current_mileage = ?, 
            last_maintenance_mileage = ?, 
            last_maintenance_date = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param('iisi', 
        $data['current_mileage'], 
        $data['last_maintenance_mileage'], 
        $data['last_maintenance_date'], 
        $data['id']
    );
    
    if ($stmt->execute()) {
        echo "<p>✅ Updated vehicle ID {$data['id']} with {$data['current_mileage']} km mileage</p>";
    } else {
        echo "<p>❌ Failed to update vehicle ID {$data['id']}</p>";
    }
}

echo "<h3>Sample Data Added:</h3>";
echo "<ul>";
echo "<li><strong>Vehicle 1 (434-34e):</strong> 15,000 km (last maintenance at 10,000 km)</li>";
echo "<li><strong>Vehicle 7 (M-2752):</strong> 8,500 km (last maintenance at 5,000 km)</li>";
echo "<li><strong>Vehicle 4 (143705):</strong> 25,000 km (last maintenance at 20,000 km)</li>";
echo "<li><strong>Vehicle 6 (343-23D):</strong> 3,500 km (no previous maintenance)</li>";
echo "<li><strong>Vehicle 9 (3432-434):</strong> 12,000 km (last maintenance at 10,000 km)</li>";
echo "</ul>";

echo "<p><strong>Expected Alerts:</strong></p>";
echo "<ul>";
echo "<li>Vehicle 1: Needs 10,000 km service (oil change, tire rotation) - 5,000 km remaining</li>";
echo "<li>Vehicle 7: Needs 10,000 km service (oil change, tire rotation) - 1,500 km remaining</li>";
echo "<li>Vehicle 4: Needs 30,000 km service (oil change, tire rotation) - 5,000 km remaining</li>";
echo "<li>Vehicle 6: Needs 5,000 km service (oil change) - 1,500 km remaining</li>";
echo "<li>Vehicle 9: Needs 15,000 km service (oil change) - 3,000 km remaining</li>";
echo "</ul>";

echo "<p><a href='super_admin/predictive_maintenance.php'>Go to Predictive Maintenance Page</a></p>";
?>
