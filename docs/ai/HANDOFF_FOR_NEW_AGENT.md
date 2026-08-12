# ERP PLANEX — handoff for the next agent

Updated: 2026-08-12

## 1. Canonical product state

ERP PLANEX is a PHP/MySQL multi-tenant logistics ERP. Production runtime is `https://plan-ex.ru/erpv2/`. The old `/erp` is legacy and must remain untouched.

The stabilization line contains the production functionality accumulated through P39/P40, including multi-driver crews, trip form/view improvements, finance controls, route points and hardened local migration handling.

## 2. Repository control plane

There must be exactly one production-capable deployment workflow: `.github/workflows/erpv2_controlled_deploy.yml`.

It is `workflow_dispatch` only. Deployment requires an exact source SHA and the literal confirmation `DEPLOY_ERPV2`. It builds from the exact commit, creates a server backup, deploys through the proven P07 engine, checks the deployed marker, preserves `.env` and runtime directories, verifies that the old `/erp` and DB fingerprints did not change, and requires `/erpv2/login` HTTP 200.

Temporary P26–P40 push-trigger workflows and trigger files were removed during stabilization. Do not restore them.

## 3. Database architecture and migrations

- Central DB stores global/account/control-plane data.
- Tenant operational data is stored in `erp_company_{id}` databases.
- Local migrations are executed by `LocalMigrationService`.
- Migration filenames/numbers are part of the journal identity and must be unique.
- Historical duplicate local numbers were normalized:
  - `029_create_crew_drivers.sql` -> `057_create_crew_drivers.sql`
  - `052_create_linear_route_points.sql` -> `058_create_linear_route_points.sql`
- Historical production journal rows may therefore contain old names. Use the reconciliation script before treating them as missing migrations; never blindly re-run CREATE/ALTER statements against production.
- Known checksum reconciliation in `LocalMigrationService` is fail-closed: compatibility must be proven from the actual schema before an historical checksum is accepted.

## 4. Important domain facts

- Route executor is the user-facing combination Contractor + Driver + Vehicle Set and is implemented with `driver_vehicle_blocks` + `crews`.
- `driver_vehicle_blocks` does not have `vehicle_id`; it uses `vehicle_set_id`.
- Legacy `crews.vehicle_id` is populated from `vehicle_sets.primary_vehicle_unit_id` where required.
- Multi-driver crews use the normalized crew-driver migration.
- Linear trips support route points through `LinearRoutePointService` and `058_create_linear_route_points.sql`.
- Finance is accessible only to `company_owner`.

## 5. UI contract

ERP PLANEX is desktop-first (production minimum desktop width 1440px), industrial-density, table-first for registries, with strict borders and small radii. Preserve the brown/copper brand system. Generic blue-white SaaS dashboards, Bootstrap/AdminLTE restyling and card-list replacements for registries are prohibited unless explicitly requested.

## 6. Production verification discipline

Before claiming PRODUCTION READY after a code change:

1. Run PHP lint/static checks and focused tests for touched modules.
2. Check duplicate migration numbers in both migration directories.
3. If schema changes are involved, verify both fresh-schema and upgrade behavior.
4. Deploy only exact SHA through the controlled deployment workflow.
5. Verify server marker equals source SHA.
6. Verify `/erpv2/login` HTTP 200.
7. Verify old `/erp` fingerprint unchanged.
8. Verify `.env` and runtime directories preserved.
9. Verify expected migration journal state in all active tenant DBs.
10. For UI changes, run Playwright/Chromium at 1920x1080, ru-RU, Europe/Moscow and check console/page/request failures.

## 7. Historical evidence

P11–P40 Markdown/JSON reports are historical evidence and may describe states that were later superseded. Do not treat a historical `BLOCKED`, old SHA, old branch name, or temporary workflow as the current product state. This handoff plus the current code and controlled production evidence are authoritative.

## 8. First action for a new agent

Read this file, inspect the exact canonical HEAD, inspect `.github/workflows`, check migration numbering, and perform read-only production verification before making changes. Never create another automatic production deploy path.
