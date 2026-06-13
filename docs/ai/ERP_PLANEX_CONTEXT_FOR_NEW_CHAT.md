# CURRENT CONTEXT OVERRIDE — 2026-06-13 — SUPERADMIN_COMPANIES_REGISTRY_FUNCTIONAL_ACCEPTED

**First functional module `SUPERADMIN Companies Registry` — FUNCTIONAL_ACCEPTED. QA passed (67/67).**

**ACCELERATED FUNCTIONAL DEVELOPMENT MODE active.** Manual visual approval deferred. UI polish deferred.

Latest stable commit remains: `91c6911`. Working tree has uncommitted functional changes.

Code status: module implemented, migration 005 applied, provisioning flow works (central record → local DB → storage).

Approved owner decisions for the next module:

1. Fields entered by `SUPERADMIN` when creating an expeditor/company:
   - use the same basic business fields as the current contractor/client standard for now;
   - this must be a separate SUPERADMIN expeditor/company registry table/model, not reuse the local contractor table;
   - baseline fields for the first implementation: `name`, `inn`, `kpp`, `ogrn`, `legal_address`, `physical_address`, `contact_person`, `contact_phone`, `contact_email`, `status`, `comments`;
   - `name` and `inn` are required at minimum because the current contractor/client standard marks them required;
   - do not invent extra legal/tax fields beyond this without owner approval.

2. Local DB name generation:
   - local DB name is generated automatically from company ID;
   - do not ask the user to enter the local DB name manually;
   - do not use slug/key as the source of truth for DB name generation.

3. Storage folder generation:
   - storage folder is generated/named by company ID;
   - do not use slug/key as the source of truth for storage folder generation.

Important correction to older docs:
- older mentions that `key/slug` is used in URL/path/DB names are superseded for DB and storage generation by this owner decision;
- `key/slug` may remain as a UI/URL/display machine code only if already required by existing schema, but it must not drive local DB name or storage folder name in the first Companies Registry implementation.

Next required action:
1. Keep MD updated with this decision.
2. Prepare exact `erp-coder` task for the first code module `SUPERADMIN Companies Registry`.
3. Coder must not implement extra functionality beyond Companies Registry/provisioning foundation.

---

# ERP PLANEX — переносимый контекст для нового ChatGPT-чата

## CURRENT CONTEXT OVERRIDE — 2026-06-13 15:04 — READY_FOR_NEW_CHAT_TRANSFER

Этот файл обновлен для перехода в новый ChatGPT-чат.

Latest stable commit: `91c6911`.

Current working tree after planning docs: dirty, because documentation for the next business path was updated and not committed yet.

Current focus: подготовка первого кодового модуля нового цикла — `SUPERADMIN Companies Registry`.

Already documented:
- `docs/business/EXPEDITOR_ONBOARDING_WORKFLOW.md`
- `docs/architecture/SUPERADMIN_COMPANIES.md`
- `docs/architecture/PERMISSIONS_MODEL.md`
- `docs/architecture/DOCUMENT_STORAGE_MODEL.md`
- `docs/ai/DECISIONS_LOG.md` — DECISION-0031
- `docs/ai/PROJECT_STATUS.md`
- `docs/ai/AGENT_WORK_LOG.md`

The 3 concrete technical details have now been clarified by the owner and documented in the newer override above plus DECISION-0032.

Next action after reading this file: prepare a precise `erp-coder` task for the first module `SUPERADMIN Companies Registry`; do not ask these 3 questions again unless the owner reopens the decision.

---

## CURRENT CONTEXT OVERRIDE — 2026-06-13 14:52 — EXPEDITOR_ONBOARDING_PLAN_DOCUMENTED

Latest stable commit before this planning task: `91c6911`.

Current project focus: start business development after accepted `/superadmin` checkpoint.

Owner-approved next functional path:

```text
SUPERADMIN создает экспедитора
→ SUPERADMIN отдельным действием создает главного пользователя экспедитора
→ главный пользователь / Руководитель создает локального Логиста
→ Логист ведет клиентов, подрядчиков, транспорт, водителей и связки
```

Key decisions:
- Экспедитор = отдельная локальная ERP / компания.
- Создание экспедитора из SUPERADMIN автоматом создает центральную запись, локальную БД и storage-папку.
- Кодовая база общая; в папке экспедитора хранятся только uploaded documents.
- Главный пользователь экспедитора = `Руководитель`.
- `Руководитель` создается SUPERADMIN отдельным действием после создания экспедитора.
- `Руководитель` хранится в центральной БД SUPERADMIN и привязывается к экспедитору.
- После общего логина `Руководитель` попадает в локальную ERP своего экспедитора.
- Локальные пользователи, включая `Логист`, хранятся в локальной БД экспедитора.
- Клиенты и подрядчики — отдельные справочники / таблицы / формы.
- Термины первого этапа: `клиент` и `подрядчик`; отдельный `перевозчик` не вводится.

Primary planning docs:
- `docs/business/EXPEDITOR_ONBOARDING_WORKFLOW.md`
- `docs/architecture/SUPERADMIN_COMPANIES.md`
- `docs/architecture/PERMISSIONS_MODEL.md`
- `docs/architecture/DOCUMENT_STORAGE_MODEL.md`
- `docs/ai/DECISIONS_LOG.md` — DECISION-0031.

Next step:
Перед кодингом уточнить техническую схему `SUPERADMIN Companies Registry`: поля экспедитора, генерацию имени локальной БД, генерацию storage-папки, хранение параметров подключения без секретов в git, применение локальных миграций, обработку provisioning failure.

Do not write business code until these technical details are clarified.

---

## Назначение файла

`docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` — главный переносимый контекст проекта ERP PLANEX.

Если текущий ChatGPT-чат станет длинным, начнёт тормозить или работу нужно передать новому агенту, владелец проекта открывает новый ChatGPT-чат и вставляет содержимое этого файла. Новый чат после чтения только этого файла должен понять проект, статус, архитектуру, правила, запреты и ближайший следующий шаг.

Это не отчёт и не временный prompt. Это живой паспорт проекта. Его нельзя оставлять устаревшим.

## Роль ChatGPT в проекте

ChatGPT в проекте ERP PLANEX выполняет роль архитектурного координатора и контролёра качества агентной разработки:

