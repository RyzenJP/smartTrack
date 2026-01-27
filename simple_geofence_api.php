<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/cors_helper.php';
setCORSHeaders(true);

require_once 'db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $limit = intval($_GET['limit'] ?? 5);
    
    // Very simple query
    $stmt = $pdo->prepare("
        SELECT ge.*, g.name as geofence_name, v.article as vehicle_name, v.plate_number
        FROM geofence_events ge
        LEFT JOIN geofences g ON ge.geofence_id = g.id
        LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
        ORDER BY ge.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
