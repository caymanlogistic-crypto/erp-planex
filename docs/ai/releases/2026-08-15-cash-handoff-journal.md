# Cash employee handoff journal fix — 2026-08-15

## Problem

Employee dispatch from the technical Main Cash copied the source purchase date into the new cash outflow and repeated the employee name inside the purpose. The cash journal also mixed source and recipient in one column. After a multi-row dispatch, new outflows could push older rows onto page 2 while pagination controls were not visible, making rows appear missing.

## Fix

- New employee-dispatch outflows use the factual dispatch date, captured once per batch.
- New outflow purpose preserves the original source purpose without embedding the employee name.
- Existing historical handoff rows are rendered from immutable resolution provenance: handoff date from `finance_cash_resolutions.created_at`, source purpose from the source operation, employee from the resolution snapshot.
- Cash journal now has separate `Источник`, `Кому передано`, and `Назначение` columns.
- Employee-resolution outflows are labelled `Передача сотруднику`.
- Default cash journal page size is 50, and explicit previous/next pagination is shown when more pages exist.
- No historical finance rows are rewritten or deleted.

## Regression coverage

`finance_cash_ledger_test.php` verifies source/recipient separation, restoration of legacy handoff display data, factual handoff date, clean purpose, unresolved-source selection, and reachability of every row across pagination.

Existing `finance_cash_resolution_test.php` continues to verify atomic batch resolution, provenance, tenant/employee isolation and fail-closed repeated dispatch.
