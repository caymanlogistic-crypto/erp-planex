# ERP PLANEX — QA_CHECKLIST

## Общий QA для агентных задач

Перед статусом DONE проверить:

- [ ] Агент прочитал `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- [ ] Агент прочитал актуальные MD-файлы.
- [ ] Если задача связана с KILO/промтами/ролями, прочитан `docs/ai/AGENT_NETWORK.md`.
- [ ] Если задача связана с UI/дизайн-кодом, прочитан `docs/ui/DESIGN_CODE_INTEGRATION.md`.
- [ ] Изменения соответствуют DECISIONS_LOG.
- [ ] Не добавлены неподтверждённые бизнес-правила.
- [ ] Обновлён AGENT_WORK_LOG.
- [ ] Обновлён PROJECT_STATUS.
- [ ] Обновлены профильные MD-файлы.
- [ ] Проверено, нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- [ ] Если переносимый контекст изменился, `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` обновлён.
- [ ] Если `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` не обновлялся, причина указана в FINAL REPORT.
- [ ] Если агент сомневался, причина решения записана в `AGENT_WORK_LOG.md` или файл обновлён.
- [ ] Указан следующий шаг.
- [ ] Выполнены доступные runtime/синтаксические проверки.
- [ ] Непроверенные части явно зафиксированы.
- [ ] Блокеры записаны как NEEDS_OWNER_DECISION или BLOCKED.

## Windows PowerShell HTTP Runtime Checks

**Обязательный пункт**: HTTP runtime checks on Windows must use PowerShell-safe commands.

Проект работает на Windows. PowerShell 5.1 отличается от Linux shell. `curl` в PowerShell — это alias для `Invoke-WebRequest`, Linux-флаги (`-s`, `-o NUL`, `-w`) ломаются.

Правильные команды для HTTP-проверок на Windows:

```powershell
# HTTP 200
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).StatusCode"

# HTTP 404 с try/catch (без try/catch прервёт проверку как ошибку)
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8015/nonexistent' -UseBasicParsing; $r.StatusCode } catch { $_.Exception.Response.StatusCode.value__ }"

