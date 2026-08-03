# P12 Finance Operation Actions Report

## Status
`P12_CODE_READY_RUNTIME_OWNER_CHECK_REQUIRED`

## Repository
- Start HEAD: `2b61d9dcd32efe3a861341da7939b26a7cd02499`
- Final HEAD: commit containing this report; exact SHA is in the final agent response.
- Branch: `chatgpt/production-stabilization-20260802`
- `/erp` was not changed.

## Root cause
The modal and allocation form were inserted with `innerHTML`, while their handlers were inside inline `<script>` tags in those fragments. Such scripts are not executed, so allocate, cancel, history and form submit were silent no-ops.

Additional defects: GET actions ran migrations; session role lookup was inconsistent; AJAX errors were HTTP 200 HTML; duplicate submits had no request token; operation history omitted allocation audit rows; operation cancellation could implicitly cancel allocations through a nested transaction path.

## Implemented workflow
Static delegated JavaScript now owns all dynamic modal events. URLs are base-path-aware. Allocation uses a one-time session token, POST, CSRF, company-owner guard, session-derived tenant and the existing transactional allocation service. Successful actions reload the modal, table state and history.

Cancellation opens a separate confirmation populated with operation details. `FinanceOperationActionService` locks the operation, validates the transition and reason, blocks cancellation while active allocations exist, updates status transactionally, records audit history and treats repeated cancellation as idempotent. Bank transactions and statement imports are not deleted.

History combines operation audit rows and linked allocation audit rows. GET endpoints no longer invoke migrations. Errors do not expose PDO/SQL/credentials.

## Status rules
Allocation states are `unallocated`, `partially_allocated`, `allocated`. Allocate is enabled only for `POSTED` operations with positive remainder. Cancel is enabled only for `POSTED` or `PENDING_CONFIRMATION` with no active allocations. Other states render disabled actions with explanations.

## Security
All mutation endpoints are POST-only, require `company_owner`, CSRF and a tenant resolved from the authenticated session. Logist is blocked on the backend.

## Tests
`tests/p12_finance_operation_actions_test.php` contains 30 regression contract checks covering DOM operation id, delegated handlers, `/erpv2` base path, role/CSRF guards, validations, status rules, bank/import preservation, idempotency, history, no GET migrations, safe errors, duplicate request token and disabled states.

## Runtime
Authenticated production runtime was not available. Owner verification is required using `P12_owner_runtime_checklist.md`.

## Remaining risk
A synthetic MySQL integration suite is still desirable for live transaction/row-lock behavior. Production acceptance depends on an authenticated owner session and a safe tenant operation.
