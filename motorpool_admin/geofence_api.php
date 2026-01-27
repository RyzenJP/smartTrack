<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php'; // This provides $conn

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'save_geofence':
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if this is a pin-to-pin rectangle (new approach)
            if (isset($data['polygon']) && is_array($data['polygon']) && count($data['polygon']) === 4) {
                // Pin-to-pin rectangle approach
                $stmt = $pdo->prepare("
                    INSERT INTO geofences 
                    (name, latitude, longitude, width, height, rotation, color, type, polygon, radius) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    width = VALUES(width),
                    height = VALUES(height),
                    rotation = VALUES(rotation),
                    color = VALUES(color),
                    type = VALUES(type),
                    polygon = VALUES(polygon),
                    radius = VALUES(radius),
                    updated_at = CURRENT_TIMESTAMP
                ");
                
                // Calculate radius as the average of width and height for rectangle geofences
                $radius = isset($data['width']) && isset($data['height']) ? 
                    round(($data['width'] + $data['height']) / 2) : 500;
                
                $stmt->execute([
                    $data['name'] ?? 'Unnamed Rectangle Geofence',
                    $data['latitude'],
                    $data['longitude'],
                    $data['width'],
                    $data['height'],
                    $data['rotation'] ?? 0,
                    $data['color'] ?? '#00b4d8',
                    $data['type'] ?? 'rectangle',
                    json_encode($data['polygon']), // Save the 4 pin coordinates as JSON
                    $radius
                ]);

                $geofenceId = $data['id'] ?? $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pin-to-pin rectangle geofence saved successfully',
                    'id' => $geofenceId
                ]);
            } 
            // Check if this is a square/rectangle geofence with width/height (previous approach)
            else if (isset($data['width']) && isset($data['height'])) {
                // Square/Rectangle approach
                $stmt = $pdo->prepare("
                    INSERT INTO geofences 
                    (name, latitude, longitude, width, height, rotation, color, type, radius, polygon) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    width = VALUES(width),
                    height = VALUES(height),
                    rotation = VALUES(rotation),
                    color = VALUES(color),
                    type = VALUES(type),
                    radius = VALUES(radius),
                    polygon = VALUES(polygon),
                    updated_at = CURRENT_TIMESTAMP
                ");
                
                // Calculate radius as the average of width and height
                $radius = round(($data['width'] + $data['height']) / 2);
                
                $stmt->execute([
                    $data['name'] ?? 'Unnamed Square Geofence',
                    $data['latitude'],
                    $data['longitude'],
                    $data['width'],
                    $data['height'],
                    $data['rotation'] ?? 0,
                    $data['color'] ?? '#00b4d8',
                    $data['type'] ?? 'square',
                    $radius,
                    '[]' // Add empty polygon for regular rectangles
                ]);

                $geofenceId = $data['id'] ?? $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Square geofence saved successfully',
                    'id' => $geofenceId
                ]);
            } 
            // Check if this is a polygon geofence (existing approach)
            else if (isset($data['polygon']) && is_array($data['polygon']) && count($data['polygon']) >= 3) {
                $stmt = $pdo->prepare("
                    INSERT INTO geofences 
                    (name, polygon, color, radius) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['name'] ?? 'Unnamed Geofence',
                    json_encode($data['polygon']), // Save polygon coordinates as JSON
                    $data['color'] ?? '#00b4d8',
                    $data['radius'] ?? 500 // Default radius for polygon geofences
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Polygon geofence saved successfully',
                    'id' => $pdo->lastInsertId()
                ]);
            } 
            // Check if this is a circle geofence
            else if (isset($data['latitude']) && isset($data['longitude']) && isset($data['radius']) && $data['type'] === 'circle') {
                $stmt = $pdo->prepare("
                    INSERT INTO geofences 
                    (name, latitude, longitude, radius, color, type, polygon) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    radius = VALUES(radius),
                    color = VALUES(color),
                    type = VALUES(type),
                    polygon = VALUES(polygon),
                    updated_at = CURRENT_TIMESTAMP
                ");
                
                $stmt->execute([
                    $data['name'] ?? 'Unnamed Circle Geofence',
                    $data['latitude'],
                    $data['longitude'],
                    $data['radius'],
                    $data['color'] ?? '#00b4d8',
                    'circle',
                    '[]' // Circles don't need polygon data, use empty JSON array
                ]);

                $geofenceId = $data['id'] ?? $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Circle geofence saved successfully',
                    'id' => $geofenceId
                ]);
            } else {
                throw new Exception('Invalid geofence data. Either polygon coordinates (4 points for rectangle, 3+ for polygon), width/height for rectangles, or latitude/longitude/radius for circles are required.');
            }
            break;

        case 'get_geofences':
            $stmt = $pdo->query("SELECT * FROM geofences ORDER BY created_at DESC");
            $geofences = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process each geofence
            foreach ($geofences as &$g) {
                // Decode polygon JSON if it exists
                if (!empty($g['polygon'])) {
                    $g['polygon'] = json_decode($g['polygon'], true);
                }
                
                // Ensure all fields are present for frontend compatibility
                $g['width'] = $g['width'] ?? null;
                $g['height'] = $g['height'] ?? null;
                $g['rotation'] = $g['rotation'] ?? 0;
                $g['type'] = $g['type'] ?? 'circle';
            }

            echo json_encode(['success' => true, 'data' => $geofences]);
            break;

        case 'delete_geofence':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Geofence ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM geofences WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Geofence deleted successfully'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
