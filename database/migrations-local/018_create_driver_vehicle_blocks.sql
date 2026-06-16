-- Migration 018: Create driver_vehicle_blocks table (idempotent)
CREATE TABLE IF NOT EXISTS `driver_vehicle_blocks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `driver_id` INT UNSIGNED NOT NULL,
    `vehicle_set_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(20) DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_driver_set` (`driver_id`, `vehicle_set_id`),
    INDEX `idx_driver_id` (`driver_id`),
    INDEX `idx_vehicle_set_id` (`vehicle_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
