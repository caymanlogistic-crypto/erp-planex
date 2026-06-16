-- Migration 016: Rename vehicles to vehicle_units + add new columns (idempotent)
SET @dbname = DATABASE();

-- Check if vehicles table exists
SET @vehiclesExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicles');
SET @vehicleUnitsExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units');

-- Case 1: vehicles exists, vehicle_units does not → rename
SET @preparedStatement = (SELECT IF(
    @vehiclesExists > 0 AND @vehicleUnitsExists = 0,
    'RENAME TABLE `vehicles` TO `vehicle_units`',
    'SELECT 1 AS skip_rename'
));
PREPARE renameIfNeeded FROM @preparedStatement;
EXECUTE renameIfNeeded;
DEALLOCATE PREPARE renameIfNeeded;

-- Case 2: both exist → error via SIGNAL
SET @preparedStatement = (SELECT IF(
    @vehiclesExists > 0 AND @vehicleUnitsExists > 0,
    CONCAT('SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Migration blocked: both vehicles and vehicle_units tables exist. Manual resolution required.\''),
    'SELECT 1 AS ok'
));
PREPARE checkConflict FROM @preparedStatement;
EXECUTE checkConflict;
DEALLOCATE PREPARE checkConflict;

-- Now add columns to vehicle_units (the table should exist by now)
-- unit_type
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'unit_type') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `unit_type` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- diagnostic_card_number
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'diagnostic_card_number') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `diagnostic_card_number` VARCHAR(50) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- diagnostic_card_date
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'diagnostic_card_date') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `diagnostic_card_date` DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- diagnostic_card_expire
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'diagnostic_card_expire') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `diagnostic_card_expire` DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_user_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'updated_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `updated_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_role
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'updated_by_role') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD COLUMN `updated_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Drop UNIQUE KEY uk_plate_number if exists (on vehicle_units)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND INDEX_NAME = 'uk_plate_number') > 0,
    'ALTER TABLE `vehicle_units` DROP INDEX `uk_plate_number`',
    'SELECT 1 AS no_index'
));
PREPARE dropIndexIfExists FROM @preparedStatement;
EXECUTE dropIndexIfExists;
DEALLOCATE PREPARE dropIndexIfExists;

-- Add INDEX idx_plate if not exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND INDEX_NAME = 'idx_plate') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `vehicle_units` ADD INDEX `idx_plate` (`plate_number`)'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
