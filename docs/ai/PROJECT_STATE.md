# ERP PLANEX — текущее состояние проекта

## Актуализация 2026-06-28 — E9 Contractors Module Extraction

**Статус**: CONTRACTORS_MODULE_E9_ACCEPTED

Выполнено:
- Модуль Contractors выделен из монолитного route-файла `company_contractors.php` (3212 → 51 строка) в модульную структуру `Route → Controller → Service → View`.
- Создан `app/Http/Controllers/Company/ContractorController.php` — тонкий контроллер (135 строк), делегирует в action includes.
- Создан `app/Service/ContractorService.php` — бизнес-логика: подключение к БД, валидация, CRUD, миграции, grants, logists.
- Созданы 22 action include файла в `app/Http/Controllers/Company/ContractorActions/`.
- Создан `docs/ai/CONTRACTORS_MODULE_E9_PLAN.md` — детальный план рефакторинга.
- Весь функционал сохранён: список, create (full page + modal), edit, view, archive, create-full, add-crew, contacts CRUD, tax history, INN/autofill, документы, ролевой доступ.
- `php -l` — 0 ошибок. `architecture_guard.php` — PASS.
- Модуль Contractors — второй полноценный пример стандарта `Route → Controller → Service → View` вслед за Clients.

Выполнено:
- Модуль Clients выделен из монолитного route-файла `company_clients.php` (1372 → 16 строк) в модульную структуру `Route → Controller → Service → View`.
- Создан `app/Http/Controllers/Company/ClientController.php` — тонкий контроллер, делегирует в action includes.
- Создан `app/Service/ClientService.php` — бизнес-логика: подключение к БД, валидация, CRUD, миграции.
- Созданы 11 action include файлов в `app/Http/Controllers/Company/ClientActions/`.
- Добавлен `require_once` для `ClientService.php` в `public/index.php`.
- Весь функционал сохранён: дизайн, маршруты, права доступа, legal entity логика, client_contacts, документы, popup "Создать клиента".
- Runtime smoke: PASS (все роли, legacy redirects, регрессия contractors, route-executors, responsible-assignments).
- `php -l` — 0 ошибок. `architecture_guard.php` — PASS.

## Актуализация 2026-06-28 — E7.2 Architecture Hardening

**Статус**: E7.2_ARCHITECTURE_HARDENED

Выполнено:
- **BLOCKER 1 FIXED**: Fatal error `Class "ContractorContactService" not found` на `/company/contractors` — добавлены `use` statements в `company_contractors.php` и `company_clients.php`.
- **BLOCKER 2 FIXED**: Битая кодировка в popup "Создать клиента" (`legal_entity_create_form.php`) — перезаписан в UTF-8 с корректным кириллическим текстом.
- Удалён дублирующий `rollBack()` в `core.php`.
- Создан `tools/architecture_guard.php` — автоматическая проверка архитектуры.
- Создан `docs/ai/MODULAR_DEVELOPMENT_RULES.md` — жёсткие правила разработки.
- Создан `docs/ai/RUNTIME_SMOKE_CHECKLIST_E7_2.md` — полный smoke-отчёт.
- Обновлены `ARCHITECTURE_STABILIZED_AFTER_E7_1.md` (-> E7.2), `REFACTOR_E7_REPORT.md`, `PROJECT_STATE.md`, `HANDOFF_FOR_NEW_CHAT.md`.

Runtime smoke: все проверки пройдены. `php -l` — 0 ошибок. `architecture_guard.php` — PASS.

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

## Назначение файла

Короткий файл текущего состояния проекта. Не хранит длинную историю, не заменяет Git и не дублирует отчёты агентов.

## Текущая агентская схема

```text
Владелец + ChatGPT → erp-architect → erp-coder → erp-architect acceptance → владелец + ChatGPT
```

## Активные KILO-агенты

```text
erp-architect
erp-coder
```

## Исключены из постоянной цепочки

```text
erp-uiux-designer
erp-qa-tester
```

QA встроен в работу кодера, архитектора и финальную приёмку владельцем + ChatGPT.

## Дизайн-база

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS:

```text
public/assets/css/erp-ui.css
public/assets/css/app.css
```

Рабочий стандарт:

```text
docs/ui/DESIGN_STANDARD.md
```

## Последний подтверждённый commit по текущей ветке работ

```text
1488d55 — feat(route-executors): complete route executor workflow
```

## Важные commits из текущей цепочки

```text
1488d55 — feat(route-executors): complete route executor workflow (E3)
f462a7c — feat(route-executors): add facade list and menu (E2)
2547904 — docs: plan route executor simplification (E1)
f993342 — fix(access): enforce contractor assignment context visibility
c0cf919 — feat(access): add contractor assignment management
d3d3524 — feat(access): add senior logist role visibility
888ba64 — feat(master-flow): add contractor crew creation workflows
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
CLIENT_CREATE_LEGAL_ENTITY_STANDARD — ПРИНЯТ.
DRIVER_CREATE_MODAL_FROM_LIST — ПРИНЯТ И ЗАКОММИЧЕН (c3b825c).
DRIVER_EDIT_MODAL_VIEW_EDIT_FLOW — В РАБОТЕ, НЕ ПРИНЯТ.
MASTER_FLOW_IMPLEMENTATION — ВЫПОЛНЕН (888ba64): contractor crew creation workflows.
BLOCK_D — ВЫПОЛНЕН (d3d3524): senior_logist role + visibility rules.
BLOCK_D2/D5 — ВЫПОЛНЕН: contractor cascade sharing заменён на contractor assignment.
CONTRACTOR_ASSIGNMENT — УСЛОВНО ПРИНЯТ (c0cf919 + f993342): привязка перевозчиков + visibility enforcement.
BLOCK_D6 — ВЫПОЛНЕН: документация новой модели доступа (3e5405c).
BLOCK_E1 — ВЫПОЛНЕН: архитектурный план «Исполнитель рейса» (2547904).
BLOCK_E2 — ВЫПОЛНЕН: UI/menu facade «Исполнители рейса» (f462a7c).
BLOCK_E3 — ВЫПОЛНЕН: полный CRUD workflow «Исполнители рейса» (1488d55).
BLOCK_E4 — ВЫПОЛНЕН: переназначение ответственных логистов (вкладки: Исполнители рейса / Подрядчики / Водители / ТС).
```

