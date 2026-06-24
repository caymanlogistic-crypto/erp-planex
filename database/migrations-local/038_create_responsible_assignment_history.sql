-- Migration 038: Create responsible_assignment_history table (idempotent)
-- Universal reassignment history for route_executor, contractor, driver, vehicle_set.
SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'responsible_assignment_history') > 0,
    'SELECT 1 AS already_exists',
    'CREATE TABLE `responsible_assignment_history` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `entity_type` VARCHAR(30) NOT NULL COMMENT ''route_executor/contractor/driver/vehicle_set'',
        `entity_id` INT UNSIGNED NOT NULL,
        `old_logist_id` INT UNSIGNED DEFAULT NULL,
        `new_logist_id` INT UNSIGNED NOT NULL,
        `changed_by_user_id` INT UNSIGNED NOT NULL,
        `changed_by_role` VARCHAR(20) NOT NULL DEFAULT ''company_owner'',
        `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `summary_json` TEXT DEFAULT NULL,
        `comment` TEXT DEFAULT NULL,
        INDEX `idx_entity` (`entity_type`, `entity_id`),
        INDEX `idx_new_logist` (`new_logist_id`),
        INDEX `idx_changed_at` (`changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE createIfNotExists FROM @preparedStatement;
EXECUTE createIfNotExists;
DEALLOCATE PREPARE createIfNotExists;
