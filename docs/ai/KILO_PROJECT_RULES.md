# ERP PLANEX — KILO_PROJECT_RULES

Краткие правила для KILO + DeepSeek в проекте ERP PLANEX.

## Рабочая папка

```
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\
```

## Основной стек

PHP / MySQL

## Запреты

- **Не писать бизнес-код** без отдельного задания.
- **Не создавать таблицы БД / миграции** без отдельного задания.
- **Не создавать `public/index.php`** без отдельного задания.
- **Не создавать `config/database.php`** без отдельного задания.
- **Не начинать SUPERADMIN** без отдельного задания.
- **Не менять утверждённую архитектуру** без записи нового решения в `DECISIONS_LOG.md`.
- **Не придумывать** бизнес-правила, роли, юридические требования, бухгалтерскую логику.
- **Не удалять** существующие MD-файлы документации.
- **Не делать commit** без явного разрешения владельца.
- **Не оставлять устаревшим** `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

## Обязанности

- **Логировать все действия** в `docs/ai/AGENT_WORK_LOG.md`.
- **Перед началом работы читать** `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` как главный переносимый контекст проекта.
- **Читать** `docs/ai/AGENT_NETWORK.md`, если задача связана с ролями, промтами, координацией агентов или порядком работы KILO.
- **Обновлять статус** проекта в `docs/ai/PROJECT_STATUS.md`.
- **Фиксировать решения** в `docs/ai/DECISIONS_LOG.md`.
- **Проверять перед завершением задачи**, нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- **Обновлять переносимый контекст**, если изменился этап, фокус, следующий шаг, архитектура, бизнес-правило, структура папок, ключевые файлы, git hash, правила агентов, правила логирования, модель БД, роли, feature toggles, deployment, запреты, проверки, важный результат, статус SUPERADMIN, статус локальной ERP, статус PHP-каркаса или любое решение владельца проекта.
- **Удалять или заменять устаревшую информацию** в `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`, чтобы следующий ChatGPT-чат не получил старый контекст.
- **Завершать задачу** только через FINAL REPORT со статусом.
- **Выполнять проверки** после изменений (`git status`, `php -l` при наличии PHP).
- Если проверка невозможна — записать причину в лог.
- Если `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` не обновлялся — указать причину в FINAL REPORT. Если есть сомнение, обновлять файл или нет, обновить его или записать в `AGENT_WORK_LOG.md`, почему обновление не требуется.
- Для UI и дизайн-кода читать `docs/ui/DESIGN_CODE_INTEGRATION.md` и не создавать разрозненные стили.

## Windows PowerShell Command Rules

Проект работает на Windows. Агенты выполняют команды через Windows PowerShell 5.1, который отличается от Linux shell.

- **Запрещено** использовать Linux-style `curl` синтаксис в PowerShell (`curl -s -o NUL -w` и т.д.).
- Для HTTP-проверок использовать `Invoke-WebRequest` или `cmd.exe /c curl.exe`.
- Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

## При нехватке данных

- Остановиться.
- Явно указать, каких данных не хватает.
- Поставить статус `NEEDS_OWNER_DECISION`.
- Записать вопрос в `AGENT_WORK_LOG.md`.

## Статусы задач

- `DONE` — задача полностью завершена.
- `PARTIAL` — задача выполнена частично.
- `BLOCKED` — задача заблокирована.
- `NEEDS_OWNER_DECISION` — требуется решение владельца.
- `NEEDS_QA` — требуется проверка качества.
- `NEEDS_REWORK` — требуется переработка.

## Правило UI-шаблонов страниц

Если задача затрагивает страницу, форму, таблицу, карточку, навигацию, фильтры, статусы, модалку, inspector или UX-сценарий, сначала должен быть актуальный MD-шаблон страницы:

```text
docs/ui/pages/[page-name].md
```

Кодер не имеет права реализовывать UI без такого шаблона.

QA не имеет права принимать UI-задачу без проверки по такому шаблону.

---

## Правило фактической проверки файлов

Если агент должен проверить настройки агентов, дизайн-код, UI, код, документацию или соответствие реализации правилам, проверка не делается по памяти или пересказу. Нужно запросить реальные файлы/архив и дать владельцу готовую cmd/PowerShell-команду для сборки ZIP.

---

## Правило DeepSeek/KILO не vision-модель

DeepSeek/KILO агенты не являются vision-моделями. Они не могут финально оценивать внешний вид «глазами». Агенты проверяют только формальное соответствие MD-шаблонам, правилам дизайн-кода, CSS-классам, DOM/HTML-структуре, отсутствию запрещённых элементов и runtime-метрикам. Финальную визуальную приёмку UI делает владелец по скриншоту или в браузере.

---

## Правило ручной визуальной приёмки UI

Новые или существенно изменённые UI-экраны нельзя считать финально принятыми и нельзя коммитить как UI-approved, пока владелец не выполнит ручную визуальную проверку.

Для каждого нового или существенно изменённого UI-экрана агент обязан выдать:
- VISUAL CHECK URL;
- список того, что владелец должен проверить глазами;
- список возможных визуальных блокеров;
- `Manual owner visual review required: YES`;
- `Commit allowed before owner visual approval: NO`.

Если владелец визуально отклоняет экран, статус задачи: `NEEDS_UI_REWORK`, даже если runtime/QA формально PASS.

---

## Правило запрета demo-placeholder UI

UI handoff и реализация не должны допускать:
- demo-placeholder вид;
- псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`;
- emoji/символы как временные иконки;
- карточный SaaS-dashboard там, где нужна ERP/settings/admin страница;
- большие пустоты;
- blue/white corporate UI;
- случайные цвета;
- случайные CSS-классы;
- `border-radius > 4px` (новые элементы);
- `box-shadow blur > 8px` (новые элементы);
- inline styles;
- Bootstrap/Tailwind/Material классы.

