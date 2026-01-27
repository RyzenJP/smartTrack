<?php
header("Content-Type: application/json");

// Secure CORS configuration
require_once __DIR__ . '/includes/cors_helper.php';
setCORSHeaders(false); // GPS receiver doesn't need credentials

// Ensure timestamps use Philippine time
date_default_timezone_set('Asia/Manila');

require_once 'db_connection.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB connection failed"]));
}

// Read incoming payload (JSON preferred, fallback to POST/GET)
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = [];
}

// Fallbacks when JSON body is empty
if (!isset($data['device_id']) && isset($_POST['device_id'])) {
    $data['device_id'] = $_POST['device_id'];
}
if (!isset($data['lat']) && isset($_POST['lat'])) {
    $data['lat'] = $_POST['lat'];
}
if (!isset($data['lng']) && isset($_POST['lng'])) {
    $data['lng'] = $_POST['lng'];
}
if (!isset($data['speed']) && isset($_POST['speed'])) {
    $data['speed'] = $_POST['speed'];
}

// Final fallback to GET
if (!isset($data['device_id']) && isset($_GET['device_id'])) {
    $data['device_id'] = $_GET['device_id'];
}
if (!isset($data['lat']) && isset($_GET['lat'])) {
    $data['lat'] = $_GET['lat'];
}
if (!isset($data['lng']) && isset($_GET['lng'])) {
    $data['lng'] = $_GET['lng'];
}
if (!isset($data['speed']) && isset($_GET['speed'])) {
    $data['speed'] = $_GET['speed'];
}

if (!isset($data['device_id']) || !isset($data['lat']) || !isset($data['lng'])) {
    error_log('GPS_RECEIVER: Missing data. Raw=' . substr($raw ?? '', 0, 200) . ' POST=' . json_encode($_POST) . ' GET=' . json_encode($_GET));
    die(json_encode(["status" => "error", "message" => "Missing data"]));
}

$device_id = $conn->real_escape_string($data['device_id']);
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);
$speed = isset($data['speed']) ? floatval($data['speed']) : 0.0;

// ✅ Step 1: Update latest location and speed in `gps_devices`
$update = $conn->prepare("UPDATE gps_devices SET lat = ?, lng = ?, speed = ?, last_update = NOW(), updated_at = NOW() WHERE device_id = ?");
$update->bind_param("ddds", $lat, $lng, $speed, $device_id);
$update->execute();
$update->close();

