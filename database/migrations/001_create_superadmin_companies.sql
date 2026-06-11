-- Migration: create superadmin_companies table

CREATE TABLE IF NOT EXISTS `companies` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `key` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `short_name` VARCHAR(100) DEFAULT NULL,
    `entity_type` VARCHAR(20) NOT NULL DEFAULT 'legal_entity',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `folder_path` VARCHAR(255) DEFAULT NULL,
    `db_identifier` VARCHAR(100) DEFAULT NULL,
    `storage_path` VARCHAR(255) DEFAULT NULL,
    `settings_json` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    INDEX `idx_status` (`status`),
    INDEX `idx_entity_type` (`entity_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