# Проверка контента
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).Content -match 'SUPERADMIN'"
```

Если команда сломалась из-за оболочки — это не ACCEPTED, нужно повторить корректной командой.

Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

---

## QA для кода

- [ ] PHP-файлы проходят `php -l`.
- [ ] SQL не ломает существующую схему.
- [ ] Нет захардкоженных company-specific правил без feature toggle.
- [ ] Нет прямого доступа к чужим компаниям из локальной ERP.
- [ ] SUPERADMIN и локальная ERP разделены.
- [ ] Права доступа проверяются до действия.
- [ ] Feature проверяется до показа страницы/отчёта.
- [ ] Загрузка файлов идёт в `/storage`, не в public.
- [ ] В БД хранятся пути и метаданные, не сами файлы.

## QA для UI

- [ ] Форма понятна пользователю.
- [ ] Labels и обязательные поля очевидны.
- [ ] Ошибки понятны.
- [ ] Нет технических сообщений для обычного пользователя.
- [ ] Таблицы читаемы.
- [ ] Действия не выглядят опасно без подтверждения.
- [ ] UI использует единый дизайн-фундамент, а не разрозненные стили.
- [ ] Дизайн-код не содержит бизнес-логики.


---

## LAYOUT FOUNDATION GATE (ПЕРВАЯ ПРОВЕРКА — до компонентов)

QA обязан пройти LAYOUT FOUNDATION GATE **до** Formal UI QA и component-source audit.

Если хотя бы один пункт не пройден — `Formal UI QA: FAIL` немедленно, без проверки компонентов.

### LAYOUT FOUNDATION GATE checklist

**App Shell:**
- [ ] `.app-shell` имеет `display: grid; grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr`
- [ ] Topbar занимает полную ширину grid (`grid-column: 1/-1`)
- [ ] Sidebar 224px (`--sidebar-w`), topbar 38px (`--topbar-h`)

**Topbar:**
- [ ] Topbar фон: `var(--surface-strong)` (#fefdf8) — СВЕТЛЫЙ, не тёмный
- [ ] Topbar border-bottom: `1px solid var(--line)`
- [ ] В правой части topbar есть user block (имя + роль пользователя), не только debug badge
- [ ] Topbar цвет текста: `var(--text-main)` (тёмный)

**Sidebar navigation:**
- [ ] Sidebar фон: `var(--nav-bg)` (#191816)
- [ ] Sidebar border-right: `1px solid var(--nav-divider)`
- [ ] Nav item height: 34px
- [ ] Nav item font-weight: **600** (ВСЕГДА, не наследуется)
- [ ] Nav item font-size: 12.5px
- [ ] Nav icons: SVG inline 16×16, opacity `var(--nav-icon-op)` = 0.45; НЕ `nav-dot`, НЕ псевдосимволы
- [ ] Nav section label: 9px, 700, uppercase, letter-spacing .12em
- [ ] Nav active state: `.is-active::before` pseudo-element (2px gold left line), НЕ `border-left` на элементе
- [ ] Есть `.nav-spacer` и `.nav-bottom` с нижним блоком (Настройки)

**Sidebar IA:**
- [ ] Навигация структурирована по группам (операционные модули / системная зона)
- [ ] Нет тавтологичных placeholder-пунктов ("Навигация")
- [ ] Операционные модули (Рейсы, Клиенты, Водители) не смешаны с системными (SUPERADMIN)
- [ ] SUPERADMIN изолирован в системной зоне
- [ ] Нижний блок: Настройки в `nav-bottom`

**Источник MASTER:** `C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\TransportERP_MASTER_UI_RULES.md`

```text
Layout Foundation Gate: PASS / FAIL
```

Если FAIL — дальнейшая QA остановлена. Статус фиксируется как `NON-COMPLIANT / NEEDS_UI_REWORK` с указанием источника нормы: `Layout Foundation Gate`.

---

## Formal UI QA (обязательно для всех UI-задач)

QA обязан проверять UI формально. Запрещены субъективные оценки:

- «визуально красиво»;
- «визуально принято»;
- «дизайн выглядит хорошо»;
- «интерфейс выглядит нормально».

QA должен писать только:

```text
Formal UI QA: PASS
Formal UI QA: FAIL
```

### Обязательный Formal UI QA checklist

- [ ] Соответствие MD-шаблону страницы (`docs/ui/pages/[page].md`).
- [ ] Соответствие `DESIGN_CODE_INTEGRATION.md`.
- [ ] Соответствие `ERP_UI_KIT_CORE.html` (PRIMARY).
- [ ] `ERP_UI_MODULE_CATALOG.html` не используется как primary source и не редактировался в ordinary UI work.
- [ ] Соответствие `PAGE_PATTERN.md`.
- [ ] Handoff содержит `CORE modules used` и selected `COMPOSITE pattern`.
- [ ] Все UI-модули из handoff существуют в `docs/ui/ERP_UI_KIT_CORE.html` (PRIMARY) или профильных MD.
- [ ] Unknown UI modules отсутствуют.
- [ ] Нет псевдоиконок: `[=]`, `[#]`, `[~]`, `[v]` и подобных.
- [ ] Нет emoji как иконок.
- [ ] Нет demo-placeholder UI (карточный SaaS-dashboard для admin/settings, большие пустоты, blue/white corporate UI).
- [ ] Нет случайных CSS-классов.
- [ ] Нет inline styles.
- [ ] Нет Bootstrap/Tailwind/Material классов.
- [ ] `border-radius` новых элементов ≤ 4px.
- [ ] `box-shadow blur` новых элементов ≤ 8px.
- [ ] Нет debug badges как основного визуального элемента.
- [ ] VISUAL CHECK URL предоставлен.
- [ ] `Manual owner visual review required: YES` (для новых/изменённых экранов).
- [ ] `Commit allowed before owner visual approval: NO` (для новых/изменённых экранов).
- [ ] Все empty/loading/error states описаны и реализованы.
- [ ] Все опасные действия имеют подтверждение.

---

## Правило фактической проверки файлов

Если QA должен проверить настройки агентов, дизайн-код, UI, код, документацию или соответствие реализации правилам, проверка не делается по памяти или пересказу. Нужно запросить реальные файлы/архив.

---

## Правило DeepSeek/KILO не vision-модель

DeepSeek/KILO агенты не являются vision-моделями. QA не может финально оценивать внешний вид «глазами». QA проверяет только формальное соответствие правилам и MD-документации. Финальную визуальную приёмку UI делает владелец по скриншоту или в браузере.

---

## Правило ручной визуальной приёмки UI

Новые или существенно изменённые UI-экраны нельзя считать финально принятыми и нельзя коммитить как UI-approved, пока владелец не выполнит ручную визуальную проверку.

Для каждого нового или существенно изменённого UI-экрана handoff обязан содержать:
- VISUAL CHECK URL;
- список того, что владелец должен проверить глазами;
- список возможных визуальных блокеров;
- `Manual owner visual review required: YES`;
- `Commit allowed before owner visual approval: NO`.

Если владелец визуально отклоняет экран, статус задачи: `NEEDS_UI_REWORK`, даже если runtime/QA формально PASS.

---

## UI-задачи

Для UI-задач QA обязан проверить:

- [ ] есть актуальный MD-шаблон страницы в `docs/ui/pages/`;
- [ ] реализация соответствует MD-шаблону;
- [ ] кодер не придумал новые layout/classes/states без дизайнера;
- [ ] empty/loading/error states реализованы или явно не требуются;
- [ ] формы, таблицы, кнопки, статусы и inspector соответствуют дизайн-коду;
- [ ] опасные действия имеют подтверждение;
- [ ] **Formal UI QA checklist** пройден (см. выше);
- [ ] `AGENT_WORK_LOG.md` обновлён;
- [ ] проверена необходимость обновить `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

## Blocking acceptance rules

Статус `ACCEPTED` запрещён, если:

- UI-задача выполнена без актуального MD-шаблона страницы в `docs/ui/pages/`;
- кодер реализовал UI не по шаблону дизайнера;
- изменённые PHP-файлы не проверены через `php -l`;
- не выполнен `git status`;
- не обновлён `AGENT_WORK_LOG.md`;
- не обновлён `PROJECT_STATUS.md`, если изменился статус;
- не проверено, нужно ли обновить `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- в код или MD попали `.env`, пароли, токены или секреты;
- нарушен `MODULE_PATTERN.md`;
- SQL написан прямо во view;
- бизнес-логика придумана без решения в `DECISIONS_LOG.md`.

## QA statuses

- `ACCEPTED` — всё соответствует ТЗ, архитектуре, UI-шаблону, проверкам и документации.
- `NEEDS_REWORK` — есть исправимые замечания, нужен второй круг.
- `REJECTED` — результат существенно не соответствует задаче или нарушает архитектуру.
- `BLOCKED` — не хватает решения, файла, UI-шаблона или невозможно выполнить проверку.

---

## Known UI Failure Patterns

Для важных business/admin UI-экранов QA обязан явно проверять известные паттерны провала:

- `UI foundation` появляется на business/admin page, в title/topbar/nav или основном контенте;
- demo/showcase/foundation wording;
- абстрактное `Основное действие` без контекста страницы;
- empty unused workspace или большая пустая зона;
- white/blue corporate color remnants и cold color tokens;
- SaaS card dashboard вместо ERP/admin/settings pattern;
- pseudo-icons `[=]`, `[#]`, `[~]`, `[v]` или emoji как иконки;
- placeholder labels вместо business/admin терминов;
- missing page-specific context;
- page does not communicate its business/admin purpose;
- wrong page title/subtitle/topbar context;
- wrong active sidebar item;
- forbidden classes from handoff;
- missing required sections from handoff;
- форма/таблица выглядят как showcase компонентов, а не как часть конкретного экрана;
- `Formal UI QA PASS` treated as final visual acceptance.
- coder is instructed to look at STYLE ERP instead of a concrete MD handoff;
- STYLE ERP treated as runtime-библиотека or библиотека компонентов.
- `UI modules used` отсутствует в page handoff;
- unknown UI modules используются в handoff или реализации;
- кодер реализовал UI-модуль, которого нет в `ERP_UI_KIT_CORE.html` / профильных MD.

Если найден хотя бы один существенный паттерн из списка, `Formal UI QA: FAIL` или `NEEDS_REWORK`.

QA отчёт по UI обязан содержать:

```text
Formal UI QA: PASS/FAIL
Architect pre-owner review required: YES
Manual owner visual review required: YES
Commit allowed before owner visual approval: NO
```

QA обязан проверить, что page handoff ссылается на `docs/ui/STYLE_ERP_EXTRACTED_RULES.md` как на формализованные правила, а не отправляет кодера в исходную папку STYLE ERP.

QA обязан проверить, что page handoff использует `docs/ui/ERP_UI_KIT_CORE.html` (PRIMARY) и что unknown UI modules отсутствуют. Если найден неизвестный модуль, результат:

```text
Formal UI QA: FAIL
```
---

## CURRENT UI KIT QA OVERRIDE — 2026-06-12

Primary UI catalog for QA:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

Legacy extraction/reference only:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

QA must check:

- handoff contains `CORE modules used`;
- handoff contains selected `COMPOSITE pattern`;
- every `CORE-xx` / `PATTERN-xx` exists in `ERP_UI_KIT_CORE.html` or a profile MD;
- private/page-specific source names are not used as universal modules;
- `ERP_UI_MODULE_CATALOG.html` was not edited for ordinary UI work.
