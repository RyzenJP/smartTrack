<?php
require_once 'db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>GPS Logs Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE gps_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>".$col['Field']."</td>";
        echo "<td>".$col['Type']."</td>";
        echo "<td>".$col['Null']."</td>";
        echo "<td>".$col['Key']."</td>";
        echo "<td>".$col['Default']."</td>";
        echo "<td>".$col['Extra']."</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>GPS Devices Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE gps_devices");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>".$col['Field']."</td>";
        echo "<td>".$col['Type']."</td>";
        echo "<td>".$col['Null']."</td>";
        echo "<td>".$col['Key']."</td>";
        echo "<td>".$col['Default']."</td>";
        echo "<td>".$col['Extra']."</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Sample GPS Logs Data:</h2>";
    $stmt = $pdo->query("SELECT * FROM gps_logs ORDER BY id DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($logs);
    echo "</pre>";
    
    echo "<h2>Sample GPS Devices Data:</h2>";
    $stmt = $pdo->query("SELECT * FROM gps_devices ORDER BY id DESC LIMIT 5");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($devices);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
