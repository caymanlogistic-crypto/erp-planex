-- Migration 039: Add soft-delete columns to all entity tables (idempotent)
-- Adds deleted_at, deleted_by_user_id, deleted_by_role to each entity table.
-- documents already has deleted_at and deleted_by_user_id — adds deleted_by_role.
SET @dbname = DATABASE();

-- Helper macro per table
-- clients
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `clients` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `clients` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `clients` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- contractors
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `contractors` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `contractors` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `contractors` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- drivers
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `drivers` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `drivers` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `drivers` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- vehicle_units
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_units` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_units` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_units' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_units` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- vehicle_sets
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_sets' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_sets` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_sets' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_sets` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicle_sets' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `vehicle_sets` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- driver_vehicle_blocks
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'driver_vehicle_blocks' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `driver_vehicle_blocks` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'driver_vehicle_blocks' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `driver_vehicle_blocks` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'driver_vehicle_blocks' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `driver_vehicle_blocks` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- crews
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `crews` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `crews` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `crews` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- users
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `users` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `users` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `users` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- document_types
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND COLUMN_NAME = 'deleted_at') > 0, 'SELECT 1', 'ALTER TABLE `document_types` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND COLUMN_NAME = 'deleted_by_user_id') > 0, 'SELECT 1', 'ALTER TABLE `document_types` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `document_types` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;

-- documents (already has deleted_at, deleted_by_user_id — add deleted_by_role)
SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'deleted_by_role') > 0, 'SELECT 1', 'ALTER TABLE `documents` ADD COLUMN `deleted_by_role` VARCHAR(32) DEFAULT NULL')); PREPARE st FROM @preparedStatement; EXECUTE st; DEALLOCATE PREPARE st;
