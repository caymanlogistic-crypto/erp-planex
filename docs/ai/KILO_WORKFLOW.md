# ERP PLANEX — KILO_WORKFLOW

## Назначение

KILO + DeepSeek используется как основной исполнитель кода и технических задач ERP PLANEX.

KILO должен работать строго по MD-документации проекта.

Текущий режим: KILO используется через `erp-architect`, но UI-задачи передаются кодеру только после accepted designer handoff по Core Kit и page MD.

STYLE ERP изучается Codex GPT / архитектором / дизайнером и формализуется в MD. Кодер работает только по MD/handoff, не по исходной папке STYLE ERP.

Текущий статус `/superadmin`: `SUPERADMIN_COMPONENT_REWORK_ACCEPTED` (2026-06-14). Component rework after Chief Designer audit: CSS gaps closed, shared statusBadge(), row actions classified, danger zone pattern applied, crews display fixed. Next step — Owner visual review.

Первичный рабочий UI-kit — `docs/ui/ERP_UI_KIT_CORE.html` (PRIMARY compact working UI-kit). Дизайнер работает только с CORE modules и COMPOSITE patterns из Core Kit. Если нужного CORE-модуля нет, сначала расширяется Core Kit, затем продолжается page handoff.

`docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy extraction/reference history only; не основной рабочий каталог дизайнера.

В проекте настроены 4 проектных KILO-режима (агента):
- `erp-architect` — главный координатор (primary, default_agent);
- `erp-uiux-designer` — UI/UX-дизайнер (primary/selectable);
- `erp-coder` — исполнитель разработки (primary/selectable);
- `erp-qa-tester` — тестировщик (primary/selectable).

Важно: все 4 проектных агента должны быть видны в списке выбора KILO. Для этого в `.kilo/agent/*.md` используется `mode: primary`.

Определения агентов находятся в `.kilo/agent/`. Главный режим — `erp-architect`.

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи дизайнеру, кодеру и тестировщику через агентскую сеть.

---

## Перед началом любой задачи KILO обязан

1. Прочитать:
   - `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
   - `docs/ai/PROJECT_STATUS.md`;
   - `docs/ai/DECISIONS_LOG.md`;
   - `docs/ai/AGENT_WORK_LOG.md`;
   - `docs/ai/AGENT_NETWORK.md`, если задача связана с ролями, промтом или агентным циклом;
   - профильные документы из `docs/architecture`, `docs/business`, `docs/ui`.

2. Понять текущий этап проекта.

3. Не начинать писать код, если задача архитектурно неясна.

4. Если данных недостаточно — поставить статус `NEEDS_OWNER_DECISION`.

---

## Запрещено

- Придумывать бизнес-логику.
- Менять архитектурные решения без записи в `DECISIONS_LOG.md`.
- Создавать страницу без feature_code и permission_code.
- Добавлять модуль без обновления MD.
- Завершать задачу без логов.
- Игнорировать проверки.
- Смешивать SUPERADMIN и локальную ERP без явного архитектурного решения.

---

## После выполнения задачи KILO обязан

1. Обновить `AGENT_WORK_LOG.md`.
2. Обновить `PROJECT_STATUS.md`.
3. Обновить профильные MD-файлы.
4. Выполнить доступные проверки.
5. Записать результаты проверок.
6. Указать следующий шаг.
7. Проверить и при необходимости обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

---

## Агентская сеть

Роли, промты и порядок работы KILO описаны в `docs/ai/AGENT_NETWORK.md`.

Запрещено запускать KILO с размытыми задачами. Любая задача должна иметь цель, список файлов для чтения, конкретные действия, запреты, проверки, список MD для обновления, указание по commit и формат FINAL REPORT.

---

## Модель работы

ERP PLANEX развивается постранично и модульно.

Каждый новый модуль должен иметь:

```text
feature_code
permission_code
routes
controller
service
repository/model
views
menu item
logs
migrations
checks
MD documentation
```


---

## UI-задачи через дизайнера

Перед передачей задачи кодеру `erp-architect` обязан определить, затрагивает ли задача интерфейс.

Если UI затрагивается, порядок обязателен:

```text
erp-architect
→ erp-uiux-designer
→ erp-coder
→ erp-qa-tester
→ erp-architect
```

`erp-uiux-designer` создаёт или обновляет MD-шаблон страницы в:

```text
docs/ui/pages/
```

`erp-coder` реализует только по актуальному MD-шаблону страницы.

`erp-qa-tester` проверяет соответствие реализации этому шаблону.

`erp-architect` не принимает UI-задачу без:

- актуального MD-шаблона страницы;
- реализации по шаблону;
- QA-проверки по шаблону;
- обновлённого `AGENT_WORK_LOG.md`;
- проверки необходимости обновить `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

## Актуальный workflow после настройки 4 агентов

### UI-задача

```text
Владелец
→ erp-architect
→ erp-uiux-designer создаёт/обновляет docs/ui/pages/[page-name].md
→ erp-coder реализует строго по шаблону
→ erp-qa-tester проверяет по шаблону
→ erp-architect принимает или запускает второй круг
```

### Техническая задача без UI

```text
Владелец
→ erp-architect
→ erp-coder
→ erp-qa-tester
→ erp-architect
```

### Правило

Если есть сомнение, затрагивает ли задача UI, дизайнер подключается.

---

## UI PRODUCTION LOOP

Важные UI-задачи не являются обычными coding-задачами.

Важная UI-задача — новый или существенно изменяемый бизнес/admin экран, форма, таблица, навигация, inspector, report/dashboard или любой экран, который должен соответствовать master UI-kit и проходить owner visual review.

Обязательный цикл:

1. **Architect intake** — `erp-architect` определяет, что задача UI, фиксирует цель, ограничения, master UI-kit, запрещённые паттерны и нужный page handoff.
2. **Designer production handoff** — `erp-uiux-designer` создаёт/обновляет `docs/ui/pages/[page].md` как production-grade visual handoff: current diagnosis, target result, exact layout, typography, spacing, tokens, sections, classes, forbidden patterns, checklists.
3. **UI module selection** — handoff содержит `CORE modules used`, selected `COMPOSITE pattern`, и `MODULE USAGE DECISIONS` по `docs/ui/ERP_UI_KIT_CORE.html` (PRIMARY). `docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy/reference only.
4. **Architect handoff review** — `erp-architect` проверяет handoff до кодера. Если handoff общий, противоречивый, устаревший, содержит `REJECTED` как актуальный статус, допускает разные трактовки или содержит unknown UI module, статус `NEEDS_DESIGNER_REWORK`; кодеру не передавать.
5. **Coder implementation** — `erp-coder` реализует строго по принятому handoff. Если handoff слабый или противоречивый, кодер возвращает `BLOCKED: NEEDS_DESIGNER_REWORK`. Если handoff использует неизвестный UI-модуль, кодер возвращает `BLOCKED: UNKNOWN_UI_MODULE`.
6. **Formal QA** — `erp-qa-tester` проверяет по handoff, UI module catalog, design-code, PAGE_PATTERN, runtime, forbidden patterns. QA пишет `Formal UI QA: PASS/FAIL`.
7. **Architect pre-owner review** — `erp-architect` после QA проверяет, устранена ли исходная визуальная проблема и нет ли demo/foundation/showcase/SaaS-dashboard признаков. `Formal UI QA: PASS` не является visual acceptance.
8. **Repeat cycle if not enough** — если результат не дотягивает до master UI-kit, архитектор возвращает задачу в цикл designer/coder/QA без показа владельцу как готовой.
9. **Owner visual review** — владелец получает VISUAL CHECK URL и checklist только после того, как агентская цепочка сама довела экран максимально близко к master UI-kit.
10. **Commit only after approval** — commit UI-экрана разрешён только после явного owner approval.

Минимальные обязательные строки для UI QA:

```text
Formal UI QA: PASS/FAIL
Architect pre-owner review required: YES
Manual owner visual review required: YES
Commit allowed before owner visual approval: NO
```

## STYLE ERP Formalization Rule

Визуальные образцы находятся в:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\
```

Они формализованы в:

```text
docs/ui/STYLE_ERP_EXTRACTED_RULES.md
```

STYLE ERP не является runtime-библиотекой или библиотекой компонентов. Нельзя ставить кодеру задачу открыть STYLE ERP и выбрать оттуда блок. Все значимые правила должны быть перенесены в MD и page handoff до кодера.

## UI Kit Core Rule (Primary UI Source)

`docs/ui/ERP_UI_KIT_CORE.html` — PRIMARY compact working UI-kit ERP PLANEX. Основной рабочий источник для дизайнера, архитектора, кодера и QA.

`docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy extraction/reference history only. Не является основным рабочим каталогом дизайнера. Не используется кодером как основание для самостоятельных UI-решений.

Правила:

- `erp-uiux-designer` проектирует страницу только из CORE modules и COMPOSITE patterns из `ERP_UI_KIT_CORE.html`.
- Page handoff обязан содержать `CORE modules used`, selected `COMPOSITE pattern`, и `MODULE USAGE DECISIONS`.
- Если нужного CORE-модуля нет, дизайнер возвращает `BLOCKED: NEEDS_UI_MODULE_EXPANSION`.
- `erp-architect` не передаёт кодеру handoff с unknown UI module.
- `erp-coder` не реализует неизвестные модули и возвращает `BLOCKED: UNKNOWN_UI_MODULE`.
- `erp-qa-tester` проверяет, что все модули перечислены и существуют в `ERP_UI_KIT_CORE.html` / профильных MD; unknown module = `Formal UI QA: FAIL`.
- KILO не используется для production UI до проверки владельцем/ChatGPT обновлённых MD/agent-файлов.
---

## CURRENT UI KIT OVERRIDE — 2026-06-12

Primary working UI catalog:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

Legacy extraction/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

Rules:

- `erp-uiux-designer` must use `ERP_UI_KIT_CORE.html` as the primary source.
- Page handoff must contain `CORE modules used`, selected `COMPOSITE pattern`, and `MODULE USAGE DECISIONS`.
- `ERP_UI_MODULE_CATALOG.html` is not the primary designer catalog and must not be updated/overwritten in ordinary UI work.
- Private/page-specific source names such as `Drivers bottom editor`, `Drivers selected row`, `Drivers right inspector`, `Drivers table card`, `Drivers filters bar`, `Drivers page header` are not universal modules.
