-- Migration: create superadmin_company_features table

CREATE TABLE IF NOT EXISTS `company_features` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `feature_code` VARCHAR(100) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `enabled_from` TIMESTAMP NULL DEFAULT NULL,
    `enabled_until` TIMESTAMP NULL DEFAULT NULL,
    `notes` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company_feature` (`company_id`, `feature_code`),
    INDEX `idx_company_id` (`company_id`),
    INDEX `idx_feature_code` (`feature_code`),
    INDEX `idx_is_enabled` (`is_enabled`),
    INDEX `idx_enabled_until` (`enabled_until`),
    CONSTRAINT `fk_company_features_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_company_features_feature` FOREIGN KEY (`feature_code`) REFERENCES `features` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
