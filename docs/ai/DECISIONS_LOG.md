# ERP PLANEX — DECISIONS_LOG

## DECISION-0001 — Стек проекта

### Решение
ERP PLANEX разрабатывается на PHP/MySQL.

### Причина
Стек выбран владельцем проекта.

### Статус
active

---

## DECISION-0002 — Модель разработки

### Решение
Вся система разрабатывается с помощью ИИ-агентов. Основной кодер: KILO + DeepSeek.

### Причина
Проект должен быть организован так, чтобы новые агенты быстро понимали состояние проекта по MD-документации и логам.

### Статус
active

---

## DECISION-0003 — Модель развёртывания компаний

### Решение
Код общий, но для каждого юридического лица используется отдельная папка и отдельная база данных.

Пример:
```text
erp/
  superadmin/
  planex/
  company_a/
  company_b/
```

### Причина
У владельца несколько юридических лиц. Нужно быстро разворачивать клон ERP в соседней папке с новой БД.

### Статус
active

---

## DECISION-0004 — Одна локальная ERP = одно юридическое лицо

### Решение
В одной локальной ERP и одной локальной БД работает одно юридическое лицо.

### Причина
Так проще разделять данные, доступы, storage, документы и ответственность.

### Статус
active

---

## DECISION-0005 — SUPERADMIN

### Решение
SUPERADMIN имеет отдельную центральную панель:

```text
erp/superadmin/
```

SUPERADMIN должен иметь максимальный функционал:
- создание и управление локальными системами;
- управление БД;
- управление пользователями;
- управление структурой;
- управление настройками;
- управление доступностью функций;
- управление модулями, страницами, отчётами и интеграциями;
- доступ к общей картине по системам.

### Статус
active

---

## DECISION-0006 — Роль “Руководитель”

### Решение
В локальной ERP должна быть роль “Руководитель”.

Руководитель — локальный администратор ERP конкретной компании. Он видит всю информацию внутри своей компании, включая бухгалтерские данные.

### Статус
active

---

## DECISION-0007 — Подрядчик

### Решение
Термины “подрядчик”, “перевозчик”, “экспедитор” объединяются в один термин:

```text
ПОДРЯДЧИК
```

### Причина
Для первого этапа не усложнять модель контрагентов лишними типами.

### Статус
active

---

## DECISION-0008 — Экипаж

### Решение
Экипаж = Подрядчик + жёсткая пара (Машина + Водитель).

Если водитель пересел на другую машину, создаётся новый экипаж.

Пример:
```text
ООО РОМАШКА
Экипаж 1: Иванов + А999АА99
Экипаж 2: Иванов + И999ПП99
```

### Статус
active

---

## DECISION-0009 — Водители и машины

### Решение
Водители и машины могут существовать отдельно в справочнике подрядчика до создания экипажа.

### Статус
active

---

## DECISION-0010 — Документы

### Решение
Документы физически хранятся в `/storage`. В БД хранятся пути и метаданные.

### Статус
active

---

## DECISION-0011 — Версионность

### Решение
Версионность обязательна для:
- шаблонов договоров;
- шаблонов документов;
- реквизитов компании;
- данных юридического лица;
- случаев, когда с определённой даты действует новый шаблон или новые реквизиты.

### Статус
active

---

## DECISION-0012 — Первый этап перевозок

### Решение
На первом этапе реализуется только линейная перевозка.

Сборный рейс резервируется в архитектуре, но не реализуется.

### Статус
active

---

## DECISION-0013 — Feature toggles

### Решение
SUPERADMIN управляет доступностью функций, модулей, страниц, отчётов и интеграций по компаниям.

Источник истины — центральная БД SUPERADMIN. Локальная ERP использует локальный кэш доступных features, чтобы не зависеть постоянно от центральной панели.

### Статус
active

---

## DECISION-0014 — Агентская сеть перед активной разработкой

### Решение
Перед активным написанием ERP нужно использовать описанную агентскую сеть:
- владелец проекта;
- ChatGPT-координатор;
- KILO + DeepSeek как основной кодер;
- архитектор;
- UI/UX-дизайн-агент;
- QA-агент;
- документационный агент.

Правила ролей, промтов, агентного цикла и приёмки результата фиксируются в `docs/ai/AGENT_NETWORK.md`.

### Причина
Проект разрабатывается ИИ-агентами. Чтобы не давать размытые задачи и не терять контекст между чатами, нужен единый порядок постановки задач, проверки и обновления MD.

### Статус
active

---

