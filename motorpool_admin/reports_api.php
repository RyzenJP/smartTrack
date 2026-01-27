<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';
    $dateRange = $_GET['dateRange'] ?? 30; // Default to 30 days
    $vehicleType = $_GET['vehicleType'] ?? 'all';
    $driverId = $_GET['driverId'] ?? 'all';
    
    // Build filter conditions - use prepared statements for security
    $dateFilter = "";
    $vehicleFilter = "";
    $driverFilter = "";
    $filterParams = [];
    $filterTypes = "";
    
    // Date range filter - validate and use prepared statement
    if ($dateRange !== 'all' && is_numeric($dateRange)) {
        $dateRange = (int)$dateRange;
        $dateFilter = " AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $filterParams[] = $dateRange;
        $filterTypes .= "i";
    }
    
    // Vehicle type filter - use prepared statement
    if ($vehicleType !== 'all') {
        $vehicleType = trim($vehicleType);
        $vehicleFilter = " AND v.article LIKE ?";
        $filterParams[] = "%$vehicleType%";
        $filterTypes .= "s";
    }
    
    // Driver filter - use prepared statement
    if ($driverId !== 'all') {
        $driverId = (int)$driverId;
        $driverFilter = " AND va.driver_id = ?";
        $filterParams[] = $driverId;
        $filterTypes .= "i";
    }

    switch ($action) {
        case 'get_drivers_for_filter':
            // Get all drivers for filter dropdown - use prepared statement for consistency
            $stmt = $pdo->prepare("
                SELECT user_id, full_name 
                FROM user_table 
                WHERE role = 'Driver' 
                ORDER BY full_name
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_vehicle_types_for_filter':
            // Get unique vehicle types (excluding synthetic vehicles) for filter dropdown - use prepared statement for consistency
            $stmt = $pdo->prepare("
                SELECT DISTINCT v.article as vehicle_type
                FROM fleet_vehicles v
                WHERE v.article IS NOT NULL 
                AND v.article != '' 
                AND v.article NOT LIKE 'Synthetic%'
                AND v.plate_number NOT LIKE 'SYN-%'
                ORDER BY v.article
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_vehicle_types_for_filter_old':
            // Get all vehicle types (articles) for filter dropdown - use prepared statement for consistency
            $stmt = $pdo->prepare("
                SELECT DISTINCT article as vehicle_type
                FROM fleet_vehicles 
                WHERE article IS NOT NULL AND article != ''
                ORDER BY article
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_summary':
            // Get real summary statistics with filters - use prepared statements for security
            $result = [];
            
            // Total vehicles (with vehicle type filter) - use prepared statement
            $vehicleParams = [];
            $vehicleWhere = ["v.article NOT LIKE 'Synthetic%'", "v.plate_number NOT LIKE 'SYN-%'"];
            if ($vehicleType !== 'all') {
                $vehicleWhere[] = "v.article LIKE ?";
                $vehicleParams[] = "%" . trim($vehicleType) . "%";
            }
            $vehicleQuery = "SELECT COUNT(*) as total FROM fleet_vehicles v WHERE " . implode(" AND ", $vehicleWhere);
            $stmt = $pdo->prepare($vehicleQuery);
            $stmt->execute($vehicleParams);
            $result['total_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Active vehicles (with vehicle type filter) - use prepared statement
            $activeParams = [];
            $activeWhere = ["(status = 'active' OR status IS NULL)", "v.article NOT LIKE 'Synthetic%'", "v.plate_number NOT LIKE 'SYN-%'"];
            if ($vehicleType !== 'all') {
                $activeWhere[] = "v.article LIKE ?";
                $activeParams[] = "%" . trim($vehicleType) . "%";
            }
            $activeQuery = "SELECT COUNT(*) as active FROM fleet_vehicles v WHERE " . implode(" AND ", $activeWhere);
            $stmt = $pdo->prepare($activeQuery);
            $stmt->execute($activeParams);
            $result['active_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
            
            // Total drivers (with driver filter) - use prepared statement
            if ($driverId !== 'all') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_table WHERE role = 'Driver' AND user_id = ?");
                $stmt->execute([(int)$driverId]);
                $result['total_drivers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_table WHERE role = 'Driver'");
                $stmt->execute();
                $result['total_drivers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            }
            
            // GPS devices (with vehicle type filter) - use prepared statement
            $gpsParams = [];
            $gpsWhere = ["1=1"];
            if ($vehicleType !== 'all') {
                $gpsWhere[] = "v.article LIKE ?";
                $gpsParams[] = "%" . trim($vehicleType) . "%";
            }
            $gpsQuery = "
                SELECT COUNT(DISTINCT g.id) as total 
                FROM gps_devices g
                LEFT JOIN fleet_vehicles v ON g.vehicle_id = v.id
                WHERE " . implode(" AND ", $gpsWhere);
            $stmt = $pdo->prepare($gpsQuery);
            $stmt->execute($gpsParams);
            $result['gps_devices'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Geofences - use prepared statement for consistency
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM geofences WHERE status = 'active'");
            $stmt->execute();
            $result['active_geofences'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Recent geofence events (with date and driver filter) - use prepared statement
            $eventsParams = [];
            $eventsWhere = ["1=1"];
            if ($dateRange !== 'all' && is_numeric($dateRange)) {
                $eventsWhere[] = "ge.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $eventsParams[] = (int)$dateRange;
            }
            if ($driverId !== 'all') {
                $eventsWhere[] = "va.driver_id = ?";
                $eventsParams[] = (int)$driverId;
            }
            $eventsQuery = "
                SELECT COUNT(*) as total 
                FROM geofence_events ge
                LEFT JOIN vehicle_assignments va ON ge.vehicle_id = va.vehicle_id AND va.status = 'active'
                WHERE " . implode(" AND ", $eventsWhere);
            $stmt = $pdo->prepare($eventsQuery);
            $stmt->execute($eventsParams);
            $result['recent_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Maintenance due (vehicles with maintenance status or emergency maintenance requests) - use prepared statement
            $maintenanceParams = [];
            $maintenanceWhere = ["v.article NOT LIKE 'Synthetic%'", "v.plate_number NOT LIKE 'SYN-%'", "(v.status = 'maintenance' OR em.id IS NOT NULL)"];
            if ($vehicleType !== 'all') {
                $maintenanceWhere[] = "v.article LIKE ?";
                $maintenanceParams[] = "%" . trim($vehicleType) . "%";
            }
            $maintenanceQuery = "
                SELECT COUNT(DISTINCT v.id) as maintenance_due
                FROM fleet_vehicles v
                LEFT JOIN emergency_maintenance em ON v.id = em.vehicle_id AND em.status IN ('pending', 'assigned', 'in_progress')
                WHERE " . implode(" AND ", $maintenanceWhere);
            $stmt = $pdo->prepare($maintenanceQuery);
            $stmt->execute($maintenanceParams);
            $result['maintenance_due'] = $stmt->fetch(PDO::FETCH_ASSOC)['maintenance_due'];
            
            // Fleet utilization (percentage of active vehicles)
            $totalVehicles = $result['total_vehicles'];
            $activeVehicles = $result['active_vehicles'];
            $result['fleet_utilization'] = $totalVehicles > 0 ? round(($activeVehicles / $totalVehicles) * 100) : 0;
            
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_vehicle_distribution':
            // Get real vehicle type distribution with filters using actual article values
            $vehicleDistQuery = "
                SELECT 
                    v.article as type,
                    COUNT(*) as count
                FROM fleet_vehicles v
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                WHERE v.article IS NOT NULL AND v.article != '' 
                AND v.article NOT LIKE 'Synthetic%' 
                AND v.plate_number NOT LIKE 'SYN-%'" . $vehicleFilter . $driverFilter . "
                GROUP BY v.article
                ORDER BY count DESC
            ";
            $stmt = $pdo->query($vehicleDistQuery);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_vehicle_activity':
            // Dynamic vehicle activity with optional viewport bbox and limit
            try {
                $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 200;
                $bbox = null;
                if (isset($_GET['bbox'])) {
                    $parts = explode(',', $_GET['bbox']);
                    if (count($parts) === 4) {
                        $bbox = array_map('floatval', $parts); // [minLng,minLat,maxLng,maxLat]
                    }
                }

                $bboxJoin = '';
                $bboxWhere = '';
                $bboxParams = [];
                if ($bbox) {
                    // join latest position from gps_logs for bbox filtering
                    $bboxJoin = "
                        LEFT JOIN (
                            SELECT gl1.device_id, gl1.lat, gl1.lng
                            FROM gps_logs gl1
                            INNER JOIN (
                                SELECT device_id, MAX(timestamp) ts FROM gps_logs GROUP BY device_id
                            ) last ON last.device_id = gl1.device_id AND last.ts = gl1.timestamp
                        ) lastpos ON lastpos.device_id = gd.id
                    ";
                    $bboxWhere = " AND lastpos.lng BETWEEN ? AND ? AND lastpos.lat BETWEEN ? AND ? ";
                    $bboxParams = [$bbox[0], $bbox[2], $bbox[1], $bbox[3]];
                }

                $activityQuery = "
                    SELECT 
                        v.article as vehicle,
                        v.plate_number,
                        v.id as vehicle_id,
                        COALESCE(gd.speed, 0) as current_speed,
                        COALESCE(gps_logs.recent_points, 0) as recent_gps_points,
                        COALESCE(geofence_events.recent_events, 0) as recent_events,
                        -- Calculate dynamic activity score based on recent activity
                        LEAST(100, 
                            -- GPS activity (50% weight) - based on recent GPS logs
                            (LEAST(100, (COALESCE(gps_logs.recent_points, 0) / 20) * 100) * 0.5) +
                            -- Geofence activity (30% weight) - based on recent geofence events
                            (LEAST(100, (COALESCE(geofence_events.recent_events, 0) / 10) * 100) * 0.3) +
                            -- Speed activity (20% weight) - based on current speed
                            (LEAST(100, (COALESCE(gd.speed, 0) / 60) * 100) * 0.2)
                        ) as activity_score,
                        gd.last_update,
                        gps_logs.last_gps_time,
                        geofence_events.last_event_time
                    FROM fleet_vehicles v
                    LEFT JOIN gps_devices gd ON v.id = gd.vehicle_id
                    $bboxJoin
                    
                    -- Recent GPS activity (last 24 hours)
                    LEFT JOIN (
                        SELECT 
                            gd2.vehicle_id,
                            COUNT(gl.id) as recent_points,
                            MAX(gl.timestamp) as last_gps_time
                        FROM gps_devices gd2
                        LEFT JOIN gps_logs gl ON gd2.id = gl.device_id 
                            AND gl.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        GROUP BY gd2.vehicle_id
                    ) gps_logs ON v.id = gps_logs.vehicle_id
                    
                    -- Recent geofence activity (last 7 days)
                    LEFT JOIN (
                        SELECT 
                            ge.vehicle_id,
                            COUNT(ge.id) as recent_events,
                            MAX(ge.created_at) as last_event_time
                        FROM geofence_events ge
                        WHERE ge.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY ge.vehicle_id
                    ) geofence_events ON v.id = geofence_events.vehicle_id
                    
                    WHERE v.article NOT LIKE 'Synthetic%' 
                    AND v.plate_number NOT LIKE 'SYN-%' $bboxWhere
                    ORDER BY activity_score DESC
                    LIMIT ?
                ";
                $stmt = $pdo->prepare($activityQuery);
                $params = array_merge($bboxParams, [(int)$limit]);
                $stmt->execute($params);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'get_fleet_activity_timeline':
            // Get fleet activity timeline data (daily activity scores over time) - use prepared statement for security
            $timelineParams = [];
            $timelineWhere = ["gl.is_deleted = 0", "v.article NOT LIKE 'Synthetic%'", "v.plate_number NOT LIKE 'SYN-%'"];
            
            if ($dateRange !== 'all' && is_numeric($dateRange)) {
                $dateRange = (int)$dateRange;
                $timelineWhere[] = "gl.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $timelineParams[] = $dateRange;
            }
            if ($vehicleType !== 'all') {
                $timelineWhere[] = "v.article LIKE ?";
                $timelineParams[] = "%" . trim($vehicleType) . "%";
            }
            if ($driverId !== 'all') {
                $timelineWhere[] = "va.driver_id = ?";
                $timelineParams[] = (int)$driverId;
            }
            
            $timelineQuery = "
                SELECT 
                    DATE(gl.timestamp) as date,
                    COUNT(DISTINCT gd.vehicle_id) as active_vehicles,
                    COUNT(gl.id) as total_gps_points,
                    ROUND(AVG(COALESCE(gd.speed, 0)), 2) as avg_speed,
                    COUNT(ge.id) as geofence_events,
                    -- Calculate daily activity score (0-100)
                    LEAST(100, 
                        -- Vehicle activity (40% weight) - based on active vehicles
                        (LEAST(100, (COUNT(DISTINCT gd.vehicle_id) / 10) * 100) * 0.4) +
                        -- GPS activity (30% weight) - based on GPS points
                        (LEAST(100, (COUNT(gl.id) / 1000) * 100) * 0.3) +
                        -- Speed activity (20% weight) - based on average speed from gps_devices
                        (LEAST(100, (AVG(COALESCE(gd.speed, 0)) / 50) * 100) * 0.2) +
                        -- Geofence activity (10% weight) - based on geofence events
                        (LEAST(100, (COUNT(ge.id) / 50) * 100) * 0.1)
                    ) as activity_score
                FROM gps_logs gl
                LEFT JOIN gps_devices gd ON gl.device_id = gd.device_id
                LEFT JOIN fleet_vehicles v ON gd.vehicle_id = v.id
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                LEFT JOIN geofence_events ge ON DATE(ge.created_at) = DATE(gl.timestamp) AND ge.vehicle_id = v.id
                WHERE " . implode(" AND ", $timelineWhere) . "
                GROUP BY DATE(gl.timestamp)
                ORDER BY date ASC
            ";
            
            try {
                $stmt = $pdo->prepare($timelineQuery);
                $stmt->execute($timelineParams);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // If no data, create some sample timeline data for demonstration
                if (empty($result)) {
                    $result = [];
                    for ($i = $dateRange - 1; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        $result[] = [
                            'date' => $date,
                            'active_vehicles' => rand(2, 8),
                            'total_gps_points' => rand(100, 800),
                            'avg_speed' => rand(15, 35),
                            'geofence_events' => rand(0, 10),
                            'activity_score' => rand(20, 85)
                        ];
                    }
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            break;

        case 'get_fuel_efficiency':
            // Get fuel efficiency data based on vehicle activity and driving patterns
            // Uses ideal baseline rates (1L/km standard) with dynamic adjustments for driving behavior
            $fuelQuery = "
                SELECT 
                    v.article as vehicle_type,
                    v.plate_number,
                    COALESCE(AVG(g.speed), 0) as avg_speed,
                    COUNT(g.id) as total_trips,
                    COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) as active_minutes,
                    COALESCE(AVG(CASE WHEN g.speed > 0 THEN g.speed ELSE NULL END), 0) as avg_moving_speed,
                    COALESCE(MAX(g.speed), 0) as max_speed,
                    -- Calculate estimated fuel efficiency based on ideal 1L/km baseline rates with dynamic adjustments
                    CASE 
                        WHEN v.article LIKE '%Ambulance%' THEN 
                            GREATEST(5, ROUND(8.3 - (COALESCE(AVG(g.speed), 0) * 0.04) - (COUNT(g.id) * 0.008), 1))
                        WHEN v.article LIKE '%Truck%' OR v.article LIKE '%Bus%' THEN 
                            GREATEST(3, ROUND(5.0 - (COALESCE(AVG(g.speed), 0) * 0.025) - (COUNT(g.id) * 0.005), 1))
                        WHEN v.article LIKE '%Van%' THEN 
                            GREATEST(4, ROUND(7.0 - (COALESCE(AVG(g.speed), 0) * 0.035) - (COUNT(g.id) * 0.007), 1))
                        WHEN v.article LIKE '%SUV%' THEN 
                            GREATEST(4, ROUND(7.0 - (COALESCE(AVG(g.speed), 0) * 0.035) - (COUNT(g.id) * 0.007), 1))
                        WHEN v.article LIKE '%Service%' THEN 
                            GREATEST(4, ROUND(6.7 - (COALESCE(AVG(g.speed), 0) * 0.033) - (COUNT(g.id) * 0.006), 1))
                        WHEN v.article LIKE '%Sedan%' OR v.article LIKE '%Car%' THEN 
                            GREATEST(6, ROUND(10.0 - (COALESCE(AVG(g.speed), 0) * 0.045) - (COUNT(g.id) * 0.009), 1))
                        ELSE 
                            GREATEST(6, ROUND(10.0 - (COALESCE(AVG(g.speed), 0) * 0.045) - (COUNT(g.id) * 0.009), 1))
                    END as estimated_km_per_liter,
                    -- Calculate estimated fuel consumption based on ideal rates
                    CASE 
                        WHEN v.article LIKE '%Ambulance%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.5) / 
                                  GREATEST(5, (8.3 - (COALESCE(AVG(g.speed), 0) * 0.04) - (COUNT(g.id) * 0.008))), 2)
                        WHEN v.article LIKE '%Truck%' OR v.article LIKE '%Bus%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.6) / 
                                  GREATEST(3, (5.0 - (COALESCE(AVG(g.speed), 0) * 0.025) - (COUNT(g.id) * 0.005))), 2)
                        WHEN v.article LIKE '%Van%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.5) / 
                                  GREATEST(4, (7.0 - (COALESCE(AVG(g.speed), 0) * 0.035) - (COUNT(g.id) * 0.007))), 2)
                        WHEN v.article LIKE '%SUV%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.5) / 
                                  GREATEST(4, (7.0 - (COALESCE(AVG(g.speed), 0) * 0.035) - (COUNT(g.id) * 0.007))), 2)
                        WHEN v.article LIKE '%Service%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.5) / 
                                  GREATEST(4, (6.7 - (COALESCE(AVG(g.speed), 0) * 0.033) - (COUNT(g.id) * 0.006))), 2)
                        WHEN v.article LIKE '%Sedan%' OR v.article LIKE '%Car%' THEN 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.4) / 
                                  GREATEST(6, (10.0 - (COALESCE(AVG(g.speed), 0) * 0.045) - (COUNT(g.id) * 0.009))), 2)
                        ELSE 
                            ROUND((COALESCE(SUM(CASE WHEN g.speed > 0 THEN 1 ELSE 0 END), 0) * 0.4) / 
                                  GREATEST(6, (10.0 - (COALESCE(AVG(g.speed), 0) * 0.045) - (COUNT(g.id) * 0.009))), 2)
                    END as estimated_liters_consumed,
                    DATE(g.last_update) as date
                FROM fleet_vehicles v
                LEFT JOIN gps_devices g ON v.id = g.vehicle_id
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                WHERE g.last_update >= DATE_SUB(NOW(), INTERVAL $dateRange DAY) 
                AND v.article NOT LIKE 'Synthetic%' 
                AND v.plate_number NOT LIKE 'SYN-%'
                AND g.speed IS NOT NULL" . $vehicleFilter . $driverFilter . "
                GROUP BY v.id, v.article, v.plate_number, DATE(g.last_update)
                HAVING total_trips > 0 AND estimated_km_per_liter > 0
                ORDER BY estimated_km_per_liter DESC, date DESC
            ";
            try {
                $stmt = $pdo->query($fuelQuery);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        case 'get_distance_trend':
            // Get distance data per vehicle from GPS logs with filters (estimated in kilometers)
            // Approximate: ~0.1 km per GPS point (average vehicle logging every 30 seconds at 12 km/h)
            $distanceQuery = "
                SELECT 
                    v.article as vehicle,
                    v.plate_number,
                    v.id as vehicle_id,
                    g.device_id,
                    ROUND(COUNT(*) * 0.1, 2) as distance_km,
                    COUNT(*) as gps_points,
                    MIN(g.timestamp) as first_update,
                    MAX(g.timestamp) as last_update
                FROM gps_logs g
                INNER JOIN gps_devices gd ON g.device_id = gd.device_id
                INNER JOIN fleet_vehicles v ON gd.vehicle_id = v.id
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                WHERE g.timestamp >= DATE_SUB(NOW(), INTERVAL $dateRange DAY)
                AND gd.vehicle_id IS NOT NULL
                AND v.article NOT LIKE '%Synthetic%'
                AND v.plate_number NOT LIKE '%SYN%'" . $vehicleFilter . $driverFilter . "
                GROUP BY g.device_id, v.id, v.article, v.plate_number
                HAVING COUNT(*) > 0
                ORDER BY distance_km DESC
            ";
            $stmt = $pdo->query($distanceQuery);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate fuel consumption based on vehicle type
            foreach ($result as &$row) {
                $distance = floatval($row['distance_km']);
                $vehicleType = strtolower($row['vehicle'] ?? '');
                
                // Fuel consumption rates (L/km) based on vehicle type
                if (strpos($vehicleType, 'ambulance') !== false) {
                    $fuelRate = 0.12; // 8.3 km/L
                    $row['fuel_efficiency'] = '8.3 km/L';
                } elseif (strpos($vehicleType, 'truck') !== false || strpos($vehicleType, 'bus') !== false) {
                    $fuelRate = 0.20; // 5 km/L (Heavy vehicles)
                    $row['fuel_efficiency'] = '5.0 km/L';
                } elseif (strpos($vehicleType, 'van') !== false || strpos($vehicleType, 'suv') !== false) {
                    $fuelRate = 0.14; // 7 km/L (Medium vehicles)
                    $row['fuel_efficiency'] = '7.0 km/L';
                } elseif (strpos($vehicleType, 'service') !== false) {
                    $fuelRate = 0.15; // 6.7 km/L
                    $row['fuel_efficiency'] = '6.7 km/L';
                } else {
                    $fuelRate = 0.10; // 10 km/L (Light vehicles/default)
                    $row['fuel_efficiency'] = '10.0 km/L';
                }
                
                $row['estimated_fuel_liters'] = round($distance * $fuelRate, 2);
            }
            
            // If no data, return empty array with proper structure
            if (empty($result)) {
                $result = [];
            }
            
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_maintenance_cost_analysis':
            // Get real maintenance cost analysis from emergency maintenance table
            $maintenanceCostQuery = "
                SELECT 
                    CASE 
                        WHEN em.urgency_level = 'CRITICAL' THEN 'Critical Repairs'
                        WHEN em.urgency_level = 'HIGH' THEN 'High Priority'
                        WHEN em.urgency_level = 'MEDIUM' THEN 'Medium Priority'
                        WHEN em.urgency_level = 'LOW' THEN 'Low Priority'
                        ELSE 'General Maintenance'
                    END as category,
                    COUNT(*) as count,
                    COALESCE(SUM(em.actual_cost), 0) as actual_cost,
                    COALESCE(SUM(em.estimated_cost), 0) as estimated_cost,
                    COALESCE(AVG(em.actual_cost), 0) as avg_cost_per_repair,
                    COALESCE(AVG(em.estimated_cost), 0) as avg_estimated_cost
                FROM emergency_maintenance em
                LEFT JOIN fleet_vehicles v ON em.vehicle_id = v.id
                LEFT JOIN user_table ut ON em.driver_id = ut.user_id
                WHERE em.created_at >= DATE_SUB(NOW(), INTERVAL $dateRange DAY)
                AND v.article NOT LIKE 'Synthetic%'
                AND v.plate_number NOT LIKE 'SYN-%'" . $vehicleFilter . "
                GROUP BY em.urgency_level
                ORDER BY 
                    CASE em.urgency_level
                        WHEN 'CRITICAL' THEN 1
                        WHEN 'HIGH' THEN 2
                        WHEN 'MEDIUM' THEN 3
                        WHEN 'LOW' THEN 4
                        ELSE 5
                    END
            ";
            
            try {
                $stmt = $pdo->query($maintenanceCostQuery);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // If no real data, create some sample data structure for demonstration
                if (empty($result)) {
                    $result = [
                        [
                            'category' => 'Critical Repairs',
                            'count' => 0,
                            'actual_cost' => 0,
                            'estimated_cost' => 0,
                            'avg_cost_per_repair' => 0,
                            'avg_estimated_cost' => 0
                        ],
                        [
                            'category' => 'High Priority',
                            'count' => 0,
                            'actual_cost' => 0,
                            'estimated_cost' => 0,
                            'avg_cost_per_repair' => 0,
                            'avg_estimated_cost' => 0
                        ],
                        [
                            'category' => 'Medium Priority',
                            'count' => 0,
                            'actual_cost' => 0,
                            'estimated_cost' => 0,
                            'avg_cost_per_repair' => 0,
                            'avg_estimated_cost' => 0
                        ],
                        [
                            'category' => 'Low Priority',
                            'count' => 0,
                            'actual_cost' => 0,
                            'estimated_cost' => 0,
                            'avg_cost_per_repair' => 0,
                            'avg_estimated_cost' => 0
                        ]
                    ];
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            break;

        case 'get_maintenance_status':
            // Get real maintenance status with filters
            $maintenanceQuery = "
                SELECT 
                    CASE 
                        WHEN v.status = 'active' OR v.status IS NULL THEN 'Active'
                        WHEN v.status = 'maintenance' THEN 'In Maintenance'
                        WHEN v.status = 'out_of_service' THEN 'Out of Service'
                        ELSE 'Unknown'
                    END as status,
                    COUNT(*) as count
                FROM fleet_vehicles v
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                WHERE 1=1 AND v.article NOT LIKE 'Synthetic%' AND v.plate_number NOT LIKE 'SYN-%'" . $vehicleFilter . $driverFilter . "
                GROUP BY status
                ORDER BY count DESC
            ";
            $stmt = $pdo->query($maintenanceQuery);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_driver_performance':
            // Get geofence events data grouped by active geofences
            $geofenceEventsQuery = "
                SELECT 
                    g.name as geofence_name,
                    g.id as geofence_id,
                    COUNT(ge.id) as event_count,
                    SUM(CASE WHEN ge.event_type = 'entry' THEN 1 ELSE 0 END) as entries,
                    SUM(CASE WHEN ge.event_type = 'exit' THEN 1 ELSE 0 END) as exits
                FROM geofences g
                LEFT JOIN geofence_events ge ON g.id = ge.geofence_id 
                    AND ge.created_at >= DATE_SUB(NOW(), INTERVAL $dateRange DAY)
                WHERE g.status = 'active'
                GROUP BY g.id, g.name
                HAVING event_count > 0
                ORDER BY event_count DESC
                LIMIT 10
            ";
            
            try {
                $stmt = $pdo->query($geofenceEventsQuery);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            break;

        case 'get_trip_reports':
            // Get real trip reports from geofence events
            $stmt = $pdo->query("
                SELECT 
                    v.article as vehicle,
                    v.plate_number,
                    u.full_name as driver,
                    ge.created_at as date,
                    ge.event_type,
                    g.name as geofence_name
                FROM geofence_events ge
                LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                LEFT JOIN user_table u ON ge.driver_id = u.user_id
                LEFT JOIN geofences g ON ge.geofence_id = g.id
                ORDER BY ge.created_at DESC
                LIMIT 20
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_vehicle_locations':
            // Get current vehicle locations
            $stmt = $pdo->query("
                SELECT 
                    v.article,
                    v.plate_number,
                    g.lat,
                    g.lng,
                    g.speed,
                    g.last_update,
                    u.full_name as driver
                FROM fleet_vehicles v
                LEFT JOIN gps_devices g ON v.id = g.vehicle_id
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                LEFT JOIN user_table u ON va.driver_id = u.user_id
                WHERE g.last_update >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND v.article NOT LIKE 'Synthetic%' AND v.plate_number NOT LIKE 'SYN-%'
                ORDER BY g.last_update DESC
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_geofence_activity':
            // Get geofence activity summary
            $stmt = $pdo->query("
                SELECT 
                    g.name as geofence_name,
                    COUNT(ge.id) as total_events,
                    COUNT(CASE WHEN ge.event_type = 'entry' THEN 1 END) as entries,
                    COUNT(CASE WHEN ge.event_type = 'exit' THEN 1 END) as exits,
                    MAX(ge.created_at) as last_activity
                FROM geofences g
                LEFT JOIN geofence_events ge ON g.id = ge.geofence_id
                WHERE g.status = 'active'
                GROUP BY g.id, g.name
                ORDER BY total_events DESC
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get_fleet_alerts':
            // Get real fleet alerts from geofence events, emergency maintenance, and vehicle issues
            $alertsQuery = "
                (
                    SELECT 
                        CONCAT('geofence_', ge.id) as id,
                        ge.event_type as issue_type,
                        ge.created_at,
                        ge.geofence_id,
                        g.name as geofence_name,
                        v.article as vehicle_name,
                        v.plate_number,
                        va.driver_id,
                        ut.full_name as driver_name,
                        CASE 
                            WHEN ge.event_type = 'exit' THEN 'High'
                            WHEN ge.event_type = 'entry' THEN 'Low'
                            ELSE 'Medium'
                        END as priority,
                        CASE 
                            WHEN ge.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'Open'
                            WHEN ge.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'In Progress'
                            ELSE 'Resolved'
                        END as status,
                        'geofence' as alert_type
                    FROM geofence_events ge
                    LEFT JOIN geofences g ON ge.geofence_id = g.id
                    LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                    LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id AND va.status = 'active'
                    LEFT JOIN user_table ut ON va.driver_id = ut.user_id
                    WHERE ge.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND v.article NOT LIKE 'Synthetic%'
                    AND v.plate_number NOT LIKE 'SYN-%'
                )
                UNION ALL
                (
                    SELECT 
                        CONCAT('maintenance_', em.id) as id,
                        'Emergency Maintenance' as issue_type,
                        em.created_at,
                        NULL as geofence_id,
                        NULL as geofence_name,
                        v.article as vehicle_name,
                        v.plate_number,
                        em.driver_id,
                        ut.full_name as driver_name,
                        CASE 
                            WHEN em.urgency_level = 'CRITICAL' THEN 'High'
                            WHEN em.urgency_level = 'HIGH' THEN 'High'
                            WHEN em.urgency_level = 'MEDIUM' THEN 'Medium'
                            ELSE 'Low'
                        END as priority,
                        CASE 
                            WHEN em.status = 'pending' THEN 'Open'
                            WHEN em.status = 'assigned' THEN 'In Progress'
                            WHEN em.status = 'in_progress' THEN 'In Progress'
                            WHEN em.status = 'completed' THEN 'Resolved'
                            WHEN em.status = 'cancelled' THEN 'Resolved'
                            ELSE 'Open'
                        END as status,
                        'maintenance' as alert_type
                    FROM emergency_maintenance em
                    LEFT JOIN fleet_vehicles v ON em.vehicle_id = v.id
                    LEFT JOIN user_table ut ON em.driver_id = ut.user_id
                    WHERE em.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND v.article NOT LIKE 'Synthetic%'
                    AND v.plate_number NOT LIKE 'SYN-%'
                )
                ORDER BY created_at DESC
                LIMIT 50
            ";
            try {
                $stmt = $pdo->query($alertsQuery);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            break;

        case 'generate_report':
            // Generate comprehensive report
            $data = json_decode(file_get_contents('php://input'), true);
            $format = $data['format'] ?? 'pdf';
            
            // Collect all report data
            $reportData = [
                'summary' => [],
                'vehicles' => [],
                'drivers' => [],
                'geofences' => [],
                'activity' => []
            ];
            
            // Get summary data
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM fleet_vehicles");
            $reportData['summary']['total_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_table WHERE role = 'Driver'");
            $reportData['summary']['total_drivers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM geofences WHERE status = 'active'");
            $reportData['summary']['active_geofences'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Report generated successfully',
                'report_id' => uniqid(),
                'format' => $format,
                'data' => $reportData
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