## Текущая активная задача

```text
ROUTE_EXECUTOR_VEHICLE_SETS_HOTFIX_V4 — применить пакет, проверить runtime и закоммитить исправления.
```

Следующий шаг: владелец применяет архив v4, проверяет создание исполнителя рейса под логистом и кнопку добавления транспорта, затем делает commit точки исправления.

## CRITICAL UI LOCK RULE

Запрещено менять без прямого подтверждения владельца:

1. Основную шапку ERP.
2. Шапку контентного блока / page-head.
3. Основное меню / sidebar.
4. Shell layout.

Разрешено только добавлять новые кнопки/действия без изменения существующей структуры и поведения.

## Правило обновления

Этот файл обновлять только при изменении текущего статуса проекта, активной задачи, агентской схемы, последнего принятого этапа или следующего блока.


## Последний рабочий пакет исправлений

```text
ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip
```

Назначение: исправить доступ/пустые состояния `/company/route-executors`, создание исполнителя рейса по реальной схеме БД и неработающую кнопку `Добавить новый транспорт` на `/company/vehicle-sets`.

Статус: **подготовлен к применению владельцем**, финальная приёмка и commit ещё требуются.

## Актуализация 2026-06-28 — modal/entity runtime closure

Статус: хвосты этапов 3.1–7 закрыты runtime-проверками без коммита.

Что подтверждено в текущем рабочем дереве:

- Общий `ContactFields` вынесен в переиспользуемый компонент `app/View/components/contact_fields.php` и подключён в client/contractor create/edit/modal forms.
- `LegalEntityCreateModal` и `ModalShell` используются для client/contractor create/view/edit/archive без удаления существующих full-page CRUD.
- Общий helper документов `app/Support/legal_entity_document_upload.php` покрывает client/contractor inline-upload, а driver/vehicle-set остаются на своём рабочем document-flow с общим runtime-циклом add/replace/delete/save/reopen.
- INN helper вынесен в общий frontend pattern (`public/assets/js/legal-entity-inn.js`) и подтверждён runtime-сценариями lookup/fallback.
- Отдельный runtime этапа 3.2 пройден: driver, vehicle-set, client create modal, contractor create modal; soft-delete после save/reopen не возвращает удалённые документы; badge-типы PDF/DOC/XLS/IMG подтверждены.
- Регрессия 3.1–4 пройдена отдельно: contacts client/contractor create/edit, INN lookup/fallback, client/contractor modal CRUD, driver/vehicle-set ModalShell smoke, базовая ERP table-структура (`page-head`, `table-card`, `table-toolbar`, `table.table`) сохранена.
- Безопасный superadmin flow подтверждён только через существующий full-page provisioning. Modal-only create для superadmin не внедрялся и остаётся `NEED_OWNER_DECISION`.

Текущий runtime-статус:

```text
3.2 DocumentUpload — PASS
3.1–4 regression — PASS
Stage 5 superadmin modal-only — NEED_OWNER_DECISION
Stage 6 safe E2E full-page flow — PASS
Stage 7 docs refresh — IN PROGRESS / закрывается этим обновлением
```

## 2026-06-28 E7 index split
- public/index.php was reduced to a thin front controller.
- Route registration now lives in pp/Http/Routes/*.php (18 files / 165 routes).
- Legacy redirects for crews, driver-vehicle-blocks, and contractor-assignments were preserved.
- Detailed map: docs/ai/ROUTE_MAP_AFTER_E7.md.

## 2026-06-28 E7.1 architecture stabilization

- Status: `COMPLETE, RUNTIME REGRESSION PASS`.
- Branch: `refactor/stabilize-modular-structure`.
- `public/index.php`: `80` lines in current tree.
- `app/Http/Routes/*.php`: `19` files after extracting `legacy_redirects.php`.
- Controllers added:
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/Company/RouteExecutorController.php`
  - `app/Http/Controllers/Company/ResponsibleAssignmentController.php`
  - `app/Http/Controllers/Superadmin/CompanyController.php`
  - `app/Http/Controllers/Superadmin/CompanyOwnerController.php`
- Support/services added:
  - `app/Support/http_runtime.php`
  - `app/Support/core_runtime.php`
  - `app/Service/LocalMigrationService.php`
  - `app/Service/RouteExecutorService.php`
  - `app/Service/ResponsibleAssignmentService.php`
  - `app/Service/SuperadminCompanyService.php`
- Runtime smoke after E7.1:
  - `/login` -> `200`
  - `superadmin` companies/owner routes -> `200`
  - `company_owner` dashboard + route-executors + responsible-assignments + protected core lists -> `200`
  - `senior_logist` route-executors -> `200`, responsible-assignments -> `403`
  - `logist` route-executors -> `200`, responsible-assignments -> `403`
  - legacy redirects preserved as `302`
- Details: `docs/ai/ARCHITECTURE_STABILIZED_AFTER_E7_1.md`
