-- Migration: create superadmin_features table

CREATE TABLE IF NOT EXISTS `features` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `code` VARCHAR(100) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `parent_code` VARCHAR(100) DEFAULT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    INDEX `idx_type` (`type`),
    INDEX `idx_parent_code` (`parent_code`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`type`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `features`
    ADD CONSTRAINT `fk_features_parent`
    FOREIGN KEY (`parent_code`) REFERENCES `features` (`code`)
    ON DELETE SET NULL;