- помогает владельцу формулировать точные задачи для KILO;
- проверяет, что агенты не уходят в размытые задачи;
- следит за архитектурой, документацией, логами, статусами и запретами;
- принимает/разбирает FINAL REPORT агентов;
- помогает обновлять переносимый контекст и проектные MD;
- не должен сам придумывать неизвестные бизнес-правила, юридические правила, налоговую логику или детали, которых нет в утверждённых документах;
- учитывает заключения **Главного дизайнера / автора дизайн-системы ERP PLANEX** как контрольный слой целостности дизайн-системы и визуального соответствия STYLE ERP.

Главное правило проекта: **не придумывать неизвестные детали**. Если данных не хватает, нужно задать вопрос владельцу или поставить статус `NEEDS_OWNER_DECISION`.

## Рабочая папка проекта

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\
```

## Кто пишет проект

Проект пишется ИИ-агентами.

Основной кодер и исполнитель технических задач: **KILO + DeepSeek**.

Стек проекта: **PHP / MySQL**.

Кодерские задачи должны быть оптимизированы под DeepSeek: конкретные, проверяемые, без двусмысленности, с точным списком файлов, запретов, проверок и ожидаемым FINAL REPORT.

# ОБЯЗАТЕЛЬНОЕ ПРАВИЛО ОБНОВЛЕНИЯ ЭТОГО ФАЙЛА

Каждый агент после выполнения любой задачи обязан проверить, нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

Файл обязательно обновляется, если изменилось хотя бы одно из следующего:
- текущий этап проекта;
- текущий фокус;
- следующий шаг;
- архитектурное решение;
- бизнес-правило;
- структура папок;
- список ключевых файлов;
- git commit hash;
- правила агентов;
- правила логирования;
- модель БД;
- модель ролей;
- feature toggles;
- deployment/развёртывание;
- запреты или обязательные проверки;
- результат важной задачи;
- статус SUPERADMIN;
- статус локальной ERP;
- статус PHP-каркаса;
- любые решения владельца проекта.

Если агент обновил `PROJECT_STATUS.md`, `DECISIONS_LOG.md`, `AGENT_WORK_LOG.md` или профильные архитектурные/бизнес/UI MD-файлы, он обязан проверить и при необходимости обновить также `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

Запрещено оставлять этот файл устаревшим.

Если агент не уверен, нужно ли обновлять файл, он должен обновить его или явно записать в `AGENT_WORK_LOG.md`, почему обновление не требуется.

В FINAL REPORT агент обязан указать, обновлялся ли этот файл. Если не обновлялся — указать причину.

## Обязательные файлы для чтения перед работой

Каждый агент перед началом любой задачи обязан прочитать:

1. `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` — главный переносимый контекст проекта.
2. `README.md` — обзор проекта и базовые правила.
3. `AGENTS.md` — входные правила для ИИ-агентов.
4. `docs/ai/PROJECT_STATUS.md` — текущий этап, фокус и уже сделанное.
5. `docs/ai/DECISIONS_LOG.md` — утверждённые решения.
6. `docs/ai/AGENT_WORK_LOG.md` — журнал работ.
7. `docs/ai/AGENT_LOGGING_MASTER_PROMPT.md` — правила логирования.
8. `docs/ai/KILO_WORKFLOW.md` — workflow KILO.
9. `docs/ai/DEEPSEEK_CODER_RULES.md` — правила кодера DeepSeek.
10. `docs/ai/KILO_PROJECT_RULES.md` — проектные правила KILO.
11. `docs/ai/TASK_TEMPLATE.md` — шаблон постановки задач.
12. `docs/ai/QA_CHECKLIST.md` — чеклист приёмки.
13. `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md` — правила выполнения команд в Windows PowerShell (обязательно!).
13. Профильные документы из `docs/architecture/`, `docs/business/`, `docs/ui/` по теме задачи.

Если задача связана с KILO, агентами, режимами, промтами или маршрутизацией, обязательно читать также:

- `docs/ai/AGENT_NETWORK.md`.

Если задача связана с UI, дизайном, страницами, формами, таблицами или фронтендом, обязательно читать также:

- `docs/ui/DESIGN_CODE_INTEGRATION.md`;
- `docs/ui/PAGE_PATTERN.md`;
- `docs/ui/FORMS_STANDARD.md`;
- `docs/ui/TABLES_STANDARD.md`;
- актуальный MD-шаблон страницы из `docs/ui/pages/`, если он существует.

## Какие файлы агенты обязаны обновлять после работы

После любой задачи агент обязан обновить:

- `docs/ai/AGENT_WORK_LOG.md` — всегда;
- `docs/ai/PROJECT_STATUS.md` — если изменился статус, фокус, следующий шаг или важный результат;
- `docs/ai/DECISIONS_LOG.md` — если принято или уточнено архитектурное/бизнес/workflow-решение;
- профильные MD-файлы в `docs/architecture/`, `docs/business/`, `docs/ui/`, если задача изменила соответствующую область;
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` — если изменился переносимый контекст проекта.

Запрещено завершать важную задачу только изменением кода без обновления логов и статуса.

## KILO-агенты проекта

В проекте настроены 4 KILO-агента:

| Агент | Роль | Назначение |
|---|---|---|
| `erp-architect` | Главный координатор | Основной режим. Общается с владельцем, определяет маршрут задачи, ставит задачи другим агентам, принимает или отправляет на доработку. |
| `erp-uiux-designer` | UI/UX-дизайнер | До кодера проектирует страницу, компоненты и пользовательские сценарии. Создаёт/обновляет MD-шаблоны страниц в `docs/ui/pages/`. |
| `erp-coder` | Исполнитель разработки | Пишет PHP/MySQL/HTML/CSS/JS строго по задаче архитектора, архитектурным MD и UI-шаблону, если UI затронут. |
| `erp-qa-tester` | Тестировщик | Проверяет соответствие задаче, архитектуре, UI-шаблону, runtime, безопасности, документации и логам. |

Все 4 агента должны быть `mode: primary`, чтобы отображаться в списке выбора KILO. Главный агент задан в `kilo.jsonc`: `default_agent: erp-architect`.

Определения агентов находятся в:

```text
.kilo/agent/erp-architect.md
.kilo/agent/erp-uiux-designer.md
.kilo/agent/erp-coder.md
.kilo/agent/erp-qa-tester.md
```

### Внешний контрольный слой: Главный дизайнер / автор дизайн-системы ERP PLANEX

Проект имеет внешний (не KILO) контрольный role-layer: **Главный дизайнер / автор дизайн-системы ERP PLANEX**.

Эта роль не является обычным `erp-uiux-designer`. Главный дизайнер:

- контролирует целостность дизайн-системы;
- проверяет, что страницы действительно собраны по STYLE ERP / Core Kit, а не «по мотивам»;
- проводит независимый дизайн-аудит спорных UI-результатов;
- находит системные причины ошибок;
- указывает, какие правила нужно добавить в Core Kit, page templates, agent rules и QA;
- не заменяет регулярный `erp-uiux-designer` в обычной работе, но подключается при системных сбоях, расхождениях с дизайн-кодом или визуальном провале;
- его заключения имеют приоритет при дизайн-системных спорах.

## Утверждённый агентный workflow

Основная модель работы: владелец проекта общается преимущественно с `erp-architect`.

`erp-architect` решает, нужна ли маршрутизация:

```text
UI-задача:
erp-architect → erp-uiux-designer → erp-coder → erp-qa-tester → erp-architect

