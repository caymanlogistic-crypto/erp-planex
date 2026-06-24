-- Migration 037: Create contractor_assignment_history table (idempotent)
-- Tracks contractor ownership reassignments by company_owner.
SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractor_assignment_history') > 0,
    'SELECT 1 AS already_exists',
    'CREATE TABLE `contractor_assignment_history` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `contractor_id` INT UNSIGNED NOT NULL,
        `old_logist_id` INT UNSIGNED DEFAULT NULL,
        `new_logist_id` INT UNSIGNED NOT NULL,
        `changed_by_user_id` INT UNSIGNED NOT NULL,
        `changed_by_role` VARCHAR(20) NOT NULL DEFAULT ''company_owner'',
        `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `summary_json` TEXT DEFAULT NULL,
        `comment` TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE createIfNotExists FROM @preparedStatement;
EXECUTE createIfNotExists;
DEALLOCATE PREPARE createIfNotExists;
