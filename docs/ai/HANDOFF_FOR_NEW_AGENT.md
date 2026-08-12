# ERP PLANEX — handoff for the next agent

Updated: 2026-08-12

## 1. Canonical product state

ERP PLANEX is a PHP/MySQL multi-tenant logistics ERP. Production runtime is `https://plan-ex.ru/erpv2/`. The old `/erp` is legacy and must remain untouched.

Canonical/default branch: `chatgpt/production-stabilization-20260802`.

Repository status: `HANDOFF_READY_AUTO_DEPLOY_ACTIVE`. Parallel P26–P40 work was normalized into one development/deployment line. Work must remain serial: one agent, one canonical branch, one production path.

The stabilization line contains the accepted product functionality through the current state, including multi-driver crews, trip form/view improvements, finance controls, route points, combined loading/unloading operations and hardened migration handling.

Recovery evidence for the pre-stabilization default branch is preserved in `backup/pre-stabilization-default-20260812`.

## 2. Repository control plane

There are exactly two workflow files in the canonical branch:

- `.github/workflows/ci.yml` — automatic engineering gate on push/PR;
- `.github/workflows/erpv2_controlled_deploy.yml` — the only production-capable workflow.

Normal production path:

`push to chatgpt/production-stabilization-20260802` -> `ERP PLANEX CI` -> successful push CI -> `workflow_run` -> `ERPv2 Controlled Deploy`.

The deploy workflow resolves the exact green CI `head_sha`, validates the 40-character commit, checks it out detached, builds the artifact from exactly that SHA, creates a server backup, deploys through the proven P07 engine, verifies the deployed marker, `/erpv2/login` HTTP 200, DB fingerprint, old `/erp`, `.env` and runtime directory preservation.

The same workflow keeps `workflow_dispatch` as a manual exact-SHA fallback. Manual deployment requires the literal confirmation `DEPLOY_ERPV2`.

Standard application deployment does NOT run database migrations automatically. Schema changes require an explicit migration/reconciliation operation and tenant journal verification.

Do not restore temporary P24–P40 deploy/reconcile workflows and do not create a second production path.

## 3. Current verified product baseline

Latest completed route-point functional SHA: `6341d7f9fd7d095203d58a3297d44014ba5c0766`.

Verification:

- canonical CI run `31598355896` — `SUCCESS`;
- automatic `ERPv2 Controlled Deploy` run `31598400792`;
- deploy job `94119454092` — `SUCCESS`.

The deploy passed exact-SHA resolution, artifact build, SSH isolation, pre-deploy backup, P07 deployment, marker equality, HTTP verification, DB fingerprint equality, old `/erp` fingerprint equality, `.env` equality, runtime directory preservation and evidence upload.

The canonical CI includes PHP syntax, `linear_route_point_dual_operation_test.php`, Python syntax, migration numbering, migration normalization regression, dynamic SQL identifier safety audit, architecture guard, JavaScript syntax and both control-plane guards.

Documentation-only commits after a functional change may advance repository/server marker SHA without changing user-facing runtime. Always identify the functional SHA as well as the final deployed marker when investigating a release.

## 4. Current linear trip route-point model

Linear trips now expose a single UI block `Загрузка / выгрузка` instead of independent loading and unloading columns.

UI contract:

- a new route starts with two full-width point rows;
- row 1 defaults to `Загрузка` active;
- row 2 defaults to `Выгрузка` active;
- every row has two independent suffix toggles, `Загрузка` and `Выгрузка`;
- a point may be loading-only, unloading-only or both;
- `+ Добавить точку` adds another logical point;
- a filled address without either operation is invalid;
- a selected operation without an address is invalid;
- a route must contain at least one loading operation and one unloading operation;
- create and edit share the same logical model;
- view combines points in route order and shows operation tags next to each address.

Canonical submitted structure:

- `route_points[points][N][address]`
- `route_points[points][N][loading]`
- `route_points[points][N][unloading]`

`LinearRoutePointService` remains backward-compatible with the previous shape `route_points[loading][]` / `route_points[unloading][]`. The browser also emits aligned hidden legacy projections so server-side validation redirects can repopulate the existing PHP partial without losing combined-operation state.

## 5. Route-point DB representation

No new DB migration was necessary for combined operations.

Existing `database/migrations-local/058_create_linear_route_points.sql` provides:

- `linear_route_id`;
- `point_type ENUM('loading','unloading')`;
- `sort_order`;
- `address_text`;
- no unique constraint preventing two rows for the same logical route point.

A logical point with both operations is stored as two physical active rows sharing the same `linear_route_id`, `sort_order` and `address_text`: one row has `point_type=loading`, the other `point_type=unloading`.

