-- Migration 046: Create finance_invoices and finance_invoice_links tables
-- Idempotent via IF NOT EXISTS / INFORMATION_SCHEMA checks.

SET @dbname = DATABASE();

-- finance_invoices table
CREATE TABLE IF NOT EXISTS `finance_invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `direction` VARCHAR(20) NOT NULL COMMENT 'OUTGOING, INCOMING',
    `number` VARCHAR(100) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `counterparty_entity_type` VARCHAR(20) DEFAULT NULL COMMENT 'client, contractor',
    `counterparty_entity_id` INT UNSIGNED DEFAULT NULL,
    `counterparty_name` VARCHAR(500) DEFAULT NULL,
    `counterparty_inn` VARCHAR(20) DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `vat_rate` DECIMAL(5,2) DEFAULT NULL COMMENT 'NULL = without VAT',
    `basis` TEXT DEFAULT NULL,
    `planned_payment_date` DATE DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'draft' COMMENT 'draft, issued, received, partially_paid, paid, overdue, cancelled',
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fi_direction` (`direction`),
    KEY `idx_fi_status` (`status`),
    KEY `idx_fi_date` (`invoice_date`),
    KEY `idx_fi_counterparty` (`counterparty_entity_type`, `counterparty_entity_id`),
    KEY `idx_fi_number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- finance_invoice_links table
CREATE TABLE IF NOT EXISTS `finance_invoice_links` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `linear_route_id` INT UNSIGNED DEFAULT NULL,
    `linear_route_payment_id` INT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(12,2) DEFAULT NULL,
    `side` VARCHAR(20) DEFAULT NULL COMMENT 'customer, carrier, principal',
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fil_invoice` (`invoice_id`),
    KEY `idx_fil_route` (`linear_route_id`),
    KEY `idx_fil_payment` (`linear_route_payment_id`),
    KEY `idx_fil_side` (`side`),
    CONSTRAINT `fk_fil_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `finance_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
