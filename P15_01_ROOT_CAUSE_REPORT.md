# P15 — Root Cause Report

## Confirmed P14 failure

The deployed P14 baseline failed after a valid UI submit with:

`SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'payment_due_type' cannot be null`

The same exception text was rendered directly in the user interface.

## Root-cause chain

`POST /company/trips/linear/create`
→ `LinearTripActions/create_submit.php`
→ modern payment form mapping (`condition_type`, `days_count`, `days_kind`, `specific_due_date`)
→ `linear_routes` insert and legacy finance rows
→ legacy persistence read `payment_due_type`, `payment_due_days`, `payment_due_days_kind`
→ create payload did not populate those compatibility keys
→ SQL received `NULL` for a non-null legacy column.

The edit path had already gained a compatibility mapper in earlier base-branch P15 commits, but the create path still used an incomplete payload. This explained why static edit tests passed while a new trip failed in production runtime.

## Canonical compatibility decision

The modern condition model remains canonical. Legacy fields are populated until the compatibility schema is retired:

- `prepayment`, `start_day` → `Предоплата на загрузке`
- `after_start`, `specific_date` → `После загрузки`
- `end_day` → `До выгрузки`
- `after_end`, `after_documents` → `После выгрузки`

Days count and calendar/working-day kind are retained in both modern and legacy persistence fields. The fix does **not** make `payment_due_type` nullable and does not remove a business field from the form.

## Production correction

Production commit:

`f6688855fa3c5e58ee690ca4c8a09e2f0a257e7c`

Commit message:

`P15: fix trip creation, payment terms and safe error handling`

Changed production behavior:

1. `LinearRouteService::legacyPaymentDueTypeFromConditionType()` is the single modern-to-legacy mapping.
2. Create payload writes legacy due type/days/kind.
3. Edit save delegates to the same mapping.
4. Unexpected create/edit exceptions are logged with a correlation ID.
5. The UI receives a safe Russian message and never receives raw SQL/stack/path details.
6. Validation and expected document-upload messages remain field/business messages rather than being converted into generic failures.

## Regression evidence

- `tests/p15_trip_create_payment_terms_regression_test.php`
- `tests/p15_mutation_error_service_test.php`
- `tests/p15_linear_trip_executor_carrier_save_test.php`
- Complete PHP 8.3 run: `31006209539`, job `92306607175`, artifact `8930397682`
- Result: 3/3 tests PASS.
- Release gate: `31004020783`, job `92299441250`, PASS.

## Production runtime evidence

- Deploy: run `31004254613`, job `92300192372`.
- Deployed SHA: `8c7571c01bcd87f42c4f2e9300fc52a2499935fe`.
- New UI trip ID: `3`.
- Trip create runtime: run `31004709154`, artifact `8929805480`, PASS.
- Full trip lifecycle: run `31005836928`, job `92305372614`, artifact `8930280906`, PASS.
- SQL leak: false.
- Console/page/request failures: 0.

## Lifecycle scope confirmed by source

`linear_routes` uses `status=active` and `deleted_at` as its current canonical lifecycle. Map-route states such as `executor_found` and `pickup_started` belong to another `routes` contour. P15 therefore did not invent or copy unrelated statuses into `linear_routes`. Its verified lifecycle is:

create → reopen → edit → financial terms → actual dates → document upload/view/download/replace → role visibility → soft-delete → SUPERADMIN deleted-data → restore.
