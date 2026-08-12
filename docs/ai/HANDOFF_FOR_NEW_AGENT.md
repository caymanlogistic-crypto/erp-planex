# ERP PLANEX — handoff for the next agent

Updated: 2026-08-12

## 1. Canonical product state

ERP PLANEX is a PHP/MySQL multi-tenant logistics ERP. Production runtime is `https://plan-ex.ru/erpv2/`. The old `/erp` is legacy and must remain untouched.

Canonical/default branch: `chatgpt/production-stabilization-20260802`.

Repository stabilization status: `HANDOFF_READY`. The parallel P26–P40 work period has been normalized into a single controlled line. The stabilization line contains the production functionality accumulated through P39/P40, including multi-driver crews, trip form/view improvements, finance controls, route points and hardened local migration handling.

The previous default-branch state was preserved as `backup/pre-stabilization-default-20260812` for recovery evidence only.

## 2. Repository control plane

There are exactly two workflow files in the canonical branch:

- `.github/workflows/ci.yml` — automatic/read-only engineering gate;
- `.github/workflows/erpv2_controlled_deploy.yml` — the only production-capable workflow.

`erpv2_controlled_deploy.yml` is `workflow_dispatch` only. Deployment requires an exact source SHA and the literal confirmation `DEPLOY_ERPV2`. It builds from the exact commit, creates a server backup, deploys through the proven P07 engine, checks the deployed marker, preserves `.env` and runtime directories, verifies that the old `/erp` and DB fingerprints did not change, and requires `/erpv2/login` HTTP 200.

Temporary P24–P40 push-trigger/reconcile/deploy workflows were removed during stabilization. Do not restore them and do not create a second production path.

Important: the normalized controlled-deploy workflow has not yet been executed after stabilization. Therefore repository HEAD and deployed production SHA must not be assumed equal. A deployment claim requires a successful controlled-deploy run plus its post-deploy evidence.

## 3. Verified repository baseline

Final stabilization CI run: `31586894872`.

Verified SHA: `99903d638b82b6cbb1c2706aae5630251f1df01e`.

Result: `SUCCESS`.

The final gate passed:

1. PHP syntax;
2. Python syntax for `tools/*.py`;
3. central/local migration number uniqueness;
4. migration-name normalization regression;
5. fail-closed dynamic SQL identifier safety audit;
6. architecture guard;
7. JavaScript syntax;
8. control-plane guard;
9. canonical P21 control-plane policy.

At that gate: central migrations = 11, local migrations = 57, duplicate migration numbers = 0, workflow files = 2, deploy-capable workflows = 1, automatic production deploy workflows = 0.

## 4. Production baseline versus repository HEAD

Last confirmed production baseline before repository-only normalization: `844c15de2455d6f45f4d136a2fcbb80dad98e984` on the P39 migration-checksum reconciliation line.

A repository comparison from that production baseline to the normalized line shows no additional user-facing PHP/JS/CSS runtime changes. The delta is primarily:

- removal of obsolete Pxx workflows and trigger files;
- canonical CI/control-plane files;
- documentation/handoff updates;
- renaming local migrations `029 -> 057` and `052 -> 058`;
- migration reconciliation/verification tools and scripts.

Therefore do not deploy the current HEAD merely to make SHA values equal. Deploy when a real product/runtime change requires publication, using the controlled workflow and the migration-journal rules below.

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

Before claiming PRODUCTION READY after a code change:

1. Run canonical CI and focused tests for touched modules.
2. Check duplicate migration numbers in both migration directories.
3. If schema changes are involved, verify both fresh-schema and upgrade behavior.
4. Reconcile renamed production migration journal rows before any migration run.
5. Deploy only exact SHA through the controlled deployment workflow.
6. Verify server marker equals source SHA.
7. Verify `/erpv2/login` HTTP 200.
8. Verify old `/erp` fingerprint unchanged.
9. Verify `.env` and runtime directories preserved.
10. Verify expected migration journal state in all active tenant DBs.
11. For UI changes, run Playwright/Chromium and check console/page/request failures.

Repository CI success alone is not production deployment evidence.

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

Inspect the exact canonical HEAD, inspect the two workflow files, confirm latest CI, check migration numbering and perform read-only production verification if the new task depends on runtime state. Never create another automatic production deploy path and never assume production matches repository HEAD without deployment evidence.
