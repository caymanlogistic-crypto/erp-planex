# ERP PLANEX — TASK_TEMPLATE

## Название задачи

...

## Цель

...

## Контекст

Перед началом прочитать:
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`
- `docs/ai/PROJECT_STATUS.md`
- `docs/ai/DECISIONS_LOG.md`
- `docs/ai/AGENT_WORK_LOG.md`
- `docs/ai/AGENT_NETWORK.md`, если задача связана с KILO, ролями, промтами или агентным циклом
- `docs/ui/DESIGN_CODE_INTEGRATION.md`, если задача связана с UI или дизайн-кодом
- `docs/ui/ERP_UI_KIT_CORE.html`, если задача связана с UI или дизайн-кодом (PRIMARY)
- `docs/ui/ERP_UI_MODULE_CATALOG.html`, если задача связана с UI или дизайн-кодом (legacy/reference)
- профильные MD-файлы

## Что нужно сделать

1. ...
2. ...
3. ...

## WINDOWS COMMAND RULES

Проект работает на Windows. Все shell-команды выполняются в **Windows PowerShell 5.1**, который не является Linux shell.

Обязательные правила:
- **Не использовать Linux curl синтаксис в PowerShell** (curl — это alias для Invoke-WebRequest, флаги `-s`, `-o NUL`, `-w` несовместимы).
- Для HTTP-проверок использовать `Invoke-WebRequest` или `cmd.exe /c curl.exe`.
- Для проверки 4xx/5xx использовать try/catch, чтобы PowerShell не прерывал проверку как ошибку.
- Если Git в PowerShell даёт мусорный/битый вывод, использовать `cmd.exe /c "git ..."`.
- Shell errors из-за несовместимости с PowerShell — это tooling/runtime issues, не app failures.
- Сломанная команда — не ACCEPTED, нужно повторить корректной.
- Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

## Что запрещено

- Не придумывать неподтверждённые бизнес-правила.
- Не менять архитектуру без записи решения.
- Не писать код без обновления документации.
- Не оставлять устаревшим `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- Не запускать KILO с размытой задачей без цели, запретов и проверок.
- Не интегрировать дизайн-код без единого UI-фундамента и документации.
- Не завершать без проверок и лога.
- Не принимать UI-экран как финально approved без ручной визуальной проверки владельца.
- Не коммитить UI-экран до получения Manual owner visual approval.
- Не проверять файлы/UI/код по памяти или пересказу — только через фактические файлы.

## Правило фактической проверки файлов

Если агент должен проверить настройки агентов, дизайн-код, UI, код, документацию или соответствие реализации правилам, проверка не делается по памяти или пересказу. Нужно запросить реальные файлы/архив и дать владельцу готовую cmd/PowerShell-команду для сборки ZIP из нужных файлов.

## Правило DeepSeek/KILO не vision-модель

DeepSeek/KILO агенты не являются vision-моделями. Они не могут финально оценивать внешний вид «глазами». Финальную визуальную приёмку UI делает владелец по скриншоту или в браузере.

## Правило ручной визуальной приёмки UI

Новые или существенно изменённые UI-экраны нельзя считать финально принятыми и нельзя коммитить как UI-approved, пока владелец не выполнит ручную визуальную проверку.

Для каждого нового или существенно изменённого UI-экрана handoff обязан содержать:
- VISUAL CHECK URL;
- список того, что владелец должен проверить глазами;
- список возможных визуальных блокеров;
- `Manual owner visual review required: YES`;
- `Commit allowed before owner visual approval: NO`.

Если владелец визуально отклоняет экран, статус задачи: `NEEDS_UI_REWORK`.

## Правило качества handoff дизайнера

Дизайнер обязан выдавать handoff так, чтобы кодер не искал примеры и не придумывал. В handoff обязательно: exact route/view, layout pattern, exact text, exact components, exact classes, prohibited elements, states, owner visual check, visual blockers, runtime URL, acceptance checklist. Если handoff допускает неоднозначность, задача дизайнера не DONE.

## Formal UI QA

QA обязан проверять UI формально. Запрещены субъективные оценки: «визуально красиво», «визуально принято», «дизайн выглядит хорошо». QA должен писать: `Formal UI QA: PASS/FAIL`, `Manual owner visual review required: YES/NO`, `Commit allowed before owner visual approval: YES/NO`.

## UI Module Catalog Rule

Для UI-задач дизайнер и архитектор обязаны использовать:

```text
docs/ui/ERP_UI_KIT_CORE.html  ← PRIMARY compact working UI-kit
```

Legacy extraction/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

Page handoff обязан содержать:

