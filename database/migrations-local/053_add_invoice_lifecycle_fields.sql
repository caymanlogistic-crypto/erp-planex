-- Migration 053: Add invoice lifecycle fields and cascade support
-- Adds first_paid_at, fully_paid_at, cancelled_at, cancellation_reason
-- Extends invoice status enum to include 'overdue_partial'
-- Adds FK from allocations to linear_route_payments for referential integrity
-- Idempotent via INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

-- Add first_paid_at column
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_invoices' AND COLUMN_NAME = 'first_paid_at') > 0,
    'SELECT 1',
    'ALTER TABLE `finance_invoices` ADD COLUMN `first_paid_at` DATE DEFAULT NULL AFTER `paid_amount`'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add fully_paid_at column
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_invoices' AND COLUMN_NAME = 'fully_paid_at') > 0,
    'SELECT 1',
    'ALTER TABLE `finance_invoices` ADD COLUMN `fully_paid_at` DATE DEFAULT NULL AFTER `first_paid_at`'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add cancelled_at column
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_invoices' AND COLUMN_NAME = 'cancelled_at') > 0,
    'SELECT 1',
    'ALTER TABLE `finance_invoices` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `fully_paid_at`'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add cancellation_reason column
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_invoices' AND COLUMN_NAME = 'cancellation_reason') > 0,
    'SELECT 1',
    'ALTER TABLE `finance_invoices` ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `cancelled_at`'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add overdue_partial as valid status comment (column already VARCHAR, no ALTER needed)
-- The status value 'overdue_partial' is handled in application code, not as DB enum

-- Add FK from finance_operation_allocations.linear_route_payment_id to linear_route_payments.id
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_foa_route_payment');

SET @s = IF(@fk_exists = 0,
    'ALTER TABLE `finance_operation_allocations` ADD CONSTRAINT `fk_foa_route_payment` FOREIGN KEY (`linear_route_payment_id`) REFERENCES `linear_route_payments` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index on linear_route_payment_id for allocation lookups
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operation_allocations' AND INDEX_NAME = 'idx_foa_route_payment_id');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `finance_operation_allocations` ADD INDEX `idx_foa_route_payment_id` (`linear_route_payment_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index on allocation cancelled_at + linear_route_payment_id for performance
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'finance_operation_allocations' AND INDEX_NAME = 'idx_foa_payment_cancelled');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `finance_operation_allocations` ADD INDEX `idx_foa_payment_cancelled` (`linear_route_payment_id`, `cancelled_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
