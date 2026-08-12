# ERP PLANEX — правила работы агентов

Актуально на 2026-08-12. Это текущий операционный контракт. Исторические E7–P40 документы — evidence, а не инструкция.

## Перед началом

Обязательно прочитать: `README.md`, `docs/ai/HANDOFF_FOR_NEW_AGENT.md`, `docs/ai/PROJECT_STATE.md`, `docs/ai/CURRENT_TASK.md`, `docs/ai/DECISIONS.md`. Для finance дополнительно `docs/ai/FINANCE_HANDOFF.md`; для UI — `docs/ui/DESIGN_STANDARD.md`.

## Каноническая линия

Repository: `caymanlogistic-crypto/erp-planex`.
Canonical/default branch: `chatgpt/production-stabilization-20260802`.
Работа последовательная: один агент, один current HEAD, один production path. Старые Pxx/recovery branches не использовать для разработки/deploy.

Production: `https://plan-ex.ru/erpv2/`. Legacy `/erp` не менять.

## CI и deploy

В canonical branch два workflow:

- `.github/workflows/ci.yml` — автоматический engineering gate;
- `.github/workflows/erpv2_controlled_deploy.yml` — единственный production-capable workflow.

Нормальный путь: `push canonical -> ERP PLANEX CI -> green push CI -> workflow_run -> ERPv2 Controlled Deploy`.

Deploy использует exact green `head_sha`, делает server backup, собирает artifact строго из SHA, deploy через P07 и проверяет marker, `/erpv2/login`, DB fingerprint, old `/erp`, `.env`, runtime directories. `workflow_dispatch` остаётся ручным exact-SHA fallback и требует `DEPLOY_ERPV2`.

Не создавать второй deploy workflow. Не сообщать READY до successful deploy и focused verification. Standard deploy не запускает migrations.

## БД

ERP multi-tenant: central DB + company operational DB `erp_company_{id}`. Любое DDL — только migration. Нельзя ALTER/CREATE production schema из controller/temp web script/deploy. Local migration numbers уникальны; известные normalized names: `057_create_crew_drivers.sql`, `058_create_linear_route_points.sql`. Historical journal aliases reconciled only controlled tooling.

При schema change обязательны fresh-schema/upgrade checks, explicit migration/reconciliation и tenant journal verification. Auto-deploy сам по себе schema change не завершает.

## Finance

Finance доступен только `company_owner`. Tenant isolation, auditability и fail-closed reconciliation обязательны. Не менять production finance rows ради тестов. Money хранить/обрабатывать как decimal; UI formatting не должно менять numeric value. При web use любого service проверять runtime dependency chain и `app/Support/entrypoint_dependencies.php`.

Перед finance modifications новый агент сначала читает `docs/ai/FINANCE_HANDOFF.md` и делает read-only карту `screen -> route -> controller/action -> service -> tables -> tests`.

## Защищённые доменные решения

`Исполнитель рейса` = подрядчик + водитель(и) + vehicle set через `driver_vehicle_blocks`/`crews`; `driver_vehicle_blocks` использует `vehicle_set_id`. Multi-driver crews поддерживаются. Linear route points поддерживают loading/unloading и обе операции в одной логической точке. Persistent documents/storage нельзя удалять deploy-ом.

## UI

Desktop-first industrial ERP, minimum production desktop width 1440px, table-first registries, compact density, strict borders/small radii, brown/copper system. Не заменять generic SaaS/Bootstrap/AdminLTE styling без прямого задания.

Для material runtime/UI acceptance использовать proven Chromium/Playwright contour: Ubuntu 24.04, Node 22, Playwright 1.54.2, Chromium headless, 1920x1080 (и чувствительные 1536x864/1366x768), deviceScaleFactor 1, ru-RU, Europe/Moscow; проверять console/page/request/unexpected HTTP failures.

## Документация

Current truth: code/schema, `README.md`, этот файл, `docs/ai/HANDOFF_FOR_NEW_AGENT.md`, `PROJECT_STATE.md`, `CURRENT_TASK.md`, `FINANCE_HANDOFF.md`, `DECISIONS.md`, `DOCUMENTATION_INDEX.md`, `docs/ui/DESIGN_STANDARD.md`. Historical docs не должны переопределять current branch/deploy/runtime.