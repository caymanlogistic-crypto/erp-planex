# ERP PLANEX — HANDOFF_FOR_NEW_CHAT

## Актуализация 2026-06-28 — E14-E15 Final Architecture Review

**Статус**: E14_E15_FINAL_ARCHITECTURE_ACCEPTED

Ключевые изменения:
- **E14 Action Bridge Assessment**: все контроллеры используют единый паттерн делегирования action-файлам через require. Признан accepted transitional pattern — рефакторинг небезопасен на текущем этапе без полного покрытия тестами.
- **E15 Architecture Review**: расширен `tools/architecture_guard.php` (E14-E15). Проверены: controller wiring, mojibake, dynamic table whitelist, запрет route_executors, business model rules (driver_vehicle_blocks без vehicle_id), sidebar отсутствие старых меню.
- **3 route-файла** с inline closures (company_dashboard 240 строк, company_logists 960 строк, superadmin_company_delete 445 строк) — refactoring-кандидаты.
- Runtime smoke: PASS. Mojibake: PASS. `php -l`: 0 errors. `architecture_guard.php`: PASS (0 errors, 3 warnings).
- Cleaned up temp tool files (check_mojibake.php, runtime_check.php, check_redirect.php).

Ключевые изменения:
- Модуль Contractors выделен из `company_contractors.php` (3212 → 51 строка) в `Route → Controller → Service → View`.
- Создан `app/Http/Controllers/Company/ContractorController.php` — тонкий контроллер, делегирует в 22 action include файла.
- Создан `app/Service/ContractorService.php` — бизнес-логика: подключение к БД, валидация, CRUD, миграции, grants.
- Созданы 22 action файла в `app/Http/Controllers/Company/ContractorActions/`.
- Полная обратная совместимость: все маршруты, права, дизайн, INN/autofill, contacts, документы, create-full, add-crew, contacts CRUD, tax history, modal view/edit/archive сохранены.
- Contractors — второй модуль (после Clients, E8), приведённый к стандарту `Route → Controller → Service → View`.
- `php -l` — 0 ошибок. `architecture_guard.php` — PASS.

Ключевые изменения:
- Модуль Clients выделен из `company_clients.php` (1372 → 16 строк) в `Route → Controller → Service → View`.
- Создан `app/Http/Controllers/Company/ClientController.php` — тонкий контроллер, делегирует в 11 action include файлов.
- Создан `app/Service/ClientService.php` — бизнес-логика: подключение к БД, валидация, CRUD, миграции.
- Action include файлы: `app/Http/Controllers/Company/ClientActions/{index,create_form,create_submit,show,edit_form,edit_submit,archive,modal_view,modal_edit_form,modal_edit_submit,modal_archive}.php`.
- Добавлен `require_once` для `ClientService.php` в `public/index.php`.
- **Это первый модуль, полностью следующий стандарту `Route → Controller → Service → View`**.
- Архитектурный guard расширен: автоматически проверяет `ClientService` в index.php.

## Актуализация 2026-06-28 — E7.2 Architecture Hardening

**Статус**: E7.2_ARCHITECTURE_HARDENED

Ключевые исправления:
- `company_contractors.php` и `company_clients.php` теперь имеют `use` statements для сервисов (причина Fatal error — PHP use file-scoped).
- `legal_entity_create_form.php` перезаписан в UTF-8 с корректным кириллическим текстом.
- `tools/architecture_guard.php` — автоматическая проверка архитектуры (`php tools/architecture_guard.php`).
- `docs/ai/MODULAR_DEVELOPMENT_RULES.md` — жёсткие правила: Route->Controller->Service->View, запрет SQL в route-файлах, лимиты строк.
- Остаточный долг: legacy route-файлы (`company_contractors.php`, `company_clients.php`, `company_drivers.php`, `company_vehicle_sets.php`, `company_documents.php`, `superadmin_management.php`) ещё содержат procedural business logic.

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

## Главное для нового ChatGPT-чата

Прочитай этот файл первым. Он является главным переносимым контекстом текущей работы ERP PLANEX.

Рабочая папка проекта:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp
```

Разработка ведётся через KILO-агентов:

```text
Владелец + ChatGPT → erp-architect → erp-coder → erp-architect acceptance → владелец + ChatGPT
```

Постоянные KILO-агенты только:

```text
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

Главный дизайнер / CODEX-дизайнер — внешние чаты/инструменты, не постоянные KILO-агенты.

