-- Quick fix to add IT-Ausbildung for today
-- Run this in phpMyAdmin or MySQL command line

INSERT IGNORE INTO Events (
    name, 
    description, 
    location, 
    start_time, 
    end_time, 
    created_by, 
    event_type, 
    breaks_info, 
    active
) VALUES (
    'IT-Ausbildung - Today', 
    'Daily IT training program with scheduled breaks', 
    'IT Training Center', 
    CONCAT(CURDATE(), ' 07:30:00'), 
    CONCAT(CURDATE(), ' 16:00:00'), 
    1, 
    'training', 
    JSON_OBJECT(
        "daily_schedule", true,
        "recurring", "workdays",
        "breaks", JSON_ARRAY(
            JSON_OBJECT("name", "Morning Break", "start_time", "09:00", "end_time", "09:30", "duration_minutes", 30),
            JSON_OBJECT("name", "Lunch Break", "start_time", "12:30", "end_time", "13:00", "duration_minutes", 30)
        ),
        "total_duration_hours", 8.5,
        "effective_training_hours", 7.5
    ), 
    1
);
