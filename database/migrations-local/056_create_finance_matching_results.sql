-- Migration 056: Create finance_matching_results table
-- Idempotent via INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_matching_results') > 0,
    'SELECT 1',
    'CREATE TABLE `finance_matching_results` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `finance_operation_id` INT UNSIGNED NOT NULL,
        `rule_id` INT UNSIGNED DEFAULT NULL,
        `reason` VARCHAR(500) DEFAULT NULL,
        `confidence` VARCHAR(20) DEFAULT NULL COMMENT \'low, medium, high\',
        `result` VARCHAR(30) DEFAULT NULL COMMENT \'no_match, possible, suggest, auto_apply\',
        `applied_at` DATETIME DEFAULT NULL,
        `source` VARCHAR(30) DEFAULT \'system\' COMMENT \'system, user, preview, test\',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_fmr_operation` (`finance_operation_id`),
        INDEX `idx_fmr_rule` (`rule_id`),
        INDEX `idx_fmr_result` (`result`),
        INDEX `idx_fmr_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