## PROTECTED WORKING CORE

4 страницы объявлены защищённым рабочим ядром. Их нельзя менять без отдельной явной задачи:

```text
/company/drivers
/company/vehicle-sets
/company/clients
/company/contractors
```

Полный список защищённых файлов (routes, views, partials, JS, CSS, сервисы, таблицы) и архитектурный план безопасного рефакторинга:

```text
docs/ai/PROTECTED_ARCHITECTURE_PLAN.md
```

Любой будущий рефакторинг должен начинаться с MD/карт/правил, а не с переноса кода.

## FOUNDATION SERVICES (не подключены к ядру)

Созданы архитектурные сервисы для будущих модулей:

```text
app/Service/AccessControlService.php  — единая проверка прав (роли, ownership, grants)
app/Service/DocumentService.php       — единый сервис документов (upload, replace, пути, бейджи)
```

Оба сервиса НЕ подключены к защищённому ядру.
Новые модули (driver_vehicle_blocks, crews) должны использовать эти сервисы с момента создания.

Подробнее: `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md`

## MASTER-FLOW (РЕАЛИЗОВАН)

Реализованы два сценария master-flow:

```text
docs/ai/MASTER_FLOW_ARCHITECTURE.md
```

### Сценарий B: «Создать перевозчика с экипажем» (create-full)
- Маршрут: GET/POST `/company/contractors/create-full`
- 4-шаговый stepper: Перевозчик → Водитель → Машина → Проверка
- Транзакционное создание: contractor + driver + vehicle_set + driver_vehicle_block + crew
- Проверка дублей перед INSERT
- Success-экран показывает все 5 сущностей со ссылками

### Сценарий A: «Добавить экипаж перевозчику» (из карточки)
- Маршрут: GET/POST `/company/contractors/{id}/add-crew`
- 3-шаговый stepper: Водитель → Машина → Проверка
- Кнопка «+ Водитель + Машина» в карточке перевозчика
- Перевозчик уже выбран (из URL), пользователь НЕ выбирает его заново
- Транзакционное создание: driver + vehicle_set + driver_vehicle_block + crew
- Проверка дублей перед INSERT
- Success-экран показывает все созданные сущности со ссылками

## Стиль взаимодействия с владельцем

- Отвечать коротко и по делу.
- Не писать промты агентам без прямой просьбы владельца: «пиши промт», «напиши промт», «дай промт».
- Если не хватает данных — запросить реальные файлы/архив, не фантазировать.
- Если нужны файлы — сразу дать CMD/PowerShell-команду в одну строку для архива.
- Не принимать отчёт агента `DONE`, если нет фактической проверки.
- UI оценивать только как соответствует / не соответствует / частично соответствует утверждённому стандарту, без вкусовщины.

## Текущий активный фокус

Актуальная модель доступа ERP PLANEX (после блоков D/D2/D5/D6):

### Модель ролей

| Роль | role_code | Видит | Управляет пользователями | Управляет привязкой |
|------|-----------|-------|------------------------|-------------------|
| superadmin | `superadmin` | Все компании | Да | Нет (системный уровень) |
| company_owner | `company_owner` | Все данные компании | Да | Да |
| senior_logist | `senior_logist` | Все данные компании | Нет | Нет |
| logist | `logist` | Своё + доступное | Нет | Нет |

### Привязка перевозчиков (основной сценарий)

Страница: `/company/contractor-assignments`
Меню: «Привязка перевозчиков» (только `company_owner`)
Назначение: сменить логиста-владельца перевозчика.

При перепривязке:
- `created_by_user_id` / `created_by_role` меняются на нового логиста;
- переносится ВЕСЬ контекст: contractor → crews → driver_vehicle_blocks → drivers → vehicle_sets;
- история записывается в `contractor_assignment_history`;
- активные grants на contractor и cascade grants отзываются.

### Ограничения для обычного logist

Обычный logist видит только свои доступные данные. При создании driver_vehicle_block и crew:
- выпадающие списки фильтруются по `created_by_user_id` + grants;
- backend-валидация запрещает чужие `driver_id` / `vehicle_set_id` / `contractor_id` / `driver_vehicle_block_id`.

### Будущая модель: Исполнитель рейса (план)

Целевая пользовательская сущность: **Исполнитель рейса** = Подрядчик + Водитель + ТС.

Вместо двух пунктов меню («Водители+ТС», «Экипажи») пользователь работает с одной сущностью. Технические таблицы `driver_vehicle_blocks` и `crews` остаются как внутренний слой.

