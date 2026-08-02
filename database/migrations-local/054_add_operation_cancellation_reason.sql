-- Migration 054: Add cancellation_reason column to finance_operations
-- Idempotent via INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND COLUMN_NAME = 'cancellation_reason') > 0,
    'SELECT 1',
    'ALTER TABLE `finance_operations` ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `cancelled_at`'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add posted_at index for cancellation lookups
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND INDEX_NAME = 'idx_fop_posted_cancelled');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `finance_operations` ADD INDEX `idx_fop_posted_cancelled` (`status`, `cancelled_at`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
