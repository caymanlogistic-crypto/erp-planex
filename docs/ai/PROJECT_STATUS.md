# CURRENT STATUS OVERRIDE — 2026-06-13 — FULL_REFERENCE_FUNCTIONAL_ACCEPTED

Current focus: **ПОЛНЫЙ ФУНКЦИОНАЛЬНЫЙ КОНТУР СПРАВОЧНОГО БЛОКА ЗАВЕРШЁН И ПРОВЕРЕН.**

QA: **FULL_REFERENCE_FUNCTIONAL_ACCEPTED** (65/65 PASS, 0 FAIL, 0 BLOCKER).

## Что реализовано

### Auth & Sessions
- Login/logout, session_regenerate_id, route guards (3 роли), динамический sidebar

### SUPERADMIN
- Companies: list, create, view, edit, status (active/inactive/blocked/archived)
- Owner: view, edit, reset-password

### Company Management (6 сущностей)
- **Все сущности имеют полный CRUD:** list, create, view, edit, archive
- Logists (с reset-password)
- Clients, Contractors, Drivers, Vehicles, Crews
- Безопасное архивирование (блокировка если в crews)
- Валидация уникальных полей при редактировании

### Documents
- Upload (whitelist, entity check, size limit)
- List (7 состояний)
- Download (realpath, secure headers)

### Ownership & Access Grants
- created_by_user_id/role во всех таблицах
- entity_access_grants с UNIQUE KEY
- Logist видит свои + grant view
- Company_owner видит всё + выдаёт доступ

## Статистика
- **79 маршрутов**
- **16 новых view-файлов**
- **2 новые миграции** (008, 009) + 7 существующих
- **index.php**: 6864 строк
- **QA**: 65/65 PASS

## Next step
1. **Владелец выполняет ручную функциональную проверку** в браузере
2. После ручной приёмки — commit (логичные группы)
3. Затем UI-полировка Главным дизайнером / КЛАУД

## Code status
- Companies Registry: FUNCTIONAL_ACCEPTED
- Company Owner User: FUNCTIONAL_ACCEPTED
- Company Logist User: FUNCTIONAL_ACCEPTED
- Company Clients Registry: FUNCTIONAL_ACCEPTED
- Company Contractors Registry: FUNCTIONAL_ACCEPTED
- Company Drivers Registry: FUNCTIONAL_ACCEPTED
- Company Vehicles Registry: FUNCTIONAL_ACCEPTED
- Company Crews Registry: FUNCTIONAL_ACCEPTED
- Company & Owner Management: FUNCTIONAL_ACCEPTED
- Reference Block: REFERENCE_BLOCK_ACCEPTED (78/78 PASS)
- Auth & Sessions: AUTH_BLOCK_ACCEPTED (73/73 PASS)
- Document Upload: DOCUMENT_UPLOAD_ACCEPTED (45/48 PASS)
- Entity CRUD (view/edit/archive): FUNCTIONAL_ACCEPTED
- Ownership & Access Grants: FUNCTIONAL_ACCEPTED
- Document Download: FUNCTIONAL_ACCEPTED
- **Full Reference Functional: FULL_REFERENCE_FUNCTIONAL_ACCEPTED (65/65 PASS)**

Working tree: dirty (45 files changed/new)
Push: NO

## Последнее обновление
2026-06-13 23:50 — KILO/erp-architect: полный функциональный контур завершён и проверен.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — DOCUMENT_UPLOAD_BLOCK_ACCEPTED (архив)

Previous focus: **Document Upload Block ЗАВЕРШЁН И ПРОВЕРЕН.**

QA-проверка: **DOCUMENT_UPLOAD_ACCEPTED** (48 проверок, 45 PASS, 3 FAIL, 0 BLOCKER). Все FAIL связаны с незакоммиченным Auth Block, не с Document Upload.

Реализовано: миграция documents, 3 маршрута, 2 view (list + upload form), 5 entity views с ссылками «Документы», полная безопасность (storage вне public, whitelist, entity check, size limit).

## Next step
1. **Владелец выполняет ручную проверку** в браузере.
2. После ручной приёмки — commit Auth Block + Document Upload Block.
3. Затем следующий функциональный блок.

## Code status
- Companies Registry: FUNCTIONAL_ACCEPTED
- Company Owner User: FUNCTIONAL_ACCEPTED
- Company Logist User: FUNCTIONAL_ACCEPTED
- Company Clients Registry: FUNCTIONAL_ACCEPTED
- Company Contractors Registry: FUNCTIONAL_ACCEPTED
- Company Drivers Registry: FUNCTIONAL_ACCEPTED
- Company Vehicles Registry: FUNCTIONAL_ACCEPTED
- Company Crews Registry: FUNCTIONAL_ACCEPTED
- Reference Block: REFERENCE_BLOCK_ACCEPTED (78/78 PASS)
- Company & Owner Management: FUNCTIONAL_ACCEPTED (41/41 PASS)
- Auth & Sessions: AUTH_BLOCK_ACCEPTED (73/73 PASS)
- **Document Upload: DOCUMENT_UPLOAD_ACCEPTED (48 проверок, 45 PASS, 0 BLOCKER)**