Техническая задача без UI:
erp-architect → erp-coder → erp-qa-tester → erp-architect

Документационная/архитектурная задача:
erp-architect выполняет сам или подключает нужного агента, затем обновляет MD/логи.
```

Если задача затрагивает UI, кодер не должен начинать без актуального MD-шаблона страницы:

```text
docs/ui/pages/[page-name].md
```

`erp-uiux-designer` обязан подготовить страницу так, чтобы кодер не придумывал внешний вид: layout, блоки, формы, таблицы, состояния, действия, тексты ошибок, empty/loading/error states.

`erp-qa-tester` не может поставить `ACCEPTED`, если:

- UI-задача сделана без актуального MD-шаблона страницы;
- кодер придумал UI сам;
- изменённые PHP-файлы не проверены через `php -l`;
- не обновлены `AGENT_WORK_LOG.md` и нужные MD;
- не проверена необходимость обновления этого файла;
- в код или MD попали `.env`, пароли, токены или секреты.

Если возникает UI foundation/design-system спор или визуальное расхождение со STYLE ERP — подключается **Главный дизайнер** как независимый контрольный слой.

## Текущий статус проекта

Статус на момент этого контекста:

- документационный и агентный фундамент создан;
- минимальный PHP-каркас создан;
- базовый UI-фундамент создан, проверен и утверждён (2026-06-11);
- PDO-обёртка Database создана и проверена (2026-06-11);
- простой GET-роутер создан и проверен (2026-06-11);
- KILO-агенты настроены;
- правила логирования, QA, KILO и переносимого контекста закреплены;
- бизнес-код ещё не пишется;
- SUPERADMIN Stage 1 прошёл синтаксические/runtime проверки (2026-06-12), затем несколько UI rework cycles. Compliance-аудит (2026-06-13): 81 проверка, 76 COMPLIANT, 5 отклонений (0 BLOCKER). Точечный coder rework (2026-06-13): все 5 отклонений исправлены. Владелец принял текущий результат для продолжения разработки. Актуальный статус `/superadmin`: `OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`. Проверка Главным дизайнером / КЛАУД: pending, not blocking.
- SUPERADMIN Stage 2 — документация центральной БД выполнена (2026-06-12): создан `docs/architecture/SUPERADMIN_DATABASE.md` с точной спецификацией 4 таблиц, индексов, FK, статусных моделей, reserved-полей. Принято решение DECISION-0021.
- SUPERADMIN Stage 3 — SQL-миграции созданы и проверены dry-run на MySQL 8.4.9 (2026-06-12): 4 таблицы созданы корректно, JSON/FK/индексы подтверждены.
- SUPERADMIN Stage 4a — CLI migration runner создан (2026-06-12): `scripts/migrate.php` применяет миграции, отслеживает через `schema_migrations` с SHA256 checksum, идемпотентен. Протестирован на dev БД: первый запуск 4 applied, повторный 4 skipped.
- Целевая версия MySQL: 5.7+ (DECISION-0022, 2026-06-12). Тип JSON используется для `companies.settings_json`.
- авторизация, роли в коде ещё не реализованы;
- Windows PowerShell command rules зафиксированы (DECISION-0020, commit f36ba81).

## Текущий фокус

Текущий фокус: **`FUNCTIONAL_ACCEPTED`** — первый бизнес-модуль `SUPERADMIN Companies Registry` реализован и прошёл QA.

Следующий модуль: **Главный пользователь экспедитора (Руководитель)** — отдельное действие SUPERADMIN после создания экспедитора.

**ACCELERATED FUNCTIONAL DEVELOPMENT MODE** — приоритет функционала над визуальной полировкой.

### Targeted coder rework result (2026-06-13)

Все 5 отклонений compliance-аудита исправлены:

| # | Элемент | Severity | Файл | Статус |
|---|---------|----------|------|--------|
| 1 | Page-head subtitle color: `var(--text-muted)` | MAJOR | `app.css` | FIXED |
| 2 | Body line-height: `1.35` | MINOR | `app.css` | FIXED |
| 3 | `$pageContext` задан явно | MINOR | `index.php` | FIXED |
| 4 | Body bg token: `var(--app-bg)` | MINOR | `app.css` | FIXED |
| 5 | `.text-muted` унифицирован, override удалён | MINOR | `app.css` | FIXED |

Foundation/shell/sidebar/topbar/IA — COMPLIANT, не менялись. Следующий шаг — **Owner visual review** в браузере. QA и commit не выполнять до owner approval.

VISUAL CHECK URL: `http://127.0.0.1:[port]/superadmin`

### История предыдущего UI-провала (зафиксирована, актуальна)

Причина первого UI-провала:
- erp-uiux-designer выдал слабый handoff с псевдоиконками `[=]`, `[#]`, `[~]`, `[v]` и карточным SaaS-dashboard подходом;
- erp-coder реализовал handoff буквально, усилив demo-вид;
- erp-qa-tester принял формально ("ACCEPTED") без visual review;
- erp-architect принял как DONE без визуальной проверки владельца.

