# ERP PLANEX — текущее состояние проекта

Актуально на 2026-08-12. История предыдущих этапов сохранена в отдельных E7–P40 планах/отчётах; этот документ описывает только текущую архитектуру и каноническое состояние.

## Канонический репозиторий

Repository: `caymanlogistic-crypto/erp-planex`.

Default/canonical branch: `chatgpt/production-stabilization-20260802`.

Перед нормализацией прежний default HEAD сохранён в `backup/pre-stabilization-default-20260812` как recovery evidence. Старые Pxx branches не являются источником правды для новой разработки.

## Runtime и deployment

Активный production runtime: `https://plan-ex.ru/erpv2/`.

Legacy `/erp` является отдельным старым контуром и не должен изменяться.

Единственный production-capable workflow: `.github/workflows/erpv2_controlled_deploy.yml`. Он запускается только вручную (`workflow_dispatch`), требует exact source SHA и `confirm_deploy=DEPLOY_ERPV2` и выполняет backup + exact-sha deploy + post-deploy guards.

Автоматические P24–P40 deploy/reconcile/runtime workflows удалены из канонической ветки. Автоматически выполняется только `.github/workflows/ci.yml`, который production не изменяет.

## CI / engineering gates

Canonical CI проверяет:

- PHP syntax;
- уникальность номеров central/local migrations;
- regression нормализации migration names;
- `tools/architecture_guard.php`;
- JavaScript syntax;
- отсутствие лишних production-mutating workflows;
- P21 control-plane policy.

Подтверждённый зелёный baseline перед текущей документационной актуализацией: run `31583989951`, SHA `0321efa32dfcd8908670f3f37e78dbd15f887628`.

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

## Known technical debt

`architecture_guard.php` проходит без ERROR, но исторически сообщает несколько WARNING по потенциально dynamic table expressions в отдельных Contractor/Document action files. Эти warning не являются доказанными уязвимостями; их нужно оценивать по реальному whitelist/data-flow и не маскировать ослаблением guard.

Часть старых action bridges и legacy route files остаётся transitional architecture. Их рефакторинг допустим только отдельной задачей с runtime regression, а не как косметическая «чистка».

## Source of truth для следующего агента

Порядок приоритета:

1. текущий code + schema на canonical HEAD;
2. `README.md`;
3. `AGENTS.md`;
4. `docs/ai/HANDOFF_FOR_NEW_AGENT.md`;
5. этот `PROJECT_STATE.md`;
6. `CURRENT_TASK.md`, `DECISIONS.md`, `DOCUMENTATION_INDEX.md`;
7. исторические планы/отчёты — только для причин и evidence.
