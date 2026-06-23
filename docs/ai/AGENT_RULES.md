# ERP PLANEX — правила агентов

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