Корень проблемы: прежние правила запрещали demo-placeholder UI и требовали visual review, но ещё допускали слабый handoff, слишком раннюю передачу кодеру, Formal UI QA PASS как «почти финал» и показ владельцу результата до architect pre-owner review.

### Обновлённый UI Production Loop для важных страниц (2026-06-13)

```text
Architect intake
→ Designer foundation handoff
→ Layout Foundation Gate
→ Sidebar Information Architecture
→ Component Source Mapping
→ Architect handoff review
→ Coder implementation
→ Architect implementation review
→ Layout Foundation QA
→ Component-source QA
→ Owner visual review
→ commit only after owner approval
```

### Системный урок

Агенты начали проверку с уровня компонентов (panel / badge / kv / hover), но не проверили базовые слои:

```text
app shell → sidebar/menu → topbar → page header → work area → components
```

Component-source audit alone is not enough. Foundation-first review order обязателен.

Кодер обязан вернуть `BLOCKED: NEEDS_DESIGNER_REWORK`, если handoff слабый, противоречивый, содержит `REJECTED` как актуальный статус или не указывает точные sections/classes/tokens.

`Formal UI QA: PASS` не является visual acceptance.

Backend/auth/CRUD/feature toggles/business modules остаются заблокированы до visual acceptance `/superadmin`.

STYLE ERP:

