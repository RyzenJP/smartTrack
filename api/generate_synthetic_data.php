<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once '../config/database.php';

class SyntheticDataGenerator {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function generateSyntheticData($numVehicles = 10, $maxKm = 100000) {
        try {
            // Get existing vehicles
            $vehicles = $this->getExistingVehicles($numVehicles);
            
            if (empty($vehicles)) {
                return [
                    'success' => false,
                    'message' => 'No vehicles found in database'
                ];
            }
            
            $totalMaintenanceRecords = 0;
            $totalGpsRecords = 0;
            
            foreach ($vehicles as $vehicle) {
                $result = $this->generateVehicleData($vehicle, $maxKm);
                if ($result['success']) {
                    $totalMaintenanceRecords += $result['maintenance_records'];
                    $totalGpsRecords += $result['gps_records'];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Synthetic data generated successfully',
                'data' => [
                    'vehicles_processed' => count($vehicles),
                    'total_maintenance_records' => $totalMaintenanceRecords,
                    'total_gps_records' => $totalGpsRecords
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating synthetic data: ' . $e->getMessage()
            ];
        }
    }
    
    private function getExistingVehicles($limit) {
        $query = "SELECT id, article, plate_number, created_at FROM fleet_vehicles WHERE status = 'active' LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $vehicles = [];
        while ($row = $result->fetch_assoc()) {
            $vehicles[] = $row;
        }
        
        return $vehicles;
    }
    
    private function generateVehicleData($vehicle, $maxKm) {
        // Calculate realistic total kilometers based on vehicle age
        $vehicleAge = (time() - strtotime($vehicle['created_at'])) / (24 * 60 * 60); // days
        $totalKm = min(rand(5000, $maxKm), $vehicleAge * 100); // Realistic km based on age
        
        // Get device ID for this vehicle
        $deviceId = $this->getDeviceId($vehicle['id']);
        if (!$deviceId) {
            return ['success' => false, 'message' => 'No GPS device found for vehicle ' . $vehicle['id']];
        }
        
        // Generate maintenance history
        $maintenanceRecords = $this->generateMaintenanceHistory($vehicle['id'], $vehicle['created_at'], $totalKm);
        
        // Generate GPS logs
        $gpsRecords = $this->generateGpsLogs($deviceId, $vehicle['created_at'], $totalKm);
        
        // Insert data
        $maintenanceInserted = $this->insertMaintenanceRecords($maintenanceRecords);
        $gpsInserted = $this->insertGpsRecords($gpsRecords);
        
        return [
            'success' => $maintenanceInserted && $gpsInserted,
            'maintenance_records' => count($maintenanceRecords),
            'gps_records' => count($gpsRecords)
        ];
    }
    
    private function getDeviceId($vehicleId) {
        $query = "SELECT device_id FROM gps_devices WHERE vehicle_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $vehicleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['device_id'] : null;
    }
    
    private function generateMaintenanceHistory($vehicleId, $vehicleCreated, $totalKm) {
        $maintenanceSchedule = [
            5000 => ['tasks' => 'CHANGE OIL', 'months' => 3],
            10000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION', 'months' => 6],
            15000 => ['tasks' => 'CHANGE OIL', 'months' => 9],
            20000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION, WHEEL BALANCE, ALIGNMENT, BRAKE INSPECTION', 'months' => 12],
            25000 => ['tasks' => 'CHANGE OIL', 'months' => 15],
            30000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION', 'months' => 18],
            35000 => ['tasks' => 'CHANGE OIL', 'months' => 21],
            40000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION, WHEEL BALANCE, ALIGNMENT, BRAKE INSPECTION, COOLING SYSTEM', 'months' => 24],
            45000 => ['tasks' => 'CHANGE OIL, ENGINE TUNE UP', 'months' => 27],
            50000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION', 'months' => 30],
            55000 => ['tasks' => 'CHANGE OIL', 'months' => 33],
            60000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION, WHEEL BALANCE, ALIGNMENT, BRAKE INSPECTION', 'months' => 36],
            65000 => ['tasks' => 'CHANGE OIL', 'months' => 39],
            70000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION', 'months' => 42],
            75000 => ['tasks' => 'CHANGE OIL', 'months' => 45],
            80000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION, WHEEL BALANCE, ALIGNMENT, BRAKE INSPECTION, COOLING SYSTEM', 'months' => 48],
            85000 => ['tasks' => 'CHANGE OIL, ENGINE TUNE UP', 'months' => 51],
            90000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION', 'months' => 54],
            95000 => ['tasks' => 'CHANGE OIL', 'months' => 57],
            100000 => ['tasks' => 'CHANGE OIL, TIRE ROTATION, WHEEL BALANCE, ALIGNMENT, BRAKE INSPECTION', 'months' => 60]
        ];
        
        $records = [];
        $currentDate = strtotime($vehicleCreated);
        $currentKm = 0;
        
        while ($currentKm < $totalKm) {
            // Find next maintenance milestone
            $nextMilestone = null;
            foreach (array_keys($maintenanceSchedule) as $km) {
                if ($currentKm < $km) {
                    $nextMilestone = $km;
                    break;
                }
            }
            
            if (!$nextMilestone) break;
            
            // Calculate when this maintenance should occur
            $kmToNext = $nextMilestone - $currentKm;
            $daysToNext = intval($kmToNext / 50); // Assume 50 km per day average
            
            // Add some randomness
            $daysVariation = rand(80, 120) / 100; // 20% variation
            $actualDays = intval($daysToNext * $daysVariation);
            
            // Ensure we don't go beyond current date
            $maintenanceDate = $currentDate + ($actualDays * 24 * 60 * 60);
            if ($maintenanceDate > time()) break;
            
            $maintenanceInfo = $maintenanceSchedule[$nextMilestone];
            
            $records[] = [
                'vehicle_id' => $vehicleId,
                'maintenance_type' => $maintenanceInfo['tasks'],
                'scheduled_date' => date('Y-m-d', $maintenanceDate),
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'status' => 'completed',
                'notes' => "Synthetic maintenance at {$nextMilestone} km milestone",
                'assigned_mechanic' => rand(1, 3),
                'created_at' => date('Y-m-d H:i:s', $maintenanceDate)
            ];
            
            $currentDate = $maintenanceDate;
            $currentKm = $nextMilestone;
        }
        
        return $records;
    }
    
    private function generateGpsLogs($deviceId, $vehicleCreated, $totalKm) {
        $records = [];
        $currentDate = strtotime($vehicleCreated);
        $currentKm = 0;
        
        while ($currentKm < $totalKm && $currentDate < time()) {
            // Daily usage varies
            $dailyKm = rand(30, 80); // 30-80 km per day
            
            // Generate GPS points for this day
            $gpsPointsToday = rand(10, 50); // 10-50 GPS points per day
            
            for ($i = 0; $i < $gpsPointsToday; $i++) {
                // Simulate GPS coordinates (Philippines area)
                $lat = 14.5995 + (rand(-100, 100) / 1000); // Manila area
                $lng = 120.9842 + (rand(-100, 100) / 1000);
                
                // Add some movement simulation
                $lat += (rand(-1, 1) / 1000);
                $lng += (rand(-1, 1) / 1000);
                
                // Generate timestamp within the day
                $hour = rand(6, 20); // 6 AM to 8 PM
                $minute = rand(0, 59);
                $second = rand(0, 59);
                
                $timestamp = $currentDate + ($hour * 3600) + ($minute * 60) + $second;
                
                $records[] = [
                    'device_id' => $deviceId,
                    'latitude' => round($lat, 6),
                    'longitude' => round($lng, 6),
                    'timestamp' => date('Y-m-d H:i:s', $timestamp)
                ];
            }
            
            $currentDate += 24 * 60 * 60; // Next day
            $currentKm += $dailyKm;
        }
        
        return $records;
    }
    
    private function insertMaintenanceRecords($records) {
        if (empty($records)) return true;
        
        $query = "INSERT INTO maintenance_schedules 
                  (vehicle_id, maintenance_type, scheduled_date, start_time, end_time, 
                   status, notes, assigned_mechanic, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($records as $record) {
            $stmt->bind_param('issssssis', 
                $record['vehicle_id'], $record['maintenance_type'], 
                $record['scheduled_date'], $record['start_time'], $record['end_time'],
                $record['status'], $record['notes'], $record['assigned_mechanic'], 
                $record['created_at']
            );
            $stmt->execute();
        }
        
        return true;
    }
    
    private function insertGpsRecords($records) {
        if (empty($records)) return true;
        
        $query = "INSERT INTO gps_logs 
                  (device_id, latitude, longitude, timestamp)
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($records as $record) {
            $stmt->bind_param('sdds', 
                $record['device_id'], $record['latitude'], $record['longitude'],
                $record['timestamp']
            );
            $stmt->execute();
        }
        
        return true;
    }
}

// Handle the request
$action = $_GET['action'] ?? 'generate';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $generator = new SyntheticDataGenerator($conn);
    
    switch ($action) {
        case 'generate':
            $numVehicles = intval($_POST['num_vehicles'] ?? 10);
            $maxKm = intval($_POST['max_km'] ?? 100000);
            
            $result = $generator->generateSyntheticData($numVehicles, $maxKm);
            echo json_encode($result);
            break;
            
        case 'status':
            // Get current data statistics - use prepared statement for consistency
            $stmt = $conn->prepare("SELECT 
                        COUNT(DISTINCT v.id) as total_vehicles,
                        COUNT(ms.id) as total_maintenance_records,
                        COUNT(gl.id) as total_gps_records
                      FROM fleet_vehicles v
                      LEFT JOIN maintenance_schedules ms ON v.id = ms.vehicle_id
                      LEFT JOIN gps_devices gd ON v.id = gd.vehicle_id
                      LEFT JOIN gps_logs gl ON gd.device_id = gl.device_id
                      WHERE v.status = 'active'");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