- `CORE modules used` — номера и названия CORE-модулей;
- `COMPOSITE pattern selected` — выбранный композитный паттерн;
- `MODULE USAGE DECISIONS` — почему выбран модуль, почему не выбран другой, где расположен блок, роль блока, primary/secondary actions, disabled elements, states.

Если подходящего модуля нет:

```text
BLOCKED: NEEDS_UI_MODULE_EXPANSION
```

Если кодер получил unknown UI module:

```text
BLOCKED: UNKNOWN_UI_MODULE
```

## Ожидаемый результат

...

## Проверки

- ...
- ...

## Обязательное завершение

В конце обновить:
- `docs/ai/AGENT_WORK_LOG.md`;
- `docs/ai/PROJECT_STATUS.md`;
- профильные MD-файлы;
- `docs/ai/DECISIONS_LOG.md`, если принято решение.
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`, если изменился переносимый контекст проекта.

Перед FINAL REPORT обязательно проверить:
- нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- если нужно — обновить;
- если не нужно — указать причину в FINAL REPORT;
- если есть сомнение — обновить файл или записать причину в AGENT_WORK_LOG.md.

Финальный отчёт должен содержать:
- статус;
- что сделано;
- что изменено;
- что проверено;
- что не проверено;
- риски;
- следующий шаг;
- обновлялся ли `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`, а если нет — почему.


---

## Дополнение для UI-задач

Если задача затрагивает интерфейс, перед кодером должен быть `erp-uiux-designer`.

В задаче нужно указать:

- какой MD-шаблон страницы создать или обновить в `docs/ui/pages/`;
- какие UI-документы прочитать;
- какие состояния и сценарии предусмотреть;
- что кодеру запрещено менять без дизайнера;
- что QA должен проверить по шаблону.

## Маршрутизация задачи архитектором

Перед выдачей задачи архитектор обязан определить маршрут:

```text
UI-задача:
erp-architect → erp-uiux-designer → erp-coder → erp-qa-tester → erp-architect

Техническая задача без UI:
erp-architect → erp-coder → erp-qa-tester → erp-architect
```

Если задача затрагивает UI, в задаче кодеру обязательно указать актуальный MD-шаблон страницы:

```text
docs/ui/pages/[page-name].md
```

Если такого файла нет, задача сначала идёт дизайнеру.

## UI PRODUCTION LOOP

Для важных UI-задач в задаче обязательно заполнить:

- UI production loop required: YES/NO
- Designer handoff file: `docs/ui/pages/[page-name].md`
- UI Kit Core used: YES/NO (`docs/ui/ERP_UI_KIT_CORE.html`)
- UI Module Catalog used: YES/NO (`docs/ui/ERP_UI_MODULE_CATALOG.html` — legacy/reference)
- CORE modules used:
- Missing UI modules: none / description
- Module expansion required: YES/NO
- Architect handoff review: PASS / NEEDS_DESIGNER_REWORK / NOT_DONE
- Coder implementation files:
- Formal UI QA result: PASS / FAIL / NOT_DONE
- Architect pre-owner review: PASS / NEEDS_REPEAT_CYCLE / NOT_DONE
- Repeat cycle required: YES/NO
- VISUAL CHECK URL:
- Manual owner visual review required: YES/NO
- Commit allowed before owner visual approval: YES/NO

Для важных UI-задач кодер не стартует, пока `Architect handoff review` не равен `PASS`.

Если handoff слабый, противоречивый, устаревший или допускает разные трактовки, архитектор ставит `NEEDS_DESIGNER_REWORK`, а кодеру задача не передаётся.

Если кодер получил слабый handoff, он обязан вернуть:

```text
BLOCKED: NEEDS_DESIGNER_REWORK
```

`Formal UI QA: PASS` не является visual acceptance. После QA обязателен `Architect pre-owner review`.

Запрещено ставить кодеру задачу "смотри STYLE ERP". Если нужное правило есть только в STYLE ERP, задача сначала возвращается дизайнеру/архитектору для формализации в MD.
---

## CURRENT UI KIT OVERRIDE — 2026-06-12

For UI/design-code tasks use the primary compact UI-kit:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

Legacy extraction/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

Task fields for UI work:

- UI Kit Core used: YES/NO (`docs/ui/ERP_UI_KIT_CORE.html`)
- Legacy catalog touched: NO (`docs/ui/ERP_UI_MODULE_CATALOG.html`)
- CORE modules used:
- COMPOSITE pattern selected:
- MODULE USAGE DECISIONS:

STYLE ERP extracted rules: `docs/ui/STYLE_ERP_EXTRACTED_RULES.md` (reference, not runtime library).