- Папка: `C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\`
- Назначение: визуальные образцы TransportERP / ERP PLANEX.
- Не является runtime-библиотекой, библиотекой компонентов или источником кода для копирования.
- Значимые правила формализованы в `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`.
- Визуальный каталог формализованных модулей создан в `docs/ui/ERP_UI_MODULE_CATALOG.html`.
- Кодер не должен открывать STYLE ERP или выбирать оттуда блоки. Если handoff требует этого, статус `BLOCKED: NEEDS_DESIGNER_REWORK`.

UI Module Catalog:

- `docs/ui/ERP_UI_KIT_CORE.html` — **PRIMARY compact working UI-kit** для всей агентной цепочки (45 CORE-модулей, 10 COMPOSITE patterns, Button/Layout Decision Matrix, Designer/Coder/QA Rules, SUPERADMIN READY SET).
- `docs/ui/ERP_UI_MODULE_CATALOG.html` — **legacy extraction/reference history only**. Содержит 224 модуля A-L и source map по STYLE ERP. Не является основным рабочим каталогом дизайнера. Не используется кодером как основание для самостоятельных UI-решений. Не удалять и не переписывать в обычной UI-работе.
- Page handoff обязан содержать `CORE modules used`, selected `COMPOSITE pattern`, и `MODULE USAGE DECISIONS`.
- Если нужного CORE-модуля нет, дизайнер возвращает `BLOCKED: NEEDS_UI_MODULE_EXPANSION`; архитектор запускает отдельную задачу на расширение Core Kit.
- Если handoff использует неизвестный UI-модуль, кодер возвращает `BLOCKED: UNKNOWN_UI_MODULE`.
- QA проверяет модульную формализацию по Core Kit; unknown module = `Formal UI QA: FAIL`.

### Layout Foundation Gate

Перед component-source audit дизайнер, архитектор и QA обязаны сначала проверить foundation:

- app shell;
- sidebar;
- sidebar menu hierarchy;
- section labels/groups/counters;
- active/disabled/future states;
- bottom settings block;
- topbar;
- topbar user block / right area;
- page context/header;
- work area spacing/density;
- соответствие STYLE ERP foundation reference.

Если хотя бы один foundation пункт = NO, страница не может получить `ACCEPTED_FOR_QA`, даже если panels/buttons/badges технически правильные.

### Sidebar Information Architecture

Для UI-страниц дизайнер обязан описывать не только блоки внутри страницы, но и навигационную структуру:

- группы меню;
- порядок пунктов;
- где находится SUPERADMIN;
- что относится к операциям;
- что относится к системе;
- какие пункты disabled/future;
- где counters;
- где bottom settings;
- как выглядит active item;
- какие пункты нельзя смешивать в одной группе.

Ближайший порядок:

1. ~~Проверить/утвердить UI-фундамент и дизайн-код.~~ **DONE (2026-06-11).**
2. ~~Перейти к техническому ядру: PDO-обёртка и роутер.~~ **DONE (2026-06-11).**
3. ~~Начать SUPERADMIN.~~ **DONE (2026-06-12) — Stage 1.**
4. Любые бизнес-страницы делать только через workflow `erp-uiux-designer → erp-coder → erp-qa-tester` с MD-шаблоном страницы, `CORE modules used`, selected `COMPOSITE pattern` и `MODULE USAGE DECISIONS` из `docs/ui/ERP_UI_KIT_CORE.html`.

Главный запрет: **не давать агенту размытые задачи типа “делай ERP”**.

## Последние commits

Актуальные последние commits на момент начала этой задачи:

```text
50f96a9 Fix ERP PLANEX UI process rules and SUPERADMIN handoff
be55198 Create SUPERADMIN Stage 4a migration runner
b3c60c4 Update portable context after SUPERADMIN Stage 4a runner commit
9513b78 Update portable context after SUPERADMIN Stage 3 migrations commit
3d7cae3 Create SUPERADMIN Stage 3 database migrations
c50b942 Document SUPERADMIN central database schema
acd5009 Create SUPERADMIN stage 1 skeleton
ab38f9d Update portable context after PowerShell rules commit
f36ba81 Add Windows PowerShell command rules for agents
766be66 Update portable context after PDO router commit
1a139cb Create PDO database layer and GET router
f336244 Approve ERP PLANEX UI foundation
bc1ada4 Update portable context commit hash after cleanup
b856edc Update portable context after documentation cleanup commit
bc8e2e9 Clean and synchronize ERP PLANEX agent documentation
a394b2f Update MD after selectable agents fix
9da9ad3 Record selectable KILO agents commit
21b355d Make all ERP KILO agents selectable
e5fba18 Update portable context with latest commit hash
ad9ae8c Configure ERP PLANEX KILO agent modes
a9ff2b7 Update portable context latest commit
2a635a7 Record agent network and UI foundation commit
473f748 Define agent network and integrate UI foundation
b350683 Record portable context commit reference
309469f Add portable ERP PLANEX context file and update agent rules
6443743 Update logs and status after PHP skeleton creation
dc75ab4 Create minimal PHP application skeleton
```

После каждого нового commit агент обязан проверить, нужно ли обновить этот раздел. Если commit относится к текущей задаче и невозможно заранее вписать его hash в тот же commit без бесконечного self-reference, hash должен быть указан в FINAL REPORT, а следующий агент должен обновить список при следующей синхронизации контекста.


## Текущая задача

Активная задача: **SUPERADMIN Companies Registry — FUNCTIONAL_ACCEPTED (2026-06-13).**

Следующий рабочий шаг:
1. ~~Foundation rework cycle.~~ **DONE.**
2. ~~SUPERADMIN Companies Registry — designer handoff.~~ **DONE (architect-created, accelerated mode).**
3. ~~SUPERADMIN Companies Registry — coder implementation.~~ **DONE (2026-06-13).**
4. ~~SUPERADMIN Companies Registry — QA.~~ **DONE: FUNCTIONAL_ACCEPTED (67/67).**
5. Следующий модуль: Главный пользователь экспедитора (Руководитель).

Исторический список завершённых шагов:

1. ~~Проверить/утвердить UI-фундамент.~~ **DONE.**
2. ~~Создать PDO-обёртку и роутер.~~ **DONE.**
3. ~~SUPERADMIN Stage 1: архитектура, UI-шаблон, реализация.~~ **REJECTED BY OWNER (визуально).**
4. ~~QA-проверка и исправление замечаний.~~ **DONE (формально, но визуально не принято).**
5. ~~SUPERADMIN Stage 2: документация центральной БД.~~ **DONE.**
6. ~~SUPERADMIN Stage 3: создание миграций.~~ **DONE.**
7. ~~SUPERADMIN Stage 4a: migration runner.~~ **DONE.**
8. ~~Исправить системные правила UI-процесса.~~ **DONE.**
9. ~~Переделать /superadmin (первый/второй круг реворка).~~ **DONE, но компонентный реворк недостаточен — foundation пропущен.**
10. ~~STAGE B: усилить KILO UI production workflow.~~ **DONE.**
11. ~~Формализовать STYLE ERP в MD.~~ **DONE.**
12. ~~Создать UI Module Catalog.~~ **DONE (legacy/reference).**
13. ~~Создать UI Kit Core (primary compact working UI-kit).~~ **DONE.**
14. ~~Предтестовый аудит дизайн-системы.~~ **DONE (2026-06-12).**
15. ~~Production handoff `/superadmin` по Core Kit.~~ **DONE, но handoff не покрыл foundation (shell/sidebar/topbar/menu).**
16. ~~Coder реализовал `/superadmin`, architect review — PASS.~~ **DONE, но foundation не соответствует STYLE ERP.**
17. ~~ACCEPTED_FOR_QA.~~ **ОТМЕНЁН (foundation audit 2026-06-13).**
18. **Foundation rework cycle — ТЕКУЩИЙ ШАГ.**

## Утверждённая архитектура ERP PLANEX

ERP PLANEX строится как многоэкземплярная система:

- общий код;
- отдельная папка для каждой локальной ERP/компании;
- отдельная база данных для каждой компании;
- отдельная центральная панель SUPERADMIN;
- SUPERADMIN видит и управляет структурами/настройками всех систем;
- обычные пользователи работают только внутри своей локальной ERP.

Код должен быть модульным. Новый модуль должен иметь понятную структуру: routes/controllers/services/repositories/views/permissions/features/logging/docs. SQL не должен писаться прямо во view.

Первый этап реализует только линейные перевозки. Сборные рейсы зарезервированы архитектурно, но функционально не реализуются на первом этапе.

## Модель SUPERADMIN

SUPERADMIN — отдельная центральная панель с максимальными правами.

Путь центральной панели:

```text
erp/superadmin/
```

SUPERADMIN должен в будущем управлять:

- созданием и управлением локальных ERP-систем;
- компаниями/юридическими лицами;
- папками и базами данных локальных ERP;
- пользователями SUPERADMIN;
- доступностью функций, модулей, страниц, отчётов и custom-отчётов по компаниям;
- общими настройками системы;
- feature toggles.

SUPERADMIN не является обычным пользователем локальной ERP.

### Правило SUPERADMIN UI

SUPERADMIN — центральная административная панель ERP PLANEX. Она должна использовать строгий **admin/settings pattern**, а не decorative dashboard.

Для SUPERADMIN по умолчанию:
- sidebar 224px с текстовой навигацией;
- topbar 38px;
- page-head;
- settings/admin sections;
- reserved modules as system sections;
- tables/forms/panels когда появляются данные;
- **запрещены** KPI dashboard cards (если явно не approved владельцем);
- **запрещены** псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` и emoji как иконки;
- **запрещены** debug badges как основной визуальный элемент;
- **запрещён** SaaS-dashboard/card-grid подход.

### Статус SUPERADMIN

SUPERADMIN Stage 1: **REJECTED BY OWNER** (2026-06-12) → **DESIGNER_HANDOFF_ACCEPTED_FOR_CODER** (2026-06-12) → ~~CODER_REWORK_ACCEPTED_FOR_QA (2026-06-13)~~ → **ОТМЕНЁН** → ~~NEEDS_LAYOUT_FOUNDATION_REWORK + NEEDS_MENU_ARCHITECTURE_REWORK~~ → `FOUNDATION_REWORK_ACCEPTED_FOR_VISUAL_REVIEW` (только разрешение на ручной visual review) → **PARTIALLY COMPLIANT / NEEDS_UI_REWORK** после просмотра владельцем → **compliance-аудит (2026-06-13): `PARTIALLY COMPLIANT`, 81 проверка, 76 COMPLIANT, 5 отклонений, 0 BLOCKER** → **точечный coder rework (2026-06-13): все 5 отклонений исправлены** → **OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT**.

Текущий статус: **`OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`**. Все 5 отклонений исправлены. Foundation/shell/sidebar/topbar/IA — COMPLIANT, не менялись. Владелец принял текущий результат для продолжения разработки. Проверка Главным дизайнером / КЛАУД остаётся pending, но не блокирует дальнейший кодинг.

