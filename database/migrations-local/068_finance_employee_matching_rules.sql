-- Migration 068: employee cash-settlement actions for matching rules.
-- Additive tenant-local schema. Employee payments remain real CASH finance_operations.
SET @dbname = DATABASE();

SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_employee_identity_type')=0,
'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_employee_identity_type` VARCHAR(20) NULL AFTER `target_cash_account_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_employee_identity_id')=0,
'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_employee_identity_id` INT UNSIGNED NULL AFTER `target_employee_identity_type`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_employee_name_snapshot')=0,
'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_employee_name_snapshot` VARCHAR(255) NULL AFTER `target_employee_identity_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_employee_role_snapshot')=0,
'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_employee_role_snapshot` VARCHAR(50) NULL AFTER `target_employee_name_snapshot`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND INDEX_NAME='idx_fmr_employee_target')=0,
'ALTER TABLE `finance_matching_rules` ADD INDEX `idx_fmr_employee_target` (`target_employee_identity_type`,`target_employee_identity_id`)','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
