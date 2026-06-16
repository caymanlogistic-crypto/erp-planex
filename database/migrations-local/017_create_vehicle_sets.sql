-- Migration 017: Create vehicle_sets table (idempotent)
CREATE TABLE IF NOT EXISTS `vehicle_sets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `set_type` VARCHAR(20) DEFAULT NULL COMMENT 'single, coupling, road_train',
    `primary_vehicle_unit_id` INT UNSIGNED NOT NULL,
    `secondary_vehicle_unit_id` INT UNSIGNED DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_primary` (`primary_vehicle_unit_id`),
    INDEX `idx_secondary` (`secondary_vehicle_unit_id`),
    INDEX `idx_set_type` (`set_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
