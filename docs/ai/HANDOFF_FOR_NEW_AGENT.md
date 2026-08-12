# ERP PLANEX — handoff for the next agent

Updated: 2026-08-12

## 1. Canonical product state

ERP PLANEX is a PHP/MySQL multi-tenant logistics ERP. Production runtime is `https://plan-ex.ru/erpv2/`. The old `/erp` is legacy and must remain untouched.

Canonical/default branch: `chatgpt/production-stabilization-20260802`.

Repository stabilization status: `HANDOFF_READY_AUTO_DEPLOY_ACTIVE`. Parallel P26–P40 work has been normalized into a single development/deployment line. The canonical branch contains the production functionality accumulated through P39/P40, including multi-driver crews, trip form/view improvements, finance controls, route points and hardened local migration handling.

The previous default-branch state is preserved as `backup/pre-stabilization-default-20260812` for recovery evidence only.

## 2. Repository control plane

There are exactly two workflow files in the canonical branch:

- `.github/workflows/ci.yml` — automatic engineering gate on push/PR;
- `.github/workflows/erpv2_controlled_deploy.yml` — the only production-capable workflow.

The production path is now deliberately single-agent and automatic only after a green canonical CI:

`push to chatgpt/production-stabilization-20260802` -> `ERP PLANEX CI` -> `workflow_run` with `conclusion=success`, original event=`push`, exact canonical branch -> `ERPv2 Controlled Deploy`.

No direct push-triggered deployment exists. The deploy workflow receives the exact `workflow_run.head_sha`, verifies it as a real 40-character commit, checks it out detached, builds the artifact from exactly that SHA, creates a server backup, deploys through the proven P07 engine, checks the deployed marker, preserves `.env` and runtime directories, verifies that old `/erp` and DB fingerprints did not change, and requires `/erpv2/login` HTTP 200.

The same workflow retains `workflow_dispatch` as a manual exact-SHA fallback. Manual deployment requires the exact source SHA and literal confirmation `DEPLOY_ERPV2`.

Standard application deployment does NOT run database migrations. Schema changes require a separate explicit migration step with tenant journal verification.

Temporary P24–P40 deploy/reconcile workflows remain removed. Do not restore them and do not create a second production path.

## 3. Verified automatic deployment baseline

Guarded auto-deploy activation was validated on 2026-08-12.

Canonical CI:

- run: `31595645852`
- SHA: `e387b2d206c84295392ec13c6630a11acf9502e5`
- result: `SUCCESS`

Automatically triggered deployment:

- workflow: `ERPv2 Controlled Deploy`
- run: `31595690419`
- job: `94110512307`
- event: `workflow_run`
- source SHA: `e387b2d206c84295392ec13c6630a11acf9502e5`
- result: `SUCCESS`

Verified deploy steps include exact-SHA checkout, artifact build, SSH setup, pre-deploy backup, P07 deployment, marker equality, HTTP verification, DB fingerprint equality, old `/erp` fingerprint equality, `.env` equality, runtime directory preservation and evidence artifact upload.

The CI gate passed PHP syntax, Python syntax, migration number uniqueness, migration-name normalization regression, dynamic SQL identifier safety audit, architecture guard, JavaScript syntax, control-plane guard and the updated P21 control-plane policy.

## 4. Current product delta

The latest user-facing finance change is in `Банк -> Банковские счета`: the transaction table now displays a separate `ИНН` column immediately after `Контрагент`. The value comes from the already imported `counterparty_inn`; no DB schema/import format change was required.

Because deploy is automatic after green CI, every completed code change must be treated as unfinished until BOTH the corresponding CI run and its triggered deploy run are successful.

## 5. Dynamic SQL identifier safety

`architecture_guard.php` deliberately continues to show four heuristic WARNINGs in two Document actions and two Contractor actions. They were reviewed and are based on closed literal maps/static arrays, not direct request-derived table names.

A fifth analogous site exists in `Superadmin/ManagementActions/entity_list.php`; it has an explicit `$entityMap` whitelist check before table selection.

`tools/dynamic_table_safety_audit.py` makes all five assumptions executable and fail-closed. It verifies the exact approved site set, literal table/display-field mappings, validation ordering and absence of request-derived identifier assignment. A new dynamic identifier site or changed map must fail CI until explicitly reviewed. Do not silence architecture_guard just to remove warnings.