// ✅ Step 1.5: Also update the corresponding vehicle's location in `fleet_vehicles` table
// Use the same direct approach as homepage.php for accuracy
$vehicleUpdate = $conn->prepare("UPDATE fleet_vehicles fv 
    INNER JOIN gps_devices gd ON fv.id = gd.vehicle_id 
    SET fv.current_latitude = ?, fv.current_longitude = ?, fv.last_updated = NOW() 
    WHERE gd.device_id = ?");
$vehicleUpdate->bind_param("dds", $lat, $lng, $device_id);
$vehicleUpdate->execute();
$vehicleUpdate->close();

// ✅ Step 2: Only insert to `gps_logs` if movement is significant
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

$shouldInsert = true;
// Use prepared statement for security (device_id comes from user input)
$lastLogStmt = $conn->prepare("SELECT latitude, longitude, timestamp FROM gps_logs WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1");
$lastLogStmt->bind_param("s", $device_id);
$lastLogStmt->execute();
$lastLogRes = $lastLogStmt->get_result();
if ($lastLogRes && $lastLog = $lastLogRes->fetch_assoc()) {
    $lastLat = $lastLog['latitude'];
    $lastLng = $lastLog['longitude'];
    $lastTime = strtotime($lastLog['timestamp']);
    $now = time();

    $distance = haversineDistance($lat, $lng, $lastLat, $lastLng);
    // Reduced threshold: log if moved more than 5 meters OR more than 60 seconds have passed
    if ($distance < 5 && ($now - $lastTime) < 60) {
        $shouldInsert = false;
    }
}

if ($shouldInsert) {
    $log = $conn->prepare("INSERT INTO gps_logs (device_id, latitude, longitude, timestamp) VALUES (?, ?, ?, NOW())");
    $log->bind_param("sdd", $device_id, $lat, $lng);
    $log->execute();
    $log->close();
}

// ✅ Step 3: Check for geofence events (entry/exit) - with timeout protection
$geofenceStartTime = microtime(true);

function pointInPolygon($lat, $lng, $polygon) {
    $inside = false;
    $j = count($polygon) - 1;
    
    for ($i = 0; $i < count($polygon); $i++) {
        $xi = $polygon[$i][0]; // latitude
        $yi = $polygon[$i][1]; // longitude
        $xj = $polygon[$j][0]; // latitude
        $yj = $polygon[$j][1]; // longitude
        
        if ((($yi > $lng) != ($yj > $lng)) && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
        $j = $i;
    }
    return $inside;
}

function pointInCircle($lat, $lng, $centerLat, $centerLng, $radius) {
    $distance = haversineDistance($lat, $lng, $centerLat, $centerLng);
    return $distance <= $radius;
}

// Get all active geofences - use prepared statement for consistency
$geofences_stmt = $conn->prepare("SELECT * FROM geofences WHERE status = 'active'");
$geofences_stmt->execute();
$geofencesRes = $geofences_stmt->get_result();
if ($geofencesRes) {
    $geofenceCount = 0;
    while ($geofence = $geofencesRes->fetch_assoc()) {
        $geofenceCount++;
        $geofenceId = $geofence['id'];
        $geofenceName = $geofence['name'];
        $geofenceType = $geofence['type'];
        
        // Check if point is inside geofence
        $isInside = false;
        
        if ($geofenceType === 'circle') {
            $isInside = pointInCircle($lat, $lng, $geofence['latitude'], $geofence['longitude'], $geofence['radius']);
            error_log("GEOFENCE DEBUG: Circle '$geofenceName' - Point ($lat, $lng) vs Center ({$geofence['latitude']}, {$geofence['longitude']}) Radius {$geofence['radius']}m - Inside: " . ($isInside ? 'YES' : 'NO'));
        } else {
            // For polygon, rectangle, square
            $polygon = json_decode($geofence['polygon'], true);
            if ($polygon && is_array($polygon)) {
                $isInside = pointInPolygon($lat, $lng, $polygon);
                error_log("GEOFENCE DEBUG: Polygon '$geofenceName' - Point ($lat, $lng) vs Polygon with " . count($polygon) . " points - Inside: " . ($isInside ? 'YES' : 'NO'));
            } else {
                error_log("GEOFENCE DEBUG: Polygon '$geofenceName' - Invalid polygon data");
            }
        }
        
        // Check previous status - use prepared statement for security
        $lastEvent_stmt = $conn->prepare("SELECT event_type FROM geofence_events 
            WHERE device_id = ? AND geofence_id = ? 
            ORDER BY created_at DESC LIMIT 1");
        $lastEvent_stmt->bind_param("si", $device_id, $geofenceId);
        $lastEvent_stmt->execute();
        $lastEventRes = $lastEvent_stmt->get_result();
        
        $lastEventType = null;
        if ($lastEventRes && $lastEvent = $lastEventRes->fetch_assoc()) {
            $lastEventType = $lastEvent['event_type'];
        }
        $lastEvent_stmt->close();
        
        // Determine if we need to create a new event
        $newEventType = null;
        if ($isInside && $lastEventType !== 'entry') {
            $newEventType = 'entry';
        } elseif (!$isInside && $lastEventType === 'entry') {
            $newEventType = 'exit';
        }
        
        // Create geofence event if needed
        if ($newEventType) {
            // Get vehicle info - use prepared statement for security
            $vehicle_stmt = $conn->prepare("SELECT gd.vehicle_id, fv.article, fv.plate_number 
                FROM gps_devices gd 
                LEFT JOIN fleet_vehicles fv ON gd.vehicle_id = fv.id 
                WHERE gd.device_id = ?");
            $vehicle_stmt->bind_param("s", $device_id);
            $vehicle_stmt->execute();
            $vehicleRes = $vehicle_stmt->get_result();
            
            $vehicleId = null;
            if ($vehicleRes && $vehicle = $vehicleRes->fetch_assoc()) {
                $vehicleId = $vehicle['vehicle_id']; // Use gd.vehicle_id directly
                error_log("GEOFENCE: Found vehicle_id = $vehicleId for device $device_id");
            } else {
                error_log("GEOFENCE: No vehicle found for device $device_id");
            }
            
            // Get driver_id if available - use prepared statement for security
            $driverId = null;
            if ($vehicleId) {
                $driver_stmt = $conn->prepare("SELECT driver_id FROM vehicle_assignments 
                    WHERE vehicle_id = ? AND status = 'active' 
                    ORDER BY id DESC LIMIT 1");
                $driver_stmt->bind_param("i", $vehicleId);
                $driver_stmt->execute();
                $driverRes = $driver_stmt->get_result();
                if ($driverRes && $driver = $driverRes->fetch_assoc()) {
                    $driverId = $driver['driver_id'];
                }
                $driver_stmt->close();
            }
            
            // Insert geofence event with driver_id if available
            $eventStmt = $conn->prepare("INSERT INTO geofence_events 
                (device_id, vehicle_id, driver_id, geofence_id, event_type, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())");
            $eventStmt->bind_param("siiss", $device_id, $vehicleId, $driverId, $geofenceId, $newEventType);
            $insertResult = $eventStmt->execute();
            $eventStmt->close();
            
            if ($insertResult) {
                error_log("GEOFENCE EVENT: Successfully inserted - Device $device_id {$newEventType} geofence '$geofenceName' (ID: $geofenceId, Vehicle: $vehicleId, Driver: " . ($driverId ?? 'NULL') . ")");
            } else {
                error_log("GEOFENCE EVENT ERROR: Failed to insert event - " . $conn->error);
            }
            
            // Send alert via geofence_alert_api.php (this will also insert, but we'll handle duplicates)
            $alertData = [
                'device_id' => $device_id,
                'geofence_id' => $geofenceId,
                'event_type' => $newEventType,
                'vehicle_data' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'speed' => $speed
                ]
            ];
            
            // Send POST request to geofence alert API
            $alertUrl = BASE_URL . 'geofence_alert_api.php';
            $alertJson = json_encode($alertData);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $alertUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $alertJson);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            $alertResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("GEOFENCE ALERT API ERROR: CURL failed - $curlError");
            } else {
                error_log("GEOFENCE ALERT API: Response received - " . substr($alertResponse, 0, 100));
            }
        }
    }
    $geofences_stmt->close();
    $geofenceEndTime = microtime(true);
    $geofenceTime = ($geofenceEndTime - $geofenceStartTime) * 1000; // Convert to milliseconds
    error_log("GEOFENCE: Checked $geofenceCount active geofences for device $device_id in {$geofenceTime}ms");
} else {
    error_log("GEOFENCE: Error querying geofences: " . $conn->error);
}

// Log the update for debugging
error_log("GPS Update: Device $device_id -> Lat: $lat, Lng: $lng, Speed: $speed (Direct update like homepage.php)");

$conn->close();
echo json_encode(["status" => "success", "message" => "Location updated successfully"]);
?>
