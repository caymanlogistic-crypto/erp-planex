# ERP PLANEX — PROTECTED ARCHITECTURE PLAN

## STATUS: PROTECTED_ARCHITECTURE_PLAN_READY

Дата: 2026-06-23
Безопасная точка Git: `c19c67a`
Рабочий tree: clean

---

## 1. Краткий вердикт

ERP PLANEX — живой production-проект на чистом PHP без фреймворка.

Архитектура НЕ готова к глубокому рефакторингу одним касанием. Требуется поэтапный safe-refactoring с сохранением 4 защищённых рабочих страниц как эталонного ядра.

Главный риск: `public/index.php` (15 541 строка, 670 KB) содержит ВСЮ бизнес-логику, роутинг, валидацию, работу с документами и БД в 137 маршрутных замыканиях. Любое неосторожное изменение может обрушить несколько модулей.

**Рекомендация**: начинать рефакторинг с изоляции новых модулей по целевому стандарту, не трогая рабочие CRUD. Постепенно выносить logic-slices в сервисы, подменяя вызовы из index.php только после acceptance-тестов.

---

## 2. Список защищённых рабочих страниц

| # | URL | Блок | Статус |
|---|-----|------|--------|
| 1 | `/company/drivers` | Водители | DO_NOT_TOUCH_WORKING_CORE |
| 2 | `/company/vehicle-sets` | Транспорт | DO_NOT_TOUCH_WORKING_CORE |
| 3 | `/company/clients` | Клиенты | DO_NOT_TOUCH_WORKING_CORE |
| 4 | `/company/contractors` | Перевозчики | DO_NOT_TOUCH_WORKING_CORE |

Эти 4 страницы — рабочее функциональное ядро. Их нельзя менять без отдельного явного разрешения владельца.

---

## 3. Карта файлов защищённого ядра

### 3.1 /company/drivers (Водители) — 16 routes (5 GET + 11 POST)

**Routes (все в public/index.php):**

| Method | Route | Line | Handler |
|--------|-------|------|---------|
| GET | `/company/drivers` | 5038 | inline closure → `company_drivers.php` |
| GET | `/company/drivers/create` | 5165 | inline closure → `company_drivers_create.php` |
| POST | `/company/drivers/create` | 5255 | `handleCompanyDriverCreate(config,db,'page')` |
| POST | `/company/drivers/modal-create` | 5720 | `handleCompanyDriverCreate(config,db,'modal')` |
| GET | `/company/drivers/{id}` | 5726 | inline closure → `company_driver_view.php` |
| GET | `/company/drivers/{id}/edit` | 5903 | inline closure → `company_driver_edit.php` |
| POST | `/company/drivers/{id}/edit` | 6012 | inline closure → update driver |
| POST | `/company/drivers/{id}/archive` | 6195 | inline closure → archive driver |
| GET | `/company/drivers/{id}/modal-view` | 6342 | inline closure → `company_driver_modal_view.php` |
| GET | `/company/drivers/{id}/modal-edit` | 6501 | inline closure → `company_driver_modal_edit.php` |
| POST | `/company/drivers/{id}/modal-edit` | 6644 | inline closure → update driver + docs + phones |
| POST | `/company/drivers/{id}/modal-delete` | 7180 | inline closure → delete driver |
| POST | `/company/drivers/{driver_id}/phones/create` | 7320 | inline closure → add phone |
| POST | `/company/drivers/{driver_id}/phones/{phone_id}/edit` | 7375 | inline closure → edit phone |
| POST | `/company/drivers/{driver_id}/phones/{phone_id}/delete` | 7425 | inline closure → delete phone |
| POST | `/company/drivers/{driver_id}/phones/{phone_id}/set-main` | 7483 | inline closure → set main phone |

**Views:**
```
app/View/pages/company_drivers.php              — list page (219 lines)
app/View/pages/company_drivers_create.php        — standalone create page
app/View/pages/company_driver_view.php           — standalone view page
app/View/pages/company_driver_edit.php           — standalone edit page
app/View/partials/company_driver_create_form.php — shared create/edit form (304 lines)
app/View/partials/company_driver_modal_view.php  — modal view partial
app/View/partials/company_driver_modal_edit.php  — modal edit partial (179 lines)
app/View/components/view_formatters.php          — shared formatters
```

**JS-функции:**
```
public/assets/js/app.js:
- initDriverForm(form)           — driver form interactive init (scoped)
- initDriverInputValidation      — date/phone validation
- showDriverClientError          — form-alert display
- Extra phone row management
- Predef/custom document file pickers
- Upload size validation
```

**CSS-зависимости:**
```
public/assets/css/erp-ui.css     — core design system
public/assets/css/app.css        — driver-specific styles:
  .driver-create-modal, .driver-layout, .entity-form-layout,
  .entity-form-main, .entity-form-docs, .driver-fields,
  .driver-contact-top-row, .driver-extra-phone-row,
  .document-file-row, .file-type-badge, .predef-file-clear
```

