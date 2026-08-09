-- Migration 028: support a second simultaneous driver in a route executor crew.
-- Existing single-driver crews remain valid: secondary_driver_id is nullable.
SET @dbname = DATABASE();
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'secondary_driver_id') > 0,
    'SELECT 1',
    'ALTER TABLE `crews` ADD COLUMN `secondary_driver_id` INT UNSIGNED DEFAULT NULL AFTER `driver_id`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND INDEX_NAME = 'idx_crews_secondary_driver') > 0,
    'SELECT 1',
    'ALTER TABLE `crews` ADD INDEX `idx_crews_secondary_driver` (`secondary_driver_id`)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
