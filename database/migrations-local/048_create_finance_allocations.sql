-- Migration 048: Create finance_operation_allocations table
-- Idempotent via IF NOT EXISTS / INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

CREATE TABLE IF NOT EXISTS `finance_operation_allocations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation_id` INT UNSIGNED NOT NULL,
    `invoice_id` INT UNSIGNED DEFAULT NULL,
    `linear_route_id` INT UNSIGNED DEFAULT NULL,
    `linear_route_payment_id` INT UNSIGNED DEFAULT NULL,
    `cash_flow_category_id` INT UNSIGNED DEFAULT NULL COMMENT 'DDS справочник — позже',
    `amount` DECIMAL(12,2) NOT NULL,
    `allocation_date` DATE NOT NULL,
    `method` VARCHAR(30) NOT NULL DEFAULT 'manual' COMMENT 'manual, auto_exact, rule',
    `rule_code` VARCHAR(100) DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(50) DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancelled_by_user_id` INT UNSIGNED DEFAULT NULL,
    `cancelled_by_role` VARCHAR(50) DEFAULT NULL,
    `cancel_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_foa_operation` (`operation_id`),
    KEY `idx_foa_invoice` (`invoice_id`),
    KEY `idx_foa_route` (`linear_route_id`),
    KEY `idx_foa_route_payment` (`linear_route_payment_id`),
    KEY `idx_foa_cash_flow_category` (`cash_flow_category_id`),
    KEY `idx_foa_cancelled` (`cancelled_at`),
    KEY `idx_foa_date` (`allocation_date`),
    CONSTRAINT `fk_foa_operation` FOREIGN KEY (`operation_id`) REFERENCES `finance_operations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_foa_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `finance_invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
