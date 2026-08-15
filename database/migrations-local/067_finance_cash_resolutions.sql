-- Migration 067: resolve technical cash inflows to their final destination.
-- This table stores traceability/idempotency only. Monetary movements remain in finance_operations
-- and employee settlements remain in finance_employee_movements.

CREATE TABLE IF NOT EXISTS `finance_cash_resolutions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_finance_operation_id` INT UNSIGNED NOT NULL,
    `resolution_type` VARCHAR(32) NOT NULL COMMENT 'EMPLOYEE; future destinations are additive',
    `target_identity_type` VARCHAR(32) DEFAULT NULL,
    `target_identity_id` INT UNSIGNED DEFAULT NULL,
    `target_name_snapshot` VARCHAR(255) DEFAULT NULL,
    `outflow_finance_operation_id` INT UNSIGNED NOT NULL,
    `employee_movement_id` INT UNSIGNED DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fcr_source_operation` (`source_finance_operation_id`),
    UNIQUE KEY `uk_fcr_outflow_operation` (`outflow_finance_operation_id`),
    UNIQUE KEY `uk_fcr_employee_movement` (`employee_movement_id`),
    KEY `idx_fcr_resolution_target` (`resolution_type`, `target_identity_type`, `target_identity_id`),
    CONSTRAINT `fk_fcr_source_operation` FOREIGN KEY (`source_finance_operation_id`) REFERENCES `finance_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fcr_outflow_operation` FOREIGN KEY (`outflow_finance_operation_id`) REFERENCES `finance_operations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fcr_employee_movement` FOREIGN KEY (`employee_movement_id`) REFERENCES `finance_employee_movements` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;