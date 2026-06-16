-- Migration 011: Add extended fields to contractors (idempotent)
SET @dbname = DATABASE();

-- contractor_type
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'contractor_type') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `contractor_type` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- bank_account
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'bank_account') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `bank_account` VARCHAR(50) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- bank_name
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'bank_name') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `bank_name` VARCHAR(255) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- bank_bik
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'bank_bik') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `bank_bik` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- bank_corr_account
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'bank_corr_account') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `bank_corr_account` VARCHAR(50) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_user_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'updated_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `updated_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_role
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'updated_by_role') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `updated_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Drop UNIQUE KEY uk_inn if exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND INDEX_NAME = 'uk_inn') > 0,
    'ALTER TABLE `contractors` DROP INDEX `uk_inn`',
    'SELECT 1 AS no_index'
));
PREPARE dropIndexIfExists FROM @preparedStatement;
EXECUTE dropIndexIfExists;
DEALLOCATE PREPARE dropIndexIfExists;

-- Add INDEX idx_inn if not exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND INDEX_NAME = 'idx_inn') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD INDEX `idx_inn` (`inn`)'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
