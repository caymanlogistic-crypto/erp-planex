-- Migration 013: Create contractor_tax_history table (idempotent)
CREATE TABLE IF NOT EXISTS `contractor_tax_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contractor_id` INT UNSIGNED NOT NULL,
    `tax_system` VARCHAR(50) DEFAULT NULL,
    `vat_mode` VARCHAR(50) DEFAULT NULL,
    `effective_from` DATE DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contractor_id` (`contractor_id`),
    INDEX `idx_effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
