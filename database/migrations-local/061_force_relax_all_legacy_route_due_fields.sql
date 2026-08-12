-- Force legacy due fields to accept NULL in both route-payment compatibility tables.
-- Modern payment timing is stored in condition_type/days_count/days_kind/specific_due_date.
-- This migration intentionally uses a new filename so tenants with stale 059/060 journal state
-- receive the schema correction on the next POST migration pass.
ALTER TABLE `linear_route_financial_terms`
    MODIFY COLUMN `payment_due_type` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days` INT NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days_kind` VARCHAR(20) NULL DEFAULT NULL;

ALTER TABLE `linear_route_payments`
    MODIFY COLUMN `payment_due_type` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days` INT NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days_kind` VARCHAR(20) NULL DEFAULT NULL;