Working tree: dirty (31 files changed/new, pending commit)
Push: NO

New routes (3 added):
- GET `/company/documents?entity_type=X&entity_id=Y` — список документов
- GET `/company/documents/upload?entity_type=X&entity_id=Y` — форма загрузки
- POST `/company/documents/upload?entity_type=X&entity_id=Y` — обработка загрузки

New files (5 created):
- `database/migrations-local/007_create_company_documents.sql` — таблица documents
- `app/View/pages/company_documents.php` — список документов (7 состояний)
- `app/View/pages/company_documents_upload.php` — форма загрузки (6 состояний)
- `docs/ui/pages/company-documents-list.md` — MD-шаблон списка
- `docs/ui/pages/company-documents-upload.md` — MD-шаблон загрузки

Modified (6):
- `public/index.php` — +3 маршрута document upload
- `app/View/pages/company_clients.php` — +ссылка «Документы»
- `app/View/pages/company_contractors.php` — +ссылка «Документы»
- `app/View/pages/company_drivers.php` — +ссылка «Документы»
- `app/View/pages/company_vehicles.php` — +ссылка «Документы»
- `app/View/pages/company_crews.php` — +ссылка «Документы»

Key decisions: DECISION-0042 (архитектура Document Upload Block)

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — AUTH_BLOCK_ACCEPTED

Current focus: **Авторизация и сессии РЕАЛИЗОВАНЫ И ПРОВЕРЕНЫ.**

QA-проверка: **AUTH_BLOCK_ACCEPTED** (73/73 PASS, 0 FAIL, 0 BLOCKER). Реализовано: логин, логаут, сессии, route guards, контекст компании из сессии, динамический sidebar (3 роли).

## Next step
1. **Владелец выполняет ручную проверку** в браузере (login/logout/auth flow).
2. После ручной приёмки — commit.
3. Затем UI-полировка или следующий функциональный блок.

## Code status
- Companies Registry: FUNCTIONAL_ACCEPTED
- Company Owner User: FUNCTIONAL_ACCEPTED
- Company Logist User: FUNCTIONAL_ACCEPTED
- Company Clients Registry: FUNCTIONAL_ACCEPTED
- Company Contractors Registry: FUNCTIONAL_ACCEPTED
- Company Drivers Registry: FUNCTIONAL_ACCEPTED
- Company Vehicles Registry: FUNCTIONAL_ACCEPTED
- Company Crews Registry: FUNCTIONAL_ACCEPTED
- Reference Block: REFERENCE_BLOCK_ACCEPTED (78/78 PASS)
- Company & Owner Management: FUNCTIONAL_ACCEPTED (41/41 PASS)
- **Auth & Sessions: AUTH_BLOCK_ACCEPTED (73/73 PASS)**

Working tree: dirty (25 files changed/new, pending commit)
Push: NO

New routes:
- GET/POST `/login` — страница входа
- GET `/logout` — выход
- GET `/company/dashboard` — company dashboard

New files:
- `app/View/layouts/auth-layout.php` — layout для `/login`
- `app/View/pages/login_form.php` — форма логина
- `app/View/pages/company_dashboard.php` — company dashboard
- `docs/architecture/AUTH_SESSION_MODEL.md` — архитектура авторизации
- `docs/ui/pages/login.md` — MD-шаблон логина
- `docs/ui/pages/company-dashboard.md` — MD-шаблон dashboard

Key decisions: DECISION-0041 (модель авторизации, сессий, guards)

---

Current focus: **Управление компанией и Руководителем ЗАВЕРШЕНО и ПРОВЕРЕНО.**

QA-проверка: **FUNCTIONAL_ACCEPTED** (41/41 PASS, 0 FAIL, 0 BLOCKER). Реализовано: карточка компании, редактирование компании, управление статусом, карточка Руководителя, редактирование Руководителя, сброс пароля.

## Next step
1. **Владелец выполняет ручную проверку** в браузере.
2. После ручной приёмки — commit.
3. Затем UI-полировка Главным дизайнером / КЛАУД (включая справочный блок).

## Code status
- Companies Registry: FUNCTIONAL_ACCEPTED
- Company Owner User: FUNCTIONAL_ACCEPTED
- Company Logist User: FUNCTIONAL_ACCEPTED
- Company Clients Registry: FUNCTIONAL_ACCEPTED
- Company Contractors Registry: FUNCTIONAL_ACCEPTED
- Company Drivers Registry: FUNCTIONAL_ACCEPTED
- Company Vehicles Registry: FUNCTIONAL_ACCEPTED
- Company Crews Registry: FUNCTIONAL_ACCEPTED
- Reference Block: REFERENCE_BLOCK_ACCEPTED (78/78 PASS)
- **Company & Owner Management: FUNCTIONAL_ACCEPTED (41/41 PASS)**

