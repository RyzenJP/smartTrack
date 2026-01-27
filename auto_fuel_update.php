<?php
/**
 * Auto Fuel Consumption Update Script
 * This script automatically decreases fuel levels based on GPS trips
 * Can be run via cron job every hour or manually
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting automatic fuel consumption update...\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Get vehicles with GPS activity in the last 7 days
    $query = "
        SELECT 
            v.id,
            v.article,
            v.plate_number,
            v.fuel_consumption_l_per_km,
            v.current_fuel_level_liters,
            v.fuel_tank_capacity_liters,
            COUNT(g.id) as gps_points,
            COUNT(g.id) * 0.1 as distance_km,
            MAX(g.timestamp) as latest_gps
        FROM fleet_vehicles v
        INNER JOIN gps_devices gd ON v.id = gd.vehicle_id
        INNER JOIN gps_logs g ON gd.device_id = g.device_id
        WHERE g.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND g.is_deleted = 0
        AND v.article NOT LIKE 'Synthetic%'
        AND v.plate_number NOT LIKE 'SYN-%'
        GROUP BY v.id
        HAVING gps_points > 0
    ";

    $stmt = $pdo->query($query);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($vehicles)) {
        echo "No vehicles with GPS activity in the last 30 days.\n";
        exit(0);
    }

    echo "Found " . count($vehicles) . " vehicles with GPS activity:\n\n";

    $totalFuelConsumed = 0;
    $vehiclesUpdated = 0;

    foreach ($vehicles as $vehicle) {
        $vehicleId = $vehicle['id'];
        $distance = floatval($vehicle['distance_km']);
        $fuelConsumption = floatval($vehicle['fuel_consumption_l_per_km']);
        $fuelConsumed = $distance * $fuelConsumption;
        $currentFuel = floatval($vehicle['current_fuel_level_liters']);
        $tankCapacity = floatval($vehicle['fuel_tank_capacity_liters']);
        
        $newFuelLevel = max(0, $currentFuel - $fuelConsumed);
        $fuelPercentage = $tankCapacity > 0 ? ($newFuelLevel / $tankCapacity) * 100 : 0;

        echo "Vehicle: {$vehicle['article']} ({$vehicle['plate_number']})\n";
        echo "  GPS Points: {$vehicle['gps_points']}\n";
        echo "  Distance: {$distance} km\n";
        echo "  Fuel Consumed: {$fuelConsumed} L\n";
        echo "  Fuel Level: {$currentFuel}L → {$newFuelLevel}L ({$fuelPercentage}%)\n";
        
        if ($fuelPercentage < 25) {
            echo "  ⚠️  LOW FUEL ALERT!\n";
        }
        echo "\n";

        // Update vehicle fuel level
        $updateQuery = "
            UPDATE fleet_vehicles 
            SET 
                current_fuel_level_liters = ?,
                total_fuel_consumed_liters = total_fuel_consumed_liters + ?,
                last_updated = NOW()
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([$newFuelLevel, $fuelConsumed, $vehicleId]);

        // Log daily consumption
        $logQuery = "
            INSERT INTO fuel_consumption_logs 
            (vehicle_id, log_date, distance_traveled_km, fuel_consumed_liters, fuel_efficiency_km_per_l, gps_points_count)
            VALUES (?, CURDATE(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                distance_traveled_km = distance_traveled_km + VALUES(distance_traveled_km),
                fuel_consumed_liters = fuel_consumed_liters + VALUES(fuel_consumed_liters),
                gps_points_count = gps_points_count + VALUES(gps_points_count),
                fuel_efficiency_km_per_l = (distance_traveled_km + VALUES(distance_traveled_km)) / (fuel_consumed_liters + VALUES(fuel_consumed_liters)),
                updated_at = CURRENT_TIMESTAMP
        ";

        $efficiency = $fuelConsumption > 0 ? (1 / $fuelConsumption) : 0;
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute([$vehicleId, $distance, $fuelConsumed, $efficiency, $vehicle['gps_points']]);

        $totalFuelConsumed += $fuelConsumed;
        $vehiclesUpdated++;
    }

    echo "Summary:\n";
    echo "  Vehicles Updated: {$vehiclesUpdated}\n";
    echo "  Total Fuel Consumed: {$totalFuelConsumed} L\n";
    echo "  Update Completed: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
