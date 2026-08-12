# ERP PLANEX — текущее состояние проекта

Актуально на 2026-08-12.

## Каноническое состояние

Repository: `caymanlogistic-crypto/erp-planex`.
Default/canonical branch: `chatgpt/production-stabilization-20260802`.
Production: `https://plan-ex.ru/erpv2/`.
Legacy `/erp` не менять.

Проект стабилизирован после параллельных P26–P40 линий и работает последовательно: один агент, один canonical branch, один production path. Recovery evidence: `backup/pre-stabilization-default-20260812`.

## Control plane

Canonical workflows: `.github/workflows/ci.yml` и `.github/workflows/erpv2_controlled_deploy.yml`.

Normal path: push canonical -> CI -> только successful push CI -> `workflow_run` -> controlled deploy. Deploy использует exact green SHA, backup, P07, marker/HTTP/DB/old-ERP/.env/runtime guards. Manual `workflow_dispatch` exact-SHA fallback сохранён с `DEPLOY_ERPV2`.

Standard deploy не запускает migrations. Поэтому schema work требует отдельного controlled migration/reconciliation.

## Архитектура данных

ERP multi-tenant: central DB для global/control data; company operational data — `erp_company_{id}`. Local migrations обслуживаются `LocalMigrationService`. Номера/journal identity должны быть однозначны. Нормализованные migration names включают `057_create_crew_drivers.sql` и `058_create_linear_route_points.sql`; historical aliases нельзя слепо повторно выполнять.

## Текущая продуктовая база

Система содержит стабилизированные модули контрагентов/клиентов/водителей/ТС/исполнителей рейса, multi-driver crews, linear trips с единым блоком загрузка/выгрузка и dual-operation points, документы и finance/accounting. Persistent documents находятся в `storage/companies/{company_id}/...` и должны переживать deploy.

UI — desktop-first industrial ERP, table-first, compact, brown/copper.

## Finance / accounting — следующий цикл

Следующая зона разработки — бухгалтерский/финансовый блок. Специализированный source of truth: `docs/ai/FINANCE_HANDOFF.md` + current code/schema.

Finance доступен только `company_owner`. Tenant isolation, auditability и fail-closed reconciliation обязательны. Основные зоны: bank accounts/statements, XLSX parser/settings/import, cash, invoices/payments/operations, allocations/actions/history, categories/reports/settings, trip-linked financial data.

Ключевые места для первичного аудита: `app/Http/Controllers/Company/BankFinance*`, `Finance*`, `app/Service/BankFinanceService.php`, `BankStatementSettingsService.php`, `BankStatementXlsxParser.php`, остальные finance services, finance views/assets/routes, `app/Support/entrypoint_dependencies.php`, finance migrations/tables/tests.

Исторически finance operation history давал live 500 из-за отсутствующей web runtime dependency; сервис был в source, но не был подключён entrypoint. Поэтому dependency chain обязателен к проверке. History должен сохранять 200 existing/empty, 404 nonexistent/foreign, 403 unauthorized.

Деньги — decimal data. Нельзя преобразовывать `97500.00` в digit-only string: такой класс ошибки уже приводил к отображению миллионов. Formatting принадлежит UI, persisted numeric value не должен меняться.

Production finance rows нельзя изменять ради тестов. Bank XLSX import/matching/deduplication/allocation должны тестироваться fail-closed и без скрытых side effects.

## Security / engineering gates

Finance role gate и tenant isolation — обязательные regression checks. Dynamic SQL identifier sites контролируются fail-closed audit. New/changed JS требует syntax check. Schema changes требуют fresh-schema + upgrade path + tenant journal verification.

Для material UI/runtime checks использовать proven Chromium/Playwright contour: Ubuntu 24.04, Node 22, Playwright 1.54.2, Chromium headless, 1920x1080; при layout-sensitive work также 1536x864 и 1366x768; ru-RU, Europe/Moscow, deviceScaleFactor 1.

## Source of truth

Приоритет: current code/schema -> `README.md` -> `AGENTS.md` -> `HANDOFF_FOR_NEW_AGENT.md` -> этот файл -> `CURRENT_TASK.md` -> `FINANCE_HANDOFF.md` -> `DECISIONS.md`/`DOCUMENTATION_INDEX.md`. Historical Pxx docs — только evidence.