**Сервисы:**
```
app/Support/driver_create_handler.php   — handleCompanyDriverCreate() (553 lines)
app/Service/CompanyInnLookupService.php — DaData lookup (shared)
```

**Таблицы (локальная БД):**
```
drivers           — migration 004
driver_phones     — migration 015
documents         — migration 007
document_types    — migration 024
entity_access_grants — migration 009
```

**Документы:**
```
entity_type = 'driver'
Предопределённые: Паспорт, Водительское удостоверение, СНИЛС
Произвольные: custom_doc_file[] через форму
```

**Зависимости по правам:**
```
requireRole(['company_owner', 'logist'])
Logist: видит свои + grants
Company_owner: видит всё
```

---

### 3.2 /company/vehicle-sets (Транспорт) — 11 routes (6 GET + 5 POST)

**Routes:**
| Method | Route | Line |
|--------|-------|------|
| GET | `/company/vehicle-sets` | 9389 |
| GET | `/company/vehicle-sets/create` | 9507 |
| POST | `/company/vehicle-sets/create` | 9565 |
| GET | `/company/vehicle-sets/{id}/modal-view` | 9962 |
| GET | `/company/vehicle-sets/{id}/modal-edit` | 10091 |
| POST | `/company/vehicle-sets/{id}/modal-edit` | 10219 |
| POST | `/company/vehicle-sets/{id}/modal-delete` | 10842 |
| GET | `/company/vehicle-sets/{id}` | 10983 |
| GET | `/company/vehicle-sets/{id}/edit` | 11079 |
| POST | `/company/vehicle-sets/{id}/edit` | 11123 |
| POST | `/company/vehicle-sets/{id}/archive` | 11196 |

**Views:**
```
app/View/pages/company_vehicle_sets.php              — list page (231 lines)
app/View/pages/company_vehicle_sets_create.php       — standalone create
app/View/pages/company_vehicle_set_view.php           — standalone view
app/View/pages/company_vehicle_set_edit.php           — standalone edit
app/View/partials/company_vehicle_set_create_form.php — shared form (1314 lines)
app/View/partials/company_vehicle_set_modal_view.php  — modal view
app/View/partials/company_vehicle_set_modal_edit.php  — modal edit
```

**Таблицы:**
```
vehicle_units      — migration 005/016
vehicle_sets        — migration 017
documents (entity_type = 'vehicle_set')
```

---

### 3.3 /company/clients (Клиенты) — 7 routes (4 GET + 3 POST)

**Routes:**
| Method | Route | Line |
|--------|-------|------|
| GET | `/company/clients` | 2076 |
| GET | `/company/clients/create` | 2178 |
| POST | `/company/clients/create` | 2263 |
| GET | `/company/clients/{id}` | 2544 |
| GET | `/company/clients/{id}/edit` | 2672 |
| POST | `/company/clients/{id}/edit` | 2776 |
| POST | `/company/clients/{id}/archive` | 2941 |

**Views:**
```
app/View/pages/company_clients.php
app/View/pages/company_clients_create.php
app/View/pages/company_client_view.php
app/View/pages/company_client_edit.php
app/View/components/client_contact_fields.php
```

**Сервисы:**
```
app/Service/ClientContactService.php
```

**Таблицы:**
```
clients            — migration 002
client_contacts    — migration 031
documents (entity_type = 'client')
```

---

### 3.4 /company/contractors (Перевозчики) — 15 routes (5 GET + 10 POST)

**Routes:**
| Method | Route | Line |
|--------|-------|------|
| GET | `/company/contractors` | 2987 |
| GET | `/company/contractors/create` | 3107 |
| GET | `/company/contractors/create-full` | 3238 |
| POST | `/company/contractors/create` | 3347 |
| POST | `/company/contractors/create-full` | 3603 |
| GET | `/company/contractors/{id}` | 4050 |
| GET | `/company/contractors/{id}/edit` | 4270 |
| POST | `/company/contractors/{id}/edit` | 4385 |
| POST | `/company/contractors/{id}/archive` | 4584 |
| POST | `.../contacts/create` | 4720 |
| POST | `.../contacts/{contact_id}/edit` | 4781 |
| POST | `.../contacts/{contact_id}/delete` | 4833 |
| POST | `.../contacts/{contact_id}/set-primary` | 4894 |
| POST | `.../contacts/{contact_id}/set-document-email` | 4939 |
| POST | `.../tax-history/create` | 4985 |

**Views:**
```
app/View/pages/company_contractors.php
app/View/pages/company_contractors_create.php
app/View/pages/company_contractors_create_full.php
app/View/pages/company_contractor_view.php
app/View/pages/company_contractor_edit.php
app/View/components/contractor_contact_fields.php
```

**Сервисы:**
```
app/Service/ContractorContactService.php
app/Service/CompanyInnLookupService.php (shared)
```

