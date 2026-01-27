-- ============================================
-- SmartTrack Database Optimization Indexes - CORRECTED
-- ============================================
-- Based on actual database schema from backup
-- Date: December 4, 2025
-- ============================================
-- 
-- IMPORTANT: 
-- - Review indexes before creating (some may already exist)
-- - Test in development environment first
-- - Monitor query performance after implementation
-- - Use EXPLAIN to verify indexes are being used
-- ============================================

-- ============================================
-- CRITICAL INDEXES (High Priority)
-- ============================================

-- User Table Indexes
-- Improves login, user lookup, and role-based filtering
CREATE INDEX IF NOT EXISTS idx_user_username ON user_table(username);
CREATE INDEX IF NOT EXISTS idx_user_email ON user_table(email);
CREATE INDEX IF NOT EXISTS idx_user_role ON user_table(role);
CREATE INDEX IF NOT EXISTS idx_user_status ON user_table(status);
CREATE INDEX IF NOT EXISTS idx_user_role_status ON user_table(role, status);

-- Fleet Vehicles Indexes
-- Improves vehicle listing, filtering, and search queries
CREATE INDEX IF NOT EXISTS idx_vehicle_status ON fleet_vehicles(status);
CREATE INDEX IF NOT EXISTS idx_vehicle_plate ON fleet_vehicles(plate_number);
CREATE INDEX IF NOT EXISTS idx_vehicle_unit ON fleet_vehicles(unit);
CREATE INDEX IF NOT EXISTS idx_vehicle_status_article ON fleet_vehicles(status, article);

-- GPS Logs Indexes (CRITICAL for performance)
-- Dramatically improves GPS tracking queries and location searches
CREATE INDEX IF NOT EXISTS idx_gps_device ON gps_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_gps_timestamp ON gps_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_gps_device_timestamp ON gps_logs(device_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_gps_location ON gps_logs(latitude, longitude);

-- Vehicle Reservations Indexes
-- FIXED: Using 'created_by' instead of 'requester_id' (column doesn't exist)
-- Improves reservation listing, filtering, and date range queries
CREATE INDEX IF NOT EXISTS idx_reservation_status ON vehicle_reservations(status);
CREATE INDEX IF NOT EXISTS idx_reservation_dates ON vehicle_reservations(start_datetime, end_datetime);
CREATE INDEX IF NOT EXISTS idx_reservation_user ON vehicle_reservations(created_by);
CREATE INDEX IF NOT EXISTS idx_reservation_dispatcher ON vehicle_reservations(assigned_dispatcher_id);
CREATE INDEX IF NOT EXISTS idx_reservation_status_dates ON vehicle_reservations(status, start_datetime);

-- Maintenance Schedules Indexes
-- Improves maintenance listing and filtering queries
CREATE INDEX IF NOT EXISTS idx_maintenance_vehicle ON maintenance_schedules(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_mechanic ON maintenance_schedules(assigned_mechanic);
CREATE INDEX IF NOT EXISTS idx_maintenance_dates ON maintenance_schedules(start_time, end_time);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance_schedules(status);
CREATE INDEX IF NOT EXISTS idx_maintenance_vehicle_status ON maintenance_schedules(vehicle_id, status);

-- Geofence Events Indexes
-- Improves geofence event queries and reporting
CREATE INDEX IF NOT EXISTS idx_geofence_device ON geofence_events(device_id);
CREATE INDEX IF NOT EXISTS idx_geofence_geofence ON geofence_events(geofence_id);
CREATE INDEX IF NOT EXISTS idx_geofence_created ON geofence_events(created_at);
CREATE INDEX IF NOT EXISTS idx_geofence_device_created ON geofence_events(device_id, created_at DESC);

-- GPS Devices Indexes
-- Improves device lookup and status queries
CREATE INDEX IF NOT EXISTS idx_device_vehicle ON gps_devices(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_device_status ON gps_devices(status);
CREATE INDEX IF NOT EXISTS idx_device_vehicle_status ON gps_devices(vehicle_id, status);

-- ============================================
-- MEDIUM PRIORITY INDEXES
-- ============================================

-- Notifications Indexes
CREATE INDEX IF NOT EXISTS idx_notification_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notification_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notification_user_read ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notification_created ON notifications(created_at);

-- Vehicle Assignments Indexes
CREATE INDEX IF NOT EXISTS idx_assignment_driver ON vehicle_assignments(driver_id);
CREATE INDEX IF NOT EXISTS idx_assignment_vehicle ON vehicle_assignments(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_assignment_status ON vehicle_assignments(status);
CREATE INDEX IF NOT EXISTS idx_assignment_driver_status ON vehicle_assignments(driver_id, status);

-- Routes Indexes
-- FIXED: Using 'created_at' instead of 'date' (column doesn't exist)
CREATE INDEX IF NOT EXISTS idx_route_unit ON routes(unit);
CREATE INDEX IF NOT EXISTS idx_route_status ON routes(status);
CREATE INDEX IF NOT EXISTS idx_route_created ON routes(created_at);
CREATE INDEX IF NOT EXISTS idx_route_unit_status ON routes(unit, status);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these queries to verify indexes were created:

-- SHOW INDEXES FROM user_table;
-- SHOW INDEXES FROM fleet_vehicles;
-- SHOW INDEXES FROM gps_logs;
-- SHOW INDEXES FROM vehicle_reservations;
-- SHOW INDEXES FROM maintenance_schedules;
-- SHOW INDEXES FROM geofence_events;

-- ============================================
-- PERFORMANCE TESTING
-- ============================================
-- Test query performance before and after:

-- EXPLAIN SELECT * FROM gps_logs WHERE device_id = 1 ORDER BY timestamp DESC LIMIT 10;
-- EXPLAIN SELECT * FROM user_table WHERE username = 'test_user';
-- EXPLAIN SELECT * FROM fleet_vehicles WHERE status = 'active';

-- ============================================
-- END OF SCRIPT
-- ============================================

