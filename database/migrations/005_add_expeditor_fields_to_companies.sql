-- Migration: add expeditor fields to companies table
-- DECISION-0032: baseline contractor/client fields for SUPERADMIN expeditor registry

ALTER TABLE `companies`
    ADD COLUMN `inn` VARCHAR(20) DEFAULT NULL AFTER `entity_type`,
    ADD COLUMN `kpp` VARCHAR(20) DEFAULT NULL AFTER `inn`,
    ADD COLUMN `ogrn` VARCHAR(20) DEFAULT NULL AFTER `kpp`,
    ADD COLUMN `legal_address` VARCHAR(500) DEFAULT NULL AFTER `ogrn`,
    ADD COLUMN `physical_address` VARCHAR(500) DEFAULT NULL AFTER `legal_address`,
    ADD COLUMN `contact_person` VARCHAR(255) DEFAULT NULL AFTER `physical_address`,
    ADD COLUMN `contact_phone` VARCHAR(50) DEFAULT NULL AFTER `contact_person`,
    ADD COLUMN `contact_email` VARCHAR(255) DEFAULT NULL AFTER `contact_phone`,
    ADD COLUMN `comments` TEXT DEFAULT NULL AFTER `contact_email`,
    ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `comments`,
    ADD INDEX `idx_inn` (`inn`);