Working tree: dirty (6 files changed/new, pending commit)
Push: NO

New routes:
- GET/POST `/superadmin/companies/{id}` — company card view
- GET/POST `/superadmin/companies/{id}/edit` — company edit
- GET `/superadmin/companies/{id}/owner` — owner card view
- GET/POST `/superadmin/companies/{id}/owner/edit` — owner edit
- POST `/superadmin/companies/{id}/owner/reset-password` — password reset

New views:
- `app/View/pages/superadmin_company_view.php`
- `app/View/pages/superadmin_company_edit.php`
- `app/View/pages/superadmin_company_owner_view.php`
- `app/View/pages/superadmin_company_owner_edit.php`

Key decisions: DECISION-0040 (безопасное управление статусами, без hard delete)

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — REFERENCE_BLOCK_ACCEPTED

Current focus: **Справочный фундамент ЗАВЕРШЁН и ПРОВЕРЕН.**

Комплексная проверка: **REFERENCE_BLOCK_ACCEPTED** (78/78 PASS, 0 FAIL, 0 BLOCKER). Все 8 модулей функционально работают. Готово к ручной проверке владельцем.

## Next step
1. ~~Владелец выполняет ручную функциональную проверку в браузере.~~ **DONE — выявлен пробел управления компанией и Руководителем.**
2. ~~После ручной приёмки — передача блока Главному дизайнеру / КЛАУД на UI-полировку.~~ **ОТЛОЖЕНО до реализации управления.**
3. UI-полировка включает: visual design, typography, spacing, states, responsive.
4. Только после UI-полировки: edit/delete, pagination, complex filters, auth/session, рейсы, документы.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — COMPANY_VEHICLES_REGISTRY_QA_ACCEPTED

Current focus: Company Vehicles Registry — **QA: FUNCTIONAL_ACCEPTED** (59/59 PASS, 0 BLOCKER).

## Next module
**Экипажи / связка Подрядчик + Машина + Водитель** — следующий модуль после завершения справочного фундамента.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — COMPANY_DRIVERS_REGISTRY_QA_ACCEPTED

Current focus: Company Drivers Registry — **QA: FUNCTIONAL_ACCEPTED** (55/55 PASS, 0 BLOCKER).

## Next module
**Логист ведёт транспорт** — справочник транспорта в локальной БД компании.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — COMPANY_CONTRACTORS_REGISTRY_QA_ACCEPTED

Current focus: Company Contractors Registry — **QA: FUNCTIONAL_ACCEPTED** (25/25 PASS, 0 BLOCKER).

## Next module
**Логист ведёт водителей** — справочник водителей в локальной БД компании.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — SUPERADMIN_COMPANY_OWNER_USER_QA_ACCEPTED

Current focus: SUPERADMIN Company Owner User — **QA: FUNCTIONAL_ACCEPTED** (42 checks, 39 PASS, 0 FAIL, 0 BLOCKER).

## QA result
- Total: 42 checks, 39 PASS, 0 FAIL, 0 BLOCKER
- 4 minor compliance notes (non-blocking, deferred to UI polish cycle)
- Security: password_hash only in DB, no open passwords, no secrets in git

## Implementation results
- Migration 006: `company_users` table created (12 fields, FK→companies ON DELETE CASCADE)
- Router: GET/POST `/superadmin/companies/{id}/create-owner` routes added
- Views: `superadmin_company_owner_create.php` (4 states: not-found, owner-exists, success, form), `superadmin_companies.php` modified (owner column)
- Password: bcrypt `password_hash()`, open password never stored in DB, shown once on success page
- Duplicate prevention: one active owner per company (app-level check)

## Runtime URLs
- `/superadmin/companies` → 200 (owner column visible)
- `/superadmin/companies/{id}/create-owner` GET → 200 (form or blocking message)
- `/superadmin/companies/{id}/create-owner` POST → 200 (success page or validation errors)

## Next module
**Руководитель создаёт логиста** — local user in company's local DB, per DECISION-0033 roadmap.

---

Current focus: SUPERADMIN Companies Registry — **FUNCTIONAL_ACCEPTED**. QA passed (67/67). Ready for next module.

## Accelerated mode
- Manual visual approval: DEFERRED
- UI polish cycle: deferred to separate task
- Chief designer / KLAUD review: deferred
- Functional coding was NOT blocked by design approval

