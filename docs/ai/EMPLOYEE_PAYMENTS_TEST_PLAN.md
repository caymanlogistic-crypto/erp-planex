# Employee Payments — acceptance and regression plan

Status: mandatory gate for the ERP PLANEX employee settlement module.

## Business definition

An employee in this module is any active ERP user in the current forwarding-company tenant. Role is not a filter: company owners, logistics users, senior logistics users and any other active company users are all eligible. Inactive/deleted users remain visible in history when they already have movements, but cannot receive new or reassigned movements.

## Architectural invariants

1. Employee settlement never creates a parallel money ledger.
2. CASH settlement uses `FinanceCashService` and a real `finance_operations` row.
3. BANK settlement links an already posted bank-backed `finance_operations` row; it must not create a second financial operation.
4. One `finance_operation_id` may belong to at most one employee movement.
5. One `bank_transaction_id` may belong to at most one employee movement.
6. Bank → Cash internal transfers can never be employee payments.
7. PAYMENT direction: CASH expense or bank debit. RETURN direction: CASH income or bank credit.
8. Correcting the employee changes only `finance_employee_movements.employee_user_id`; amount/date/source/bank transaction/finance operation are not rewritten.
9. Employee correction is transactional and audited.
10. Cancelled finance operations do not affect current employee balance but remain visible in the employee history.
11. Money calculations use exact decimal/cents semantics; no floating-point arithmetic is permitted for balances.
12. Every mutation is company-owner-only, POST, CSRF-protected and tenant-local through `companyDatabaseConfig()`.

## Automated behavioral regression

`tests/finance_employee_payments_test.php` runs against an isolated MySQL 8 service in `Finance Matching Regression`.

Mandatory scenarios:

- all active tenant users are selectable regardless of role;
- inactive/deleted user is not selectable for a new movement;
- CASH PAYMENT creates one POSTED cash finance operation plus one employee movement;
- CASH RETURN creates one POSTED cash finance operation plus one employee movement;
- insufficient CASH balance rejects PAYMENT and leaves no partial movement;
- decimal precision check (`100.10 - 0.20 = 99.90`) is exact;
- BANK PAYMENT links an existing POSTED bank finance operation;
- BANK RETURN links an existing POSTED bank finance operation;
- wrong bank direction is rejected;
- internal bank transfer is rejected;
- duplicate bank link is rejected;
- unlink removes only employee relation, never the bank/finance operation;
- an unlinked bank transaction can be linked again;
- movement can be reassigned to another active employee without changing money fields;
- reassignment to inactive user is rejected;
- reassignment writes finance audit log;
- employee with only cancelled movements remains accessible in summaries/history;
- cancelled movements contribute zero to current balance;
- unlinked posted bank debit appears among PAYMENT candidates;
- linked/internal bank transactions do not appear among candidates;
- BANK/CASH filters preserve expected rows;
- one employee movement per finance operation invariant remains true.

The regression passes only when `FINANCE_EMPLOYEE_PAYMENTS_BEHAVIOR_OK` is emitted and the shell is fail-closed with `set -euo pipefail`.

## Production read-only acceptance after deploy

Production verification must be performed only after checking the deployed marker equals the expected canonical SHA.

Required OWNER-browser checks in Chromium/Playwright:

1. `/company/finance/employee-payments` returns HTTP 200 and no page/console/fatal errors.
2. Page title is `Выплаты сотрудникам`.
3. `+ Выплата` and `+ Возврат` controls exist.
4. Employee selector includes active users without role-based filtering.
5. CASH and BANK source choices are present.
6. Existing employee row opens the full ledger by double click.
7. Ledger exposes cancelled history and the employee correction control.
8. Bank transaction details expose employee settlement block for non-internal transfers.
9. Existing employee-bank links show unlink control; unlinked bank rows show link control.
10. No production mutation is performed by the runtime acceptance itself.

## Release gate

The module is considered production-ready only when all of the following hold on one exact SHA:

- PHP syntax: PASS;
- Finance Matching Regression: PASS;
- employee MySQL behavioral regression: PASS;
- ERP PLANEX CI: PASS;
- Controlled Deploy: PASS;
- deployed marker equals expected SHA: PASS;
- P41/P42/P44 production finance runtime acceptance: PASS;
- no migration is required for this completion patch;
- legacy `/erp` remains untouched.