## DECISION-0015 — Дизайн-код интегрируется до бизнес-модулей

### Решение
Перед активной разработкой бизнес-страниц нужно создать и интегрировать единый UI-фундамент:
- layout;
- дизайн-токены;
- базовые компоненты;
- стили форм;
- стили таблиц;
- статусы;
- ошибки;
- пустые состояния;
- правила подключения CSS/JS.

Правила фиксируются в `docs/ui/DESIGN_CODE_INTEGRATION.md`.

### Причина
ERP PLANEX должна иметь единый рабочий интерфейс, а не набор разрозненных страниц с разными стилями.

### Статус
active

---

## DECISION-0016 — Проектные KILO-агенты вместо внешнего координатора

### Решение
Агентская сеть ERP PLANEX перенесена внутрь KILO. Вместо внешнего ChatGPT-координатора используются 4 проектных KILO-агента:

- `erp-architect` (primary, default_agent) — главный координатор;
- `erp-uiux-designer` (primary/selectable) — UI/UX-дизайнер;
- `erp-coder` (primary/selectable) — исполнитель разработки;
- `erp-qa-tester` (primary/selectable) — тестировщик.

Все 4 агента должны быть доступны в выпадающем списке KILO. `subagent`-режим не используется для этих ролей, потому что интерфейс KILO показывает только primary/selectable agents.

Все агенты определены в `.kilo/agent/*.md`. Главный режим — `erp-architect` (установлен как `default_agent` в `kilo.jsonc`).

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи остальным агентам через `task` tool.

Рабочий цикл:
```text
Владелец → erp-architect → erp-uiux-designer / erp-coder / erp-qa-tester → erp-architect → принятие / второй круг
```

### Причина
Переход от внешнего ChatGPT-координатора к внутренним проектным KILO-агентам упрощает агентный цикл: все роли работают в одной среде KILO, контекст не теряется между чатами, задачи и результаты передаются внутри одной сессии.

### Статус
active


---

## DECISION-0017 — UI-страницы реализуются через MD-шаблоны дизайнера

### Решение
При создании или изменении любой страницы ERP PLANEX сначала должен работать `erp-uiux-designer`, если задача затрагивает интерфейс, пользовательский сценарий, форму, таблицу, карточку сущности, навигацию, статусы, модалки, inspector, empty/loading/error states или расположение блоков.

Дизайнер обязан создать или обновить MD-шаблон страницы в:

```text
docs/ui/pages/
```

MD-шаблон страницы является источником истины для кодера и QA.

Рабочий цикл UI-задачи:

```text
erp-architect → erp-uiux-designer → erp-coder → erp-qa-tester → erp-architect
```

Кодер реализует UI строго по актуальному MD-шаблону страницы.

QA проверяет соответствие реализации этому шаблону.

Архитектор не принимает UI-задачу как `DONE`, если MD-шаблон отсутствует, устарел или реализация ему не соответствует.

### Причина
ERP PLANEX должна развиваться единообразно. Кодер не должен сам придумывать внешний вид, структуру страницы, классы, layout и UX-поведение. MD-шаблон страницы снижает хаос, упрощает проверку и сохраняет дизайн-код в документации.

### Статус
active

## DECISION-0018 — Очистка UI-документации

### Решение

Удалить дублирующий файл:

```text
docs/ui/UI_UX_RULES.md
```

и ошибочный дубликат папки:

```text
docs/ui/ui/
```

Главным UI/UX и дизайн-код регламентом считать:

```text
docs/ui/DESIGN_CODE_INTEGRATION.md
```

MD-шаблоны конкретных страниц хранить в:

```text
docs/ui/pages/
```

### Причина

`UI_UX_RULES.md` дублировал общий смысл `DESIGN_CODE_INTEGRATION.md` и создавал риск расхождения правил. Папка `docs/ui/ui/` была техническим дублем.

### Статус

active

---

## DECISION-0019 — SUPERADMIN Stage 1: минимальный каркас

### Решение

SUPERADMIN Stage 1 создаёт минимальный архитектурный и технический каркас центральной панели SUPERADMIN без бизнес-логики.

Конкретные решения Stage 1:

1. **Маршрут**: `/superadmin` — простой путь, совместимый с текущим посегментным Router.
2. **Структура папок**: `app/Superadmin/` — модуль SUPERADMIN в общем коде.
3. **Файловая папка** `erp/superadmin/` зарезервирована под будущее развёртывание центральной панели как отдельной точки входа.
4. **UI**: страница-заглушка dashboard с информационными карточками, навигационными пунктами (disabled), empty states.
5. **Интеграция**: существующий layout/main.php и UI-фундамент.
6. **Архитектурные документы**: `docs/architecture/SUPERADMIN.md`, `docs/ui/pages/superadmin-dashboard.md`.

