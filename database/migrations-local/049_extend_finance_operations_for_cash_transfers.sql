-- Migration 049: Add transfer_group_id and transfer_direction to finance_operations
-- Idempotent via INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

-- Add transfer_group_id column
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND COLUMN_NAME = 'transfer_group_id');
SET @sql = IF(@exists = 0,
    'ALTER TABLE finance_operations ADD COLUMN `transfer_group_id` VARCHAR(64) NULL AFTER `transfer_account_id`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add transfer_direction column
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND COLUMN_NAME = 'transfer_direction');
SET @sql = IF(@exists = 0,
    'ALTER TABLE finance_operations ADD COLUMN `transfer_direction` VARCHAR(10) NULL COMMENT \'out, in\' AFTER `transfer_group_id`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on transfer_group_id
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND INDEX_NAME = 'idx_fop_transfer_group');
SET @sql = IF(@exists = 0,
    'ALTER TABLE finance_operations ADD INDEX `idx_fop_transfer_group` (`transfer_group_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on transfer_direction
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND INDEX_NAME = 'idx_fop_transfer_direction');
SET @sql = IF(@exists = 0,
    'ALTER TABLE finance_operations ADD INDEX `idx_fop_transfer_direction` (`transfer_direction`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
