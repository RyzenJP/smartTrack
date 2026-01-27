-- ============================================
-- Check Actual Column Names in Your Database
-- ============================================
-- Run these queries FIRST to see what columns actually exist
-- Then we can create the correct indexes
-- ============================================

-- Check vehicle_reservations table columns
DESCRIBE vehicle_reservations;
-- OR
SHOW COLUMNS FROM vehicle_reservations;

-- Check other tables that might have different column names
DESCRIBE user_table;
DESCRIBE fleet_vehicles;
DESCRIBE gps_logs;
DESCRIBE maintenance_schedules;
DESCRIBE geofence_events;
DESCRIBE gps_devices;
DESCRIBE notifications;
DESCRIBE vehicle_assignments;
DESCRIBE routes;