Что НЕ входит в Stage 1:
- Центральная БД, таблицы, миграции
- Авторизация, login/logout, сессии
- CRUD компаний, пользователей SUPERADMIN
- Feature toggles (код и UI)
- Бизнес-модули

### Причина

SUPERADMIN должен начинаться с минимального проверяемого каркаса, а не с полноценной реализации. Это соответствует правилу маленьких задач: одна задача — один понятный результат.

### Статус

active

---

## DECISION-0020 — Windows PowerShell command rules

### Решение

Зафиксировать обязательные правила выполнения команд в Windows PowerShell для всех агентов проекта.

Конкретные правила:
1. Проект работает на Windows. Команды выполняются в Windows PowerShell 5.1, которая не является Linux shell.
2. **Запрещено** использовать Linux-style `curl` синтаксис в PowerShell: `curl` — это alias для `Invoke-WebRequest`, флаги `-s`, `-o NUL`, `-w` несовместимы.
3. Для HTTP-проверок использовать `Invoke-WebRequest` с `-UseBasicParsing` или `cmd.exe /c curl.exe`.
4. Для проверки HTTP 4xx/5xx использовать try/catch, чтобы PowerShell не прерывал выполнение.
5. В спорных случаях для Git использовать `cmd.exe /c "git ..."` или `git --no-pager`.
6. Команды должны быть проверяемыми и не должны зависать.
7. Если команда сломалась из-за оболочки — это не ACCEPTED, нужно повторить корректной командой.
8. Shell-ошибки из-за несовместимости — tooling/runtime issues, не app failures.

Правила зафиксированы в отдельном документе: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

Ссылки добавлены в: `KILO_PROJECT_RULES.md`, `QA_CHECKLIST.md`, `DEEPSEEK_CODER_RULES.md`, `TASK_TEMPLATE.md`.

### Причина

Во время QA SUPERADMIN Stage 1 (2026-06-12) агент использовал Linux-style `curl` команду в PowerShell:
```
curl -s -o NUL -w "%{http_code}" http://127.0.0.1:8015/superadmin
```
PowerShell воспринял `curl` как alias для `Invoke-WebRequest`, команда сломалась, QA-проверка зависла/остановилась. Чтобы предотвратить повторение, правила зафиксированы как системное требование.

### Статус

active

---

## DECISION-0021 — Схема центральной БД SUPERADMIN

### Решение

Утверждена точная архитектурная схема центральной БД SUPERADMIN. Создан документ `docs/architecture/SUPERADMIN_DATABASE.md`.

**Таблицы:**

1. `companies` — центральный реестр компаний/локальных ERP. Поля: id, key (уникальный slug, неизменяем), name, short_name, entity_type, status (active/inactive/suspended/provisioning), folder_path, db_identifier (только имя БД, НЕ пароль), storage_path, settings_json (JSON), created_at, updated_at. 5 индексов.

2. `features` — реестр функций. Поля: id, code (уникальный, конвенция `type.name`), name, type (module/page/report/custom_report/action/integration/ui_block), description, parent_code (иерархия), is_system, is_active, sort_order, created_at, updated_at. 6 индексов. Само-ссылка FK: parent_code → code.

3. `company_features` — связка компаний и функций (feature toggles). Поля: id, company_id (FK→companies), feature_code (FK→features), is_enabled, enabled_from, enabled_until, notes, created_at, updated_at. 6 индексов. Уникальность (company_id, feature_code). Default-deny: если записи нет — функция недоступна.

4. `superadmin_users` — пользователи SUPERADMIN (отдельные от локальных users). Поля: id, name, email (уникальный), password_hash (bcrypt, cost ≥ 12), role (admin/operator/viewer), is_active, last_login_at, last_login_ip, created_at, updated_at. 4 индекса.

**Ключевые архитектурные решения:**
- Все таблицы: InnoDB, utf8mb4, utf8mb4_unicode_ci, timestamps created_at/updated_at.
- DB credentials (пароли, хосты, порты, пользователи) **никогда не хранятся в таблице companies или любой другой таблице БД**. Только логический `db_identifier`. Реальные credentials — в локальном `.env` или внешнем хранилище секретов.
- Feature toggles: модель default-deny (компания не имеет доступа к функции, пока нет явной записи с is_enabled=1).
- Конвенция кодов feature: `type.name` (например, `module.trips`, `page.finance_report`, `report.monthly_pnl`).
- Статусная модель companies: provisioning → active, active ↔ suspended, active ↔ inactive.
- Зарезервированы поля для будущих этапов (мягкое удаление, аватарки, 2FA, сброс пароля, иконки, конфигурации).

