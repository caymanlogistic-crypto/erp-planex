CREATE TABLE IF NOT EXISTS `vehicles` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `plate_number` VARCHAR(20) NOT NULL,
    `brand` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `vehicle_type` VARCHAR(50) DEFAULT NULL,
    `vin` VARCHAR(50) DEFAULT NULL,
    `sts_number` VARCHAR(50) DEFAULT NULL,
    `pts_number` VARCHAR(50) DEFAULT NULL,
    `capacity_tons` DECIMAL(8,2) DEFAULT NULL,
    `volume_m3` DECIMAL(8,2) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plate_number` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
