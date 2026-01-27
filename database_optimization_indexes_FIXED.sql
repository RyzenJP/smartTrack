-- ============================================
-- SmartTrack Database Optimization Indexes - FIXED VERSION
-- ============================================
-- This version handles missing columns gracefully
-- Run this AFTER checking your actual column names
-- Date: December 4, 2025
-- ============================================

-- ============================================
-- CRITICAL INDEXES (High Priority)
-- ============================================

-- User Table Indexes
-- Check if columns exist first, then create indexes
CREATE INDEX IF NOT EXISTS idx_user_username ON user_table(username);
CREATE INDEX IF NOT EXISTS idx_user_email ON user_table(email);
CREATE INDEX IF NOT EXISTS idx_user_role ON user_table(role);
CREATE INDEX IF NOT EXISTS idx_user_status ON user_table(status);
CREATE INDEX IF NOT EXISTS idx_user_role_status ON user_table(role, status);

-- Fleet Vehicles Indexes
CREATE INDEX IF NOT EXISTS idx_vehicle_status ON fleet_vehicles(status);
CREATE INDEX IF NOT EXISTS idx_vehicle_plate ON fleet_vehicles(plate_number);
CREATE INDEX IF NOT EXISTS idx_vehicle_unit ON fleet_vehicles(unit);
CREATE INDEX IF NOT EXISTS idx_vehicle_status_article ON fleet_vehicles(status, article);

-- GPS Logs Indexes (CRITICAL for performance)
CREATE INDEX IF NOT EXISTS idx_gps_device ON gps_logs(device_id);
CREATE INDEX IF NOT EXISTS idx_gps_timestamp ON gps_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_gps_device_timestamp ON gps_logs(device_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_gps_location ON gps_logs(latitude, longitude);

-- Vehicle Reservations Indexes
-- NOTE: Check your actual column names first!
-- Common variations: requester_id, user_id, created_by, requester_user_id
CREATE INDEX IF NOT EXISTS idx_reservation_status ON vehicle_reservations(status);
CREATE INDEX IF NOT EXISTS idx_reservation_dates ON vehicle_reservations(start_datetime, end_datetime);
-- Try these one at a time based on your actual column names:
-- CREATE INDEX IF NOT EXISTS idx_reservation_user ON vehicle_reservations(requester_id);
-- CREATE INDEX IF NOT EXISTS idx_reservation_user ON vehicle_reservations(user_id);
-- CREATE INDEX IF NOT EXISTS idx_reservation_user ON vehicle_reservations(created_by);
CREATE INDEX IF NOT EXISTS idx_reservation_dispatcher ON vehicle_reservations(assigned_dispatcher_id);
CREATE INDEX IF NOT EXISTS idx_reservation_status_dates ON vehicle_reservations(status, start_datetime);

-- Maintenance Schedules Indexes
CREATE INDEX IF NOT EXISTS idx_maintenance_vehicle ON maintenance_schedules(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_mechanic ON maintenance_schedules(assigned_mechanic);
CREATE INDEX IF NOT EXISTS idx_maintenance_dates ON maintenance_schedules(start_time, end_time);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance_schedules(status);
CREATE INDEX IF NOT EXISTS idx_maintenance_vehicle_status ON maintenance_schedules(vehicle_id, status);

-- Geofence Events Indexes
CREATE INDEX IF NOT EXISTS idx_geofence_device ON geofence_events(device_id);
CREATE INDEX IF NOT EXISTS idx_geofence_geofence ON geofence_events(geofence_id);
CREATE INDEX IF NOT EXISTS idx_geofence_created ON geofence_events(created_at);
CREATE INDEX IF NOT EXISTS idx_geofence_device_created ON geofence_events(device_id, created_at DESC);

-- GPS Devices Indexes
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
CREATE INDEX IF NOT EXISTS idx_route_unit ON routes(unit);
CREATE INDEX IF NOT EXISTS idx_route_status ON routes(status);
CREATE INDEX IF NOT EXISTS idx_route_date ON routes(date);
CREATE INDEX IF NOT EXISTS idx_route_unit_status ON routes(unit, status);