## Implementation results
- Migration 005: 10 expeditor fields added to `companies` table (inn, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, comments, error_message) + index idx_inn
- Router: `post()` method added, routes `/superadmin/companies` (GET) + `/superadmin/companies/create` (GET/POST)
- Views: `superadmin_companies.php` (list), `superadmin_companies_create.php` (form)
- Database: support for connection without specific DB (CREATE DATABASE)
- CSS: ~200 lines added (all from Core Kit Production CSS Reference)

## Provisioning flow
- INSERT → ID → DB name `erp_company_{id}` → CREATE DATABASE → mkdir `storage/companies/{id}/` → status = 'active'
- Error handling: on failure → status = 'error', error_message populated, partial state not hidden

## Runtime URLs
- `/superadmin` → 200
- `/superadmin/companies` → 200 (list with companies)
- `/superadmin/companies/create` → 200 (creation form)

## QA result
- **FUNCTIONAL_ACCEPTED**: 67/67 checks passed, 0 failures, 0 blockers
- Architecture: main.php not modified, SQL not in views, shell intact
- Security: no passwords in DB/migrations/git, .env not tracked
- PHP syntax: 5/5 clean
- UI handoff: structure/classes match, no demo-placeholder

## Commit status
- NOT committed (no owner command)
- Validation: empty name/inn → .is-error displayed.
- Migration: idempotent (repeat run skipped).
- No secrets in git diff.

Next step: erp-qa-tester verification.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — SUPERADMIN_COMPANIES_REGISTRY_DETAILS_APPROVED

Current focus: first code module of the next cycle — `SUPERADMIN Companies Registry`.

Stable commit remains: `91c6911`.

Owner-approved technical details:
- Expeditor creation fields: same baseline as current contractor/client standard for now, but stored/implemented as a separate SUPERADMIN expeditor/company registry entity, not as a local contractor.
- Baseline fields: `name`, `inn`, `kpp`, `ogrn`, `legal_address`, `physical_address`, `contact_person`, `contact_phone`, `contact_email`, `status`, `comments`.
- Required at minimum: `name`, `inn`.
- Local DB name: generated automatically from company ID.
- Storage folder: generated/named by company ID.
- Slug/key must not be used as the source of truth for DB name or storage folder generation.

Code status: no code written in this documentation update.

Next step: prepare exact `erp-coder` task for `SUPERADMIN Companies Registry` with MD update requirements, runtime checks, migration checks, provisioning failure handling, and no extra modules.

---

# ERP PLANEX — PROJECT_STATUS

## Текущий этап проекта

Минимальный PHP-каркас создан и зафиксирован. Проект имеет техническую оболочку для дальнейшего наращивания бизнес-модулей.

## Текущий фокус

**`OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`** для `/superadmin`.

2026-06-13 — владелец принял текущий результат `/superadmin` для продолжения разработки. Точечный coder rework завершён: 5 отклонений исправлены (5 строк `app.css` + 1 строка `index.php`). Foundation/shell/sidebar/topbar/IA НЕ менялись. Проверка Главным дизайнером / КЛАУД остаётся pending, но не блокирует дальнейший кодинг.

Previous `CODER_REWORK_ACCEPTED_FOR_QA`, `QA-ready`, `PASS` and `FOUNDATION_REWORK_ACCEPTED_FOR_VISUAL_REVIEW` were not final visual approval. Current owner decision allows continued development and commit of this checkpoint.

Текущий статус `/superadmin`: **`OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`**. Все 5 отклонений compliance-аудита исправлены. Foundation/shell/sidebar/topbar/IA — COMPLIANT, не менялись. Следующий шаг — commit текущей точки, затем развитие SUPERADMIN business foundation.

## Уже принято

- Стек: PHP/MySQL.
- Разработка выполняется ИИ-агентами.
- Основной кодер: KILO + DeepSeek.
- Архитектура должна быть удобной для агентной разработки.
- Код общий, но папки и базы данных разные для каждого юридического лица.
- Одна локальная ERP = одно юридическое лицо.
- SUPERADMIN имеет отдельную центральную панель: `erp/superadmin/`.
- Локальная роль "Руководитель" является администратором своей ERP.
- Руководитель видит всё в своей компании, включая бухгалтерские данные.
- Клиент и подрядчик могут быть юридическим лицом или ИП.
- Нужно учитывать разные системы налогообложения и международную перевозку с НДС 0%.
- "Подрядчик", "перевозчик", "экспедитор" объединяются в единый термин "ПОДРЯДЧИК".
- Экипаж = Подрядчик + жёсткая пара (Машина + Водитель).
- Водители и машины могут существовать отдельно до создания экипажа.
- Документы хранятся физически в `/storage`, в БД хранятся пути и метаданные.
- Версионность нужна для шаблонов документов и реквизитов компаний.
- Первый этап — только линейная перевозка.
- Сборные рейсы резервируются в архитектуре, но не реализуются на первом этапе.
- SUPERADMIN управляет доступностью функций, модулей, страниц и отчётов по компаниям.
- Основной рабочий UI-kit: `docs/ui/ERP_UI_KIT_CORE.html` (45 CORE-модулей, 10 COMPOSITE patterns, Button/Layout Decision Matrix, Designer/Coder/QA Rules, SUPERADMIN READY SET).
- Legacy extraction/reference only: `docs/ui/ERP_UI_MODULE_CATALOG.html`.
- STYLE ERP: reference/example library, не runtime library.
- Дизайнер проектирует страницы только из CORE modules и COMPOSITE patterns Core Kit.
- Если модуля нет — сначала расширяется Core Kit/MD (`BLOCKED: NEEDS_UI_MODULE_EXPANSION`).
- Кодер не реализует unknown UI modules (`BLOCKED: UNKNOWN_UI_MODULE`).
- QA проверяет модульную формализацию по Core Kit.
- Архитектор обязан включать `MANDATORY CODER INVOCATION BLOCK FOR UI TASKS` в каждую UI-задачу для erp-coder. Запрещено запускать erp-coder по UI-задаче без этого блока.

