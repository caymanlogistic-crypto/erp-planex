# ERP PLANEX — handoff for the next agent

Updated: 2026-08-12.

## Mission

The next development cycle is modification of the accounting/finance block. First perform read-only familiarization; do not refactor, migrate or deploy until the system/finance map is understood and the user gives the first concrete finance task.

## Canonical product state

ERP PLANEX is a PHP/MySQL multi-tenant logistics ERP. Production: `https://plan-ex.ru/erpv2/`. Legacy `/erp` is separate and must remain untouched.

Repository: `caymanlogistic-crypto/erp-planex`.
Canonical/default branch: `chatgpt/production-stabilization-20260802`.
Work serially: one agent, current HEAD, one production path. Do not resume old Pxx branches/workflows. Recovery evidence is `backup/pre-stabilization-default-20260812` only.

Current product includes stabilized contractors/clients/drivers/vehicles, route executors with multi-driver crews, linear trips with combined loading/unloading points, documents and finance/accounting.

## Mandatory reading

Read current code/schema first, then `README.md`, `AGENTS.md`, this file, `PROJECT_STATE.md`, `CURRENT_TASK.md`, `FINANCE_HANDOFF.md`, `DECISIONS.md`, `DOCUMENTATION_INDEX.md`; for UI also `docs/ui/DESIGN_STANDARD.md`. Historical E7–P40 reports are evidence only.

## Finance focus

Finance is restricted to `company_owner`. Preserve tenant isolation, auditability and fail-closed behavior. Start by mapping current screens/routes/controllers/actions/services/tables/tests for:

- bank accounts and bank statements;
- XLSX bank statement parsing/settings/import;
- cash;
- invoices/payments/finance operations;
- allocations, cancel/actions and operation history;
- cash-flow categories/reports/settings;
- trip-linked finance.

Primary source locations include `app/Http/Controllers/Company/BankFinance*`, `Finance*`, `app/Service/BankFinanceService.php`, `BankStatementSettingsService.php`, `BankStatementXlsxParser.php`, other finance services, finance views/assets/routes, finance migrations/tables/tests and `app/Support/entrypoint_dependencies.php`.

Do not assume service autoloading. A previous live finance history 500 was caused by a service missing from `entrypoint_dependencies.php`. Verify the web runtime dependency chain whenever a service is introduced or referenced.

History/action security contract: same-tenant existing/empty operation -> 200; nonexistent/foreign -> 404/no leakage; unauthorized logistics role -> 403.

Money is decimal numeric data. UI formatting must never change persisted value. Never strip all non-digits from a decimal DB value: `97500.00` must remain 97,500, not 9,750,000. Do not mutate production finance rows to manufacture tests.

## DB rules

Central DB stores global/control data; operational company data lives in `erp_company_{id}`. Any DDL must be a numbered migration. Standard deploy does NOT execute migrations. Schema work requires explicit controlled migration/reconciliation, fresh-schema + upgrade-path checks and migration journal verification for active tenants. Never ALTER/CREATE production schema from a controller or temporary web script.

Normalized local migration identities include `057_create_crew_drivers.sql` and `058_create_linear_route_points.sql`; historical aliases require reconciliation, not blind DDL rerun.

## Domain invariants outside finance

Route executor = contractor + driver(s) + vehicle set via `driver_vehicle_blocks`/`crews`; `driver_vehicle_blocks` uses `vehicle_set_id`. Multi-driver crews are supported. Linear route points support loading-only, unloading-only and both operations at one logical point. Persistent documents live under `storage/companies/{company_id}/...`.

Do not break these while changing finance.

## UI contract

Desktop-first, table-first industrial ERP; compact density; strict borders/small radii; brown/copper visual system. Reuse working system patterns instead of inventing generic SaaS/Bootstrap/AdminLTE styling.

For material UI/runtime acceptance use GitHub Actions Chromium/Playwright when needed: Ubuntu 24.04, Node 22, Playwright 1.54.2, Chromium headless, 1920x1080; layout-sensitive 1536x864/1366x768; deviceScaleFactor 1, ru-RU, Europe/Moscow. Check console/page/request/unexpected HTTP failures.

## Production control plane

Exactly two canonical workflows matter:

- `.github/workflows/ci.yml` — automatic engineering gate;
- `.github/workflows/erpv2_controlled_deploy.yml` — only production-capable workflow.

Normal path:

`push canonical -> ERP PLANEX CI -> successful push CI -> workflow_run -> ERPv2 Controlled Deploy`.

Controlled deploy resolves exact green `head_sha`, checks out detached, builds artifact from that SHA, creates server backup, deploys through P07 and verifies marker equality, `/erpv2/login` 200, DB fingerprint equality, old `/erp` equality, `.env` equality and runtime directory preservation. It uploads evidence. Manual `workflow_dispatch` exact-SHA fallback remains and requires `DEPLOY_ERPV2`.

Never create a second production workflow/path. A green CI is not production evidence. Completion for code/UI = green CI + successful triggered deploy + focused runtime verification. Completion for schema work additionally = controlled migration/reconciliation + tenant journal verification.

## First response expected from the next agent

Before making changes, report:

1. exact canonical HEAD inspected;
2. current CI/deploy topology understood;
3. finance access/security model understood;
4. map `screen -> route -> controller/action -> service -> tables -> tests`;
5. finance DB ownership (central vs tenant) for each major area;
6. import/history/allocation risk points;
7. current production finance screens checked read-only;
8. contradictions or technical debt found;
9. confirmation that no code/DB/deploy was changed during familiarization.

Then wait for the user's concrete accounting modification.