## Модель локальной ERP

Одна локальная ERP = одна компания/одно юридическое лицо.

Локальная ERP работает только со своей базой данных и своим `/storage`. Пользователи локальной ERP не должны видеть данные других компаний.

Локальная ERP должна поддерживать роль `Руководитель` как администратора внутри своей компании.

## Роль “Руководитель”

`Руководитель` — локальный администратор компании.

Права:

- видит все данные внутри своей компании;
- видит бухгалтерские данные своей компании;
- управляет пользователями/настройками в рамках своей компании, если это разрешено архитектурой;
- не имеет доступа к данным других компаний;
- не имеет SUPERADMIN-доступа.

## Модель клиентов

Клиент — заказчик перевозки/услуги.

Клиент может быть:

- юридическим лицом;
- индивидуальным предпринимателем.

Архитектура должна поддерживать разные системы налогообложения и международную перевозку с НДС 0%.

На первом этапе у клиента фиксируются базовые карточки, реквизиты, документы и заявки. Расширенные проверки документов могут быть зарезервированы, но не реализуются глубоко.

## Модель подрядчиков

Термины “подрядчик”, “перевозчик”, “экспедитор” объединены в один термин:

```text
ПОДРЯДЧИК
```

Подрядчик может быть:

- юридическим лицом;
- индивидуальным предпринимателем.

Подрядчик может иметь несколько водителей, машин и экипажей.

## Модель экипажа

Формула:

```text
ЭКИПАЖ = Подрядчик + (Машина + Водитель)
```

Водитель и машина могут существовать отдельно в справочниках подрядчика до создания экипажа.

Экипаж фиксирует жёсткую пару водитель + машина внутри подрядчика.

Если тот же водитель едет на другой машине, создаётся отдельный экипаж:

```text
ООО Ромашка
  Экипаж 1 = Иванов + А999АА99
  Экипаж 2 = Иванов + И999ПП99
```

## Модель документов

Документы хранятся физически в файловой системе:

```text
/storage
```

В БД хранятся только:

- путь к файлу;
- оригинальное имя;
- MIME/type;
- размер;
- тип документа;
- сущность, к которой документ привязан;
- статус;
- метаданные;
- кто загрузил/проверил/отклонил;
- даты загрузки/проверки/отклонения.

Статусы документов на будущее:

```text
uploaded / verified / rejected
```

На первом этапе статусы можно зарезервировать, но не реализовывать глубоко.

Запрещено хранить сами файлы в БД.

## Версионность шаблонов и реквизитов

Нужна версионность:

- шаблонов документов;
- реквизитов компании;
- данных компании, которые могут меняться с датой действия.

Пример: договор или реквизиты должны иметь `valid_from` / `valid_to`, чтобы старые документы могли ссылаться на актуальную на тот момент версию.

## Feature toggles

SUPERADMIN должен управлять доступностью функций по компаниям.

Feature toggles должны поддерживать:

- включение/отключение модуля;
- включение/отключение страницы;
- включение/отключение отчёта;
- включение custom-отчёта только для одной компании;
- скрытие функций от компаний, которым они не доступны.

Локальная ERP должна проверять доступность feature до показа страницы/отчёта/действия.

## Бизнес-блоки ERP

Базовые бизнес-блоки:

- компании/локальные ERP;
- пользователи и роли;
- клиенты;
- подрядчики;
- водители;
- машины;
- экипажи;
- заявки клиентов;
- рейсы;
- документы;
- шаблоны документов;
- feature toggles;
- отчёты;
- SUPERADMIN.

## Базовый процесс линейной перевозки

Первый этап — только линейная перевозка.

Базовая цепочка:

1. Создать/выбрать клиента.
2. Создать заявку клиента.
3. Указать маршрут: погрузка → выгрузка.
4. Указать условия перевозки.
5. Выбрать подрядчика.
6. Выбрать экипаж подрядчика.
7. Создать рейс.
8. Прикрепить нужные документы.
9. Вести статус рейса.
10. Завершить рейс.

Сборные рейсы не реализуются на первом этапе.

## Черновая модель БД

Черновик БД описан в `docs/architecture/DATABASE_DRAFT.md`. Он не является финальной схемой и не должен превращаться в миграции без отдельного задания.

Центральная БД SUPERADMIN:

- `companies` — реестр компаний (key, name, entity_type, status, folder_path, db_identifier, settings_json)
- `features` — реестр функций (code, name, type, parent_code, is_system, is_active)
- `company_features` — feature toggles (company_id, feature_code, is_enabled, enabled_from, enabled_until)
- `superadmin_users` — пользователи SUPERADMIN (name, email, password_hash, role)

Полная спецификация: `docs/architecture/SUPERADMIN_DATABASE.md`. DB credentials (пароли) НЕ хранятся в таблицах БД — только логический `db_identifier`.

Локальная БД компании:

- `company_profile`;
- `company_requisites_versions`;
- `users`;
- `roles`;
- `permissions`;
- `role_permissions`;
- `local_feature_cache`;
- `clients`;
- `contractors`;
- `contractor_drivers`;
- `contractor_vehicles`;
- `crews`;
- `document_types`;
- `documents`;
- `document_templates`;
- `document_template_versions`;
- `client_requests`;
- `trips`.

В `trips` предусмотрен `trip_type`:

```text
linear / consolidated_reserved
```

На первом этапе функционально используется только `linear`.

## Текущая структура проекта

```text
app/
  Core/
    Database.php
  Http/
    Router.php
  Superadmin/
    .gitkeep
  Support/helpers.php
  View/
    layouts/main.php
    components/
    pages/ui_demo.php
    pages/superadmin_dashboard.php
    pages/superadmin_dashboard.php
bootstrap/app.php
config/app.php
config/database.php
database/
  migrations/.gitkeep
  migrations/001_create_superadmin_companies.sql
  migrations/002_create_superadmin_features.sql
  migrations/003_create_superadmin_company_features.sql
  migrations/004_create_superadmin_users.sql
  seeds/.gitkeep
docs/
  ai/
  architecture/
  business/
  ui/
    pages/
.kilo/
  agent/
    erp-architect.md
    erp-uiux-designer.md
    erp-coder.md
    erp-qa-tester.md
logs/
public/
  index.php
  assets/css/app.css
  assets/js/app.js
scripts/
  migrate.php
storage/
.env.example
.gitignore
AGENTS.md
kilo.jsonc
README.md
```

