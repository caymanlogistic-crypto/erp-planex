-- Migration 025: Add document_type_id FK column to documents table + seed predefined document types
SET @dbname = DATABASE();

-- document_type_id column (idempotent ALTER)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'document_type_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `document_type_id` INT UNSIGNED DEFAULT NULL AFTER `document_type`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Index on document_type_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_document_type_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD INDEX `idx_document_type_id` (`document_type_id`)'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;

-- Ensure document_types table exists (for seed inserts)
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

-- Clean up duplicates caused by previous runs without UNIQUE constraint
-- Keep only the row with the lowest id for each (name, COALESCE(entity_type, '')) combination
DELETE t1 FROM `document_types` t1
INNER JOIN `document_types` t2
WHERE t1.id > t2.id AND t1.name = t2.name AND COALESCE(t1.entity_type, '') = COALESCE(t2.entity_type, '');

-- Add UNIQUE constraint on (name, entity_type) to prevent future duplicates
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND INDEX_NAME = 'uk_name_entity_type') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `document_types` ADD UNIQUE INDEX `uk_name_entity_type` (`name`, `entity_type`)'
));
PREPARE addUniqueIfNotExists FROM @preparedStatement;
EXECUTE addUniqueIfNotExists;
DEALLOCATE PREPARE addUniqueIfNotExists;

-- Seed predefined document types (INSERT IGNORE now effective with UNIQUE constraint)
INSERT IGNORE INTO `document_types` (`name`, `code`, `entity_type`, `category`, `sort_order`) VALUES
-- Driver documents
('Паспорт', 'passport', 'driver', 'predefined', 1),
('Водительское удостоверение', 'driver_license', 'driver', 'predefined', 2),
('СНИЛС', 'snils', 'driver', 'predefined', 3),
-- Vehicle Set documents
('СТС', 'sts', 'vehicle_set', 'predefined', 1),
('ПТС', 'pts', 'vehicle_set', 'predefined', 2),
('Страховка ОСАГО', 'osago', 'vehicle_set', 'predefined', 3),
-- Contractor documents
('Карточка предприятия', 'company_card', 'contractor', 'predefined', 1),
('Свидетельство ИНН', 'inn_cert', 'contractor', 'predefined', 2),
('Свидетельство ОГРН', 'ogrn_cert', 'contractor', 'predefined', 3),
('Договор', 'contract', 'contractor', 'predefined', 4);