**Таблицы:**
```
contractors         — migration 003
contractor_contacts — migration 012
contractor_tax_history — migration 013
documents (entity_type = 'contractor')
```

---

## 4. Карта маршрутов защищённого ядра

Все маршруты живут в `public/index.php` как inline-замыкания. Ниже — полная карта:

### Drivers: 16 routes, lines 5038–7483 (~2445 строк в index.php)
- GET list: 5038–5163 (125 строк)
- GET create page: 5165–5253 (88 строк)
- POST create page: 5255 → `handleCompanyDriverCreate` (через driver_create_handler.php)
- POST modal-create: 5720 → `handleCompanyDriverCreate` modal mode
- GET view: 5726–5902 (176 строк)
- GET edit page: 5903–6011 (108 строк)
- POST edit: 6012–6194 (182 строки)
- POST archive: 6195–6341 (146 строк)
- GET modal-view: 6342–6497 (155 строк)
- GET modal-edit: 6501–6640 (139 строк)
- POST modal-edit: 6644–7179 (535 строк — includes full document re-upload logic)
- POST modal-delete: 7180–7319 (139 строк)
- Phone CRUD: 7320–7483 (163 строки)

### Vehicle-sets: 11 routes, lines 9389–11195 (~1806 строк)
### Clients: 7 routes, lines 2076–2985 (~909 строк)
### Contractors: 15 routes, lines 2987–5068 (~2081 строка)

**Всего на 4 защищённых модуля: ~7 241 строка бизнес-логики внутри index.php**

---

## 5. Что нельзя трогать

### DO_NOT_TOUCH_WORKING_CORE — полный список

**Файлы, затрагивающие 4 защищённые страницы:**

```
public/index.php                    — ВСЕ роуты и бизнес-логика (стр. 2076–7483, 9389–11195)

# Views (защищённых страниц)
app/View/pages/company_drivers.php
app/View/pages/company_drivers_create.php
app/View/pages/company_driver_view.php
app/View/pages/company_driver_edit.php
app/View/pages/company_vehicle_sets.php
app/View/pages/company_vehicle_sets_create.php
app/View/pages/company_vehicle_set_view.php
app/View/pages/company_vehicle_set_edit.php
app/View/pages/company_clients.php
app/View/pages/company_clients_create.php
app/View/pages/company_client_view.php
app/View/pages/company_client_edit.php
app/View/pages/company_contractors.php
app/View/pages/company_contractors_create.php
app/View/pages/company_contractors_create_full.php
app/View/pages/company_contractor_view.php
app/View/pages/company_contractor_edit.php

# Partials
app/View/partials/company_driver_create_form.php
app/View/partials/company_driver_modal_view.php
app/View/partials/company_driver_modal_edit.php
app/View/partials/company_vehicle_set_create_form.php
app/View/partials/company_vehicle_set_modal_view.php
app/View/partials/company_vehicle_set_modal_edit.php

# Components (используемые внутри защищённых страниц)
app/View/components/view_formatters.php
app/View/components/alert.php
app/View/components/button.php
app/View/components/input.php
app/View/components/form_actions.php
app/View/components/page_header.php
app/View/components/status_badge.php
app/View/components/table.php
app/View/components/empty_state.php
app/View/components/client_contact_fields.php
app/View/components/contractor_contact_fields.php

# Layouts
app/View/layouts/main.php
app/View/layouts/auth-layout.php

# Services
app/Service/ContractorContactService.php
app/Service/ClientContactService.php
app/Service/CompanyInnLookupService.php

# Handlers
app/Support/driver_create_handler.php

# JS / CSS
public/assets/js/app.js
public/assets/css/app.css
public/assets/css/erp-ui.css

# Migrations (локальные)
database/migrations-local/002–004, 007–009, 011–035

# Core
app/Core/Database.php
app/Http/Router.php
app/Support/helpers.php
```

**Запрет на изменение распространяется на:**
- routes (пути, методы, параметры)
- views (HTML-структура, классы, data-атрибуты)
- partials (формы, поля, input name, form action, method)
- JS-поведение: initDriverForm, модальные окна, file-pickers, upload validation
- CSS-геометрия: .driver-create-modal, .entity-form-layout, .driver-layout, все размеры
- формы: поля, валидация, submit-логика
- бизнес-логика: создание, редактирование, удаление, архивация
- документы: upload, replace, soft-delete, predef/custom docs
- модалки: openModal/closeModal, AJAX-подгрузка, DOM-структура
- код ошибок/сообщений: поля $errors, $formError, .form-alert

---

## 6. Что можно безопасно трогать

### 6.1 Документация
```
docs/ai/*.md                   — любые аналитические MD
docs/ui/DESIGN_STANDARD.md     — дизайн-стандарт
docs/design-audit/             — архив аудитов
```

