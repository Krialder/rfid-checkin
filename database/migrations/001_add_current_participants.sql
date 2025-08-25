-- Migration Script: Add current_participants column to existing Events table
-- Run this script if you already have an existing database without the current_participants column

-- Check if column exists before adding it
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'Events' 
AND COLUMN_NAME = 'current_participants';

-- Add column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE Events ADD COLUMN current_participants INT DEFAULT 0 AFTER capacity, ADD INDEX idx_current_participants (current_participants)', 
    'SELECT "Column current_participants already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Initialize the values by recalculating from CheckIn table
UPDATE Events e
SET current_participants = (
    SELECT COALESCE(COUNT(*), 0)
    FROM CheckIn c
    WHERE c.event_id = e.event_id
    AND c.status = 'checked-in'
);

-- Show the results
SELECT 
    'Migration completed' as status,
    COUNT(*) as total_events,
    SUM(current_participants) as total_current_participants,
    ROUND(AVG(current_participants), 2) as avg_participants_per_event
FROM Events;

SELECT 
    name,
    current_participants,
    capacity,
    CASE 
        WHEN capacity > 0 THEN CONCAT(ROUND((current_participants / capacity) * 100, 1), '%')
        ELSE 'No limit'
    END as capacity_usage
FROM Events 
WHERE current_participants > 0 
ORDER BY current_participants DESC 
LIMIT 10;
