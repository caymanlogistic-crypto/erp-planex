# ERP PLANEX — AGENT_NETWORK

## Назначение

Этот документ описывает агентскую сеть ERP PLANEX: какие проектные KILO-режимы (агенты) используются, как писать промты, как принимать результаты и кто за что отвечает.

Цель — чтобы владелец проекта не давал размытые задачи типа `делай ERP`, а запускал точные агентские циклы с понятным результатом, проверками и обновлением MD-контекста.

## Проектные KILO-режимы

В проекте настроены 4 проектных KILO-агента (режима):

| Режим | Тип | Роль |
|-------|-----|------|
| `erp-architect` | primary, default_agent | Главный координатор. Общается с владельцем, ставит задачи, принимает результат. |
| `erp-uiux-designer` | primary/selectable | UI/UX-дизайнер. Проектирует интерфейсы, готовит требования для кодера. |
| `erp-coder` | primary/selectable | Исполнитель разработки. Пишет код строго по ТЗ. |
| `erp-qa-tester` | primary/selectable | Тестировщик. Проверяет качество, пишет отчёт. |

Все 4 агента определены как проектные в `.kilo/agent/`. Главный режим — `erp-architect` (установлен как `default_agent` в `kilo.jsonc`).

## Главный принцип

ERP PLANEX разрабатывается по цепочке:

```text
Владелец проекта
→ erp-architect
→ erp-uiux-designer / erp-coder / erp-qa-tester
→ erp-architect
→ принятие результата или второй круг
```

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи дизайнеру, кодеру и тестировщику через агентскую сеть.

KILO снова используется через `erp-architect`, но UI-задачи проходят только по формализованным MD-правилам, Core Kit и page handoff. Кодер не получает UI-задачу без accepted handoff и обязательного блока ограничений.

Текущий статус `/superadmin`: `PARTIALLY COMPLIANT / NEEDS_UI_REWORK`. Следующий шаг — compliance-аудит текущего экрана дизайнером, не QA и не commit.

STYLE ERP — это визуальные образцы, не runtime-библиотека и не библиотека компонентов. К агентам и кодеру передаются только формализованные MD-правила, прежде всего `docs/ui/STYLE_ERP_EXTRACTED_RULES.md` и page handoff.

Первичный рабочий UI-kit для всей агентной цепочки:

```text
docs/ui/ERP_UI_KIT_CORE.html  ← PRIMARY compact working UI-kit
```

`docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy extraction/reference history only; не основной рабочий каталог дизайнера.

Дизайнер проектирует page handoff только из CORE modules и COMPOSITE patterns из `ERP_UI_KIT_CORE.html`. Если нужного CORE-модуля нет, дизайнер возвращает `BLOCKED: NEEDS_UI_MODULE_EXPANSION`; архитектор запускает отдельную задачу на расширение Core Kit. Кодер не реализует unknown UI modules и возвращает `BLOCKED: UNKNOWN_UI_MODULE`.

UI оценивается только через `COMPLIANT` / `PARTIALLY COMPLIANT` / `NON-COMPLIANT` с указанием источника нормы: STYLE ERP / Core Kit / page handoff / Layout Foundation Gate / Sidebar IA / Design Code. Слова "лучше/хуже", "красиво/некрасиво", "нравится/не нравится" не используются.

## Обязательный входной контекст для всех агентов

Перед любой задачей агент обязан прочитать:
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- `README.md`;
- `AGENTS.md`;
- `docs/ai/PROJECT_STATUS.md`;
- `docs/ai/DECISIONS_LOG.md`;
- `docs/ai/AGENT_WORK_LOG.md`;
- профильные MD-файлы по задаче.

Если задача затрагивает UI, обязательно читать:
- `docs/ui/ERP_UI_KIT_CORE.html` — PRIMARY compact working UI-kit;
- `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`;
- `docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy extraction/reference history only;
- `docs/ui/PAGE_PATTERN.md`;
- `docs/ui/FORMS_STANDARD.md`;
- `docs/ui/TABLES_STANDARD.md`;
- `docs/ui/DESIGN_CODE_INTEGRATION.md`.