### 6.2 Новые файлы без подключения
```
app/Service/DocumentService.php          — новый единый сервис документов (не подключать)
app/Service/AccessControlService.php     — новый слой прав (не подключать)
app/Service/DriverService.php            — рефакторинг driver-логики (параллельно)
app/Controller/Company/DriverController.php — новый контроллер (не подключать)
```

### 6.3 Тестово-диагностические скрипты
```
scripts/check_routes.php        — анализ всех маршрутов
scripts/check_duplicates.php   — поиск дублирования
scripts/check_unused.php       — поиск неиспользуемых файлов
```

### 6.4 Новые модули (НЕ трогая 4 ядра)
- /company/driver-vehicle-blocks (существующий, но без модалок — можно дорабатывать)
- /company/crews (существующий, можно дорабатывать)
- /company/documents (существующий, отдельный маршрут)
- /company/document-types (существующий, отдельный)

### 6.5 Подготовительные архитектурные карты
- Маршрутная карта (все 137 маршрутов с привязкой к строкам index.php)
- Карта зависимостей view → partial → component
- Карта таблиц → миграций → entity_type
- Карта прав: role → route → access check
- Карта дублирования: document upload, DB connect, migration ensure

### 6.6 Безопасные улучшения index.php (ТОЛЬКО с разрешения владельца)
- Исправление форматирования/отступов (без изменения логики)
- Добавление комментариев-разделителей
- Вынос helper-функций (lines 42–454) в отдельный файл
- Эти правки не затрагивают бизнес-логику

---

## 7. Legacy / Duplicate / Unused suspect

### 7.1 ACTIVE_CORE (подтверждённо активны)
```
app/Core/Database.php          — используется всеми модулями
app/Http/Router.php            — ядро роутинга
app/Support/helpers.php        — env(), base_path(), storage_path(), e()
app/View/layouts/main.php      — основной layout
app/View/components/*.php      — 10 компонентов, все активны
```

### 7.2 ACTIVE_MODULE (рабочие модули, не защищены но активны)
```
app/View/pages/company_vehicles.php          — /company/vehicles (работает)
app/View/pages/company_crews.php             — /company/crews
app/View/pages/company_driver_vehicle_blocks.php — /company/driver-vehicle-blocks
app/View/pages/company_logists.php           — /company/logists
app/View/pages/company_dashboard.php         — /company/dashboard
```

### 7.3 DO_NOT_TOUCH_WORKING_CORE
```
см. раздел 5 — полный список
```

### 7.4 LEGACY_SUSPECT (подозрение на устаревший код)
```
# Файлы, которые существуют но требуют проверки runtime
app/View/pages/login_form.php               — есть login route?
app/View/pages/error_403.php                — активен
app/View/pages/ui_demo.php                  — / demo page
app/View/layouts/auth-layout.php            — используется ли?
```

### 7.5 DUPLICATE_SUSPECT (подозрение на дублирование)
```
# Document upload logic — ДУБЛИРОВАНА минимум 4 раза:
- /company/clients/create    (стр. 2486–2530)
- /company/contractors/create (стр. 3545–3589)
- /company/drivers POST modal-edit (стр. 6960–7140)
- handleCompanyDriverCreate (driver_create_handler.php стр. 359–540)

# DB connect + migration ensure — ДУБЛИРОВАНО 50+ раз:
Патерн повторяется в КАЖДОМ роуте:
  $localDbConfig = $config['database'];
  $localDbConfig['database'] = $dbIdentifier;
  $localDb = new \App\Core\Database($localDbConfig);
  $localPdo = $localDb->connection();
  applyLocalMigrations($localPdo);

# Entity table ensure — ДУБЛИРОВАНО 30+ раз:
  try { $localPdo->query("SELECT 1 FROM <table> LIMIT 1")->fetch(); }
  catch (\Exception $e) { exec(migration);

# Validation patterns — ДУБЛИРОВАНЫ:
- ИНН: 10 или 12 цифр
- КПП: 9 цифр
- ОГРН: 13 или 15 цифр
- Банковский счёт: 20 цифр
- БИК: 9 цифр
Код валидации повторён в clients/create, contractors/create, contractors/edit
```

### 7.6 UNUSED_SUSPECT (возможно не используются)
```
# Требуют проверки grep по index.php
app/View/layouts/auth-layout.php      — упоминается ли в роутах?
app/View/pages/login_form.php         — есть ли /login route?
../FINAL3.html                        — исторический дизайн-источник (не в repo)
```

### 7.7 NEEDS_RUNTIME_CHECK
```
# Работоспособность через браузер:
- /company/crews — создание/редактирование экипажа
- /company/driver-vehicle-blocks — создание связки
- /company/logists — создание пользователя
- /company/documents — загрузка/замена документа
- /company/document-types — управление типами
- /superadmin/* — все админские страницы
```

---

## 8. Главные архитектурные ошибки

### 8.1 public/index.php — монолит (КРИТИЧЕСКИЙ)

