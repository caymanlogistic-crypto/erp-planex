# ERP PLANEX — правила агентов

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

## 1. Общая модель

Постоянная цепочка:

```text
Владелец + ChatGPT → KILO erp-architect → KILO erp-coder
```

Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев. Основной поток: erp-architect ставит задачу erp-coder и принимает результат.

## 2. Роль ChatGPT владельца

ChatGPT в чате владельца:

- проектирует систему вместе с владельцем;
- принимает архитектурные и UX-решения;
- пишет задачи/промты агентам только по прямой просьбе владельца;
- контролирует документацию и не раздувает MD;
- не создаёт лишние MD без необходимости.

Правило:

```text
ChatGPT не пишет промты агентам без прямого явного согласия владельца.
По умолчанию ChatGPT отвечает анализом, вердиктом, следующим шагом или краткой рекомендацией.
```

## 3. Роль erp-architect

Архитектор:

- читает `docs/ai/HANDOFF_FOR_NEW_CHAT.md` первым;
- переводит решение владельца + ChatGPT в техническую задачу;
- не расширяет scope;
- не фантазирует бизнес-логику;
- определяет файлы для изменения;
- ставит задачу erp-coder;
- требует от кодера функционал + runtime + проверки + соблюдение design-system;
- после кодера проверяет соответствие задаче;
- не принимает `DONE`, если фактические проверки не выполнены.

Архитектор не должен превращаться в документационного бюрократа.

## 4. Роль erp-coder

Кодер работает циклом:

```text
Понять задачу → продумать пользовательский сценарий → реализовать точечно → проверить runtime → применить erp-ui.css/app.css → повторно проверить → коротко отчитаться
```

Кодер обязан:

- реализовать только поставленную задачу;
- работать точечно;
- не переписывать модуль целиком без прямого указания;
- не менять утверждённый UI без прямого указания;
- использовать `public/assets/css/erp-ui.css` как базу;
- page-specific CSS держать в `public/assets/css/app.css`, а не в `erp-ui.css`;
- соблюдать `docs/ui/DESIGN_STANDARD.md`;
- запускать доступные проверки;
- делать интерфейсы понятными: нормальные действия, ошибки, пустые состояния, подтверждения опасных действий.

## 5. Главный дизайнер / CODEX-дизайнер

Главный дизайнер и CODEX-дизайнер — внешние чаты/инструменты, не постоянные KILO-агенты.

CODEX-дизайнер обязан сам проверять правки:

- `php -l` по изменённым PHP/view-файлам;
- `git diff --check`;
- отсутствие inline-style кроме `display:none`;
- отсутствие случайных цветов/стилей;
- сохранность `input name` / `form action` / `method` / `routes`;
- подключение CSS;
- визуальную целостность страниц.

Если найден функциональный баг — фиксирует его в отчёте, а исправление идёт через архитектора и кодера.

## 6. QA

Постоянный QA-агент не используется.

Минимальный QA встроен:

- кодер проверяет runtime;
- архитектор проверяет соответствие задаче;
- владелец + ChatGPT принимают результат;
- отдельная большая проверка допускается только по специальной команде.

### Runtime acceptance rule

Нельзя принимать `DONE`, если задача требовала runtime, а агент проверил только:

```text
302 → login
```

Для форм, CRUD и документов нужно реально проверить:

- создание/редактирование сущности;
- ошибки валидации;
- запись в БД;
- документы и storage, если есть upload;
- list/view/edit после миграций;
- отсутствие регрессии соседних страниц;
- отсутствие JS errors в консоли, если задача затрагивает frontend.

## 7. Документация

Разрешённый минимум:

```text
docs/ai/PROJECT_STATE.md
docs/ai/AGENT_RULES.md
docs/ai/CURRENT_TASK.md
docs/ai/DECISIONS.md
docs/ai/HANDOFF_FOR_NEW_CHAT.md
docs/ui/DESIGN_STANDARD.md
```

Новые управляющие MD создаются только по решению владельца + ChatGPT.

После важных принятых commits обновлять только нужные короткие MD:

- `PROJECT_STATE.md`;
- `CURRENT_TASK.md`;
- `DECISIONS.md`, если появилось новое решение;
- `HANDOFF_FOR_NEW_CHAT.md`, если новый чат должен знать изменение.

