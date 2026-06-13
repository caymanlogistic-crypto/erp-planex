CREATE TABLE IF NOT EXISTS `drivers` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `license_number` VARCHAR(50) DEFAULT NULL,
    `license_category` VARCHAR(50) DEFAULT NULL,
    `license_issue_date` DATE DEFAULT NULL,
    `license_expire_date` DATE DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
