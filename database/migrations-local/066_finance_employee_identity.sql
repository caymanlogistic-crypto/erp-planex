-- Migration 066: unify employee identity across central company_users and tenant users.
-- Existing movements are tenant-user movements and are backfilled without changing money data.

ALTER TABLE `finance_employee_movements`
    DROP FOREIGN KEY `fk_fem_employee`;

ALTER TABLE `finance_employee_movements`
    MODIFY COLUMN `employee_user_id` INT UNSIGNED NULL,
    ADD COLUMN `employee_identity_type` VARCHAR(20) NOT NULL DEFAULT 'TENANT_USER' AFTER `employee_user_id`,
    ADD COLUMN `employee_identity_id` INT UNSIGNED NULL AFTER `employee_identity_type`,
    ADD COLUMN `employee_name_snapshot` VARCHAR(255) NULL AFTER `employee_identity_id`,
    ADD COLUMN `employee_role_snapshot` VARCHAR(50) NULL AFTER `employee_name_snapshot`;

UPDATE `finance_employee_movements` fem
JOIN `users` u ON u.id = fem.employee_user_id
SET fem.employee_identity_type = 'TENANT_USER',
    fem.employee_identity_id = fem.employee_user_id,
    fem.employee_name_snapshot = u.full_name,
    fem.employee_role_snapshot = u.role_code
WHERE fem.employee_identity_id IS NULL;

ALTER TABLE `finance_employee_movements`
    ADD KEY `idx_fem_employee_identity` (`employee_identity_type`, `employee_identity_id`, `created_at`),
    ADD CONSTRAINT `fk_fem_employee` FOREIGN KEY (`employee_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
