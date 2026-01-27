-- Database is already set up! All required columns exist.
-- This script only updates existing records if needed.

-- Update existing records to have proper type (only if needed)
UPDATE `geofences` SET `type` = 'circle' WHERE `type` IS NULL AND `radius` IS NOT NULL;
UPDATE `geofences` SET `type` = 'polygon' WHERE `type` IS NULL AND `polygon` IS NOT NULL AND `polygon` != '';

-- Add indexes for better performance (only if they don't exist)
-- CREATE INDEX `idx_geofence_type` ON `geofences` (`type`);
-- CREATE INDEX `idx_geofence_coordinates` ON `geofences` (`latitude`, `longitude`);

-- Show final table structure
DESCRIBE `geofences`;
