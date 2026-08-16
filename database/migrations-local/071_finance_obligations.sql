-- Migration 071: universal payment obligations linking source events, invoices and bank allocations.
-- Idempotent. Keeps durable obligation identity independent from replaceable route payment row ids.

SET @dbname = DATABASE();

CREATE TABLE IF NOT EXISTS `finance_obligations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_type` VARCHAR(50) NOT NULL,
    `source_key` VARCHAR(255) NOT NULL,
    `source_id` INT UNSIGNED DEFAULT NULL,
    `source_parent_type` VARCHAR(50) NOT NULL,
    `source_parent_id` INT UNSIGNED NOT NULL,
    `direction` VARCHAR(20) NOT NULL COMMENT 'RECEIVABLE, PAYABLE',
    `party_role` VARCHAR(20) DEFAULT NULL,
    `counterparty_entity_type` VARCHAR(20) DEFAULT NULL,
    `counterparty_entity_id` INT UNSIGNED DEFAULT NULL,
    `counterparty_name` VARCHAR(500) DEFAULT NULL,
    `counterparty_inn` VARCHAR(20) DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `condition_type` VARCHAR(50) DEFAULT NULL,
    `days_count` INT DEFAULT NULL,
    `days_kind` VARCHAR(20) DEFAULT NULL,
    `specific_due_date` DATE DEFAULT NULL,
    `event_date` DATE DEFAULT NULL,
    `forecast_due_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(30) NOT NULL DEFAULT 'planned',
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_finance_obligation_source_key` (`source_key`),
    KEY `idx_fo_source` (`source_type`, `source_id`),
    KEY `idx_fo_parent` (`source_parent_type`, `source_parent_id`),
    KEY `idx_fo_counterparty` (`counterparty_entity_type`, `counterparty_entity_id`),
    KEY `idx_fo_counterparty_inn` (`counterparty_inn`),
    KEY `idx_fo_direction_status_due` (`direction`, `status`, `due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_invoice_links' AND COLUMN_NAME='obligation_id') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `finance_invoice_links` ADD COLUMN `obligation_id` INT UNSIGNED DEFAULT NULL AFTER `invoice_id`'
    )
);
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_invoice_links' AND INDEX_NAME='idx_fil_obligation') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `finance_invoice_links` ADD INDEX `idx_fil_obligation` (`obligation_id`)'
    )
);
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operation_allocations' AND COLUMN_NAME='obligation_id') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `finance_operation_allocations` ADD COLUMN `obligation_id` INT UNSIGNED DEFAULT NULL AFTER `invoice_id`'
    )
);
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operation_allocations' AND INDEX_NAME='idx_foa_obligation') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `finance_operation_allocations` ADD INDEX `idx_foa_obligation` (`obligation_id`)'
    )
);
PREPARE stmt FROM @preparedStatement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
