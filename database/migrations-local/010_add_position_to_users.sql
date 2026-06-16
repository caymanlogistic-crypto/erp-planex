-- Migration 010: Add position column to local users table (idempotent)
-- Applied via auto-migration mechanism in route handlers

SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'position';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
       AND TABLE_NAME = @tablename
       AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `users` ADD COLUMN `position` VARCHAR(255) DEFAULT NULL AFTER `phone`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
