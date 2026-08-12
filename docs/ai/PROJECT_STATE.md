# ERP PLANEX — текущее состояние проекта

Актуально на 2026-08-12. История предыдущих этапов сохранена в отдельных E7–P40 планах/отчётах; этот документ описывает текущее каноническое состояние после финальной нормализации репозитория.

## Канонический репозиторий

Repository: `caymanlogistic-crypto/erp-planex`.

Default/canonical branch: `chatgpt/production-stabilization-20260802`.

Перед нормализацией прежний default HEAD сохранён в `backup/pre-stabilization-default-20260812` как recovery evidence. Старые Pxx branches и historical reports не являются источником текущего состояния.

Статус repository control plane: `STABILIZED`.

## Runtime и deployment

Активный production runtime: `https://plan-ex.ru/erpv2/`.

Legacy `/erp` является отдельным старым контуром и не должен изменяться.

Единственный production-capable workflow: `.github/workflows/erpv2_controlled_deploy.yml`. Он запускается только вручную (`workflow_dispatch`), требует exact source SHA и `confirm_deploy=DEPLOY_ERPV2` и выполняет backup + exact-SHA deploy + post-deploy guards.

Автоматические P24–P40 deploy/reconcile/runtime workflows удалены из канонической ветки. Автоматически выполняется только `.github/workflows/ci.yml`, который production не изменяет.

После нормализации controlled deploy ещё не запускался. Поэтому `REPOSITORY_STABILIZED` не означает, что текущий canonical HEAD уже опубликован в production. Production deployment подтверждается только отдельным successful controlled-deploy run и post-deploy evidence.

Последний подтверждённый production baseline перед repository-only cleanup: `844c15de2455d6f45f4d136a2fcbb80dad98e984` (P39 migration-checksum reconciliation line). Сравнение этого baseline с нормализованным repository HEAD не показывает новых пользовательских PHP/JS/CSS runtime changes: различия относятся к GitHub control plane, документации, migration filenames и обслуживающим scripts/tools.

## CI / engineering gates

Canonical CI проверяет:

- PHP syntax;
- Python syntax для `tools/*.py`;
- уникальность номеров central/local migrations;
- regression нормализации migration names;
- fail-closed dynamic SQL identifier safety audit;
- `tools/architecture_guard.php`;
- JavaScript syntax;
- отсутствие лишних production-mutating workflows;
- P21 control-plane policy.

Финальный подтверждённый stabilization gate: run `31586894872`, SHA `99903d638b82b6cbb1c2706aae5630251f1df01e`, `SUCCESS`. На нём central migrations = 11, local migrations = 57, duplicates = 0; workflows = 2, deploy-capable = 1, automatic production deploys = 0.

## Dynamic SQL identifier safety

`architecture_guard.php` сохраняет четыре WARNING по потенциально dynamic table expressions в:

- `DocumentActions/upload_form.php`;
- `DocumentActions/index.php`;
- `ContractorActions/create_full_submit.php`;
- `ContractorActions/add_crew_submit.php`.

Они проверены по фактическому data-flow и используют только закрытые literal maps/whitelists. Аналогичный Superadmin site `ManagementActions/entity_list.php` использует `$entityMap` и fail-fast `!isset($entityMap[$entityType])` до выбора таблицы.

Чтобы это не оставалось ручным допущением, создан `tools/dynamic_table_safety_audit.py`. Он fail-closed контролирует все пять известных dynamic-identifier sites, их допустимые table/display-field mappings, порядок whitelist validation и отсутствие request-derived identifiers. Любой новый site или изменение утверждённой map ломает CI и требует отдельного review. Сам `architecture_guard.php` намеренно не ослаблен и продолжает показывать свои четыре heuristic WARNING.

## Архитектура данных

ERP многотенантная. Центральная БД содержит глобальные/учётные/управляющие данные. Операционные сущности компаний находятся в отдельных БД `erp_company_{id}`.

Локальные миграции обслуживает `LocalMigrationService`. Номер миграции и journal identity должны оставаться однозначными. После P40 устранены дубли номеров:

- `057_create_crew_drivers.sql` — multi-driver crew relation;
- `058_create_linear_route_points.sql` — точки линейного маршрута.

Исторические journal rows с `029_create_crew_drivers.sql` и `052_create_linear_route_points.sql` нормализуются через `scripts/p40_reconcile_migration_names.php`; повторный DDL без reconciliation запрещён.

## Основные доменные решения

`Исполнитель рейса` отображает связку Подрядчик + Водитель + ТС. Технически используются `driver_vehicle_blocks` + `crews`; отдельную таблицу `route_executors` создавать нельзя.

`driver_vehicle_blocks` хранит `vehicle_set_id`, а не `vehicle_id`. Legacy `crews.vehicle_id` при необходимости заполняется `vehicle_sets.primary_vehicle_unit_id`. Multi-driver crews используют отдельную crew-driver relation.

Линейные рейсы поддерживают последовательные route points через `LinearRoutePointService` и migration `058_create_linear_route_points.sql`.

Finance доступен только роли `company_owner`. Финансовые изменения должны сохранять auditability, fail-closed matching/reconciliation и tenant isolation.

Документы привязаны к company/entity и хранятся в `storage/companies/{company_id}/documents/...`; storage — persistent runtime data, а не build artifact.

## Роли и доступ

Ключевые роли: `superadmin`, `company_owner`, `senior_logist`, `logist`. Изоляция пользовательских сущностей логиста строится на ownership + `entity_access_grants`. Межтенантный доступ запрещён.

## UI contract

ERP PLANEX — desktop-first industrial ERP. Минимальная production desktop width — 1440px; основной verification viewport — 1920×1080. Реестры должны использовать плотные таблицы, строгие borders и малые радиусы. Визуальная система — коричнево-медная. Generic SaaS/Bootstrap/AdminLTE/blue-white dashboard и самовольная замена реестров на cards запрещены.

## Remaining technical debt

Часть старых action bridges и legacy route files остаётся transitional architecture. Они не являются текущими blockers и не должны рефакториться ради косметической чистки. Такой рефакторинг допускается только отдельной задачей с runtime regression.

Четыре architecture-guard WARNING являются контролируемыми heuristic warnings с отдельным fail-closed CI audit, а не неразобранным риском.

## Source of truth для следующего агента

Порядок приоритета:

1. текущий code + schema на canonical HEAD;
2. `README.md`;
3. `AGENTS.md`;
4. `docs/ai/HANDOFF_FOR_NEW_AGENT.md`;
5. этот `PROJECT_STATE.md`;
6. `CURRENT_TASK.md`, `DECISIONS.md`, `DOCUMENTATION_INDEX.md`;
7. исторические планы/отчёты — только для причин и evidence.
