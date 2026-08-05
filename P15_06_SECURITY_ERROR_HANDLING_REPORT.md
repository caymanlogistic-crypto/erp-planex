# P15 — Security and Error Handling Report

## Previous behavior

Unexpected database exceptions from linear trip create/edit could reach the browser as raw exception messages. The P14 blocker exposed SQLSTATE, the internal column name and database constraint details.

## Implemented flow

`MutationErrorService` now separates technical evidence from the public response:

- Generates a public correlation ID in format `ERP-YYYYMMDD-HHMMSS-xxxxxxxx`.
- Writes technical data to protected `storage/logs/mutation_errors.log`.
- Redacts context keys matching password, token, secret, cookie, session, authorization or API-key patterns.
- Sends the browser a Russian message explaining that data were not applied and includes only the correlation ID.
- Does not include SQL, table/column names, stack traces, filesystem paths, cookies or credentials in the UI response.

## Error classes kept distinct

- Field/request validation remains attached to the form and preserves entered values.
- Document-upload validation keeps its expected user-facing validation message.
- Permission failures remain HTTP 403 through existing authorization checks.
- Unexpected create/edit failures use the correlation flow.

## Tests

- `tests/p15_mutation_error_service_test.php`: PASS.
- `tests/p15_trip_create_payment_terms_regression_test.php`: confirms raw create/edit unexpected exception messages are absent.
- Complete suite run `31006209539`: 3/3 PASS.
- Release gate run `31004020783`: secret/UTF-8/mojibake/BOM scans PASS.

## Runtime

- Main create and edit runtime produced no SQLSTATE, stack trace or `payment_due_type` leak.
- Console errors: 0.
- Page errors: 0.
- Request failures: 0.
- HTTP 500 during accepted lifecycle: 0.

## Secrets

- Credentials were decrypted only into a temporary Actions environment value and masked.
- Private key files were created with mode 600 and removed in cleanup steps.
- Credentials, cookies, session IDs and storageState were not committed or uploaded.
- Final artifacts must repeat a secret scan before ZIP creation.

## Remaining scope

P16/P17 acceptance must review other mutating controllers for legacy `getMessage()` redirects. P15 changed the trip create/edit paths required to close the confirmed blocker; no claim is made that every historical controller in the repository already uses the new service.