---

## Правило SUPERADMIN UI

SUPERADMIN — центральная административная панель ERP PLANEX. Использует строгий admin/settings pattern:
- sidebar 224px с текстовой навигацией;
- topbar 38px;
- page-head;
- settings/admin sections;
- reserved modules as system sections;
- tables/forms/panels когда появляются данные;
- **запрещены** KPI dashboard cards (если явно не approved);
- **запрещены** псевдоиконки;
- **запрещены** debug badges как основной визуальный элемент.

---

## Правило UI Production Loop

UI-задачи не являются обычными coding-задачами.

Важный UI нельзя отдавать кодеру без production-grade handoff от `erp-uiux-designer`.

Перед кодером `erp-architect` обязан выполнить handoff review. Если handoff общий, противоречивый, устаревший, содержит `REJECTED` как актуальный статус или допускает разные трактовки, статус `NEEDS_DESIGNER_REWORK`, кодеру не передавать.

`erp-coder` обязан вернуть `BLOCKED: NEEDS_DESIGNER_REWORK`, если handoff слабый, отсутствует, противоречит design-code/PAGE_PATTERN или не указывает точные sections/classes/tokens.

`Formal UI QA: PASS` не является visual acceptance.

После QA `erp-architect` обязан выполнить pre-owner review и удерживать задачу в loop до соответствия master UI-kit. Если экран всё ещё похож на demo/foundation/showcase/SaaS-dashboard или не сообщает конкретное business/admin назначение, запускать повторный цикл designer/coder/QA.

Owner visual review и commit разрешены только после того, как агентская цепочка сама довела экран максимально близко к master UI-kit. Commit UI — только после явного owner approval.

---

## Правило STYLE ERP

Папка `C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\` является набором визуальных образцов, а не runtime-библиотекой или библиотекой компонентов.

STYLE ERP изучается Codex GPT / архитектором / дизайнером и формализуется в:

```text
docs/ui/STYLE_ERP_EXTRACTED_RULES.md
docs/ui/ERP_UI_KIT_CORE.html
```

Кодеру запрещено выбирать дизайн из STYLE ERP, копировать HTML/CSS из STYLE ERP или получать задачу "посмотри STYLE ERP". Если handoff требует этого, кодер возвращает `BLOCKED: NEEDS_DESIGNER_REWORK`.

KILO используется через `erp-architect`, но UI-задачи передаются кодеру только после accepted handoff по Core Kit и page MD. QA и commit для `/superadmin` запрещены до Manual owner visual approval.

## Правило UI Kit Core

`docs/ui/ERP_UI_KIT_CORE.html` — основной рабочий UI-kit ERP PLANEX.

- Дизайнер проектирует страницу только из CORE modules и COMPOSITE patterns, описанных в Core Kit и профильных MD.
- Дизайнер обязан перечислять `CORE modules used` и selected `COMPOSITE pattern` в page handoff.
- Дизайнер обязан заполнить `MODULE USAGE DECISIONS` с причинами выбора и отказа от альтернатив.
- Если модуля нет, дизайнер возвращает `BLOCKED: NEEDS_UI_MODULE_EXPANSION`.
- Архитектор не передаёт кодеру handoff с unknown UI module.
- Кодер реализует только формализованные UI-модули.
- Если handoff использует неизвестный модуль, кодер возвращает `BLOCKED: UNKNOWN_UI_MODULE`.
- QA проверяет, что все UI-модули перечислены и существуют в `ERP_UI_KIT_CORE.html` / профильных MD.
- Unknown UI module = `Formal UI QA: FAIL`.
- `docs/ui/ERP_UI_MODULE_CATALOG.html` остаётся legacy extraction/reference history only и не является основным рабочим каталогом.
- `/superadmin` остаётся `PARTIALLY COMPLIANT / NEEDS_UI_REWORK` до точечного UI rework и Manual owner visual approval.
---

## CURRENT UI KIT OVERRIDE — 2026-06-12

`docs/ui/ERP_UI_KIT_CORE.html` is the primary compact working UI-kit for ERP PLANEX.

`docs/ui/ERP_UI_MODULE_CATALOG.html` is legacy extraction/reference history only.

Designer handoff must use `CORE modules used`, selected `COMPOSITE pattern`, and `MODULE USAGE DECISIONS`. Missing module means `BLOCKED: NEEDS_UI_MODULE_EXPANSION`; coder unknown module means `BLOCKED: UNKNOWN_UI_MODULE`; QA fails unknown/private modules.
