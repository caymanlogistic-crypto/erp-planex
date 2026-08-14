-- Migration 065: employee settlements over real finance operations.
-- Additive tenant-local schema. Money remains sourced from finance_operations.

CREATE TABLE IF NOT EXISTS `finance_employee_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_user_id` INT UNSIGNED NOT NULL,
    `movement_type` VARCHAR(20) NOT NULL COMMENT 'PAYMENT, RETURN',
    `source_type` VARCHAR(20) NOT NULL COMMENT 'BANK, CASH',
    `finance_operation_id` INT UNSIGNED NOT NULL,
    `bank_transaction_id` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(1000) DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fem_finance_operation` (`finance_operation_id`),
    UNIQUE KEY `uk_fem_bank_transaction` (`bank_transaction_id`),
    KEY `idx_fem_employee_created` (`employee_user_id`, `created_at`),
    KEY `idx_fem_type_source` (`movement_type`, `source_type`),
    CONSTRAINT `fk_fem_employee` FOREIGN KEY (`employee_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fem_operation` FOREIGN KEY (`finance_operation_id`) REFERENCES `finance_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fem_bank_tx` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
