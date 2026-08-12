# ERP PLANEX — handoff for a new chat

Updated: 2026-08-12

## Current status

`REPOSITORY_STABILIZED_HANDOFF_READY`

Canonical/default branch: `chatgpt/production-stabilization-20260802`.

Do not continue from old `master`, `develop`, Pxx feature branches or historical report SHAs. The previous default-branch state is preserved only as recovery evidence in `backup/pre-stabilization-default-20260812`.

## Read first

1. `docs/ai/HANDOFF_FOR_NEW_AGENT.md` — full canonical handoff.
2. `docs/ai/PROJECT_STATE.md` — current architecture/state.
3. `docs/ai/CURRENT_TASK.md` — stabilization completion and next-task rule.
4. `docs/ai/DOCUMENTATION_INDEX.md` — CURRENT / REFERENCE / HISTORICAL classification.
5. `README.md` and `AGENTS.md` — repository safety/operating rules.

This file is intentionally short. Historical details formerly accumulated here are preserved in Git history and the historical E7–P40 evidence documents; they must not override the current handoff.

## Control plane

Canonical branch contains exactly two GitHub Actions workflow files:

- `.github/workflows/ci.yml` — non-mutating CI;
- `.github/workflows/erpv2_controlled_deploy.yml` — the only production-capable workflow.

Production deployment is `workflow_dispatch` only and requires exact SHA plus `confirm_deploy=DEPLOY_ERPV2`. Automatic production deployment from push/PR/schedule/workflow_run is prohibited.

The normalized controlled-deploy workflow has not yet been run, so do not assume current repository HEAD equals deployed production SHA.

## Verified engineering baseline

CI run `31586574444` on SHA `11ccb7d085f054f21d2accf82f96e5c233110b12` passed the final code/control-plane hardening gate:

- PHP syntax;
- Python syntax;
- migration numbering;
- migration normalization regression;
- fail-closed dynamic SQL identifier safety audit;
- architecture guard;
- JavaScript syntax;
- control-plane guard;
- P21 policy audit.

A later documentation-only HEAD must also remain CI-green before handoff; check Actions rather than relying on a copied SHA.

## Database facts

- Central/global data and tenant operational data are separated.
- Company operational DBs are `erp_company_{id}`.
- Local migration numbers are unique.
- Multi-driver crews use `057_create_crew_drivers.sql`.
- Linear route points use `058_create_linear_route_points.sql`.
- Historical journal names `029_create_crew_drivers.sql` and `052_create_linear_route_points.sql` are reconciled by `scripts/p40_reconcile_migration_names.php`; never blindly re-run their DDL in production.
- `driver_vehicle_blocks` uses `vehicle_set_id`, not `vehicle_id`.
- Route executor is implemented through `driver_vehicle_blocks` + `crews`; do not introduce a `route_executors` table.
- Finance remains `company_owner` only.

## Dynamic identifier hardening

Four heuristic WARNINGs from `architecture_guard.php` were inspected and found to use closed literal whitelist/static-map sources. A fifth Superadmin dynamic-identifier site also uses a closed `$entityMap` with fail-fast validation. `tools/dynamic_table_safety_audit.py` now guards all five sites and fails CI if a new site appears, an approved map changes, validation order is lost or an identifier becomes request-derived.

Do not weaken the architecture guard merely to make its warning count zero.

## UI / runtime rules

Production runtime: `https://plan-ex.ru/erpv2/`. Legacy `/erp` must remain untouched.

UI is desktop-first, industrial-density, table-first and brown/copper. Minimum desktop width is 1440px; primary browser verification viewport is 1920×1080. Do not replace the system with generic Bootstrap/AdminLTE/SaaS styling.

When browser runtime is needed, use the proven GitHub Actions Playwright/Chromium path: Node.js 22, Playwright 1.54.2, Chromium, Ubuntu 24.04, headless, 1920×1080, deviceScaleFactor 1, `ru-RU`, `Europe/Moscow`.

## Rule for the next chat

The stabilization task is closed. Do not perform broad cleanup or refactoring on arrival. Start only from the user's next explicit functional/fix task, inspect current HEAD first, preserve the single-deploy control plane, and distinguish repository CI evidence from production deployment/runtime evidence.
