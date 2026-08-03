# P13 — Linear trip document save

## Status
`P13_CODE_READY_RUNTIME_OWNER_CHECK_REQUIRED`

## Git
- START_HEAD: `e27a6c33516b07e82f493a5ab9f378ece286bb71`
- Concurrent branch commit observed before push: `29bbedec7068b5ae349ca14d0c0e61e178b8cfa9` (`chore(P12): trigger isolated ERPV2 deployment`)
- FUNCTIONAL_FINAL_HEAD: `ac33e3a24314d02f2bcd32fed95da75e32574027`
- Commit: `fix: restore linear trip document save`
- Push: fast-forward; force push was not used.

## Confirmed root cause
The edit form already used `multipart/form-data`; `ModalShell` also created `new FormData(form)`, so the selected file was not lost by JSON serialization and the `/erpv2` base path was preserved by `form.action`/`app_url`.

The failure was in the old server-side save path plus its frontend error handling:
1. The ordinary edit POST called migration/schema-repair logic.
2. The old upload code moved the PHP temporary upload directly into the permanent tenant directory before document metadata and the surrounding transaction had completed.
3. Metadata/type creation and replacement of old metadata happened after the permanent file move. An exception could therefore produce a 500 and leave filesystem/DB state inconsistent.
4. The frontend `ModalShell` treated every non-2xx response as an opaque failure, removed the active edit form, discarded the backend response body, and displayed only `Не удалось сохранить рейс.`

The observed symptom was therefore a real backend upload/metadata failure path whose detail was hidden by the modal controller, not a missing `enctype`, input-name mismatch, JSON serialization, or hardcoded `/erp` URL.

## Changed files
- `app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php`
- `app/Http/Controllers/Company/LinearTripActions/modal_edit_submit.php`
- `app/Http/Controllers/Company/LinearTripActions/modal_edit_submit_p13.php`
- `app/Service/LinearTripDocumentUploadException.php`
- `app/Service/LinearTripDocumentUploadService.php`
- `app/Service/LinearTripEditSaveService.php`
- `app/Service/LinearTripEditTokenService.php`
- `app/View/partials/company_linear_trip_modal_edit.php`
- `tests/p13_linear_trip_document_save_test.php`
- four P13 report artifacts in `tmp/codex-reports/`

## Corrected request flow
1. GET edit loads the route read-only, applies role/tenant access checks, and issues a one-time save token.
2. The existing multipart form retains predefined file input names and `custom_doc_file[]`.
3. Capture-phase P13 submit uses `new FormData(form)`, `credentials: same-origin`, `form.action`, and JSON response handling.
4. Global entrypoint CSRF injection/verification remains authoritative.
5. POST consumes the one-time token, resolves the current company from session, opens only that company's DB, fetches the route, and rechecks edit access.
6. Normal fields and owner-only finance rows are validated.
7. Files are validated and staged.
8. The route is locked with `SELECT ... FOR UPDATE`.
9. Route, related finance rows, permanent files, metadata, and replacement metadata are processed under one coordinated save.
10. Success returns JSON and reopens the saved trip card; errors keep the modal/form open and display a safe specific message.

## Storage flow
- Shared `DocumentService` whitelist, 20 MB limit, safe filename, MIME validation, and path builders are reused.
- Entity type: `linear_route`.
- Staging: `companies/{company_id}/tmp/linear_route_uploads/{route_id}/{request_token}/`.
- Final: `companies/{company_id}/documents/linear_route/{route_id}/{stored_name}`.
- The old `/erp` path is not used.
- The action performs no migration or DDL workaround.

## Transaction and compensation
- Every upload is validated before DB mutation.
- All submitted files are staged before the transaction; a failure on a later file cleans earlier staged files.
- The route is row-locked.
- New file is moved from staging to its unique final name, then metadata is inserted.
- Existing predefined metadata is soft-deleted only after new metadata exists.
- On any exception: SQL rollback, deletion of every newly moved final file, and staging cleanup.
- Old physical files are retained under the existing soft-delete document policy.
- A one-time request token and disabled/loading button prevent duplicate submits.

## Tenant, role, CSRF
- Current company comes only from session.
- Company must be active.
- Route is fetched from the tenant DB and checked with `LinearRouteService::canEditRoute`.
- Allowed roles remain `company_owner`, `senior_logist`, `logist`; finance edits remain owner-only.
- The route ID alone is insufficient.
- CSRF is enforced by `startCsrfFormInjection()` and `verifyCsrfRequest()` in the application entrypoint.

## Tests
Local reconstructed checks:
- PHP lint for all changed PHP files: PASS.
- P13 regression contract: `TOTAL=38; FAILED=0`.
- Synthetic valid PDF staging in tenant temp path: PASS.
- Forbidden extension rejection: PASS.
- UTF-8/BOM/mojibake scan: PASS.
- Secret-pattern scan: PASS.
- Mutating production DB tests: NOT RUN.

The committed regression test covers multipart/input naming, route/auth/CSRF contracts, tenant scope, role checks, no migrations/DDL in request, `linear_route`, 20 MB/shared validation, staging order, metadata, replacement order, compensation, idempotency, FormData/base path, loading/double-click protection, specific errors, selected-file removal, success refresh, and no hardcoded old ERP path.

## GitHub Actions and deploy
`fetch_commit_workflow_runs` returned no runs for `ac33e3a24314d02f2bcd32fed95da75e32574027`. The available connector only exposes pull-request-triggered runs on the first page, so push workflow/autodeploy status cannot be established from this result.

- AUTODEPLOY_RUN: `NOT_OBSERVABLE`
- AUTODEPLOY_STATUS: `NOT_OBSERVABLE`
- Production runtime: not verified.

## Owner runtime check required
See `P13_owner_runtime_checklist.md`.

## Remaining risks
- No authenticated browser session was available, so live `/erpv2` runtime and storage permissions are owner checks.
- A process-level hard crash between filesystem rename and PHP compensation can still leave an orphan file; ordinary exceptions/rollback are compensated.
- Existing architecture intentionally retains physical files for soft-deleted/replaced documents.
- Push-triggered Actions/autodeploy could not be observed through the connector.