## 8. CRITICAL UI LOCK RULE

Архитектору, кодеру и любому агенту ERP PLANEX запрещено менять без прямого подтверждения владельца:

### 8.1 Основная шапка ERP

HTML, CSS, размеры, отступы, структуру, классы, поведение, внешний вид.

### 8.2 Шапка контентного блока

Page-head / page-header, заголовочная зона страницы, высота, отступы, кнопочная структура, классы и визуальное поведение.

### 8.3 Основное меню

Структура, внешний вид, классы, поведение, отступы, активные состояния, раскрытие/сворачивание.

### 8.4 Shell layout

Глобальная сетка приложения, sidebar/topbar/content shell.

### 8.5 Разрешённые действия

Разрешено только добавлять новые кнопки/пункты/действия без изменения существующей структуры и поведения.

## 9. PROTECTED WORKING CORE

Следующие страницы объявлены защищённым рабочим ядром (DO_NOT_TOUCH_WORKING_CORE):

```text
/company/drivers
/company/vehicle-sets
/company/clients
/company/contractors
```

Архитектору, кодеру и любому агенту ERP PLANEX запрещено изменять без отдельной явной задачи от владельца:

- routes этих страниц;
- views (app/View/pages/company_drivers*.php, company_vehicle_sets*.php, company_clients*.php, company_contractors*.php);
- partials (app/View/partials/company_driver_*.php, company_vehicle_set_*.php);
- JS-поведение (initDriverForm, модальные окна, file-pickers, upload validation в app.js);
- CSS-геометрию (.driver-create-modal, .entity-form-layout, .driver-layout, размеры и отступы в app.css/erp-ui.css);
- формы (input name, form action, method, поля, валидацию, submit-логику);
- бизнес-логику (создание, редактирование, удаление, архивацию);
- документы (upload, replace, soft-delete, predef/custom docs);
- сервисы (ContractorContactService, ClientContactService, CompanyInnLookupService, driver_create_handler);
- таблицы и миграции (локальные миграции 002–004, 007–009, 011–035);
- компоненты (app/View/components/*.php — используются внутри защищённых страниц);
- layout (app/View/layouts/main.php).

Полный список защищённых файлов: `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md`.

Если какой-то файл относится к этим страницам и кажется устаревшим или дублирующимся — не предлагать удаление. Помечать как DO_NOT_TOUCH_WORKING_CORE или NEEDS_RUNTIME_CHECK.

## 10. Формы ERP: правило create/edit

Для карточек и справочников, где есть create-form и edit-form:

```text
edit-form должна использовать тот же form partial/pattern, что и create-form.
Редактирование не должно быть отдельной “похожей” формой.
Разница только: предзаполненные данные, action update, footer save, состояния существующих документов.
```

Для AJAX/modals:

```text
- form JS должен инициализироваться через scoped initFunction(form);
- запрещено полагаться на глобальные document.getElementById для элементов формы;
- create и edit могут одновременно быть в DOM, id должны быть уникальны или JS должен использовать data-* внутри form;
- запрещено cloneNode(form) для формы с интерактивом;
- browser alert запрещён, ошибки показывать внутри формы.
```

## 11. Документы в формах

Предопределённые документы используют единый pattern:

```text
.file-item
.document-file-row
.file-type-badge
.file-info
.file-name
.file-meta
.file-action-btn
.predef-file-clear
```

В edit-mode для существующих файлов:

```text
кнопка = Заменить
× = рабочая очистка/soft delete через hidden input или не показывается, если backend не поддерживает удаление
Открыть не используется внутри edit-form
```

Hard delete файлов запрещён без отдельного решения.

## 12. Windows / Git / UTF-8

PowerShell часто ломает кириллицу и Git output. По умолчанию использовать `cmd.exe /c`:

```bat
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git status"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l public\index.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git diff --check"
```

Файлы с кириллицей сохранять как UTF-8 без BOM.

Проверять:

- кириллица читается;
- нет mojibake / кракозябр;
- нет NULL bytes;
- файл не обрезан;
- PHP-файлы проходят `php -l`.

Если вывод PowerShell/Git с кириллицей повреждён, повторить через `cmd.exe /c` и не считать это ошибкой проекта.

## 13. Правило доступа и прав

При задачах, связанных с доступом, правами и видимостью сущностей:

### 13.1 Модель ролей

- `company_owner` — видит всё, управляет пользователями и привязкой перевозчиков.
- `senior_logist` — видит все данные компании, НЕ управляет пользователями, НЕ управляет привязкой.
- `logist` — видит только свои записи (`created_by_user_id`) + записи с grants.

### 13.2 Привязка перевозчиков

- Основной сценарий управления между логистами: `/company/contractor-assignments`.
- Использовать только этот механизм. Не создавать новые UI «расшаривания».
- Не возвращать блок «Доступ логистов» в карточку перевозчика.

### 13.3 Ограничения для обычного logist

- При создании `driver_vehicle_block` / `crew`: фильтровать выпадающие списки по `created_by_user_id` + grants.
- Backend-валидация обязательна — не полагаться только на frontend dropdown.
- Чужие ID (`driver_id`, `vehicle_set_id`, `contractor_id`, `driver_vehicle_block_id`) через POST должны отклоняться.

### 13.4 Grants

- Таблица `entity_access_grants` сохранена как технический механизм.
- Cascade sharing через UI удалён.
- Grants могут использоваться другими модулями, но НЕ как основной пользовательский сценарий управления доступом между логистами.

## 15. Обязательное правило runtime для forms/modals/documents

Если задача касается хотя бы одного из пунктов ниже, агент обязан сделать реальный browser/runtime до отчёта о приёмке:

- create/edit/archive формы;
- modal CRUD;
- document upload / replace / delete / reopen;
- lookup/helper-поведение на frontend;
- JS-инициализация частичных форм;
- изменения в protected core страницах списка и карточек.

Минимум для приёмки:

- реальный сценарий в браузере, а не только HTTP-статус;
- `Console = 0 JS errors`;
- `Network = 0` запросов `4xx/5xx/500`, кроме явно ожидаемых тестовых исключений, зафиксированных в задаче;
- повторное открытие сущности после сохранения там, где это важно для state/documents;
- `php -l` по изменённым PHP и `git diff --check`.

## 16. Modal/entity pattern 2026-06-28

Для client/contractor принят следующий безопасный pattern:

- `ModalShell` отвечает за открытие/закрытие и загрузку modal routes;
- `LegalEntityCreateModal` используется для create-сценария;
- modal view/edit/archive работают рядом с существующими full-page routes и не заменяют их;
- общий `ContactFields` и общий INN helper не дублируются отдельным кодом в каждой форме;
- document helper legal entities проверяется отдельным runtime-блоком и не считается принятым по косвенной регрессии.

## 17. Superadmin provisioning guardrail

Текущий superadmin/company create flow находится под архитектурным ограничением:

- modal-only create для superadmin/company/owner запрещён без owner decision;
- разрешены только безопасные проверки существующего full-page flow;
- повод остановить работу: риск сломать provisioning, роли/доступы, структуру БД, SUPERADMIN create-owner flow или удаление данных.


## 14. Исполнитель рейса и реальная схема БД

При любых задачах по `/company/route-executors`, `driver_vehicle_blocks`, `crews`, `vehicle_sets` агент обязан сначала сверяться с реальной схемой БД.

Критичные правила:

```text
driver_vehicle_blocks.vehicle_id НЕ существует.
```

`driver_vehicle_blocks` — техническая связка:

```text
id, driver_id, vehicle_set_id, status, comments,
created_by_user_id, created_by_role, updated_by_user_id, updated_by_role,
created_at, updated_at
```

`crews` — legacy-таблица исполнителя/экипажа, в ней остаются:

```text
contractor_id, vehicle_id, driver_id, driver_vehicle_block_id
```

При создании исполнителя рейса:

- `driver_vehicle_blocks` получает только `driver_id` и `vehicle_set_id`;
- `crews.vehicle_id` получает `vehicle_sets.primary_vehicle_unit_id`;
- `crews.driver_id` получает выбранный `driver_id`;
- `crews.driver_vehicle_block_id` получает id созданной/найденной связки;
- не угадывать поля БД; при ошибке SQL сначала выгрузить `DESCRIBE`/`SHOW CREATE TABLE`.

Пустое состояние для `logist` не должно называться «Нет доступа». Если доступ к странице есть, но данных нет, нужно показывать нормальный empty-state и понятное действие.
