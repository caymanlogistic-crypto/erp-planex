-- Migration 014: Add passport/snils fields to drivers (idempotent)
SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'passport_number') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `passport_number` VARCHAR(50) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'passport_issued_by') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `passport_issued_by` VARCHAR(500) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'passport_department_code') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `passport_department_code` VARCHAR(10) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'passport_issue_date') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `passport_issue_date` DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'snils') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `snils` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'updated_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `updated_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'updated_by_role') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `updated_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
