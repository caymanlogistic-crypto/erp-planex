-- Migration 045: Extend linear_route_payments with financial core fields
-- and create finance_audit_log table.
-- Idempotent via conditional column checks.

SET @dbname = DATABASE();

-- Add payment_method column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'payment_method') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `payment_method` VARCHAR(20) DEFAULT NULL COMMENT ''cashless, cash'' AFTER `payment_type`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vat_rate column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'vat_rate') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `vat_rate` DECIMAL(5,2) DEFAULT NULL COMMENT ''NULL = without VAT'' AFTER `payment_method`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add paid_amount column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'paid_amount') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `vat_rate`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add payment_status column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'payment_status') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT ''unpaid'' AFTER `paid_amount`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add calculated_due_date column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'calculated_due_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `calculated_due_date` DATE DEFAULT NULL AFTER `payment_status`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add paid_at column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'paid_at') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `paid_at` DATE DEFAULT NULL AFTER `calculated_due_date`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add status_updated_at column
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'status_updated_at') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `status_updated_at` DATETIME DEFAULT NULL AFTER `paid_at`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill deterministic mapping from legacy payment_type
UPDATE `linear_route_payments`
   SET `payment_method` = CASE
           WHEN `payment_type` = 'Нал' THEN 'cash'
           WHEN `payment_type` = 'Без НДС' OR `payment_type` IS NULL OR `payment_type` = '' THEN 'cashless'
           WHEN `payment_type` LIKE 'НДС %' THEN 'cashless'
           ELSE 'cashless'
       END,
       `vat_rate` = CASE
           WHEN `payment_type` = 'Нал' THEN NULL
           WHEN `payment_type` = 'Без НДС' OR `payment_type` IS NULL OR `payment_type` = '' THEN NULL
           WHEN `payment_type` = 'НДС 0%' THEN 0
           WHEN `payment_type` = 'НДС 5%' THEN 5
           WHEN `payment_type` = 'НДС 7%' THEN 7
           WHEN `payment_type` = 'НДС 20%' THEN 20
           WHEN `payment_type` = 'НДС 22%' THEN 22
           ELSE NULL
       END
 WHERE `payment_method` IS NULL;

-- Create finance_audit_log table
CREATE TABLE IF NOT EXISTS `finance_audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_values` TEXT DEFAULT NULL,
    `new_values` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fal_entity` (`entity_type`, `entity_id`),
    KEY `idx_fal_action` (`action`),
    KEY `idx_fal_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for payment status/due date queries
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND INDEX_NAME = 'idx_lrp_payment_status') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD INDEX `idx_lrp_payment_status` (`payment_status`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND INDEX_NAME = 'idx_lrp_calculated_due_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD INDEX `idx_lrp_calculated_due_date` (`calculated_due_date`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
