-- Migration 074: link employee-funded company expenses to employee ledger and cash operations.
-- Additive/idempotent. Migration 073 remains immutable because it is already recorded in production.
SET @dbname = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND COLUMN_NAME='event_group_id');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD COLUMN event_group_id VARCHAR(64) NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND COLUMN_NAME='employee_movement_id');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD COLUMN employee_movement_id INT UNSIGNED NULL AFTER event_group_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND COLUMN_NAME='receipt_finance_operation_id');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD COLUMN receipt_finance_operation_id INT UNSIGNED NULL AFTER employee_movement_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND COLUMN_NAME='expense_finance_operation_id');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD COLUMN expense_finance_operation_id INT UNSIGNED NULL AFTER receipt_finance_operation_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND COLUMN_NAME='cash_resolution_id');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD COLUMN cash_resolution_id INT UNSIGNED NULL AFTER expense_finance_operation_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND INDEX_NAME='uk_fepe_event_group');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD UNIQUE KEY uk_fepe_event_group (event_group_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND INDEX_NAME='uk_fepe_employee_movement');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD UNIQUE KEY uk_fepe_employee_movement (employee_movement_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND INDEX_NAME='uk_fepe_receipt_operation');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD UNIQUE KEY uk_fepe_receipt_operation (receipt_finance_operation_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='finance_employee_personal_expenses' AND INDEX_NAME='uk_fepe_expense_operation');
SET @sql = IF(@exists=0, 'ALTER TABLE finance_employee_personal_expenses ADD UNIQUE KEY uk_fepe_expense_operation (expense_finance_operation_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