## Роли агентской сети

### 1. erp-architect (главный координатор)

Отвечает за:
- понимание задачи владельца;
- разбиение больших задач на точные шаги;
- подготовку промтов для дизайнера, кодера и тестировщика;
- проверку FINAL REPORT;
- контроль логов и статуса проекта;
- контроль `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- выявление мест, где нужно решение владельца;
- приёмку или отклонение результата;
- запуск второго круга при слабом результате.

Не должен:
- придумывать бизнес-правила;
- скрывать неопределённость;
- давать агенту размытую задачу;
- начинать бизнес-код без отдельного решения владельца.

### 2. erp-uiux-designer (UI/UX-дизайнер)

Отвечает за:
- дизайн-код;
- визуальные стандарты;
- page pattern;
- формы;
- таблицы;
- понятные состояния интерфейса;
- адаптацию дизайна под рабочую ERP, а не маркетинговый сайт;
- подготовку понятных требований для erp-coder.

Работает по:
- `docs/ui/ERP_UI_KIT_CORE.html` — PRIMARY compact working UI-kit;
- `docs/ui/PAGE_PATTERN.md`;
- `docs/ui/FORMS_STANDARD.md`;
- `docs/ui/TABLES_STANDARD.md`;
- `docs/ui/DESIGN_CODE_INTEGRATION.md`.

Не должен:
- писать backend-логику;
- менять бизнес-архитектуру без erp-architect;
- делать красивый, но неудобный интерфейс.

### 3. erp-coder (исполнитель разработки)

Отвечает за:
- написание PHP/MySQL/HTML/CSS/JS строго по ТЗ;
- соблюдение архитектуры проекта;
- выполнение проверок (`php -l`, git status);
- исправление ошибок;
- обновление `AGENT_WORK_LOG.md`, `PROJECT_STATUS.md`, профильных MD-файлов и `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- подготовку FINAL REPORT.

Не должен:
- писать бизнес-код без отдельного задания;
- создавать БД или миграции без отдельного задания;
- начинать SUPERADMIN без отдельного задания;
- добавлять страницы без `feature_code`;
- добавлять действия без `permission_code`;
- смешивать SQL, HTML, бизнес-логику и доступы в одном месте;
- менять архитектурные решения без записи в `DECISIONS_LOG.md`;
- коммитить `.env` и секреты.

### 4. erp-qa-tester (тестировщик)

Отвечает за:
- поиск багов;
- проверку реализованного функционала;
- проверку форм, таблиц, прав, ошибок, UX, адаптива;
- проверку соответствия ТЗ;
- проверку обновления MD и логов;
- проверку `php -l` для PHP-файлов;
- проверку отсутствия секретов;
- проверку git status;
- подготовку понятного отчёта: принято / не принято / нужен второй круг.

Не принимает задачу как DONE, если:
- нет логов;
- нет статуса;
- не выполнены доступные проверки;
- не ясно, обновлялся ли переносимый контекст;
- есть неподтверждённые бизнес-правила.

## Формат задачи для KILO

Каждая задача для агента должна иметь:

```md
# ЗАДАЧА ДЛЯ KILO

## Цель
...

## Перед началом прочитать
- docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md
- docs/ai/PROJECT_STATUS.md
- docs/ai/DECISIONS_LOG.md
- docs/ai/AGENT_WORK_LOG.md
- профильные MD-файлы

## Что сделать
1. ...
2. ...

## Что запрещено
- ...

## Проверки
- ...

## Какие MD обновить
- docs/ai/AGENT_WORK_LOG.md
- docs/ai/PROJECT_STATUS.md
- docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md, если изменился контекст
- профильные MD-файлы

## Commit
Сделать / не делать.

## FINAL REPORT
Дать отчёт по шаблону проекта.
```

## Правило маленьких задач

Одна задача KILO должна иметь один понятный результат.

Плохо:

```text
Сделай SUPERADMIN.
```

Хорошо:

```text
Создай технический каркас роутера без бизнес-страниц и без БД. Обнови PHP_APP_SKELETON.md, AGENT_WORK_LOG.md, PROJECT_STATUS.md и ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md.
```