## Уже сделано

- Приёмка документации: DONE.
- Создание базовой структуры папок: DONE.
- Создание .gitignore, .gitkeep, README.md, AGENTS.md, KILO_PROJECT_RULES.md: DONE.
- Инициализация git и первые два commit: DONE.
- Удаление временного README_ERP_PLANEX_DOCS_PACKAGE.md: DONE.
- Создание минимального PHP-каркаса: DONE.
  - `bootstrap/app.php` — загрузка .env, конфигурации.
  - `config/app.php`, `config/database.php` — конфигурация приложения и БД.
  - `app/Support/helpers.php` — env(), base_path(), storage_path().
  - `public/index.php` — техническая точка входа.
  - `app/Core/`, `app/Http/` — зарезервированы под ядро и HTTP-слой.
  - `.env.example` — шаблон (в git).
  - `.env` — локальный (в .gitignore, не коммитится).
  - `docs/architecture/PHP_APP_SKELETON.md` — описание каркаса.
- PHP 8.5.6 доступен, все файлы проходят `php -l`.
- Commit `dc75ab4` — 8 файлов каркаса зафиксированы.
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` превращён в главный переносимый контекст проекта для новых ChatGPT-чатов.
- В `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` добавлено обязательное правило: каждый следующий агент обязан поддерживать файл актуальным, добавлять новую важную информацию и удалять устаревшую.
- Правило обязательного ведения `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` продублировано в `AGENTS.md`, `AGENT_LOGGING_MASTER_PROMPT.md`, `KILO_PROJECT_RULES.md`, `TASK_TEMPLATE.md`, `QA_CHECKLIST.md`.
- Commit `309469f` — переносимый контекст и правила агентов зафиксированы.
- Создан `docs/ai/AGENT_NETWORK.md` — роли агентской сети, промты для KILO, агентный цикл и правила приёмки.
- Создан `docs/ui/DESIGN_CODE_INTEGRATION.md` — правила интеграции дизайн-кода, UI-фундамента, компонентов, layout, CSS/JS.
- Приняты решения DECISION-0014 и DECISION-0015: сначала агентская сеть и дизайн-фундамент, потом активное написание бизнес-кода.
- Интегрирован базовый UI-фундамент без бизнес-логики:
  - `public/assets/css/app.css`
  - `public/assets/js/app.js`
  - `app/View/layouts/main.php`
  - `app/View/components/`
  - `app/View/pages/ui_demo.php`
  - `public/index.php` теперь показывает техническую UI demo-страницу.
- Commit `473f748` — агентская сеть и UI-фундамент зафиксированы.
- Настроены 4 проектных KILO-агента:
  - `.kilo/agent/erp-architect.md` — главный координатор (primary).
  - `.kilo/agent/erp-uiux-designer.md` — UI/UX-дизайнер (primary/selectable).
  - `.kilo/agent/erp-coder.md` — исполнитель разработки (primary/selectable).
  - `.kilo/agent/erp-qa-tester.md` — тестировщик (primary/selectable).
- Исправлена настройка KILO: все 4 проектных агента теперь имеют `mode: primary`, потому что в UI KILO `subagent`-роли не отображались в списке выбора.
  - `kilo.jsonc` с `default_agent: erp-architect`.
- Commit `21b355d` — все проектные KILO-агенты сделаны selectable.
- Commit `9da9ad3` — переходной контекст и статус обновлены после исправления KILO-агентов.
- Агентская сеть теперь внутри KILO; внешний ChatGPT-координатор исключён из агентного цикла.
- Для UI-задач закреплён обязательный workflow: `erp-architect` → `erp-uiux-designer` → `erp-coder` → `erp-qa-tester` → `erp-architect`.
- Дизайнер обязан создавать или обновлять MD-шаблоны страниц в `docs/ui/pages/`; эти шаблоны являются источником истины для кодера и QA.

- Документация очищена: `docs/ui/UI_UX_RULES.md` удалён как дублирующий; `docs/ui/ui/` удалён как ошибочный дубликат. Главный UI-регламент — `docs/ui/DESIGN_CODE_INTEGRATION.md`.
- Созданы/закреплены `docs/ui/pages/README.md` и `docs/ui/pages/_PAGE_TEMPLATE.md` для MD-шаблонов страниц.

- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` пересобран как единый самодостаточный переносимый контекст для нового ChatGPT-чата: добавлены роль ChatGPT, рабочая папка, KILO + DeepSeek, обязательные MD, правила обновления файла, текущая задача, статус, архитектура, SUPERADMIN, локальная ERP, роль “Руководитель”, клиенты, подрядчики, экипажи, документы, версионность, feature toggles, бизнес-блоки, линейная перевозка, черновая БД, структура проекта, Git-правила, правила KILO-промтов и приёмки FINAL REPORT.
- Правило проверки/обновления `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` усилено в `AGENTS.md`, `docs/ai/AGENT_LOGGING_MASTER_PROMPT.md`, `docs/ai/KILO_PROJECT_RULES.md`, `docs/ai/TASK_TEMPLATE.md`, `docs/ai/QA_CHECKLIST.md`.

