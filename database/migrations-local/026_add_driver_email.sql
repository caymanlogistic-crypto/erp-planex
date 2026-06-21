-- Migration 026: add optional email to drivers (idempotent)
SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'email') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `phone`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
