# ERP PLANEX

Production PHP/MySQL multi-tenant ERP for PLANEX logistics operations.

## Canonical state — 2026-08-12

Repository: `caymanlogistic-crypto/erp-planex`.
Canonical/default branch: `chatgpt/production-stabilization-20260802`.
Production runtime: `https://plan-ex.ru/erpv2/`.
Legacy `/erp` is separate and must not be changed.

Development is serial: one agent, current canonical HEAD, one production path. Historical Pxx branches/reports are evidence only.

## Start here

Read in order:

1. `AGENTS.md`
2. `docs/ai/HANDOFF_FOR_NEW_AGENT.md`
3. `docs/ai/PROJECT_STATE.md`
4. `docs/ai/CURRENT_TASK.md`
5. `docs/ai/FINANCE_HANDOFF.md` for the current accounting/finance work
6. `docs/ai/DECISIONS.md` and `DOCUMENTATION_INDEX.md`
7. `docs/ui/DESIGN_STANDARD.md` for UI work

## CI / production deployment

There are two canonical workflows:

- `.github/workflows/ci.yml` — automatic engineering gate;
- `.github/workflows/erpv2_controlled_deploy.yml` — the only production-capable workflow.

Normal release path:

`push to canonical -> ERP PLANEX CI -> successful push CI -> workflow_run -> ERPv2 Controlled Deploy`.

The deploy resolves the exact green CI `head_sha`, creates a server backup, builds the artifact strictly from that SHA, deploys through the proven P07 engine and verifies server marker, `/erpv2/login`, DB fingerprint, old `/erp`, `.env` and runtime directory preservation. Manual `workflow_dispatch` exact-SHA deployment remains available as fallback and requires `confirm_deploy=DEPLOY_ERPV2`.

Standard deploy does NOT run database migrations.

## Database

Central DB stores global/control data; company operational data lives in `erp_company_{id}`. Schema changes require migrations and explicit controlled migration/reconciliation after deployment. Never mutate production schema ad hoc from web code. Local migration numbers must remain unique; normalized route/crew migrations include `057_create_crew_drivers.sql` and `058_create_linear_route_points.sql`.

## Finance

The next development cycle focuses on the accounting/finance block. Finance remains restricted to `company_owner`; tenant isolation and auditability are mandatory. The module includes bank accounts/statements and XLSX import, cash, invoices/payments/operations, allocations/actions/history, categories/reports/settings and trip-linked finance. See `docs/ai/FINANCE_HANDOFF.md` before changing it.

Money is decimal data. Never parse persisted decimal money by stripping non-digits. UI formatting must not alter values. Do not mutate production financial rows to create test fixtures.

## UI

ERP PLANEX is desktop-first, table-first and compact, with brown/copper accents, strict borders and small radii. Do not replace it with generic Bootstrap/AdminLTE/SaaS styling unless explicitly requested.

## Completion rule

A commit is not READY. Green CI alone is not READY. Ordinary code/UI work is ready only after successful triggered deploy plus focused verification. Schema work additionally requires explicit migration/reconciliation and tenant journal verification.

Recovery evidence: `backup/pre-stabilization-default-20260812`. Do not deploy from it without an explicit recovery decision.