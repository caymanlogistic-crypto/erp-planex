-- Legacy compatibility table: modern route payments store condition data in linear_route_payments.
-- Creation/edit flows still synchronize the first payment row into linear_route_financial_terms.
-- Modern conditions do not always have a 1:1 legacy payment_due_type/payment_due_days_kind value,
-- so these legacy-only columns must allow NULL instead of aborting the whole route transaction.
ALTER TABLE `linear_route_financial_terms`
    MODIFY COLUMN `payment_due_type` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `payment_due_days_kind` VARCHAR(20) NULL DEFAULT NULL;