15 541 строка, 670 KB. 137 маршрутов. Все замыкания содержат:
- подключение к БД (центральной и локальной)
- создание локальной БД (CREATE DATABASE IF NOT EXISTS)
- применение миграций (applyLocalMigrations)
- проверку существования таблиц (SELECT 1 ... LIMIT 1)
- inline ALTER TABLE
- валидацию форм
- бизнес-логику (INSERT/UPDATE/DELETE)
- upload документов с полным циклом обработки
- рендеринг view через ob_start/require/ob_get_clean
- проверки прав доступа

**Риск**: при росте до 200+ маршрутов файл станет неуправляемым.

### 8.2 Смешение слоёв (КРИТИЧЕСКИЙ)

В одном замыкании живут:
- Routing (получение параметров)
- Controller (validation, business logic)
- Service (document upload, contact management)
- Repository (SQL-запросы)
- View (рендеринг HTML)

Нет разделения на Controller / Service / Repository / ViewModel.

### 8.3 Дублирование document upload logic (ВЫСОКИЙ)

Логика загрузки документов (валидация расширений, размеров, создание директорий, move_uploaded_file, INSERT в documents) повторена минимум 4 раза с незначительными отличиями. При добавлении нового entity_type код будет скопирован в 5-й раз.

### 8.4 Дублирование DB connect + ensure (ВЫСОКИЙ)

50+ раз повторён паттерн:
```php
$localDbConfig = $config['database'];
$localDbConfig['database'] = $dbIdentifier;
$localDb = new \App\Core\Database($localDbConfig);
$localPdo = $localDb->connection();
applyLocalMigrations($localPdo);
```

Нет единой фабрики/реестра PDO-подключений.

### 8.5 Inline-JS в AJAX partials (СРЕДНИЙ)

Modal edit partial (`company_driver_modal_edit.php`) рендерит существующие документы с inline HTML и data-атрибутами. JS инициализируется через `initDriverForm(form)`, что лучше, чем было, но всё ещё хрупко при сложных DOM-манипуляциях.

### 8.6 Разные паттерны create/edit/view/list (СРЕДНИЙ)

- Водители: create через modal + standalone page, edit через modal
- Клиенты: create через standalone page + redirect, edit через standalone page
- Перевозчики: create через standalone page + create-full stepper
- Транспорт: create через modal

Нет единого стандарта. Каждый модуль реализован по-своему.

### 8.7 Риски с документами (СРЕДНИЙ)

- Нет единого DocumentService
- Upload logic дублирован
- Soft-delete реализован через `deleted_at`, но не везде консистентно
- Нет транзакционной связки "создание сущности + документы" — при падении upload остаётся orphan-запись

### 8.8 Риски с правами доступа (ВЫСОКИЙ)

Проверки прав разбросаны по замыканиям:
- `requireRole(['company_owner', 'logist'])` — на уровне роута
- `$isLogist = ... created_by_user_id = ? OR entity_access_grants ...` — в SQL
- `$canEdit = ...` — отдельная проверка для modal-view/modal-edit

Нет централизованного AccessControl. При добавлении senior_logist придётся править 20+ мест.

---

## 9. Риски для будущего развития

### 9.1 Риск: добавление senior_logist
Потребуется изменить:
- `requireRole()` — 1 место
- SQL-запросы `created_by_user_id` / `entity_access_grants` — ~12 мест в index.php
- sidebar `main.php` — добавить пункт меню для senior_logist
- Все list-страницы (drivers, vehicle-sets, clients, contractors) — фильтрация для senior_logist
- ~20 точечных правок в разных маршрутах

### 9.2 Риск: развитие экипажей (crews)
Сейчас crews = contractor_id + driver_vehicle_block_id (migration 019).
При доработке формы создания экипажа потребуется:
- Новый маршрут в index.php (ещё +200 строк)
- Новая view-страница
- JS для выбора driver_vehicle_block из списка
- Валидация уникальности связки

### 9.3 Риск: driver_vehicle_blocks
Сейчас список работает, но без модалок. Редактирование/просмотр через standalone page.
При добавлении модалок:
- Новые маршруты GET/POST modal-view, modal-edit
- JS-обработчики для модальных окон
- Документы для driver_vehicle_block

### 9.4 Риск: рост index.php
При текущем темпе каждый новый модуль добавляет ~800–2500 строк в index.php.
При 10 модулях файл превысит 25 000 строк — станет практически неподдерживаемым.

### 9.5 Риск: массовое удаление «дубликатов»
Удаление «дублированного» кода без предварительного тестирования может сломать:
- Работу с документами в конкретном entity_type
- Валидацию полей, специфичных для модуля
- Неочевидные зависимости (например, clients использует ClientContactService, а contractors — ContractorContactService, хотя логика похожа)

---

## 10. Модель ролей: superadmin / company_owner / logist / senior_logist

### 10.1 Текущая реализация

