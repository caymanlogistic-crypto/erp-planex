-- Migration 055: Create finance_matching_rules table
-- Idempotent via INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_matching_rules') > 0,
    'SELECT 1',
    'CREATE TABLE `finance_matching_rules` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `active` TINYINT(1) DEFAULT 1,
        `priority` INT DEFAULT 100,
        `direction` VARCHAR(20) DEFAULT NULL COMMENT \'INCOME, EXPENSE, NULL = BOTH\',
        `bank_account_id` INT UNSIGNED DEFAULT NULL,
        `counterparty_inn` VARCHAR(20) DEFAULT NULL,
        `counterparty_id` INT UNSIGNED DEFAULT NULL,
        `counterparty_type` VARCHAR(20) DEFAULT NULL COMMENT \'client, contractor\',
        `invoice_number_pattern` VARCHAR(255) DEFAULT NULL,
        `purpose_contains` VARCHAR(255) DEFAULT NULL,
        `purpose_regex` VARCHAR(500) DEFAULT NULL,
        `amount_from` DECIMAL(15,2) DEFAULT NULL,
        `amount_to` DECIMAL(15,2) DEFAULT NULL,
        `action_type` VARCHAR(30) NOT NULL DEFAULT \'categorize\' COMMENT \'categorize, match_invoice, match_counterparty\',
        `target_dds_category_id` INT UNSIGNED DEFAULT NULL,
        `target_counterparty_id` INT UNSIGNED DEFAULT NULL,
        `target_counterparty_type` VARCHAR(20) DEFAULT NULL,
        `auto_apply` TINYINT(1) DEFAULT 0,
        `created_by_user_id` INT UNSIGNED DEFAULT NULL,
        `created_by_role` VARCHAR(20) DEFAULT NULL,
        `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
        `updated_by_role` VARCHAR(20) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_fmr_active_priority` (`active`, `priority`),
        INDEX `idx_fmr_direction` (`direction`),
        INDEX `idx_fmr_bank_account` (`bank_account_id`),
        INDEX `idx_fmr_auto_apply` (`auto_apply`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
