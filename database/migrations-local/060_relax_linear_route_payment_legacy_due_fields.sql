-- Migration 060: make legacy-only route payment due fields nullable.
-- The active payment condition model is stored in condition_type/days_count/days_kind/specific_due_date.
-- Some modern condition types have no exact legacy payment_due_type equivalent, so legacy columns must not abort inserts.
ALTER TABLE `linear_route_payments`
    MODIFY COLUMN `payment_due_type` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days_kind` VARCHAR(20) NULL DEFAULT NULL;
