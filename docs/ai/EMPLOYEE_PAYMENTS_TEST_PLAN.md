# Employee Payments — acceptance and regression plan

Status: mandatory gate for the ERP PLANEX employee settlement module.

## Business definition

An employee in this module is any active ERP user belonging to the current forwarding company. Role is not a filter.

The ERP currently has two factual user stores and both are part of the employee directory:

- central `company_users` — company owners / management users tied to the current `company_id`;
- tenant-local `users` — logist, senior_logist and other tenant users.

Therefore an active company owner and an active logist must both be selectable in employee payments. Foreign-company, inactive and deleted users must never be selectable. Historical movements remain visible even when the associated account later becomes inactive.

Finance employee identities are explicit and collision-safe:

- `COMPANY_USER:<id>` for central `company_users`;
- `TENANT_USER:<id>` for tenant `users`.

No shadow/fake owner account may be created in tenant `users` merely to make finance work.

## Architectural invariants

1. Employee settlement never creates a parallel money ledger.
2. CASH settlement uses `FinanceCashService` and a real `finance_operations` row.
3. BANK settlement links an already posted bank-backed `finance_operations` row; it must not create a second financial operation.
4. One `finance_operation_id` may belong to at most one employee movement.
5. One `bank_transaction_id` may belong to at most one employee movement.
6. Bank → Cash internal transfers can never be employee payments.
7. PAYMENT direction: CASH expense or bank debit. RETURN direction: CASH income or bank credit.
8. Correcting the employee changes only employee-identity fields in `finance_employee_movements`; amount/date/source/bank transaction/finance operation are not rewritten.
9. Employee correction is transactional and audited.
10. Cancelled finance operations do not affect current employee balance but remain visible in employee history.
11. Money calculations use exact decimal/cents semantics; no floating-point arithmetic is permitted for employee balances.
12. Every mutation is company-owner-only, POST, CSRF-protected and tenant-local through `companyDatabaseConfig()`.
13. Central and tenant numeric IDs may collide; identity type is therefore mandatory and must never be inferred from the number alone when reading persisted movements.
14. Existing migration-065 tenant-user movements must be backfilled without altering their linked financial operations.

## Automated behavioral regression

`tests/finance_employee_payments_test.php` runs against an isolated MySQL 8 service in `Finance Matching Regression`.

Mandatory scenarios:

- active current-company central owner is selectable;
- active tenant logist is selectable;
- senior logist and another active tenant role are selectable;
- foreign-company central owner is excluded;
- inactive central owner is excluded;
- inactive/deleted tenant user is excluded;
- a central owner movement does not create a fake tenant `users` row;
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
- an unlinked bank transaction can be linked again to another valid employee identity;
- movement can be reassigned between central and tenant employee identities without changing money fields;
- reassignment writes finance audit log;
- employee with only cancelled movements remains accessible in summaries/history;
- cancelled movements contribute zero to current balance;
- unlinked posted bank debit appears among PAYMENT candidates;
- linked/internal bank transactions do not appear among candidates;
- BANK/CASH filters preserve expected rows;
- one employee movement per finance operation invariant remains true.

The regression passes only when `FINANCE_EMPLOYEE_PAYMENTS_BEHAVIOR_OK` is emitted and the shell is fail-closed with `set -euo pipefail`.

## Schema gate

Migration `066_finance_employee_identity.sql` must be applied to active tenant schemas before the new employee-directory runtime is accepted. It must:

- preserve existing `finance_employee_movements` rows;
- backfill existing rows as `TENANT_USER` identities;
- allow `employee_user_id` to be NULL for central `COMPANY_USER` identities;
- add employee identity type/id plus name/role snapshots;
- preserve existing unique guards on finance operation and bank transaction.

P41 migration verification must check the exact migration checksum and required columns before browser acceptance.

## Production read-only acceptance after deploy

Production verification must be performed only after checking the deployed marker equals the expected canonical SHA and migration 066 has been verified.

Required OWNER-browser checks in Chromium/Playwright:

1. `/company/finance/employee-payments` returns HTTP 200 and no page/console/fatal errors.
2. Page title is `Выплаты сотрудникам`.
3. `+ Выплата` and `+ Возврат` controls exist.
4. Employee filter contains at least `Все сотрудники`, current-company owner and tenant logist.
5. New-payment selector contains at least current-company owner and tenant logist.
6. Selector contains both `COMPANY_USER:*` and `TENANT_USER:*` identities.
7. CASH and BANK source choices are present.
8. The obsolete visible hint `Двойной клик — полный журнал...` is absent; double-click journal behavior itself remains functional.
9. Existing employee row opens the full ledger by double click.
10. Ledger exposes cancelled history and the employee correction control.
11. Bank transaction details expose employee settlement block for non-internal transfers and the same combined employee directory.
12. Existing employee-bank links show unlink control; unlinked bank rows show link control.
13. No production financial mutation is performed by runtime acceptance itself.

## Release gate

The module is considered production-ready only when all of the following hold on one exact SHA:

- PHP syntax: PASS;
- Finance Matching Regression: PASS;
- employee MySQL behavioral regression: PASS;
- migration 066 shape/checksum gate: PASS;
- ERP PLANEX CI: PASS;
- Controlled Deploy: PASS;
- deployed marker equals expected SHA: PASS;
- tenant migration 066 applied/verified: PASS;
- P41/P42/P44 production finance runtime acceptance: PASS;
- no production financial test movements are created;
- legacy `/erp` remains untouched.