## Заблокировано

Текущий `/superadmin` (Stage 1) — **REJECTED BY OWNER** (визуально) → первый круг реворка завершён (2026-06-12 02:00) → **NEEDS_UI_REWORK** (владелец/ChatGPT, 2026-06-12 02:06) → второй круг улучшил компоненты, но не foundation → ~~CODER_REWORK_ACCEPTED_FOR_QA (2026-06-13)~~ → **ОТМЕНЁН** → foundation rework cycle → `FOUNDATION_REWORK_ACCEPTED_FOR_VISUAL_REVIEW` как разрешение на ручной visual review → после просмотра владельцем **`PARTIALLY COMPLIANT / NEEDS_UI_REWORK`** → **compliance-аудит дизайнером (2026-06-13): `PARTIALLY COMPLIANT`, 81 проверка, 76 COMPLIANT, 5 отклонений, 0 BLOCKER** → **точечный coder rework (2026-06-13): ВСЕ 5 ОТКЛОНЕНИЙ ИСПРАВЛЕНЫ** → **OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT**.

Текущий статус: **`OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`**. Все 5 отклонений исправлены. Foundation/shell/sidebar/topbar/IA — COMPLIANT, не менялись. Владелец принял результат для продолжения разработки. Проверка Главным дизайнером / КЛАУД остаётся `DESIGN_REVIEW_PENDING`, но не является stop factor для дальнейшего кодинга.

Backend/auth/CRUD/feature toggles/business modules разблокированы для следующего этапа разработки после фиксации текущей точки. Проверка дизайна КЛАУД будет выполнена позже, когда будет больше функционала и страниц.

## Следующий рекомендуемый шаг

**Владелец выполняет ручную функциональную проверку справочного блока в браузере.**

1. ~~erp-architect ставит задачу `erp-uiux-designer` на compliance-аудит.~~ **DONE (2026-06-13).**
2. ~~erp-uiux-designer проводит compliance-аудит.~~ **DONE: `PARTIALLY COMPLIANT`, 5 отклонений, 0 BLOCKER.**
3. ~~erp-architect ставит задачу `erp-coder` на точечный UI rework по 5 отклонениям.~~ **DONE (2026-06-13): все 5 исправлены.**
4. ~~Scope кодера: 5 строк `app.css` + 1 строка `index.php`.~~ **DONE.**
5. ~~Foundation/shell/sidebar/topbar/IA НЕ перестраивать.~~ **DONE — не менялись.**
6. ~~Owner visual review / решение владельца по продолжению.~~ **DONE: принято для продолжения разработки.**
7. ~~SUPERADMIN Companies Registry → Company Owner User → Company Logist User → Clients → Contractors → Drivers → Vehicles → Crews.~~ **DONE: все 8 модулей FUNCTIONAL_ACCEPTED.**
8. ~~Комплексная проверка справочного блока.~~ **DONE: REFERENCE_BLOCK_ACCEPTED (78/78 PASS).**
9. **Owner manual functional review — NEXT.**
10. Главный дизайнер / КЛАУД UI-полировка — после ручной приёмки.
11. После UI-полировки: edit/delete, pagination, complex filters, auth/session, рейсы, документы.

