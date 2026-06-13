CREATE TABLE IF NOT EXISTS `crews` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `contractor_id` INT UNSIGNED NOT NULL,
    `vehicle_id` INT UNSIGNED NOT NULL,
    `driver_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_crew` (`contractor_id`, `vehicle_id`, `driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
