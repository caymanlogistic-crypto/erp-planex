-- Migration 007: Add position column to company_users (central DB)
-- Idempotent: uses a stored procedure approach to check if column exists

SET @dbname = DATABASE();
SET @tablename = 'company_users';
SET @columnname = 'position';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
       AND TABLE_NAME = @tablename
       AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `company_users` ADD COLUMN `position` VARCHAR(255) DEFAULT NULL AFTER `phone`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