При создании исполнителя рейса:
- Выпадающие списки contractor/driver/vehicle_set фильтруются по доступности (как сейчас для crews).
- Backend создаёт driver_vehicle_block + crew в одной транзакции.
- Обычный logist не может выбрать чужие contractor/driver/vehicle_set.

Переназначение ответственного логиста:
- Отдельная страница с вкладками: Исполнители рейса / Подрядчики / Водители / ТС.
- Для contractor — каскадный перенос (как сейчас).
- Для driver/vehicle_set/crew — точечный перенос с опциональным каскадом.

Подробнее: `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`.

### Что НЕ использовать

- **НЕ возвращать UI «Доступ логистов»** в карточку перевозчика — сценарий удалён.
- **НЕ развивать cascade sharing** — заменён на contractor assignment.
- **НЕ создавать новые сложные UI расшаривания** без прямого запроса владельца.
- **НЕ вводить «Водители+ТС» и «Экипажи»** как основные пользовательские пункты — заменены на «Исполнители рейса».

## Последние подтверждённые commits

```text
3e5405c docs(access): document contractor assignment model
f993342 fix(access): enforce contractor assignment context visibility
c0cf919 feat(access): add contractor assignment management
d5a6ace feat(access): add contractor cascade sharing (заменён)
d3d3524 feat(access): add senior logist role visibility
888ba64 feat(master-flow): add contractor crew creation workflows
```

## BLOCK E3 — Исполнитель рейса: полный CRUD (ПРИНЯТ)

**Статус**: E3 выполнен и принят. Commit `1488d55`.

Реализован полный пользовательский модуль «Исполнитель рейса»:

### Новые routes

```
GET  /company/route-executors
GET  /company/route-executors/create
POST /company/route-executors/create
GET  /company/route-executors/{id}
GET  /company/route-executors/{id}/edit
POST /company/route-executors/{id}/edit
POST /company/route-executors/{id}/archive
```

### Новые views

```
app/View/pages/company_route_executors.php         (список)
app/View/pages/company_route_executors_create.php  (создание)
app/View/pages/company_route_executor_view.php     (просмотр)
app/View/pages/company_route_executor_edit.php     (редактирование)
```

### Логика создания

- Пользователь выбирает: Подрядчик + Водитель + ТС (3 отдельных выпадающих списка).
- Backend находит или создаёт `driver_vehicle_block` для driver_id + vehicle_set_id.
- Backend создаёт `crew` для contractor_id + driver_vehicle_block_id.
- Всё в одной транзакции.
- Проверка дублей через UNIQUE constraint на crews.

### Логика редактирования

- При изменении водителя/ТС backend находит/создаёт новый driver_vehicle_block и обновляет crew.
- При изменении подрядчика обновляется crew.contractor_id.
- Проверка дублей исключая текущий crew.

### Защита доступа (logist)

- Dropdown фильтруются: только свои + grants (contractor/driver/vehicle_set).
- POST backend проверяет created_by_user_id + entity_access_grants для каждого ID.
- Чужие ID отклоняются с понятной ошибкой.

### Что НЕ изменилось

- Таблицы `driver_vehicle_blocks` и `crews` не менялись.
- Старые routes `/company/crews/*`, `/company/driver-vehicle-blocks/*`, `/company/contractor-assignments/*` сохранены и работают.
- Protected core (`/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`) не тронут.
- Документы в форму Исполнителя рейса не добавлены.

## BLOCK E4 — Переназначение ответственных логистов (ЗАВЕРШЁН)

**Статус**: E4 реализован.

### Новые routes

```
GET  /company/responsible-assignments
POST /company/responsible-assignments/reassign
```

### Новые views

```
app/View/pages/company_responsible_assignments.php  (страница с 4 вкладками)
```

### Новые миграции

```
database/migrations-local/038_create_responsible_assignment_history.sql
```

### Меню

Пункт «Ответственные логисты» добавлен в ОПЕРАЦИИ (виден только company_owner).

### Логика переназначения

- **Исполнитель рейса**: полный каскад (crew → block → contractor + driver + vehicle_set).
- **Подрядчик**: опциональный каскад через чекбокс «Перенести вместе с исполнителями рейса».
- **Водитель**: опциональный каскад на crews + blocks.
- **ТС**: опциональный каскад на crews + blocks.
- **Массовое переназначение**: чекбоксы + общий select.
- **История**: таблица `responsible_assignment_history`.
- **Grants**: отзыв активных grants при переназначении.
- **Доступ**: только company_owner.

