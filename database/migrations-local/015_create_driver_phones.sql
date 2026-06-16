-- Migration 015: Create driver_phones table (idempotent)
CREATE TABLE IF NOT EXISTS `driver_phones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `driver_id` INT UNSIGNED NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `is_main` TINYINT(1) DEFAULT 0,
    `comment` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_driver_id` (`driver_id`),
    INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
