-- Add a sample mobile device to your existing gps_devices table
-- This script adds a mobile device entry that the mobile app can use

INSERT INTO `gps_devices` (
    `device_id`, 
    `imei`, 
    `vehicle_id`, 
    `status`, 
    `last_update`, 
    `battery_level`, 
    `lat`, 
    `lng`, 
    `speed`
) VALUES (
    'MOBILE-001', 
    'MOBILE-001', 
    NULL, 
    'active', 
    NOW(), 
    100, 
    NULL, 
    NULL, 
    0.00
);

-- You can also add more mobile devices as needed:
-- INSERT INTO `gps_devices` (
--     `device_id`, 
--     `imei`, 
--     `vehicle_id`, 
--     `status`, 
--     `last_update`, 
--     `battery_level`, 
--     `lat`, 
--     `lng`, 
--     `speed`
-- ) VALUES (
--     'MOBILE-002', 
--     'MOBILE-002', 
--     NULL, 
--     'active', 
--     NOW(), 
--     100, 
--     NULL, 
--     NULL, 
--     0.00
-- );