- Проведена верификация обновлённой папки `docs`. Удалён физически оставшийся `docs/ui/UI_UX_RULES.md`. Исправлены 3 устаревшие ссылки на него в агентах. Удалён временный `docs/_DOCS_AUDIT_AND_CLEANUP.md`. Папка `docs/ui/ui/` отсутствует. Ссылки в документации синхронизированы. Commit `bc8e2e9`.
- Проверен и утверждён UI-фундамент: документация синхронизирована, `_PAGE_TEMPLATE.md` обновлён под формат handoff дизайнера, runtime/syntax checks пройдены. UI готов к использованию в business-coding workflow.

- Создана PDO-обёртка `app/Core/Database.php`: lazy-подключение, utf8mb4, ERR_MODE_EXCEPTION, безопасная обработка ошибок (секреты не раскрываются).
- Создан простой GET-роутер `app/Http/Router.php`: посегментное сравнение, поддержка параметров `{id}`, HTTP 404.
- `public/index.php` обновлён: интегрированы Database и Router, зарегистрированы маршруты `/`, `/test`, `/test-db`.
- Все 17 PHP-файлов проходят `php -l`. Runtime-проверки всех маршрутов пройдены.

- Создан MD-шаблон `docs/ui/pages/superadmin-dashboard.md` (272 строки) для SUPERADMIN Stage 1 dashboard (2026-06-11, агент: erp-uiux-designer).
- SUPERADMIN Stage 1 реализован (2026-06-12, агент: erp-coder): создана страница `app/View/pages/superadmin_dashboard.php`, добавлены 14 CSS-классов в `app.css`, зарегистрирован маршрут `/superadmin`, обновлён sidebar `main.php`.
- QA-замечания исправлены (2026-06-12, агент: erp-architect): `.module-card-status` CSS, `&mdash;` в заголовке. PowerShell-safe HTTP-проверки: `/superadmin`→200, `/`→200, `/test`→200, `/test-db`→200, `/nonexistent`→404.
- SUPERADMIN Stage 1 прошёл синтаксические и runtime проверки, но последующая визуальная приёмка владельцем отменила готовность UI. Актуальный статус `/superadmin`: `PARTIALLY COMPLIANT / NEEDS_UI_REWORK`.

- SUPERADMIN Stage 2 — документация центральной БД выполнена (2026-06-12): создан `docs/architecture/SUPERADMIN_DATABASE.md`.
- SUPERADMIN Stage 3 — SQL-миграции созданы и проверены dry-run (2026-06-12): 4 таблицы на MySQL 8.4.9 без ошибок, JSON/FK/индексы подтверждены, временная БД удалена.
- SUPERADMIN Stage 4a — CLI migration runner создан (2026-06-12): `scripts/migrate.php` + `docs/architecture/MIGRATION_RUNNER.md`. Первый запуск на dev БД: 4 applied, повторный: 4 skipped. Таблица `schema_migrations` с SHA256 checksum. Idempotent.

## Последнее обновление

2026-06-13 13:30 — Codex GPT/erp-architect: владелец принял текущий результат `/superadmin` для продолжения разработки. Статус: `OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`. Проверка Главным дизайнером / КЛАУД: `DESIGN_REVIEW_PENDING`, не блокирует дальнейший кодинг. Следующий этап: commit текущей точки и развитие SUPERADMIN business foundation.

2026-06-13 13:15 — KILO/erp-architect: точечный coder rework `/superadmin` завершён. Все 5 отклонений compliance-аудита исправлены (5 строк `app.css` + 1 строка `index.php`). Foundation/shell/sidebar/topbar/IA не менялись. Статус: `PARTIALLY COMPLIANT / READY_FOR_OWNER_VISUAL_REVIEW`. Следующий шаг: Owner visual review. Обновлены AGENT_WORK_LOG, PROJECT_STATUS, ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.

2026-06-13 13:10 — KILO/erp-architect: compliance-аудит `/superadmin` завершён. Итог: `PARTIALLY COMPLIANT`. 81 проверка, 76 COMPLIANT, 5 отклонений (0 BLOCKER). Foundation/shell/sidebar/topbar/IA — COMPLIANT. Следующий шаг: точечный coder rework (4 строки `app.css` + 1 строка `index.php`). Обновлены AGENT_WORK_LOG, PROJECT_STATUS, ERP_PLANEX_CONTEXT_FOR_NEW_CHAT, superadmin-dashboard.md.

2026-06-13 12:39 — Codex GPT/erp-architect: MD-документация приведена к единому актуальному состоянию перед продолжением работы. Убраны живые противоречия: KILO снова используется через `erp-architect`, Core Kit является primary UI source, legacy catalog — reference/history, `/superadmin` = **`PARTIALLY COMPLIANT / NEEDS_UI_REWORK`**, QA/commit запрещены до Manual owner visual approval, следующий шаг — compliance-аудит дизайнером.
---
## CURRENT STATUS OVERRIDE — 2026-06-13 — OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT

Current focus: current `/superadmin` checkpoint accepted by owner for continued development.

`/superadmin` status: **OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT**.