| Роль | Где хранится | Кто создаёт | Что видит |
|------|-------------|------------|-----------|
| superadmin | users (центральная БД) | seed/миграция 004 | Все компании, все данные |
| company_owner | company_users (центральная БД) | superadmin через /superadmin/companies/{id}/create-owner | Всё внутри своей компании |
| logist | users (локальная БД) | company_owner через /company/logists/create | Свои записи + grants |

### 10.2 Целевая модель

```
SUPERADMIN
├── Управляет компаниями (создание, редактирование, статус)
├── Создаёт company_owner для каждой компании
├── Видит все данные всех компаний
└── Не вмешивается в локальные данные (read-only мониторинг)

COMPANY_OWNER (Руководитель)
├── Создаёт logist и senior_logist
├── Видит ВСЕ записи внутри компании (drivers, vehicles, clients, contractors, crews)
├── Управляет доступами (entity_access_grants)
└── Не может видеть другие компании

SENIOR_LOGIST (Логист+)  ← НОВАЯ РОЛЬ
├── role_code = 'senior_logist'
├── Логин в локальной БД (users)
├── Видит ВСЕ записи внутри компании (как company_owner, но без прав администрирования)
├── НЕ может создавать/редактировать пользователей
├── НЕ может управлять доступами
├── НЕ видит другие компании
└── UI-лейбл: «Логист+»

LOGIST (Обычный логист)
├── role_code = 'logist'
├── Логин в локальной БД (users)
├── Видит ТОЛЬКО свои записи (created_by_user_id = self)
├── + записи, к которым выдан доступ через entity_access_grants
├── НЕ видит записи других логистов
└── UI-лейбл: «Логист»
```

### 10.3 Технические поля для ownership (уже есть)

```sql
-- В каждой entity-таблице (drivers, clients, contractors, etc.):
created_by_user_id INT UNSIGNED  -- кто создал запись
created_by_role VARCHAR(20)       -- роль создавшего

-- entity_access_grants:
entity_type VARCHAR(30)           -- 'driver', 'client', 'contractor', etc.
entity_id INT UNSIGNED            -- ID записи
granted_to_user_id INT UNSIGNED   -- кому выдан доступ
access_level VARCHAR(10)          -- 'view' или 'edit'
granted_by_user_id INT UNSIGNED   -- кто выдал
comment TEXT                      -- комментарий к доступу
revoked_at DATETIME               -- когда отозван
```

### 10.4 Как senior_logist должен видеть все записи компании

Вариант A (рекомендуемый): в коде проверять роль
```php
if ($role === 'company_owner' || $role === 'senior_logist') {
    // Видит всё
    $stmt = $localPdo->query("SELECT * FROM drivers ORDER BY created_at DESC");
} else {
    // Обычный logist
    $stmt = $localPdo->prepare("SELECT * FROM drivers WHERE created_by_user_id = ? OR ...");
}
```

Вариант B: автоматические grants при создании (сложнее, больше записей)

**Рекомендация**: Вариант A, проще и не меняет схему БД.

### 10.5 Таблица ролей — что нужно для миграции

```sql
-- Добавить в whitelist ролей:
-- В company/logists/create и company/logists/{id}/edit:
$allowedRoles = ['logist', 'senior_logist'];

-- В sidebar (main.php) — роль senior_logist видит то же меню, что logist
-- но без пункта «Логисты» (не может управлять пользователями)
```

**Без миграции БД** — роль будет храниться в существующем поле `users.role_code`.

---

## 11. План safe-refactoring по этапам

### Этап 0 — ЗАЩИТА ТЕКУЩЕГО ЯДРА (сейчас)

**Действия:**
1. ✅ Зафиксировать 4 рабочие страницы как DO_NOT_TOUCH_WORKING_CORE
2. ✅ Создать этот PROTECTED_ARCHITECTURE_PLAN.md
3. Обновить AGENT_RULES.md — добавить правило:
   ```
   DO_NOT_TOUCH_WORKING_CORE: /company/drivers, /company/vehicle-sets,
   /company/clients, /company/contractors. Любое изменение этих страниц
   требует отдельной явной задачи от владельца.
   ```
4. Обновить DECISIONS.md — зафиксировать решение о protected core
5. Обновить HANDOFF_FOR_NEW_CHAT.md — добавить ссылку на PROTECTED_ARCHITECTURE_PLAN.md

**Результат**: документальная защита, агенты не могут случайно сломать ядро.

### Этап 1 — ДОКУМЕНТАЦИЯ И КАРТЫ (безопасно)

**Действия:**
1. Маршрутная карта: CSV/таблица всех 137 маршрутов (method, path, line, view, handler-type)
2. Карта entity → table → migration → document_types → entity_type
3. Карта view → partial → component — полный граф зависимостей
4. Карта дублирования: найти все повторяющиеся блоки кода с указанием точных строк
5. Карта прав: role → route → access pattern
6. Карта файлов: каждый .php файл → статус (CORE/MODULE/LEGACY/UNUSED)

