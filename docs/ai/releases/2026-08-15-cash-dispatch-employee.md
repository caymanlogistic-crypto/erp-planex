# 2026-08-15 — Technical cash → employee dispatch

## Scope
- `Основная касса` is treated as a technical clearing buffer; normal unresolved state is zero.
- Cash journal exposes selectable unresolved incoming source rows.
- Owner can batch-select rows and dispatch them to one active employee.
- Dispatch creates real CASH expense operations plus authoritative `finance_employee_movements`; source history remains immutable.
- `finance_cash_resolutions` stores one-to-one traceability and idempotency between source cash inflow and resulting employee movement/outflow.
- Both `COMPANY_USER:*` and `TENANT_USER:*` employee identities are supported; foreign/inactive identities fail closed.
- Cash sidebar badge counts unresolved technical-cash sources, analogous to bank statement attention count.
- Carrier action is visible but disabled; no carrier mutation path is introduced in this release.

## Safety / regression
- Batch dispatch is transactional.
- One source can be resolved only once.
- Invalid mixed batches roll back fully.
- Existing matching, Bank→Cash idempotency, bank reconciliation, invoices and allocations are unchanged.
- Production acceptance: P49 Cash Resolution Runtime is read-only and never submits a dispatch form.