`LinearRoutePointService::store()` writes this representation inside the existing route transaction. `fetch()` groups rows by global order/address and returns one logical point with `loading=true` and `unloading=true`. Existing one-operation rows remain valid without data migration.

Regression protection lives in `tests/linear_route_point_dual_operation_test.php` and is mandatory in canonical CI.

## 6. Database architecture and migration rules

- Central DB stores global/account/control-plane data.
- Tenant operational data is stored in `erp_company_{id}` databases.
- Local migrations are executed by `LocalMigrationService`.
- Migration filenames/numbers are journal identity and must remain unique.
- Historical duplicate local names were normalized:
  - `029_create_crew_drivers.sql` -> `057_create_crew_drivers.sql`
  - `052_create_linear_route_points.sql` -> `058_create_linear_route_points.sql`
- Historical journal rows may retain the old names. Use `scripts/p40_reconcile_migration_names.php`; never blindly rerun DDL.
- Known historical checksum reconciliation in `LocalMigrationService` is fail-closed and requires schema compatibility.
- Normal CRUD must not use a full migration chain as a hidden side effect.
- Standard auto-deploy does not execute migrations.

## 7. Important domain facts

- Route executor is the user-facing Contractor + Driver(s) + Vehicle Set combination implemented through `driver_vehicle_blocks` + `crews`.
- `driver_vehicle_blocks` uses `vehicle_set_id`, not `vehicle_id`.
- Legacy `crews.vehicle_id` is populated from `vehicle_sets.primary_vehicle_unit_id` where required.
- Multi-driver crews are supported by the normalized crew-driver migration.
- Linear trip points are handled by `LinearRoutePointService` and `058_create_linear_route_points.sql`.
- One logical route point may now carry both loading and unloading operations.
- Finance is accessible only to `company_owner`.
- Persistent uploaded documents live under `storage/companies/{company_id}/...` and must not be treated as disposable build output.

## 8. Dynamic SQL identifier safety

`architecture_guard.php` deliberately retains heuristic warnings at reviewed Company dynamic-table sites. These sites use closed literal maps/static arrays, not direct request-derived table names.

`tools/dynamic_table_safety_audit.py` makes the approved dynamic identifier set fail-closed. A new site, changed map or request-derived identifier must fail CI until explicitly reviewed. Do not silence architecture warnings merely to make output look clean.

## 9. UI contract

ERP PLANEX is desktop-first with minimum production desktop width 1440px, industrial density, table-first registries, strict borders and small radii. Preserve the brown/copper visual system. Do not replace it with generic Bootstrap/AdminLTE/blue-white SaaS patterns unless explicitly requested.

For browser acceptance use the proven GitHub Actions runtime where needed: Node.js 22, Playwright 1.54.2, Chromium, Ubuntu 24.04, headless, 1920×1080, deviceScaleFactor 1, `ru-RU`, `Europe/Moscow`.

## 10. Production verification discipline

Before telling the user that a change is ready for testing:

1. Work only from the exact current canonical HEAD.
2. Make the smallest coherent change and do not create a parallel deploy branch/workflow.
3. Wait for canonical CI on that pushed SHA to finish successfully.
4. Confirm the triggered deploy originated from that successful push CI.
5. Wait for deploy `SUCCESS`.
6. Verify server marker equals the deployed source SHA.
7. Verify `/erpv2/login` HTTP 200.
8. Verify old `/erp`, DB and `.env` fingerprints did not change unexpectedly.
9. Verify runtime directories were preserved.
10. If schema changes are involved, run the explicit migration/reconciliation procedure and verify all active tenant journals; standard auto-deploy is insufficient.
11. For material UI work, use the proven Playwright/Chromium runtime where available and check console/page/request failures.

A green CI alone is not production evidence. A pushed commit alone is not production evidence. Completion means green CI + successful triggered deploy + relevant focused verification.

## 11. Documentation priority

Read in this order:

1. current code/schema;
2. `README.md`;
3. `AGENTS.md`;
4. this file;
5. `PROJECT_STATE.md`;
6. `CURRENT_TASK.md`;
7. `DOCUMENTATION_INDEX.md`;
8. `DECISIONS.md` and reference docs as needed.

P11–P40 reports are historical evidence and may describe superseded SHA values, branches, workflows, blockers or migration names. Do not treat those historical states as current instructions.

## 12. First action for a new agent

Inspect the exact canonical HEAD, the two workflow files, latest CI and latest triggered deploy. Keep the single deployment path. Work serially: change -> push -> green CI -> automatic controlled deploy -> runtime/focused verification -> report ready.