**Следующий блок**: **E5** — определяется владельцем (возможные варианты: зачистка старых пунктов, документы, master-flow адаптация).


## АКТУАЛЬНЫЙ HOTFIX 2026-06-26 — route-executors / vehicle-sets

Контекст последней работы:

- Владелец вошёл как `logist` и получил ложное сообщение `Нет доступа` на `/company/route-executors`.
- На `/company/vehicle-sets` кнопка `Добавить новый транспорт` не реагировала, когда список пустой/невидимый для логиста.
- Были подготовлены несколько пакетов, но v3 оказался неверным из-за предположения по полю `vehicle_id` в `driver_vehicle_blocks`.
- После выгрузки реальной схемы БД (`db_schema_route_executor.txt`) подтверждено: `driver_vehicle_blocks.vehicle_id` отсутствует, а `crews.vehicle_id` существует как legacy-поле.

Правильная схема создания исполнителя рейса:

```text
1. Найти или создать driver_vehicle_blocks(driver_id, vehicle_set_id).
2. Получить vehicle_sets.primary_vehicle_unit_id.
3. Создать crews(contractor_id, vehicle_id, driver_id, driver_vehicle_block_id, status, comments, created_by_user_id, created_by_role).
4. vehicle_id в crews = primary_vehicle_unit_id, НЕ vehicle_set_id.
```

Правила доступа:

- `logist` видит свои записи по `created_by_user_id` + активные grants.
- Активный grant: `revoked_at IS NULL` и `access_level IN ('view','edit')`.
- Пустой список для логиста — это пустое состояние, не отказ доступа.

Последний подготовленный архив:

```text
ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip
```

Перед доверием к результату обязательно выполнить runtime-проверки: создать исполнителя рейса под логистом, открыть `/company/route-executors`, открыть `/company/vehicle-sets` и нажать `Добавить новый транспорт`.

## Актуализация 2026-06-28 — что уже добито в текущем чате

Перед продолжением новой сессии считай подтверждёнными только следующие runtime-блоки:

- `3.2 DocumentUpload` — отдельный headless Chromium runtime пройден для driver create/edit, vehicle-set create/edit, client LegalEntityCreateModal, contractor LegalEntityCreateModal. Удалённые документы после save/reopen не возвращаются. Badge-типы PDF/DOC/XLS/IMG подтверждены. Для client/contractor modal edit document section сейчас `NOT_APPLICABLE`, потому что в modal edit документы не выведены.
- `3.1–4 regression` — отдельно пройдены ContactFields create/edit, INN lookup/fallback, client/contractor modal CRUD, driver/vehicle-set ModalShell smoke, сохранность table layout (`page-head`, `table-card`, `table-toolbar`, `table.table`).
- `Stage 6 safe E2E` — подтверждён существующий full-page flow: superadmin создал компанию и owner; owner создал logist; logist создал client, contractor, driver, vehicle-set и route executor; страницы view/edit/docs открываются там, где предусмотрены системой.

Какие runtime-артефакты уже лежат в проекте:

```text
tmp/runtime_stage32.spec.js
tmp/runtime_regression_314.spec.js
tmp/runtime_stage6_e2e.spec.js
tmp/runtime-stage32-result.json
tmp/runtime-regression-314-result.json
tmp/runtime-stage6-e2e-result.json
tmp/playwright.runtime.config.js
tmp/codex-stage-docs/*
```

Критичные правила продолжения:

- Не коммитить без разрешения владельца.
- Не трогать superadmin provisioning архитектуру и не делать modal-only create без owner decision.
- Любой новый шаг по forms/modals/documents подтверждать повторным browser runtime, а не только lint/checks.

## 2026-06-28 handoff note: E7 route split
- public/index.php no longer contains route registration blocks.
- New route source of truth: pp/Http/Routes/*.php.
- If a route regression appears, inspect the dedicated route file first, not the old monolith.
- Shared helpers and pplyLocalMigrations() still remain in public/index.php for now.

## 2026-06-28 storage/companies rule

`storage/companies/*` — рабочие загруженные документы (PDF, изображения и т.д.).
При очистке проекта/подготовке архива эти директории можно исключать из архива,
но НЕЛЬЗЯ удалять из рабочей среды. Удаление приводит к осиротевшим записям в БД
и 404 при просмотре/скачивании.
