-- Migration 050: Create finance_dds_categories table
-- Idempotent via IF NOT EXISTS / INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

-- Create table
CREATE TABLE IF NOT EXISTS `finance_dds_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `direction` VARCHAR(20) NOT NULL COMMENT 'INCOME, EXPENSE, BOTH',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 100,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fdc_parent` (`parent_id`),
    UNIQUE KEY `uk_fdc_code` (`code`),
    KEY `idx_fdc_direction` (`direction`),
    KEY `idx_fdc_active` (`is_active`),
    KEY `idx_fdc_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed categories (idempotent via INSERT IGNORE)
INSERT IGNORE INTO `finance_dds_categories` (`code`, `name`, `direction`, `is_active`, `sort_order`, `is_system`) VALUES
('IN_TRANSPORT_SERVICES', 'Транспортно-экспедиционные услуги', 'INCOME', 1, 10, 1),
('IN_ADDITIONAL_SERVICES', 'Дополнительные услуги', 'INCOME', 1, 20, 1),
('IN_OTHER', 'Прочие поступления', 'INCOME', 1, 30, 1),
('IN_REFUNDS', 'Возвраты', 'INCOME', 1, 40, 1),
('IN_LOANS', 'Займы', 'INCOME', 1, 50, 1),
('IN_OWNER_CONTRIBUTION', 'Вклад собственника', 'INCOME', 1, 60, 1),
('OUT_CARRIERS', 'Оплата перевозчикам', 'EXPENSE', 1, 10, 1),
('OUT_BANK_FEES', 'Банковские комиссии', 'EXPENSE', 1, 20, 1),
('OUT_TAXES', 'Налоги', 'EXPENSE', 1, 30, 1),
('OUT_SALARY', 'Зарплата', 'EXPENSE', 1, 40, 1),
('OUT_RENT', 'Аренда', 'EXPENSE', 1, 50, 1),
('OUT_COMMUNICATION', 'Связь', 'EXPENSE', 1, 60, 1),
('OUT_SOFTWARE', 'ПО', 'EXPENSE', 1, 70, 1),
('OUT_HOUSEHOLD', 'Хозяйственные расходы', 'EXPENSE', 1, 80, 1),
('OUT_TRAVEL', 'Командировочные', 'EXPENSE', 1, 90, 1),
('OUT_CLIENT_REFUNDS', 'Возвраты клиентам', 'EXPENSE', 1, 100, 1),
('OUT_OTHER', 'Прочие расходы', 'EXPENSE', 1, 110, 1);

-- Add index on dds_category_id in finance_operations if not exists
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operations' AND INDEX_NAME = 'idx_fop_dds_category');
SET @sql = IF(@exists = 0,
    'ALTER TABLE finance_operations ADD INDEX `idx_fop_dds_category` (`dds_category_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
