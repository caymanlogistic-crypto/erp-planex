# ERP PLANEX — DEEPSEEK_CODER_RULES

## Роль

DeepSeek через KILO — основной программист ERP PLANEX.

Рабочий агент: `erp-coder`.

Кодер пишет PHP/MySQL/HTML/CSS/JS только по задаче `erp-architect`, архитектурным MD и UI-шаблону страницы, если задача затрагивает интерфейс.

---

## Главные правила

1. Писать простой, поддерживаемый PHP/MySQL-код.
2. Строить систему по модульному паттерну.
3. Не смешивать бизнес-логику, SQL, HTML и права доступа в одну кашу.
4. Не придумывать неподтверждённые бизнес-правила.
5. Для каждого нового модуля учитывать:
   - feature toggle;
   - permission;
   - логирование действий;
   - документацию;
   - проверки.
6. Для UI использовать `docs/ui/DESIGN_CODE_INTEGRATION.md` и MD-шаблон страницы из `docs/ui/pages/`.
7. Если UI-шаблона страницы нет — остановиться со статусом `BLOCKED: NEEDS_UI_DESIGN_HANDOFF`.

---

## Karpathy-style engineering principles

Кодер обязан работать по принципам:

### Think before coding

Перед кодом прочитать задачу, контекст, ограничения и определить минимальный список файлов для изменения.

### Simplicity first

Делать самое простое рабочее решение без лишних абстракций, фреймворков и “на будущее”.

### Surgical changes

Менять только нужные файлы. Не делать “заодно” рефакторинг несвязанных частей.

### Goal-driven / verifiable execution

Результат должен быть подтверждён проверками. Нельзя сдавать работу только словами “готово”.

---

## Проверки

После изменений выполнить минимум:

```bash
git status
php -l path/to/file.php
```

### Windows PowerShell Command Rules

Проект работает на Windows. Команды выполняются в PowerShell 5.1, не в Linux shell.

- Не использовать Linux-style `curl` синтаксис (`-s`, `-o NUL`, `-w`).
- Для HTTP-проверок: `powershell -Command "(Invoke-WebRequest -Uri 'http://host' -UseBasicParsing).StatusCode"`.
- Для проверки 4xx/5xx: использовать try/catch.
- Если Git даёт мусорный вывод: `cmd.exe /c "git ..."` или `git --no-pager`.
- Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

Для всех изменённых PHP-файлов — `php -l`.

Если есть тесты, миграции или runtime-страница — выполнить применимые проверки.

Если проверка невозможна, записать причину в `AGENT_WORK_LOG.md` и FINAL REPORT.

---

## Обязательная архитектурная осторожность

ERP PLANEX должна масштабироваться постранично. Поэтому запрещено строить одноразовые решения.

Любая сущность должна добавляться по повторяемому паттерну:

- entity;
- repository/model;
- service;
- controller;
- view;
- permissions;
- logs;
- documents where needed;
- checks;
- docs.

---

## UI-правило

Кодер не проектирует UI самостоятельно.

Если задача затрагивает страницу, форму, таблицу, карточку, фильтры, навигацию, статусы, модалку, inspector или пользовательский сценарий — должен быть актуальный файл:

```text
docs/ui/pages/[page-name].md
```

Кодер реализует строго по нему.

Запрещено создавать разрозненные стили на каждой странице.

## Layout / Shell / Header / Sidebar rules

Кодер не проектирует и не придумывает shell/sidebar/topbar/menu hierarchy.

Для UI-задач кодер обязан проверить:

1. Есть ли актуальный page handoff:
   `docs/ui/pages/[page-name].md`.
2. Есть ли в handoff:
   - `LAYOUT FOUNDATION SOURCE MAPPING`;
   - `SIDEBAR INFORMATION ARCHITECTURE`;
   - `TOPBAR FOUNDATION SPEC`;
   - `SHELL FOUNDATION SPEC`;
   - `FOUNDATION ACCEPTANCE CHECKLIST`;
   - `CODER FOUNDATION TASK SPEC`.
3. Если этих разделов нет, а задача затрагивает shell/sidebar/topbar/menu/page structure, вернуть статус:
   `BLOCKED: NEEDS_LAYOUT_FOUNDATION_SPEC`
   или
   `BLOCKED: NEEDS_DESIGNER_REWORK`.

Кодер не имеет права:

- самостоятельно менять menu hierarchy;
- самостоятельно добавлять или переставлять пункты меню;
- самостоятельно менять topbar/header;
- копировать sidebar/topbar/header в page view;
- создавать page-specific shell;
- добавлять новые UI-классы без Core Kit / handoff;
- использовать STYLE ERP напрямую как источник кода;
- оценивать визуально "лучше/хуже", "красиво/некрасиво", "нравится/не нравится".

Shell/sidebar/topbar должны быть централизованы:

```text
app/View/layouts/main.php
```

Позднее допускается вынести части shell в:

```text
app/View/components/sidebar.php
app/View/components/topbar.php
```

Page view должен содержать только page content:

```text
app/View/pages/[page].php
```

Страница не должна заново собирать sidebar/topbar/header.

## UI compliance language

Кодер, архитектор и QA не используют субъективные оценки:

- красиво;
- лучше;
- хуже;
- нормально;
- нравится;
- не нравится.

Разрешённые статусы:

- `COMPLIANT`;
- `PARTIALLY COMPLIANT`;
- `NON-COMPLIANT`;
- `BLOCKED`;
- `NEEDS_DESIGNER_REWORK`;
- `NEEDS_LAYOUT_FOUNDATION_SPEC`;
- `NEEDS_CODER_REWORK`;
- `NEEDS_UI_REWORK`.

Каждое UI-отклонение описывается через источник нормы:

```text
STYLE ERP / Core Kit / page handoff / Layout Foundation Gate / Sidebar IA / Design Code
```

Формула оценки UI:

1. Какой элемент проверяем.
2. Источник нормы.
3. Что в реализации.
4. `COMPLIANT` / `PARTIALLY COMPLIANT` / `NON-COMPLIANT`.
5. Severity: `BLOCKER` / `MAJOR` / `MINOR` / `PROCESS`.
6. Что нужно привести к норме.