**Результат**: полная документация архитектуры для безопасного рефакторинга.

### Этап 2 — ЕДИНЫЙ ACCESS LAYER (новый код, не подключать к ядру)

**Действия:**
1. Создать `app/Service/AccessControlService.php`:
   ```php
   class AccessControlService {
       public static function canViewAll(string $role): bool;
       public static function canEditAll(string $role): bool;
       public static function canManageUsers(string $role): bool;
       public static function getListQuery(string $role, string $entityType, int $userId): array;
       // Возвращает ['where' => '...', 'params' => [...]]
   }
   ```
2. Добавить `senior_logist` в логику
3. НЕ подключать к существующим 4 страницам
4. Использовать ТОЛЬКО на новых модулях (crews, driver-vehicle-blocks)

**Результат**: готовый access layer для будущего использования.

### Этап 3 — ЕДИНЫЙ DOCUMENT SERVICE (новый код, не подключать к ядру)

**Действия:**
1. Создать `app/Service/DocumentService.php`:
   ```php
   class DocumentService {
       public static function upload(PDO $pdo, array $file, string $entityType, int $entityId, ...): array;
       public static function softDelete(PDO $pdo, int $documentId): void;
       public static function replace(PDO $pdo, int $documentId, array $newFile): array;
       public static function getByEntity(PDO $pdo, string $entityType, int $entityId): array;
       public static function validateFile(array $file): array; // ['ok' => bool, 'error' => string]
   }
   ```
2. Вынести общие константы ($allowedExt, $maxSize, пути)
3. НЕ подключать к существующим 4 страницам
4. Использовать на новых модулях

**Результат**: единый document service, готовый к использованию.

### Этап 4 — НОВЫЕ МОДУЛИ ПО НОВОМУ СТАНДАРТУ

**Действия:**
1. Доработка `/company/driver-vehicle-blocks`:
   - Добавить модалки create/edit/view (по образцу drivers)
   - Использовать новый AccessControlService
   - Использовать новый DocumentService
   - Реализовать через отдельный Controller-класс (НЕ в index.php, а через подключаемый файл)

2. Доработка `/company/crews`:
   - Модалки create/edit/view
   - Новые сервисы

**Результат**: два модуля, реализованных по целевому стандарту, без затрагивания ядра.

### Этап 5 — ПОСТЕПЕННАЯ ДЕКОМПОЗИЦИЯ PUBLIC/INDEX.PHP (после acceptance)

**Действия (по одному модулю за этап):**

1. Вынести DB connect + migration ensure в `app/Support/db.php`:
   ```php
   function getLocalPdo(array $config, string $dbIdentifier): PDO;
   ```
   Заменить 50+ повторений на один вызов.

2. Вынести валидацию ИНН/КПП/ОГРН/банковских реквизитов в `app/Support/validation.php`

3. Создать Controller-классы для каждого модуля:
   ```
   app/Controller/Company/DriverController.php
   app/Controller/Company/VehicleSetController.php
   app/Controller/Company/ClientController.php
   app/Controller/Company/ContractorController.php
   ```
   Маршруты в index.php станут тонкими: `$router->get('/company/drivers', [DriverController::class, 'list'])`

4. Каждый этап:
   - Один модуль
   - Сохранение маршрутов
   - Runtime-проверка
   - Коммит

**Результат**: index.php сокращается с 15 541 до ~2000 строк (только routing).

---

## 12. Первый рекомендуемый безопасный этап

### ЭТАП 0: Защита ядра (готов к выполнению прямо сейчас)

**Не требует изменения кода. Только документация.**

1. Принять этот PROTECTED_ARCHITECTURE_PLAN.md как утверждённый
2. Обновить AGENT_RULES.md:
   - Добавить раздел "PROTECTED WORKING CORE"
   - Перечислить 4 защищённые страницы
   - Запретить агентам их изменять без явной задачи
3. Обновить DECISIONS.md:
   - Решение #47: 4 страницы объявлены DO_NOT_TOUCH_WORKING_CORE
4. Обновить HANDOFF_FOR_NEW_CHAT.md:
   - Добавить ссылку на PROTECTED_ARCHITECTURE_PLAN.md
5. Обновить PROJECT_STATE.md:
   - Добавить строку: `PROTECTED_ARCHITECTURE_PLAN — ПРИНЯТ (c19c67a)`

**Проверка**: `git diff --check`, `php -l` на изменённых MD (не применимо к MD), визуальная проверка.

---

## 13. Какие MD нужно обновить

| MD файл | Что обновить |
|---------|-------------|
| `docs/ai/AGENT_RULES.md` | Добавить раздел 9 "PROTECTED WORKING CORE" с 4 страницами |
| `docs/ai/DECISIONS.md` | Решение #47: DO_NOT_TOUCH_WORKING_CORE |
| `docs/ai/HANDOFF_FOR_NEW_CHAT.md` | Ссылка на PROTECTED_ARCHITECTURE_PLAN.md |
| `docs/ai/PROJECT_STATE.md` | Строка о принятом плане |
| `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md` | Этот документ — создан |