### Причина

Перед созданием миграций нужна точная спецификация, чтобы кодер не придумывал схему на ходу, а следовал утверждённому архитектурному документу.

### Статус

active

---

## DECISION-0022 — Целевая версия MySQL: 5.7+

### Решение

Целевая версия MySQL для ERP PLANEX: **MySQL 5.7+**.

Следовательно:
- В миграциях центральной БД SUPERADMIN используется тип `JSON` для поля `companies.settings_json`.
- Тип `JSON` в MySQL 5.7+ поддерживает нативную валидацию JSON, компактное хранение и функции JSON_EXTRACT/JSON_SET.
- Вопрос из `SUPERADMIN_DATABASE.md` (строка «JSON-поле settings_json требует MySQL 5.7+. Если минимальная версия MySQL ниже, заменить на TEXT») **снят**: используется JSON.

### Причина

Решение владельца проекта. MySQL 5.7 является минимальной поддерживаемой версией с 2015 года, широко доступна на хостингах, и тип JSON даёт преимущества перед TEXT+JSON-валидацией на уровне приложения.

### Статус

active

---

## DECISION-0023 — Системные правила UI-процесса ERP PLANEX

### Решение

После визуального отклонения `/superadmin` владельцем (2026-06-12) зафиксированы 6 обязательных системных правил UI-процесса:

1. **Правило фактической проверки файлов** — проверка делается только по фактическим файлам/архиву, не по памяти.
2. **Правило DeepSeek/KILO не vision-модель** — агенты проверяют формальное соответствие, финальную визуальную приёмку делает владелец.
3. **Правило ручной визуальной приёмки UI** — новые UI-экраны не коммитятся без visual approval владельца.
4. **Правило запрета demo-placeholder UI** — запрещены псевдоиконки, карточный SaaS-dashboard, blue/white corporate UI, случайные цвета/классы.
5. **Правило качества handoff дизайнера** — handoff однозначный: exact route/view, components, classes, states, visual check.
6. **Правило Formal UI QA** — QA пишет PASS/FAIL, запрещены субъективные оценки.

Правила внесены в 4 агента, 12+ MD-документов.

### Причина

Текущий `/superadmin` (Stage 1) был технически принят, но визуально отклонён владельцем: страница выглядит как SaaS-dashboard demo с псевдоиконками вместо строгой ERP-панели. Проблема системная — слабый handoff дизайнера, отсутствие визуальной приёмки, формальный QA без запрета demo-placeholder.

### Статус

active

---

## DECISION-0024 — UI Production Loop для важных UI-экранов

### Решение

Для важных UI-экранов ERP PLANEX вводится обязательный UI Production Loop:

```text
Architect intake
→ Designer production handoff
→ Architect handoff review
→ Coder implementation
→ Formal QA
→ Architect pre-owner review
→ repeat designer/coder/QA cycle if not enough
→ Owner visual review
→ commit only after owner approval
```

Ключевые правила:

1. Важный UI нельзя отдавать кодеру без production-grade handoff от `erp-uiux-designer`.
2. `erp-architect` обязан выполнить handoff review до передачи кодеру.
3. Если handoff общий, противоречивый, устаревший, содержит `REJECTED` как актуальный статус или допускает разные трактовки, задача возвращается дизайнеру со статусом `NEEDS_DESIGNER_REWORK`.
4. `erp-coder` обязан вернуть `BLOCKED: NEEDS_DESIGNER_REWORK`, если handoff слабый, отсутствует, противоречит design-code/PAGE_PATTERN или не указывает точные sections/classes/tokens.
5. `Formal UI QA: PASS` не является visual acceptance.
6. После QA `erp-architect` обязан выполнить pre-owner review.
7. Если результат всё ещё похож на demo/foundation/showcase/web-page/SaaS-dashboard или не соответствует master UI-kit, владелец не получает экран как готовый; запускается повторный цикл designer/coder/QA.
8. Owner visual review запускается только после того, как агентская цепочка сама довела экран максимально близко к master UI-kit.
9. Commit UI-экрана разрешён только после явного owner approval.

### Причина

