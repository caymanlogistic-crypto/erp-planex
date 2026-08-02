-- Migration 008: Add ownership columns to local tables (idempotent)
SET @dbname = DATABASE();

-- users
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `users` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- clients
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `clients` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- contractors
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- drivers
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `drivers` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- vehicles / vehicle_units (supports both pre- and post-rename schemas)
SET @vehicleTable = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units') > 0,
    'vehicle_units',
    'vehicles'
);
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @vehicleTable AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    CONCAT('ALTER TABLE `', @vehicleTable, '` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- crews
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `crews` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- documents
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'created_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `created_by_user_id` INT UNSIGNED DEFAULT NULL, ADD COLUMN `created_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