---

## 14. Какие проверки нужны перед первым рефакторингом

```bat
REM Проверка синтаксиса всех PHP-файлов
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && for /r %f in (*.php) do @php -l "%f" 2>&1 | findstr /V "No syntax errors""

REM Проверка целостности Git
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git status && git diff --check"

REM Подсчёт строк в ключевых файлах
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && find /c /v "" public\index.php"

REM Поиск ВСЕХ маршрутов (для карты)
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && findstr /N \"router->get\|router->post\" public\index.php"

REM Поиск entity_access_grants (сколько мест править для senior_logist)
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && findstr /N \"entity_access_grants\" public\index.php"

REM Runtime: запустить сервер и проверить 4 страницы
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -d upload_max_filesize=25M -d post_max_size=100M -d max_file_uploads=50 -d memory_limit=256M -d max_execution_time=120 -d max_input_time=120 -S 127.0.0.1:8016 -t public public/index.php"
```

---

## 15. Что передать ChatGPT для написания следующего промта на рефакторинг

См. раздел NEXT_PROMPT_INPUT ниже.

---

## NEXT_PROMPT_INPUT

```text
ЗАЩИЩЁННЫЕ СТРАНИЦЫ (DO_NOT_TOUCH_WORKING_CORE):
- /company/drivers
- /company/vehicle-sets
- /company/clients
- /company/contractors

Эти страницы и все их файлы (routes, views, partials, JS, CSS, services,
таблицы, документы) НЕЛЬЗЯ изменять без отдельной явной задачи.

ФАЙЛЫ, КОТОРЫЕ НЕЛЬЗЯ ТРОГАТЬ:
- public/index.php (строки 2076–7483, 9389–11195)
- Все View/pages/company_drivers*.php, company_vehicle_sets*.php,
  company_clients*.php, company_contractors*.php
- Все View/partials/company_driver_*.php, company_vehicle_set_*.php
- Все View/components/*.php
- app/View/layouts/main.php
- app/Service/ContractorContactService.php, ClientContactService.php,
  CompanyInnLookupService.php
- app/Support/driver_create_handler.php
- public/assets/js/app.js (initDriverForm, модалки, file-pickers)
- public/assets/css/app.css, erp-ui.css
- database/migrations-local/002–004, 007–009, 011–035
- app/Core/Database.php, app/Http/Router.php

ПЕРВЫЙ БЕЗОПАСНЫЙ ЭТАП (не требует изменения кода):
Обновить MD-документацию:
- AGENT_RULES.md: добавить раздел PROTECTED WORKING CORE
- DECISIONS.md: решение #47
- HANDOFF_FOR_NEW_CHAT.md: ссылка на PROTECTED_ARCHITECTURE_PLAN.md
- PROJECT_STATE.md: строка о принятом плане

ГЛАВНЫЕ АРХИТЕКТУРНЫЕ РИСКИ:
- index.php: 15 541 строка, 137 маршрутов, монолит
- Document upload дублирован 4 раза
- DB connect + ensure дублирован 50+ раз
- Нет единого AccessControl
- Нет единого DocumentService
- Разные паттерны create/edit для разных модулей

ПЛАН ДАЛЬНЕЙШИХ ЭТАПОВ (после защиты ядра):
1. Документация и карты (маршрутов, сущностей, файлов, прав)
2. Создать AccessControlService (новый, не подключать к ядру)
3. Создать DocumentService (новый, не подключать к ядру)
4. Новые модули (driver-vehicle-blocks, crews) по новому стандарту
5. Постепенная декомпозиция index.php (по одному модулю)

КОМАНДЫ ПРОВЕРКИ:
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git status"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l public\index.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && findstr /N \"entity_access_grants\" public\index.php"
```

---

## FINAL REPORT

```
STATUS: PROTECTED_ARCHITECTURE_PLAN_READY

FILES CHANGED:
  CREATED: docs/ai/PROTECTED_ARCHITECTURE_PLAN.md (этот документ)

CHECKS:
  - git status: clean
  - 4 защищённые страницы идентифицированы
  - 137 маршрутов в index.php (68 GET + 69 POST)
  - index.php: 15 541 строка, 670 KB
  - 35 локальных миграций (001–034)
  - app.js: 2 216 строк
  - 3 Service-класса, 2 handler-файла

RUNTIME:
  Не требуется (аналитическая задача)

WHAT OWNER MUST CHECK:
  1. Состав защищённых страниц (4 шт.) — утвердить
  2. Состав DO_NOT_TOUCH файлов — утвердить
  3. План этапов 0–5 — утвердить очерёдность
  4. Модель ролей senior_logist — утвердить
  5. Следующий шаг: обновить MD-документацию (Этап 0)

COMMIT: нет (только аналитический документ, ждать решения владельца)
```
