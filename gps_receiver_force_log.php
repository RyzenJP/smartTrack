<?php
// Force GPS logging version - logs every GPS update
header("Content-Type: application/json");
require_once __DIR__ . '/includes/cors_helper.php';
setCORSHeaders(true);
date_default_timezone_set('Asia/Manila');

require_once 'db_connection.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB connection failed"]));
}

// Read incoming JSON with GET/POST fallbacks
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) { $data = []; }

// POST fallbacks
if (!isset($data['device_id']) && isset($_POST['device_id'])) { $data['device_id'] = $_POST['device_id']; }
if (!isset($data['lat']) && isset($_POST['lat'])) { $data['lat'] = $_POST['lat']; }
if (!isset($data['lng']) && isset($_POST['lng'])) { $data['lng'] = $_POST['lng']; }
if (!isset($data['speed']) && isset($_POST['speed'])) { $data['speed'] = $_POST['speed']; }

// GET fallbacks
if (!isset($data['device_id']) && isset($_GET['device_id'])) { $data['device_id'] = $_GET['device_id']; }
if (!isset($data['lat']) && isset($_GET['lat'])) { $data['lat'] = $_GET['lat']; }
if (!isset($data['lng']) && isset($_GET['lng'])) { $data['lng'] = $_GET['lng']; }
if (!isset($data['speed']) && isset($_GET['speed'])) { $data['speed'] = $_GET['speed']; }

if (!isset($data['device_id']) || !isset($data['lat']) || !isset($data['lng'])) {
    error_log('GPS_FORCE_LOG: Missing data. Raw=' . substr($raw ?? '', 0, 200) . ' POST=' . json_encode($_POST) . ' GET=' . json_encode($_GET));
    die(json_encode(["status" => "error", "message" => "Missing data"]));
}

$device_id = $conn->real_escape_string($data['device_id']);
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);
$speed = isset($data['speed']) ? floatval($data['speed']) : 0.0;

// Update latest location in gps_devices
$update = $conn->prepare("UPDATE gps_devices SET lat = ?, lng = ?, speed = ?, last_update = NOW(), updated_at = NOW() WHERE device_id = ?");
$update->bind_param("ddds", $lat, $lng, $speed, $device_id);
$update->execute();
$update->close();

// Update vehicle location
$vehicleUpdate = $conn->prepare("UPDATE fleet_vehicles fv 
    INNER JOIN gps_devices gd ON fv.id = gd.vehicle_id 
    SET fv.current_latitude = ?, fv.current_longitude = ?, fv.last_updated = NOW() 
    WHERE gd.device_id = ?");
$vehicleUpdate->bind_param("dds", $lat, $lng, $device_id);
$vehicleUpdate->execute();
$vehicleUpdate->close();

// FORCE INSERT GPS LOG - Always log every GPS update (use NOW with timezone)
$log = $conn->prepare("INSERT INTO gps_logs (device_id, latitude, longitude, timestamp) VALUES (?, ?, ?, NOW())");
$log->bind_param("sdd", $device_id, $lat, $lng);
$log->execute();
$log->close();

// Log the update
error_log("GPS FORCE LOG: Device $device_id -> Lat: $lat, Lng: $lng, Speed: $speed");

$conn->close();
echo json_encode(["status" => "success", "message" => "Location updated and logged successfully"]);
?>
