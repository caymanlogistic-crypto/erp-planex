-- Finance matching/classification extension. Tenant-local, additive and idempotent.
SET @dbname = DATABASE();

CREATE TABLE IF NOT EXISTS `finance_cash_flow_centers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_by_role` VARCHAR(20) DEFAULT NULL,
  `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
  `updated_by_role` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fcfc_active_sort` (`is_active`,`sort_order`),
  KEY `idx_fcfc_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND COLUMN_NAME='cash_flow_center_id')=0,'ALTER TABLE `finance_operations` ADD COLUMN `cash_flow_center_id` INT UNSIGNED NULL AFTER `dds_category_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND COLUMN_NAME='classification_status')=0,"ALTER TABLE `finance_operations` ADD COLUMN `classification_status` VARCHAR(30) NOT NULL DEFAULT 'UNALLOCATED' AFTER `cash_flow_center_id`",'SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND COLUMN_NAME='classification_rule_id')=0,'ALTER TABLE `finance_operations` ADD COLUMN `classification_rule_id` INT UNSIGNED NULL AFTER `classification_status`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND COLUMN_NAME='classification_locked')=0,'ALTER TABLE `finance_operations` ADD COLUMN `classification_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `classification_rule_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND COLUMN_NAME='classification_updated_at')=0,'ALTER TABLE `finance_operations` ADD COLUMN `classification_updated_at` DATETIME NULL AFTER `classification_locked`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='cash_flow_center_id')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `cash_flow_center_id` INT UNSIGNED NULL AFTER `purpose`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='dds_category_id')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `dds_category_id` INT UNSIGNED NULL AFTER `cash_flow_center_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='classification_status')=0,"ALTER TABLE `bank_transactions` ADD COLUMN `classification_status` VARCHAR(30) NOT NULL DEFAULT 'UNALLOCATED' AFTER `dds_category_id`",'SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='classification_rule_id')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `classification_rule_id` INT UNSIGNED NULL AFTER `classification_status`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='classification_locked')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `classification_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `classification_rule_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='classification_updated_at')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `classification_updated_at` DATETIME NULL AFTER `classification_locked`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='is_internal_transfer')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `is_internal_transfer` TINYINT(1) NOT NULL DEFAULT 0 AFTER `classification_updated_at`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND COLUMN_NAME='linked_cash_transaction_id')=0,'ALTER TABLE `bank_transactions` ADD COLUMN `linked_cash_transaction_id` INT UNSIGNED NULL AFTER `is_internal_transfer`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_cash_flow_center_id')=0,'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_cash_flow_center_id` INT UNSIGNED NULL AFTER `target_dds_category_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='target_cash_account_id')=0,'ALTER TABLE `finance_matching_rules` ADD COLUMN `target_cash_account_id` INT UNSIGNED NULL AFTER `target_cash_flow_center_id`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_matching_rules' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE `finance_matching_rules` ADD COLUMN `deleted_at` DATETIME NULL AFTER `auto_apply`','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='bank_transactions' AND INDEX_NAME='idx_bank_tx_classification')=0,'ALTER TABLE `bank_transactions` ADD INDEX `idx_bank_tx_classification` (`classification_status`,`operation_date`)','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s=(SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_operations' AND INDEX_NAME='idx_fop_classification')=0,'ALTER TABLE `finance_operations` ADD INDEX `idx_fop_classification` (`classification_status`,`operation_date`)','SELECT 1')); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