После двух rework `/superadmin` стало ясно, что прежние правила закрывали отдельные симптомы, но не закрывали процесс: designer мог выдать общий handoff, architect мог слишком рано передать задачу кодеру, coder формально реализовывал слабую спецификацию, QA давал Formal PASS, а владелец затем визуально видел несоответствие master UI-kit.

Нужен производственный UI-конвейер с обязательными gate points до кодера, после QA и перед owner visual review.

### Статус

active

---

## DECISION-0025 — STYLE ERP как визуальный reference, не runtime-библиотека

### Решение

Папка:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\
```

является набором визуальных образцов TransportERP / ERP PLANEX.

STYLE ERP не является:

- runtime-библиотекой;
- библиотекой компонентов;
- dependency;
- источником HTML/CSS для копирования кодером;
- местом, куда кодер должен ходить и выбирать блоки.

Все значимые правила из STYLE ERP должны быть формализованы в MD до передачи UI-задачи кодеру. Главный документ формализации:

```text
docs/ui/STYLE_ERP_EXTRACTED_RULES.md
```

Правило для workflow:

1. Codex GPT / architect / designer изучают STYLE ERP.
2. Значимые правила переносятся в MD и page handoff.
3. `erp-uiux-designer` создаёт production-grade handoff по MD.
4. `erp-coder` реализует строго по MD/handoff.
5. Если handoff требует открыть STYLE ERP или выбрать оттуда блок, `erp-coder` возвращает `BLOCKED: NEEDS_DESIGNER_REWORK`.

### Причина

STYLE ERP содержит полезные визуальные паттерны, но также содержит showcase-экраны. Если дать кодеру прямой доступ к образцам как к библиотеке, он может скопировать showcase/demo composition вместо production ERP screen. Поэтому STYLE ERP используется только как материал для формализации правил в MD.

### Статус

active

---

## DECISION-0026 — UI Module Catalog как обязательный gate для UI handoff

### Решение

Создан визуальный каталог формализованных UI-модулей:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

Каталог является обязательным источником для `erp-uiux-designer`, `erp-architect`, `erp-coder` и `erp-qa-tester`.

Правила:

1. Дизайнер проектирует страницы только из модулей, описанных в `ERP_UI_MODULE_CATALOG.html` и профильных MD.
2. Page handoff обязан содержать `UI modules used` с номерами/названиями модулей.
3. Page handoff обязан содержать `MODULE USAGE DECISIONS` с обоснованием выбора/отказа от модулей.
4. Если подходящего модуля нет, дизайнер возвращает `BLOCKED: NEEDS_UI_MODULE_EXPANSION`.
5. Архитектор не передаёт кодеру handoff с unknown UI module.
6. Кодер не реализует неизвестные UI-модули и возвращает `BLOCKED: UNKNOWN_UI_MODULE`.
7. QA проверяет, что все модули перечислены и существуют в catalog/MD; unknown UI module = `Formal UI QA: FAIL`.

### Причина

После визуального провала `/superadmin` и формализации STYLE ERP нужно исключить ситуацию, когда дизайнер создаёт page handoff из неформализованных блоков или кодер вынужден изобретать UI. Каталог превращает STYLE ERP из набора визуальных примеров в проверяемый набор разрешённых модулей.

### Статус

superseded by DECISION-0027. `ERP_UI_MODULE_CATALOG.html` remains legacy extraction/reference history only; primary UI source is `ERP_UI_KIT_CORE.html`.
---

## DECISION-0027 — UI Kit Core as primary compact designer catalog

### Decision

Created and adopted the compact primary UI-kit:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

The previous extraction catalog remains legacy/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

Rules:

1. `erp-uiux-designer` designs pages from CORE modules and COMPOSITE patterns in `ERP_UI_KIT_CORE.html`.
2. Page handoff must contain `CORE modules used`, selected `COMPOSITE pattern`, and `MODULE USAGE DECISIONS`.
3. Missing module = `BLOCKED: NEEDS_UI_MODULE_EXPANSION`.
4. Unknown/private module for coder = `BLOCKED: UNKNOWN_UI_MODULE`.
5. QA fails handoff/implementation when unknown modules or private/page-specific names are used as universal modules.
6. `ERP_UI_MODULE_CATALOG.html` is not updated/overwritten in ordinary UI work.

### Reason

The 224-item extraction catalog was too large and source-oriented for daily designer work. The project needs a compact universal kit with stable IDs, previews, rules, and matrices.

### Status

active

---

## DECISION-0028 — Предтестовый аудит дизайн-системы: контрольная точка

### Решение

Завершён предтестовый аудит дизайн-системы ERP PLANEX. Зафиксирована контрольная точка:

1. **`docs/ui/ERP_UI_KIT_CORE.html`** является primary compact working UI-kit для всей агентной цепочки (architect, designer, coder, QA). Содержит 45 CORE-модулей, 10 COMPOSITE patterns, Button Decision Matrix, Layout Decision Matrix, Designer/Coder/QA Rules, SUPERADMIN READY SET.

2. **`docs/ui/ERP_UI_MODULE_CATALOG.html`** является legacy extraction/reference history only. Не удаляется, не переписывается в обычной UI-работе. Не является основным рабочим каталогом дизайнера.

3. **STYLE ERP** (`C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\`) является reference/example library, не runtime library. Кодер не ходит туда за самостоятельными дизайнерскими решениями.

4. **Агентский workflow** architect → designer → coder → QA теперь использует Core Kit как основной источник. Все agent files (AGENT_NETWORK.md, KILO_WORKFLOW.md, QA_CHECKLIST.md, TASK_TEMPLATE.md, KILO_PROJECT_RULES.md) синхронизированы.

5. **`/superadmin`** остаётся UI-blocked. На момент DECISION-0028 следующий шаг был production-grade designer handoff по Core Kit. Актуальный статус после последующего owner visual review зафиксирован в DECISION-0029 и `PROJECT_STATUS.md`: `PARTIALLY COMPLIANT / NEEDS_UI_REWORK`.

6. **Commit не выполнялся** — документационная фиксация контрольной точки, код не менялся.

### Причина

После создания Core Kit и завершения аудита требуется явная фиксация состояния, чтобы новый ChatGPT-чат или агент понимал текущую контрольную точку, не возвращался к старому каталогу как основному и не начинал кодинг `/superadmin` без designer handoff.

### Статус

active

---

## DECISION-0029 — Централизация app shell / sidebar / topbar и compliance-only UI review

### Решение

Для ERP PLANEX app shell, sidebar/menu, topbar/header, user block и общий content wrapper должны быть централизованы в:

```text
app/View/layouts/main.php
```

Позднее допускается вынести части shell в:

```text
app/View/components/sidebar.php
app/View/components/topbar.php
```

Page view должен содержать только рабочий контент конкретной страницы:

```text
app/View/pages/[page].php
```

Страницы не должны копировать sidebar/topbar/header и не должны создавать page-specific shell.

UI review выполняется только через compliance language:

```text
COMPLIANT / PARTIALLY COMPLIANT / NON-COMPLIANT
```

Каждое UI-отклонение должно ссылаться на источник нормы:

```text
STYLE ERP / Core Kit / page handoff / Layout Foundation Gate / Sidebar IA / Design Code
```

Субъективные оценки вида "лучше/хуже", "красиво/некрасиво", "нравится/не нравится" не используются в отчётах архитектора, дизайнера, кодера и QA.

### Причина

После visual review `/superadmin` стало ясно, что статусы `ACCEPTED_FOR_QA`, `PASS` и `FOUNDATION_REWORK_ACCEPTED_FOR_VISUAL_REVIEW` не могут считаться финальным visual approval. Чтобы исключить самодельные page-specific shell решения и неоднозначные UI-оценки, правила layout foundation и compliance-language фиксируются как архитектурное решение.

### Статус

active

---

## DECISION-0030 — `/superadmin` accepted for continued development; KLAUD design review pending

### Решение

Владелец принял текущий результат `/superadmin` для продолжения разработки системы:

```text
/superadmin = OWNER_ACCEPTED_FOR_CONTINUED_DEVELOPMENT
```

Проверка Главным дизайнером / КЛАУД остаётся обязательной позже, когда в системе будет больше функционала и страниц, но не является stop factor для дальнейшего кодинга.

Текущая оговорка:

```text
Chief designer / KLAUD design review = PENDING, not blocking
```

Разработка может продолжаться в сторону SUPERADMIN business foundation. Рекомендуемый следующий модуль:

```text
SUPERADMIN Companies Registry
```

### Причина

Текущий `/superadmin` прошёл compliance-аудит и точечный coder rework: 81 проверка, 76 `COMPLIANT`, 5 отклонений исправлены, 0 `BLOCKER`. Владелец принимает результат как достаточный для продолжения развития ERP, а комплексную дизайн-приёмку Главным дизайнером планирует выполнить позже по набору экранов.

### Статус

active
