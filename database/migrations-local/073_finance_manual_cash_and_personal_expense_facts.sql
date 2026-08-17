-- Migration 073: manual client cash receipts and employee personal-funded expense facts.
-- Client cash is real company money and is linked to a route/payment allocation.
-- Employee personal-funded expenses are economic facts only: they do NOT create
-- finance_operations, cash/bank movements or employee settlement balances.

-- A client cash receipt resolves the business meaning of an incoming Main Cash
-- operation without moving that money out of the cashbox. Existing employee
-- resolutions still have an outflow; NULL is reserved for non-monetary resolution.
ALTER TABLE `finance_cash_resolutions`
    MODIFY COLUMN `outflow_finance_operation_id` INT UNSIGNED DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `finance_cash_route_receipts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `finance_operation_id` INT UNSIGNED NOT NULL,
    `finance_allocation_id` INT UNSIGNED NOT NULL,
    `linear_route_id` INT UNSIGNED NOT NULL,
    `linear_route_payment_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fcrr_operation` (`finance_operation_id`),
    UNIQUE KEY `uk_fcrr_allocation` (`finance_allocation_id`),
    KEY `idx_fcrr_route` (`linear_route_id`, `linear_route_payment_id`),
    KEY `idx_fcrr_client` (`client_id`),
    CONSTRAINT `fk_fcrr_operation` FOREIGN KEY (`finance_operation_id`) REFERENCES `finance_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fcrr_allocation` FOREIGN KEY (`finance_allocation_id`) REFERENCES `finance_operation_allocations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_employee_personal_expenses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation_date` DATE NOT NULL,
    `employee_identity_type` VARCHAR(30) NOT NULL,
    `employee_identity_id` INT UNSIGNED NOT NULL,
    `employee_name_snapshot` VARCHAR(255) NOT NULL,
    `employee_role_snapshot` VARCHAR(50) DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'RUR',
    `cash_flow_center_id` INT UNSIGNED NOT NULL,
    `dds_category_id` INT UNSIGNED NOT NULL,
    `linear_route_id` INT UNSIGNED DEFAULT NULL,
    `counterparty_name` VARCHAR(500) DEFAULT NULL,
    `purpose` VARCHAR(1000) NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancelled_by_user_id` INT UNSIGNED DEFAULT NULL,
    `cancelled_by_role` VARCHAR(20) DEFAULT NULL,
    `cancel_reason` VARCHAR(1000) DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fepe_date_status` (`operation_date`, `status`),
    KEY `idx_fepe_employee` (`employee_identity_type`, `employee_identity_id`, `operation_date`),
    KEY `idx_fepe_cfu_dds` (`cash_flow_center_id`, `dds_category_id`, `operation_date`),
    KEY `idx_fepe_route` (`linear_route_id`, `operation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;