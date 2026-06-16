-- Migration 012: Create contractor_contacts table (idempotent)
CREATE TABLE IF NOT EXISTS `contractor_contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contractor_id` INT UNSIGNED NOT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `is_document_email` TINYINT(1) DEFAULT 0,
    `comment` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contractor_id` (`contractor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
