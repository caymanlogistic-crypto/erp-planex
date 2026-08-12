# ERP PLANEX — правила агента

Актуально на 2026-08-12. Параллельная схема KILO/Codex/Pxx больше не является текущим способом управления репозиторием. До отдельного решения владельца изменения выполняет один агент последовательно от канонической ветки.

## Обязательное чтение

Перед изменениями: `README.md` → `AGENTS.md` → `HANDOFF_FOR_NEW_AGENT.md` → `PROJECT_STATE.md` → `CURRENT_TASK.md` → `DECISIONS.md` → `DOCUMENTATION_INDEX.md`. Для UI дополнительно `docs/ui/DESIGN_STANDARD.md`.

## Git / branch discipline

- Repository: `caymanlogistic-crypto/erp-planex`.
- Canonical/default branch: `chatgpt/production-stabilization-20260802`.
- Не работать из старых Pxx branches.
- `backup/pre-stabilization-default-20260812` — recovery evidence, не development/deploy source.
- Не force-push без отдельной необходимости и recovery point.
- Не создавать параллельные automatic deploy branches/workflows.

## Production discipline

- Active runtime: `/erpv2`; old `/erp` запрещено менять.
- Единственный production workflow: `.github/workflows/erpv2_controlled_deploy.yml`.
- Он остаётся `workflow_dispatch` only, exact SHA + `DEPLOY_ERPV2`.
- `.github/workflows/ci.yml` может запускаться автоматически, но production/data не мутирует.
- Любой новый push/PR/schedule/workflow_run production deploy запрещён.
- Нельзя объявлять новый SHA deployed, пока exact marker и post-deploy guards фактически не подтверждены.

## Schema / tenant rules

- Central DB и `erp_company_{id}` имеют разные обязанности.
- Schema change только миграцией.
- Local migration numbers уникальны.
- Нормализованные migration names: `057_create_crew_drivers.sql`, `058_create_linear_route_points.sql`.
- Старые journal names `029_create_crew_drivers.sql` и `052_create_linear_route_points.sql` — только через reconciliation script.
- Нельзя выполнять migration DDL «на всякий случай» из UI/controller кода.

## Protected domain rules

- Route executor = contractor + driver + vehicle set, технически `driver_vehicle_blocks` + `crews`.
- `driver_vehicle_blocks.vehicle_id` запрещён; использовать `vehicle_set_id`.
- Finance = `company_owner` only, включая AJAX/JSON/POST endpoints.
- Tenant isolation и `entity_access_grants` нельзя ослаблять ради удобства UI.
- `storage/companies/*` — persistent user data, не cleanup artifact.

## UI lock

Глобальные shell/sidebar/page-header patterns и утверждённая коричнево-медная ERP PLANEX визуальная система меняются только по явной UI-задаче владельца. Desktop-first, minimum 1440px. Реестры table-first. Generic SaaS/AdminLTE/Bootstrap restyle запрещён.

Create/edit для одной сущности должны сохранять единый field/layout contract. Frontend-правки не должны менять `name`, route/action/method, CSRF или business semantics без функциональной задачи.

## Acceptance

Минимум перед commit/acceptance:

- PHP lint по затронутым PHP;
- focused tests;
- architecture guard;
- duplicate migration scan;
- JS syntax при JS changes;
- green canonical CI.

Для UI/runtime изменений: Playwright/Chromium 1920×1080, `ru-RU`, `Europe/Moscow`, console/page/request/http failure review.

Для production deploy: backup, exact SHA build, server marker equality, `/erpv2/login` 200, old `/erp` unchanged, `.env`/runtime dirs preserved, expected tenant migration journal state.

## Documentation

Управляющие CURRENT документы должны описывать только текущее состояние. Исторические E/P reports не переписываются как будто их старые статусы не существовали; их статус определяется `DOCUMENTATION_INDEX.md`. Если текущая информация конфликтует с historical report, приоритет у current code + current governing docs.