Локальный `.env` может существовать в рабочей папке, но он не должен попадать в git и не должен цитироваться в MD-файлах.

## Git-правила

- Не делать commit без явного разрешения владельца.
- Не коммитить `.env`.
- Не коммитить реальные секреты, токены, API-ключи, MYSQL-пароли.
- Перед commit выполнить `git status`.
- После commit снова выполнить `git status`.
- Финальный статус после принятой задачи должен быть clean.
- Если commit важен для статуса проекта, обновить `PROJECT_STATUS.md`, `AGENT_WORK_LOG.md` и проверить необходимость обновления этого файла.

## Как писать промты KILO

Промт KILO должен быть конкретным и проверяемым.

Запрещено писать:

```text
делай ERP
сделай систему
продолжай разработку
сделай нормально
```

В каждом промте должны быть:

- название задачи;
- цель;
- рабочая папка;
- какие файлы обязательно прочитать;
- что именно сделать;
- что запрещено;
- какие проверки выполнить;
- какие MD-файлы обновить;
- нужен ли commit;
- точный формат FINAL REPORT;
- критерии DONE / PARTIAL / BLOCKED / NEEDS_OWNER_DECISION.

Для кодера нужно явно указать модель исполнения: DeepSeek, точечные изменения, без расширения scope, обязательные runtime/syntax checks.

## Как принимать FINAL REPORT агента

FINAL REPORT должен содержать:

- статус: `DONE`, `PARTIAL`, `BLOCKED` или `NEEDS_OWNER_DECISION`;
- что сделано;
- какие файлы прочитаны;
- какие файлы созданы;
- какие файлы обновлены;
- проверки;
- git: commit hash и финальный `git status`, если был commit;
- что не делалось;
- риски;
- вопросы владельцу;
- следующий шаг;
- обновлялся ли `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`, а если нет — почему.

Если агент не обновил логи, статус или этот файл при изменении контекста, задача не считается полностью закрытой.

## Главные запреты

