-- Migration 019: Update crews table structure (idempotent)
SET @dbname = DATABASE();

-- Check crew count
SET @crewCount = (SELECT COUNT(*) FROM `crews`);

-- If crews has data, block migration
SET @preparedStatement = (SELECT IF(
    @crewCount > 0,
    CONCAT('SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Обнаружены старые экипажи в старой схеме. Автоматическая миграция заблокирована. Нужно отдельное решение владельца.\''),
    'SELECT 1 AS ok_to_migrate'
));
PREPARE checkCrews FROM @preparedStatement;
EXECUTE checkCrews;
DEALLOCATE PREPARE checkCrews;

-- Only proceed if crews is empty
-- Add driver_vehicle_block_id
SET @preparedStatement = (SELECT IF(
    @crewCount = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'driver_vehicle_block_id') = 0,
    'ALTER TABLE `crews` ADD COLUMN `driver_vehicle_block_id` INT UNSIGNED DEFAULT NULL',
    'SELECT 1 AS skip_driver_vehicle_block_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add updated_by_user_id
SET @preparedStatement = (SELECT IF(
    @crewCount = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'updated_by_user_id') = 0,
    'ALTER TABLE `crews` ADD COLUMN `updated_by_user_id` INT UNSIGNED DEFAULT NULL',
    'SELECT 1 AS skip_updated_by_user_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add updated_by_role
SET @preparedStatement = (SELECT IF(
    @crewCount = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'updated_by_role') = 0,
    'ALTER TABLE `crews` ADD COLUMN `updated_by_role` VARCHAR(20) DEFAULT NULL',
    'SELECT 1 AS skip_updated_by_role'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Drop old UNIQUE KEY uk_crew if exists (only when no data)
SET @preparedStatement = (SELECT IF(
    @crewCount = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND INDEX_NAME = 'uk_crew') > 0,
    'ALTER TABLE `crews` DROP INDEX `uk_crew`',
    'SELECT 1 AS no_index'
));
PREPARE dropIndexIfExists FROM @preparedStatement;
EXECUTE dropIndexIfExists;
DEALLOCATE PREPARE dropIndexIfExists;

-- Add new UNIQUE KEY uk_crew (contractor_id, driver_vehicle_block_id) if not exists
SET @preparedStatement = (SELECT IF(
    @crewCount = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND INDEX_NAME = 'uk_crew') = 0,
    'ALTER TABLE `crews` ADD UNIQUE KEY `uk_crew` (`contractor_id`, `driver_vehicle_block_id`)',
    'SELECT 1 AS skip_new_index'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