## Порядок агентного цикла

1. Владелец описывает цель.
2. erp-architect уточняет недостающие решения, если они критичны.
3. erp-architect формирует точный промт для erp-uiux-designer / erp-coder / erp-qa-tester.
4. Агент выполняет задачу.
5. Агент даёт FINAL REPORT.
6. erp-architect проверяет результат по QA и принимает или отклоняет.
7. При необходимости формируется задача на исправление (второй круг).
8. После принятия результата обновляется следующий шаг.

## Когда нужен владелец

Ставить `NEEDS_OWNER_DECISION`, если нужно решить:
- бизнес-правило;
- роль или права доступа;
- юридическую/налоговую логику;
- структуру документа;
- внешний вид ключевого UI, если нет утверждённого дизайна;
- изменение архитектуры;
- изменение deployment-модели.

## Что нельзя поручать агенту

Запрещены задачи:
- `делай ERP`;
- `сделай красиво`;
- `сделай как обычно`;
- `придумай роли`;
- `настрой бухгалтерию`;
- `создай все таблицы`;
- `сразу делай SUPERADMIN полностью`.

Любая большая задача сначала разбивается на маленькие шаги.


---

## Обязательный UI/UX page-template workflow

Для задач, которые затрагивают интерфейс, агентская сеть работает так:

```text
Владелец проекта
→ erp-architect
→ erp-uiux-designer
→ erp-coder
→ erp-qa-tester
→ erp-architect
→ принято / второй круг
```

`erp-architect` обязан перед каждой задачей определить, затрагивает ли она UI.

### Дизайнер обязателен, если задача затрагивает

- новую страницу;
- существующую страницу;
- новый модуль;
- форму;
- таблицу;
- карточку сущности;
- фильтры;
- поиск;
- навигацию;
- статусы;
- пустые состояния;
- ошибки;
- модалки;
- inspector;
- UX-сценарий;
- адаптив;
- расположение блоков.

### Дизайнер не нужен, если задача чисто техническая

- PDO-обёртка;
- роутер;
- backend-сервис;
- репозиторий;
- конфигурация;
- исправление PHP-ошибки;
- технический рефакторинг без UI;
- документация без изменения UI.

Если есть сомнение — дизайнер подключается.

### Источник истины для UI

Для каждой создаваемой или изменяемой страницы дизайнер обязан создать или обновить MD-шаблон:

```text
docs/ui/pages/[page-name].md
```

Этот файл является источником истины для кодера и QA.

Кодер реализует строго по шаблону.

QA проверяет строго по шаблону.

Архитектор не принимает UI-задачу как DONE, если шаблон отсутствует, устарел или реализация ему не соответствует.

## Актуальная схема 4 KILO-агентов

В проекте используются 4 selectable KILO-агента:

```text
erp-architect
erp-uiux-designer
erp-coder
erp-qa-tester
```

### erp-architect

Главный координатор. Решает маршрут задачи, подключает дизайнера/кодера/QA, принимает результат или запускает второй круг.

### erp-uiux-designer

Обязателен для UI-задач. Создаёт/обновляет MD-шаблоны страниц в `docs/ui/pages/`.

### erp-coder

Пишет код по ТЗ архитектора, архитектурным MD и UI-шаблону страницы. Работает по принципам: think before coding, simplicity first, surgical changes, verifiable execution.

### erp-qa-tester

Проверяет ТЗ, UI-шаблон, архитектуру, runtime, безопасность, MD и логи. Возвращает `ACCEPTED / NEEDS_REWORK / REJECTED / BLOCKED`.
---

## CURRENT UI KIT OVERRIDE — 2026-06-12

UI tasks now use:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

`docs/ui/ERP_UI_MODULE_CATALOG.html` is legacy extraction/reference history only.

Agent handoff rules:

- Designer selects CORE modules and one or more COMPOSITE patterns from `ERP_UI_KIT_CORE.html`.
- Architect checks those IDs before coder handoff.
- Coder blocks unknown/private modules.
- QA checks that all used modules/patterns exist in the core kit or profile MD.