- Не писать бизнес-код без отдельного задания.
- Не создавать БД без отдельного задания.
- Не создавать миграции без отдельного задания.
- Не начинать SUPERADMIN без отдельного задания.
- Не менять утверждённые архитектурные решения без записи решения.
- Не создавать второй/дублирующий файл контекста.
- Не создавать отдельный `START_NEW_CHAT_PROMPT.txt`.
- Не создавать отдельный `.txt` для переносимого контекста.
- Не хранить реальные секреты в MD-файлах.
- Не записывать MYSQL-пароль в этот MD-файл.
- Не коммитить `.env`.
- Не придумывать неподтверждённые бизнес-правила.
- Не давать KILO размытые задачи типа "делай ERP".
- **Не использовать Linux-style команды в Windows PowerShell** (curl, grep и т.д. с Linux-флагами). Все команды должны быть PowerShell-совместимыми. Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.
- **Не принимать UI-экран как финально approved без ручной визуальной проверки владельца.**
- **Не коммитить UI-экран до получения Manual owner visual approval.**
- **Не проверять файлы/UI/код/документацию по памяти или пересказу — только через фактические файлы/архив.**
- **Не использовать demo-placeholder UI** (псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`, emoji как иконки, карточный SaaS-dashboard для admin/settings, большие пустоты, blue/white corporate UI, debug badges).
- **Не принимать UI-страницу без прохождения Layout Foundation Gate** (проверка shell/sidebar/menu/topbar/page header/work area на соответствие STYLE ERP foundation reference).
- **Не начинать component-source audit до прохождения foundation gate.**
- **Не считать component-pass = page-pass, если foundation не проверен.**

---

## Системные правила UI-процесса (добавлены 2026-06-12 после UI-провала /superadmin)

### 1. Правило фактической проверки файлов

Если агент должен проверить настройки агентов, дизайн-код, UI, код, документацию или соответствие реализации правилам, проверка не делается по памяти или пересказу. Нужно запросить реальные файлы/архив и дать владельцу готовую cmd/PowerShell-команду для сборки ZIP из нужных файлов.

### 2. Правило DeepSeek/KILO не vision-модель

DeepSeek/KILO агенты не являются vision-моделями. Они не могут финально оценивать внешний вид «глазами». Агенты проверяют только формальное соответствие MD-шаблону, DESIGN_CODE_INTEGRATION.md, PAGE_PATTERN.md, CSS-классам, DOM/HTML-структуре, отсутствию запрещённых элементов и runtime-проверкам. Финальную визуальную приёмку UI делает владелец по скриншоту или в браузере.

### 3. Правило ручной визуальной приёмки UI

Новые или существенно изменённые UI-экраны нельзя считать финально принятыми и нельзя коммитить как UI-approved, пока владелец не выполнит ручную визуальную проверку. Handoff обязан содержать: VISUAL CHECK URL, список проверок глазами, визуальные блокеры, `Manual owner visual review required: YES`, `Commit allowed before owner visual approval: NO`. При визуальном отклонении — статус `NEEDS_UI_REWORK`, даже если runtime/QA формально PASS.

### 4. Правило запрета demo-placeholder UI

UI handoff и реализация не должны допускать: demo-placeholder вид, псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`, emoji/символы как временные иконки, карточный SaaS-dashboard для admin/settings страниц, большие пустоты, blue/white corporate UI, случайные цвета/классы, `border-radius > 4px`, `box-shadow blur > 8px`, inline styles, Bootstrap/Tailwind/Material классы.

### 5. Правило качества handoff дизайнера

Дизайнер обязан выдавать handoff так, чтобы кодер не искал примеры и не придумывал. В handoff обязательно: exact route/view, layout pattern, exact text, exact components, exact classes, prohibited elements, states, owner visual check, visual blockers, runtime URL, acceptance checklist. Неоднозначность — задача дизайнера не DONE.

### 7. Правило Layout Foundation Gate

Перед component-source audit дизайнер, архитектор и QA обязаны сначала проверить foundation: app shell, sidebar, sidebar menu hierarchy, section labels/groups/counters, active/disabled/future states, bottom settings block, topbar, topbar user block / right area, page context/header, work area spacing/density, соответствие STYLE ERP foundation reference. Если хотя бы один foundation пункт = NO, страница не может получить `ACCEPTED_FOR_QA`, даже если panels/buttons/badges технически правильные.

### 8. Правило Sidebar Information Architecture

Для UI-страниц дизайнер обязан описывать не только блоки внутри страницы, но и навигационную структуру: группы меню, порядок пунктов, где находится SUPERADMIN, что относится к операциям, что относится к системе, какие пункты disabled/future, где counters, где bottom settings, как выглядит active item, какие пункты нельзя смешивать в одной группе.

### 9. Правило Foundation-first review order

Агенты обязаны проверять UI в порядке слоёв:

```text
app shell → sidebar/menu → topbar → page header → work area → components
```

Component-source audit alone is not enough. Запрещено начинать проверку с уровня компонентов (panel / badge / kv / hover), если не проверены базовые слои.

### 10. Правило Главного дизайнера

Главный дизайнер / автор дизайн-системы ERP PLANEX — внешний (не KILO) контрольный role-layer. Он контролирует целостность дизайн-системы, проверяет, что страницы действительно собраны по STYLE ERP / Core Kit, проводит независимый дизайн-аудит спорных UI-результатов, находит системные причины ошибок, указывает какие правила нужно добавить в Core Kit, page templates, agent rules и QA. При UI foundation/design-system споре подключается Главный дизайнер. Его заключения имеют приоритет при дизайн-системных спорах.

### 11. Правило отмены QA-ready статуса

Если владелец или Главный дизайнер визуально выявляет расхождение со STYLE ERP на уровне foundation (shell/sidebar/topbar/menu), все предыдущие агентские статусы `ACCEPTED_FOR_QA` / `QA-ready` отменяются. Страница возвращается на foundation rework независимо от результатов component-source audit.

## Последнее обновление этого файла

2026-06-11 — ChatGPT: файл пересобран как единый самодостаточный переносимый контекст для нового ChatGPT-чата.

2026-06-12 01:50 — KILO/erp-architect: commit `50f96a9` — системное исправление UI-процесса, 15 файлов, DECISION-0023.

2026-06-12 02:00 — KILO/erp-architect: переделка `/superadmin` завершена (первый круг). 3 файла изменены.

2026-06-12 02:10 — KILO/erp-architect: второй круг UI rework. Исправлена палитра, font stack, ERP-плотность.

2026-06-12 — ChatGPT/erp-architect: STAGE B начат. `/superadmin` остаётся `NEEDS_UI_REWORK`.

2026-06-12 — Codex GPT/erp-architect: STYLE ERP formalization. Создан `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`.

2026-06-12 — Codex GPT/erp-architect: UI Module Catalog. Создан `docs/ui/ERP_UI_MODULE_CATALOG.html` (legacy/reference).

2026-06-12 — Codex GPT/erp-architect: UI Kit Core. Создан `docs/ui/ERP_UI_KIT_CORE.html` (PRIMARY, 45 CORE modules, 10 COMPOSITE patterns).

2026-06-13 — KILO/erp-architect: ~~Corrective cycle complete. Coder rework accepted for QA.~~ **ОТМЕНЁН ВЛАДЕЛЬЦЕМ (foundation audit 2026-06-13).** Core Kit + template + agents + handoff обновлены. Coder rework: 10 fixes, BLOCKER `.panel` padding устранён. Однако foundation (shell/sidebar/topbar/menu) не был проверен и не соответствует STYLE ERP.

2026-06-13 — KILO/erp-architect: **Foundation rework cycle COMPLETE. `/superadmin` status: FOUNDATION_REWORK_ACCEPTED_FOR_VISUAL_REVIEW.** Designer foundation handoff (6 разделов), architect review (PASS), coder foundation rewrite (main.php + app.css), architect implementation review (PASS). Это разрешение на ручной visual review, не финальное approval.

2026-06-13 — Codex GPT/erp-architect: после переходного промта нового ChatGPT-чата зафиксировано: owner visual review по скриншоту не принял `/superadmin`. Актуальный статус: `PARTIALLY COMPLIANT / NEEDS_UI_REWORK`. Следующий шаг: compliance-аудит дизайнером, не QA.

2026-06-13 13:15 — KILO/erp-architect: точечный coder rework `/superadmin` завершён. Все 5 отклонений compliance-аудита исправлены (5 строк `app.css` + 1 строка `index.php`). Актуальный статус: `PARTIALLY COMPLIANT / READY_FOR_OWNER_VISUAL_REVIEW`. Следующий шаг: Owner visual review. MD обновлены: AGENT_WORK_LOG, PROJECT_STATUS, ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.

2026-06-13 13:30 — Codex GPT/erp-architect: владелец принял текущий результат `/superadmin` для продолжения разработки. Актуальный статус: `OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`. Проверка Главным дизайнером / КЛАУД остаётся `DESIGN_REVIEW_PENDING`, но не блокирует дальнейший кодинг. Следующий этап — commit текущей точки и развитие SUPERADMIN business foundation.

---
## CURRENT CONTEXT OVERRIDE — 2026-06-13 — OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT

Current UI source of truth:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

Legacy extraction/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

STYLE ERP — reference/example library, not runtime library:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\
```

Important update (2026-06-13 after owner decision):

- `/superadmin` status: **`OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT`**.
- Compliance audit: 81 checks, 76 COMPLIANT, 5 deviations, 0 BLOCKER.
- All 5 deviations FIXED: 5 lines in `app.css` + 1 line in `index.php`.
- Foundation/shell/sidebar/topbar/IA: COMPLIANT, NOT modified.
- `main.php` and `superadmin_dashboard.php`: NOT modified.
- Backend/auth/CRUD/database/scripts/Core Kit: NO changes.
- Owner accepted current result for continued development.
- Chief designer / KLAUD design review: **PENDING, not blocking**.
- QA not started for this checkpoint.
- Commit is now allowed by owner request to finalize the checkpoint.
- Next after commit: continue SUPERADMIN business foundation, recommended first module `SUPERADMIN Companies Registry`.
