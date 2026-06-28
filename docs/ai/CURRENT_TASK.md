# ERP PLANEX — текущая задача

## Актуализация 2026-06-26 — Исполнители рейса / Транспорт

Статус: подготовлен пакет исправлений `ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip`; перед финальной фиксацией владелец должен применить файлы, проверить runtime и затем закоммитить результат.

Что обязательно учитывать дальше:

- `/company/route-executors` должен быть доступен `company_owner`, `senior_logist`, `logist`. Для `logist` пустой список — это не «Нет доступа», а нормальное пустое состояние с действием `Создать исполнителя рейса`.
- `Исполнитель рейса` — пользовательская сущность `Подрядчик + водитель + ТС`; технически создаются/используются `driver_vehicle_blocks` + `crews`.
- Реальная локальная схема БД: таблицы сущностей находятся в `erp_company_{id}`, а не в центральной `erp_planex`.
- `driver_vehicle_blocks` НЕ имеет поля `vehicle_id`. Запрещено писать `vehicle_id` в `driver_vehicle_blocks`.
- `driver_vehicle_blocks` хранит: `driver_id`, `vehicle_set_id`, `status`, `comments`, `created_by_user_id`, `created_by_role`, `updated_by_user_id`, `updated_by_role`.
- `crews` всё ещё имеет legacy-поля `vehicle_id` и `driver_id`; при создании исполнителя рейса `crews.vehicle_id` нужно заполнять значением `vehicle_sets.primary_vehicle_unit_id`, а `crews.driver_id` — выбранным водителем.
- Для `logist` выбор contractor/driver/vehicle_set и видимость списков должны фильтроваться по `created_by_user_id` + активным grants. Активный grant: `revoked_at IS NULL` и `access_level IN ('view','edit')`.
- На `/company/vehicle-sets` модалка создания транспорта должна быть в DOM всегда, включая пустой список, иначе кнопка `Добавить новый транспорт` визуально есть, но не работает.
- Если возникает ошибка схемы БД, сначала запускать `db_schema_route_executor.php` и сверять реальные `DESCRIBE/SHOW CREATE TABLE`, не угадывать поля.

## STATUS: BLOCK_E4_COMPLETE

Блоки E1 (архитектурный план), E2 (facade list + menu), E3 (полный CRUD workflow), E4 (переназначение ответственных логистов) выполнены.

## Что реализовано в E4

1. **Страница «Ответственные логисты»** (`/company/responsible-assignments`) — доступ только company_owner.
2. **4 вкладки**: Исполнители рейса | Подрядчики | Водители | ТС.
3. **Переназначение Исполнителя рейса**: полный каскад (crew → block → contractor + driver + vehicle_set).
4. **Переназначение Подрядчика**: опциональный каскад (чекбокс «Перенести вместе с исполнителями рейса»).
5. **Переназначение Водителя**: опциональный каскад (только crews + blocks).
6. **Переназначение ТС**: опциональный каскад (только crews + blocks).
7. **Массовое переназначение**: чекбоксы + общий select + кнопка «Переназначить выбранные».
8. **История**: таблица `responsible_assignment_history` (миграция 038).
9. **Grants**: отзыв entity_access_grants при переназначении.
10. **Меню**: пункт «Ответственные логисты» виден только company_owner.

## Commit

`ЗАВЕРШЁН — см. git log`

## Следующая задача

Определяется владельцем.

## Запреты (всё ещё актуальны)

- Не удалять таблицы `driver_vehicle_blocks`, `crews`.
- Не менять protected core (`/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`).
- Не менять topbar/sidebar без подтверждения владельца.
- Не добавлять документы в форму Исполнителя рейса без отдельного этапа.


## STATUS: ROUTE_EXECUTOR_VEHICLE_SETS_HOTFIX_V4_READY

Пакет исправлений подготовлен, но финальный runtime-статус зависит от применения владельцем архива `ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip`.

Исправляемый блок:

1. `/company/route-executors` под ролью `logist` не должен показывать ложное «Нет доступа» при пустом списке.
2. Кнопка `Создать исполнителя рейса` должна быть видна и доступна в пустом состоянии.
3. Создание исполнителя рейса должно учитывать реальную схему БД:
   - `driver_vehicle_blocks`: только `driver_id + vehicle_set_id`;
   - `crews`: `contractor_id + vehicle_id + driver_id + driver_vehicle_block_id`;
   - `vehicle_id` для `crews` = `vehicle_sets.primary_vehicle_unit_id`.
4. `/company/vehicle-sets`: кнопка `Добавить новый транспорт` должна работать и при пустом списке, потому что modal создаётся вне условий списка.
5. Для grants обычного `logist` обязательно использовать `revoked_at IS NULL` и `access_level IN ('view','edit')`.

Перед следующим коммитом: применить v4, проверить runtime создание исполнителя рейса под `logist`, проверить открытие модалки транспорта, затем `php -l public/index.php` и `git diff --check`.

## Актуализация 2026-06-28 — текущий фокус

Текущий блок завершения перед приёмкой:

1. Закрыть хвосты предыдущего промта отдельными runtime-отчётами, а не общим статусом.
2. Держать `3.2 DocumentUpload` как отдельный принятый runtime-блок.
3. Держать `3.1 ContactFields`, `3.3 INN helper`, `4 client/contractor modal CRUD` как отдельную регрессию после document runtime.
4. Не внедрять modal-only superadmin create: статус этого направления остаётся `NEED_OWNER_DECISION`.
5. Проверять безопасный E2E через существующий full-page flow: superadmin → company → owner → logist → client → contractor → driver → vehicle-set → route executor.
6. После каждого крупного блока обязательно прогонять `php -l`, `git diff --check`, `git status --short`.

Runtime, уже подтверждённый в этом цикле:

- `tmp/runtime_stage32.spec.js` — отдельный headless Chromium runtime этапа 3.2.
- `tmp/runtime_regression_314.spec.js` — отдельная регрессия этапов 3.1–4.
- `tmp/runtime_stage6_e2e.spec.js` — безопасный E2E существующего full-page provisioning flow.

Следующий шаг: держать рабочее дерево без новых функциональных отклонений, не коммитить без разрешения владельца и отдавать финальный отчёт только с покомпонентной самосверкой PASS/FAIL/NOT_APPLICABLE.

## 2026-06-28 E7 progress update
- Structural split of the index monolith is complete.
- Remaining follow-up is helper/service extraction and browser-level regression, not route registration extraction.
- Report: docs/ai/REFACTOR_E7_REPORT.md.

## 2026-06-28 E7.1 completion

- Status: `DONE / READY FOR ACCEPTANCE`.
- Closed in this pass:
  - auth moved to `AuthController`
  - route executors moved to controller-based route registration
  - responsible assignments moved to controller-based route registration
  - superadmin companies/owner moved to controller-based route registration
  - shared helpers extracted from `public/index.php` and `core.php`
  - GET legacy redirects isolated in `legacy_redirects.php`
- Verification:
  - `php -l` -> PASS on front controller, routes, controllers, services, support, action includes
  - runtime regression -> PASS on expected `200/403/302` contract
- Next step: create a dedicated E7.1 commit without unrelated workspace changes.
