# ERP PLANEX

Production ERP for PLANEX logistics operations.

## Canonical state — 2026-08-12

The repository has been normalized after the P26–P40 parallel-agent development period. New work must start from the canonical branch `chatgpt/production-stabilization-20260802` after it is advanced to the stabilization commit documented in `docs/ai/HANDOFF_FOR_NEW_AGENT.md`.

### Safety rules

- `/erpv2` is the active ERP runtime. Legacy `/erp` must not be changed.
- Production deployment is allowed only through `.github/workflows/erpv2_controlled_deploy.yml` and only by explicit `workflow_dispatch` with an exact 40-character SHA and `confirm_deploy=DEPLOY_ERPV2`.
- Do not add push, pull_request, schedule, workflow_run or issue-triggered production deploy workflows.
- Do not embed branch names or fixed deployment SHAs in deployment workflows.
- Database changes must use migrations. Never edit production schema ad hoc.
- Central DB and tenant DBs are separate. Company operational entities live in `erp_company_{id}`.
- Local migration numbers must be unique. Current normalized local migrations include `057_create_crew_drivers.sql` and `058_create_linear_route_points.sql`.
- Finance remains restricted to `company_owner`.
- UI is desktop-first and follows the ERP PLANEX master reference: industrial density, table-first registries, small radii and the brown/copper visual system. Do not replace it with generic SaaS/AdminLTE/Bootstrap styling.

## Start here

Read `docs/ai/HANDOFF_FOR_NEW_AGENT.md` before changing code or schema. Historical P11–P40 reports are evidence snapshots, not the current source of truth.
