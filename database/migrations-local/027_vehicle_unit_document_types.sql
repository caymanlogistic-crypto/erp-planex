-- Migration 027: predefined document types for vehicle units
SET @dbname = DATABASE();

CREATE TABLE IF NOT EXISTS `document_types` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `entity_type` VARCHAR(20) DEFAULT NULL,
    `category` VARCHAR(20) NOT NULL DEFAULT 'custom',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_entity_type` (`entity_type`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE t1 FROM `document_types` t1
INNER JOIN `document_types` t2
WHERE t1.id > t2.id AND t1.name = t2.name AND COALESCE(t1.entity_type, '') = COALESCE(t2.entity_type, '');

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND INDEX_NAME = 'uk_name_entity_type') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `document_types` ADD UNIQUE INDEX `uk_name_entity_type` (`name`, `entity_type`)'
));
PREPARE addUniqueIfNotExists FROM @preparedStatement;
EXECUTE addUniqueIfNotExists;
DEALLOCATE PREPARE addUniqueIfNotExists;

INSERT IGNORE INTO `document_types` (`name`, `code`, `entity_type`, `category`, `sort_order`) VALUES
('СТС', 'sts', 'vehicle_unit', 'predefined', 1),
('Диагностическая карта', 'diagnostic_card', 'vehicle_unit', 'predefined', 2),
('Фото', 'photo', 'vehicle_unit', 'predefined', 3);
