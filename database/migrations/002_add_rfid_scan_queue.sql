-- RFID Scan Queue Migration
-- This table temporarily stores RFID scans for the web interface polling system

CREATE TABLE IF NOT EXISTS `rfid_scan_queue` (
  `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
  `tag_value` VARCHAR(50) NOT NULL,
  `device_id` INT DEFAULT 1,
  `source_ip` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tag_value` (`tag_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-cleanup old entries (older than 5 minutes)
CREATE EVENT IF NOT EXISTS `cleanup_rfid_queue`
ON SCHEDULE EVERY 1 MINUTE
DO
  DELETE FROM `rfid_scan_queue` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