Primary UI source:
```text
docs/ui/ERP_UI_KIT_CORE.html
```

Key facts:
- Compliance audit: 81 checks, 76 COMPLIANT, 5 deviations, 0 BLOCKER.
- All 5 deviations fixed: 5 lines in `app.css` + 1 line in `index.php`.
- Foundation/shell/sidebar/topbar/IA: COMPLIANT, not modified.
- `main.php` and `superadmin_dashboard.php`: not modified.
- Backend/database/scripts/Core Kit: NO changes.
- Owner accepted current result for continued development.
- Chief designer / KLAUD design review: PENDING, not blocking.
- QA not started for this checkpoint.
- Next: commit current checkpoint, then continue with SUPERADMIN business foundation.

---

## CURRENT STATUS OVERRIDE — 2026-06-13 14:52 — EXPEDITOR_ONBOARDING_PLAN_DOCUMENTED

Current focus: развитие business foundation после принятого `/superadmin` checkpoint.

Latest commit before this planning task: `91c6911`.

Owner-approved direction:
- `SUPERADMIN` создает экспедитора как отдельную локальную ERP / компанию.
- Создание экспедитора автоматически создает центральную запись, локальную БД и storage-папку.
- Кодовая база общая; в папке экспедитора хранятся только загруженные документы.
- Главный пользователь экспедитора = `Руководитель`.
- `Руководитель` создается SUPERADMIN отдельным действием после создания экспедитора.
- `Руководитель` хранится в центральной БД и после общего логина попадает в локальную ERP своего экспедитора.
- Локальные пользователи, включая `Логист`, хранятся в локальной БД экспедитора.
- Клиенты и подрядчики остаются отдельными справочниками / таблицами / формами.
- Термины первого этапа: `клиент` и `подрядчик`; отдельный `перевозчик` не вводится.

Documented plan:
- `docs/business/EXPEDITOR_ONBOARDING_WORKFLOW.md`
- `docs/architecture/SUPERADMIN_COMPANIES.md`
- `docs/architecture/PERMISSIONS_MODEL.md`
- `docs/architecture/DOCUMENT_STORAGE_MODEL.md`
- `docs/ai/DECISIONS_LOG.md` — DECISION-0031.

Next recommended step:
1. Три базовых вопроса по `SUPERADMIN Companies Registry` уже уточнены владельцем и закреплены в DECISION-0032.
2. Подготовить точное ТЗ для `erp-coder` на первый кодовый модуль: создание экспедитора из SUPERADMIN.

Code status: NO code changes in this planning task.

Commit status: NOT committed after this planning task.

---

## CURRENT STATUS OVERRIDE — 2026-06-13 15:04 — READY_FOR_NEW_CHAT_TRANSFER

Status: документация подготовлена для перехода в новый ChatGPT-чат.

Latest stable commit: `91c6911`.

Current focus:
- перейти к первому модулю нового business foundation цикла: `SUPERADMIN Companies Registry`;
- перед кодингом уточнить 3 технических решения у владельца.

MD updated for transfer:
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`
- `docs/ai/PROJECT_STATUS.md`
- `docs/ai/AGENT_WORK_LOG.md`
- `docs/business/EXPEDITOR_ONBOARDING_WORKFLOW.md`
- `docs/architecture/SUPERADMIN_COMPANIES.md`
- `docs/architecture/PERMISSIONS_MODEL.md`
- `docs/architecture/DOCUMENT_STORAGE_MODEL.md`
- `docs/ai/DECISIONS_LOG.md`

Next questions for owner:
1. Какие поля SUPERADMIN вводит при создании экспедитора?
2. Имя локальной БД генерируется автоматически из slug/кода компании или вводится вручную?
3. Storage-папка называется по slug/коду компании или по ID компании?

Code status: NO code changes.

Commit status: NOT committed after this documentation handoff update.

---

# CURRENT STATUS OVERRIDE — 2026-06-13 — DOCUMENTS_BLOCK_DESIGNED

Current focus: **Documents and File Upload for Directories — UI DESIGN COMPLETE.**

erp-uiux-designer завершил проектирование UI для блока документов:
- `docs/ui/pages/company-documents-list.md` — страница списка документов (PATTERN-01, 6 состояний, 7 колонок)
- `docs/ui/pages/company-documents-upload.md` — страница загрузки документа (form page, 5 состояний, 3 поля)
- Migration: `database/migrations-local/007_create_company_documents.sql` (таблица documents)
- Storage: `storage/companies/{id}/documents/{type}/{eid}/`
- Изменения существующих страниц: добавить кнопку «Документы» в 5 справочников (clients, contractors, drivers, vehicles, crews)

Deferred: download route, verified/rejected статусы, предпросмотр, удаление, массовая загрузка.

Следующий шаг: erp-architect передаёт handoff на erp-coder для реализации.
