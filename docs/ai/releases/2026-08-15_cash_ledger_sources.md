# 2026-08-15 — Cash ledger source/recipient UX

## Change
- `/company/finance/cash` now shows only the CASH side of each movement; the bank-side half of an internal transfer is no longer duplicated in the cash journal.
- The journal uses business labels `Поступление` / `Списание` instead of technical `Перевод (входящий/исходящий)`.
- Added `Источник / Получатель`:
  - company account name for internal transfers Bank ↔ Cash;
  - employee snapshot name for employee cash payment/return movements.
- Cash balances and finance records are unchanged; this is a read-only presentation projection.

## Regression protection
- `tests/finance_cash_ledger_test.php` verifies cash-only scoping, Bank ↔ Cash counterpart names, employee source/recipient names and pagination.
- `P48 Cash Ledger Runtime` performs fail-closed read-only Chromium acceptance on production.