## 6. Database architecture and migrations

- Central DB stores global/account/control-plane data.
- Tenant operational data is stored in `erp_company_{id}` databases.
- Local migrations are executed by `LocalMigrationService`.
- Migration filenames/numbers are part of the journal identity and must be unique.
- Historical duplicate local numbers were normalized:
  - `029_create_crew_drivers.sql` -> `057_create_crew_drivers.sql`
  - `052_create_linear_route_points.sql` -> `058_create_linear_route_points.sql`
- Historical production journal rows may therefore contain old names. Use `scripts/p40_reconcile_migration_names.php` before treating them as missing migrations; never blindly re-run CREATE/ALTER statements against production.
- Known checksum reconciliation in `LocalMigrationService` is fail-closed: compatibility must be proven from the actual schema before an historical checksum is accepted.
- Normal CRUD must not run the full local migration chain as a side effect.
- Standard auto-deploy explicitly does not execute migrations.

## 7. Important domain facts

- Route executor is the user-facing combination Contractor + Driver + Vehicle Set and is implemented with `driver_vehicle_blocks` + `crews`.
- `driver_vehicle_blocks` does not have `vehicle_id`; it uses `vehicle_set_id`.
- Legacy `crews.vehicle_id` is populated from `vehicle_sets.primary_vehicle_unit_id` where required.
- Multi-driver crews use the normalized crew-driver migration.
- Linear trips support route points through `LinearRoutePointService` and `058_create_linear_route_points.sql`.
- Finance is accessible only to `company_owner`.
- Persistent uploaded documents live under `storage/companies/{company_id}/...` and must not be treated as disposable build output.

## 8. UI contract

ERP PLANEX is desktop-first (production minimum desktop width 1440px), industrial-density, table-first for registries, with strict borders and small radii. Preserve the brown/copper brand system. Generic blue-white SaaS dashboards, Bootstrap/AdminLTE restyling and card-list replacements for registries are prohibited unless explicitly requested.

For browser acceptance use the proven GitHub Actions runtime when needed: Node.js 22, Playwright 1.54.2, Chromium, Ubuntu 24.04, headless, 1920×1080, deviceScaleFactor 1, `ru-RU`, `Europe/Moscow`.

## 9. Production verification discipline

Before telling the user that a change is ready for testing:

1. Work only from the canonical branch and inspect its exact current HEAD.
2. Make the smallest coherent change; do not create a parallel deploy workflow/branch.
3. Wait for canonical CI on that SHA to complete successfully.
4. Confirm the triggered `ERPv2 Controlled Deploy` run started from that same CI push SHA.
5. Wait for deploy `SUCCESS`.
6. Verify server marker equals source SHA.
7. Verify `/erpv2/login` HTTP 200.
8. Verify old `/erp`, DB and `.env` fingerprints did not change unexpectedly.
9. Verify runtime directories are preserved.
10. If schema changes are involved, do NOT rely on standard auto-deploy; perform the explicit migration/reconciliation procedure and verify all active tenant migration journals.
11. For material UI changes, run Playwright/Chromium and check console/page/request failures.

A green CI alone is not production evidence. A pushed commit alone is not production evidence. The normal completion criterion is green CI + successful triggered deploy + relevant focused runtime verification.

## 10. Documentation priority

Read in this order:

1. current code/schema;
2. `README.md`;
3. `AGENTS.md`;
4. this file;
5. `PROJECT_STATE.md`;
6. `CURRENT_TASK.md`;
7. `DOCUMENTATION_INDEX.md`;
8. `DECISIONS.md` and REFERENCE docs as needed.

P11–P40 Markdown/JSON reports are historical evidence and may describe states later superseded. Do not treat an old `BLOCKED`, SHA, branch name, migration number or temporary workflow as current product state.

## 11. First action for a new agent

Inspect the exact canonical HEAD, the two workflow files, latest CI and latest triggered deploy. Keep the single deployment path. Do not create new Pxx auto-deploy workflows. Work serially: change -> push -> green CI -> automatic controlled deploy -> runtime verification -> report ready.
