-- Migration 069: fold employee matching-rule cash chains into one resolved cash lifecycle.
-- Safe/idempotent backfill for rows created by employee_cash_settlement before explicit
-- finance_cash_resolutions registration was added.
--
-- PAYMENT: bank -> cash -> employee
--   canonical source = incoming cash transfer, outflow = employee cash expense.
-- RETURN: employee -> cash -> bank
--   canonical source = employee cash income, outflow = cash transfer to bank.

INSERT INTO finance_cash_resolutions
    (source_finance_operation_id,
     resolution_type,
     target_identity_type,
     target_identity_id,
     target_name_snapshot,
     outflow_finance_operation_id,
     employee_movement_id,
     created_by_user_id,
     created_by_role)
SELECT
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN cash_transfer.id
        ELSE employee_op.id
    END AS source_finance_operation_id,
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN 'EMPLOYEE'
        ELSE 'BANK'
    END AS resolution_type,
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_identity_type
        ELSE 'MONEY_ACCOUNT'
    END AS target_identity_type,
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_identity_id
        ELSE cash_transfer.transfer_account_id
    END AS target_identity_id,
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_name_snapshot
        ELSE COALESCE(NULLIF(bank_account.name, ''), 'Расчётный счёт')
    END AS target_name_snapshot,
    CASE
        WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN employee_op.id
        ELSE cash_transfer.id
    END AS outflow_finance_operation_id,
    fem.id AS employee_movement_id,
    NULL AS created_by_user_id,
    'system' AS created_by_role
FROM finance_employee_movements fem
JOIN bank_transactions bt
  ON bt.id = fem.bank_transaction_id
 AND COALESCE(bt.is_internal_transfer, 0) = 1
 AND bt.linked_cash_transaction_id IS NOT NULL
JOIN finance_operations employee_op
  ON employee_op.id = fem.finance_operation_id
 AND employee_op.status = 'POSTED'
 AND employee_op.source = 'CASH'
JOIN finance_operations cash_transfer
  ON cash_transfer.id = bt.linked_cash_transaction_id
 AND cash_transfer.status = 'POSTED'
 AND cash_transfer.operation_type = 'TRANSFER'
 AND cash_transfer.source = 'TRANSFER'
 AND cash_transfer.money_account_id = employee_op.money_account_id
LEFT JOIN finance_money_accounts bank_account
  ON bank_account.id = cash_transfer.transfer_account_id
LEFT JOIN finance_cash_resolutions source_existing
  ON source_existing.source_finance_operation_id = CASE
       WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN cash_transfer.id
       ELSE employee_op.id
     END
LEFT JOIN finance_cash_resolutions outflow_existing
  ON outflow_existing.outflow_finance_operation_id = CASE
       WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN employee_op.id
       ELSE cash_transfer.id
     END
LEFT JOIN finance_cash_resolutions movement_existing
  ON movement_existing.employee_movement_id = fem.id
WHERE UPPER(fem.movement_type) IN ('PAYMENT', 'RETURN')
  AND employee_op.classification_rule_id IS NOT NULL
  AND cash_transfer.classification_rule_id = employee_op.classification_rule_id
  AND (
       (UPPER(fem.movement_type) = 'PAYMENT'
        AND employee_op.operation_type = 'EXPENSE'
        AND LOWER(COALESCE(cash_transfer.transfer_direction, '')) = 'in')
       OR
       (UPPER(fem.movement_type) = 'RETURN'
        AND employee_op.operation_type = 'INCOME'
        AND LOWER(COALESCE(cash_transfer.transfer_direction, '')) = 'out')
      )
  AND source_existing.id IS NULL
  AND outflow_existing.id IS NULL
  AND movement_existing.id IS NULL;
