-- Migration 072: invoice lifecycle is derived from direction, obligations and settlements.
-- The legacy planned_payment_date column is retained for backward schema compatibility,
-- but no active invoice logic may use it as the source of payment deadlines.

UPDATE finance_invoices
   SET status = CASE
       WHEN direction = 'INCOMING' THEN 'received'
       ELSE 'issued'
   END,
       updated_at = NOW()
 WHERE status = 'draft'
   AND cancelled_at IS NULL;

UPDATE finance_invoices
   SET planned_payment_date = NULL,
       updated_at = NOW()
 WHERE planned_payment_date IS NOT NULL;

ALTER TABLE finance_invoices
    MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'issued'
    COMMENT 'system-managed: issued, received, partially_paid, paid, overdue, overdue_partial, cancelled';