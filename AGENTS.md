# ERP PLANEX — правила работы агентов

Актуально на 2026-08-12. Этот файл является текущим операционным контрактом для разработки. Исторические E7–P40 документы сохраняются как evidence и не переопределяют эти правила.

## Начало работы

Перед любым изменением агент обязан прочитать:

1. `README.md`.
2. `docs/ai/HANDOFF_FOR_NEW_AGENT.md`.
3. `docs/ai/PROJECT_STATE.md`.
4. `docs/ai/CURRENT_TASK.md`.
5. `docs/ai/DECISIONS.md`.
6. Для UI — `docs/ui/DESIGN_STANDARD.md` и master-reference ERP PLANEX.

## Каноническая ветка

Каноническая/default ветка: `chatgpt/production-stabilization-20260802`.

Нельзя продолжать разработку из старых Pxx-веток, исторических deploy-веток или recovery-ветки. Перед работой всегда сверять текущий HEAD канонической ветки и состояние GitHub Actions.

Recovery evidence: `backup/pre-stabilization-default-20260812`. Она нужна только для аварийного сравнения/восстановления и не является источником deployment.

## Production control plane

Production runtime: `/erpv2`. Legacy `/erp` запрещено менять.

Единственный production-capable workflow: `.github/workflows/erpv2_controlled_deploy.yml`.

Он должен оставаться только `workflow_dispatch`, принимать точный 40-символьный SHA и требовать `confirm_deploy=DEPLOY_ERPV2`. Автоматические production deploy по `push`, `pull_request`, `schedule`, `workflow_run`, issue/event запрещены.

`.github/workflows/ci.yml` — единственный автоматический CI workflow. Он read-only относительно production и выполняет lint, migration numbering, architecture guard, JavaScript syntax и control-plane policy audit.

## База данных и миграции

ERP многотенантная: центральная БД хранит глобальные/управляющие данные, операционные данные компаний находятся в `erp_company_{id}`.

Любое изменение схемы — только миграцией. Нельзя вручную ALTER/CREATE production-схему из контроллера, временного скрипта или deploy workflow без отдельной migration/reconciliation процедуры.

Номера локальных миграций уникальны. После нормализации используются:

- `057_create_crew_drivers.sql`;
- `058_create_linear_route_points.sql`.

Исторические journal names `029_create_crew_drivers.sql` и `052_create_linear_route_points.sql` должны обрабатываться через `scripts/p40_reconcile_migration_names.php`; нельзя слепо повторно выполнять их DDL.

## Защищённые доменные решения

`Исполнитель рейса` — пользовательская сущность `Подрядчик + водитель + ТС`, технически реализованная через `driver_vehicle_blocks` и `crews`.

`driver_vehicle_blocks` использует `vehicle_set_id`; поля `vehicle_id` в этой таблице быть не должно. Legacy `crews.vehicle_id` при необходимости соответствует `vehicle_sets.primary_vehicle_unit_id`.

Finance доступен только `company_owner`.

Документы хранятся в `storage/companies/{company_id}/documents/...`; storage нельзя очищать как временный каталог.

## UI

ERP PLANEX — desktop-first, minimum production desktop width 1440px, industrial density, строгие borders, малые радиусы и коричнево-медная визуальная система. Реестры — table-first. Запрещено самовольно заменять интерфейс на generic SaaS, Bootstrap/AdminLTE dashboard или card lists.

## Проверка изменений

Перед утверждением изменения обязательны focused tests по затронутому модулю, PHP lint, JavaScript syntax при изменении JS, architecture guard, отсутствие дублей migration numbers и зелёный `.github/workflows/ci.yml`.

После schema change дополнительно проверяются fresh-schema и upgrade path. После production deploy проверяются exact server marker, `/erpv2/login`, сохранность `.env`/runtime directories, old `/erp` fingerprint и ожидаемое состояние migration journal всех активных tenants.

Для UI/runtime audit используется Chromium/Playwright: 1920×1080, deviceScaleFactor 1, `ru-RU`, `Europe/Moscow`, headless=true, с проверкой console/page/request/http failures.

## Работа с документацией

Текущими источниками правды являются `README.md`, этот файл, `docs/ai/HANDOFF_FOR_NEW_AGENT.md`, `PROJECT_STATE.md`, `CURRENT_TASK.md`, `DECISIONS.md`, `DOCUMENTATION_INDEX.md` и `docs/ui/DESIGN_STANDARD.md`.

Все документы, помеченные в `DOCUMENTATION_INDEX.md` как HISTORICAL, сохраняют исторические факты и не должны использоваться как инструкция по текущему branch/deploy/runtime.
