# ERP PLANEX — Finance / accounting handoff

Updated: 2026-08-12.

This is the focused entry point for the next agent modifying the accounting/finance block. Read it together with `AGENTS.md`, `HANDOFF_FOR_NEW_AGENT.md`, `PROJECT_STATE.md`, `CURRENT_TASK.md`, `DECISIONS.md` and the actual current code/schema.

## Scope and access

Finance is company-tenant data and is restricted to `company_owner`. Do not broaden access while doing UI work. `logist`/`senior_logist` finance denial is a security regression gate. Tenant isolation is mandatory: foreign-company operation IDs must not disclose data and should fail closed.

The finance area currently covers bank accounts/statements, cash, invoices, payments/operations, allocations, finance operation actions/history, cash-flow categories/reports/settings and trip-linked financial data. The exact current route/controller/view set must be inspected before each change; do not infer it from old Pxx reports.

## Primary code to inspect

Start with:

- `app/Http/Controllers/Company/BankFinanceController.php`
- `app/Http/Controllers/Company/BankFinanceActions/`
- `app/Http/Controllers/Company/FinanceCashController.php`
- `app/Http/Controllers/Company/FinanceCashActions/`
- other `Finance*` controllers/actions under `app/Http/Controllers/Company/`
- `app/Service/BankFinanceService.php`
- `app/Service/BankStatementSettingsService.php`
- `app/Service/BankStatementXlsxParser.php`
- all finance-related services in `app/Service/`
- finance views under `app/View/company/`
- finance CSS/JS under `public/assets/`
- finance routes in the current router/entrypoints
- `app/Support/entrypoint_dependencies.php`
- local migrations containing finance tables/columns
- finance-focused tests under `tests/`

Do not assume Composer autoloading is available for every web entrypoint. P20 proved that a service can exist and still produce a live HTTP 500 if it is missing from `app/Support/entrypoint_dependencies.php`. When adding/using a service from a web action, verify the runtime dependency chain explicitly.

## Important established finance behavior

- Finance access: `company_owner` only.
- Bank statement XLSX import exists and is part of the operational accounting flow.
- Bank account registry displays counterparty data including INN where available.
- Finance operations support allocation/action flows and operation history.
- History endpoint was previously fixed to return correct 200/empty state and 404 for nonexistent/foreign operations instead of 500.
- Operation/action UI has dedicated CSS (`public/assets/css/finance-operation-actions.css`) and was tested for modal scroll, sticky footer, disabled states and action buttons.
- Financial data must remain auditable. Do not silently rewrite historical amounts, matches, allocations or imported bank rows as a side effect of UI work.
- Money values must be treated as decimal money, not digit strings. A prior trip-edit regression turned `97500.00` into `9750000` by stripping the decimal separator. Never reuse such parsing for finance.

## Data and DB discipline

ERP is multi-tenant. Central DB contains global/control data; operational company data lives in `erp_company_{id}`. Before changing finance schema, identify exactly which DB owns the table.

Any schema change requires a numbered migration. Standard deployment intentionally does NOT run migrations. Therefore a finance change requiring DDL is not complete after CI/deploy: run an explicit controlled migration/reconciliation procedure, verify upgrade path and fresh-schema behavior, and verify migration journals for all active tenants. Never run ad-hoc ALTER/CREATE from a controller or temporary web script.

For monetary columns preserve DECIMAL semantics and existing precision/scale. Do not convert persisted money to formatted strings. Formatting (`97 500`, `₽`) belongs in UI only.

## Bank statement import

`BankStatementXlsxParser.php` and `BankStatementSettingsService.php` are high-risk integration code. Before modifying import:

1. preserve original source row identity/deduplication behavior;
2. preserve amount sign/direction semantics;
3. preserve account/counterparty/INN extraction;
4. preserve fail-closed behavior for malformed input;
5. test repeated import and partial/invalid files;
6. never mutate unrelated production rows merely to make a fixture pass.

## Finance operation history / actions

The live history bug fixed in P20 was caused by runtime dependency loading, not the history query itself. The endpoint must preserve:

- same-tenant existing operation: 200;
- same-tenant operation with no history: 200 + empty state;
- nonexistent operation: 404;
- foreign-tenant operation: 404/no leakage;
- unauthorized logistics role: 403.

When touching allocation/cancel/history/action flows, test the action and the resulting persisted state, not only rendering.

## UI rules

Use ERP PLANEX visual language: desktop-first, table-first, compact industrial density, brown/copper accent, small radii, strict alignment. Reuse nearby working finance and entity screens instead of inventing a new SaaS style. Do not change business logic while doing a visual-only task.

For material finance UI changes test 1920x1080 and, where layout is sensitive, 1536x864 and 1366x768. Check modal scrolling, no double-scroll, long content, sticky action/footer behavior, disabled buttons, keyboard/focus behavior and zero unexpected console/page/request/HTTP failures.

## Development and deployment contract

Canonical/default branch: `chatgpt/production-stabilization-20260802`. Work serially on the current HEAD. Do not revive old Pxx branches or add another deploy workflow.

Normal path is:

`push canonical -> ERP PLANEX CI -> green push CI -> workflow_run -> ERPv2 Controlled Deploy -> backup -> P07 exact-SHA deploy -> marker/HTTP/DB/old-ERP/.env/runtime verification`.

The deploy workflow also retains manual `workflow_dispatch` exact-SHA fallback with confirmation `DEPLOY_ERPV2`. It does not run migrations.

Never report READY from a commit or green CI alone. For ordinary code/UI work require successful auto-deploy and focused runtime verification. For schema work require the explicit migration step too.

## First task for the next finance agent

Before changing anything, perform a read-only inventory of the current finance module: routes, controllers/actions, services, views, JS/CSS, migrations/tables, tests, role gates and current production screens. Produce a short map of `screen -> route -> controller/action -> service -> tables -> tests`, identify technical debt and contradictions, and ask for the first requested accounting modification. Do not refactor or deploy during this familiarization pass.