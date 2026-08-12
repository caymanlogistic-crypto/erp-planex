# ERP PLANEX

Production ERP for PLANEX logistics operations.

## Canonical state — 2026-08-12

Repository status: `STABILIZED / HANDOFF READY`.

The repository was normalized after the P26–P40 parallel-agent development period. New work must start from the canonical/default branch `chatgpt/production-stabilization-20260802` and from its current HEAD. The complete handoff is `docs/ai/HANDOFF_FOR_NEW_AGENT.md`.

### Verified stabilization baseline

Canonical CI run `31586574444` on SHA `11ccb7d085f054f21d2accf82f96e5c233110b12` completed successfully after the final control-plane hardening. It passed PHP syntax, Python syntax, migration numbering, migration normalization regression, dynamic SQL identifier safety, architecture guard, JavaScript syntax, control-plane guard and P21 policy audit.

At that gate: central migrations = 11, local migrations = 57, duplicate migration numbers = 0; workflow files = 2, production-capable workflows = 1, automatic production deploy workflows = 0.

### Safety rules

- `/erpv2` is the active ERP runtime. Legacy `/erp` must not be changed.
- Production deployment is allowed only through `.github/workflows/erpv2_controlled_deploy.yml` and only by explicit `workflow_dispatch` with an exact 40-character SHA and `confirm_deploy=DEPLOY_ERPV2`.
- Automatic production deploys from `push`, `pull_request`, `schedule`, `workflow_run`, issues or other indirect triggers are prohibited.
- Do not embed branch names or fixed deployment SHAs in deployment workflows.
- `.github/workflows/ci.yml` is the canonical non-mutating CI gate. It may run automatically but must never deploy or mutate production data.
- Database changes must use migrations. Never edit production schema ad hoc.
- Central DB and tenant DBs are separate. Company operational entities live in `erp_company_{id}`.
- Local migration numbers must be unique. Current normalized local migrations include `057_create_crew_drivers.sql` and `058_create_linear_route_points.sql`.
- Finance remains restricted to `company_owner`.
- UI is desktop-first and follows the ERP PLANEX master reference: industrial density, table-first registries, small radii and the brown/copper visual system. Do not replace it with generic SaaS/AdminLTE/Bootstrap styling.

### Dynamic SQL identifiers

Five intentional legacy dynamic-identifier sites are now protected by `tools/dynamic_table_safety_audit.py`. Four of them remain visible as heuristic WARNINGs in `architecture_guard.php`; they were reviewed as closed literal whitelist/static-map patterns. The fifth Superadmin site has its own entity-map guard. Any new site or change to the approved mappings fails canonical CI until explicitly reviewed.

## Production deployment state

Repository stabilization and production deployment are deliberately separate. The normalized `erpv2_controlled_deploy.yml` has not yet been run, so the current repository HEAD must not be described as deployed merely because CI is green. A production SHA is authoritative only after a successful exact-SHA controlled-deploy run and its post-deploy checks.

## Recovery and history

The previous default-branch state was preserved before normalization in `backup/pre-stabilization-default-20260812`. It is recovery evidence only and must not be used as a deployment source without an explicit owner decision.

Historical P11–P40 reports are evidence snapshots, not the current source of truth. Their old blockers, branches, SHAs, workflows and migration numbers must not override current code and CURRENT documentation.

## Start here

Read `docs/ai/HANDOFF_FOR_NEW_AGENT.md`, then `docs/ai/PROJECT_STATE.md`, `docs/ai/CURRENT_TASK.md` and `docs/ai/DOCUMENTATION_INDEX.md` before changing code, schema or GitHub Actions.
