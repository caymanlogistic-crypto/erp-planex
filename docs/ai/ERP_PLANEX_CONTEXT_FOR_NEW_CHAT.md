# ERP PLANEX — переносимый контекст для нового ChatGPT-чата

## Назначение этого файла

Этот файл — главный переносимый контекст проекта ERP PLANEX.

Если текущий ChatGPT-чат станет длинным, начнёт тормозить или потребуется передать работу другому агенту, владелец проекта должен открыть новый чат и вставить содержимое этого файла. Новый агент обязан после чтения только этого файла понять проект, текущий статус, архитектуру, правила работы, запреты и следующий шаг.

Файл не является разовым отчётом. Это живой актуальный контекст проекта.

## Рабочая папка

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\
```

## Роль ChatGPT и агентов

ERP PLANEX разрабатывается ИИ-агентами.

Основной исполнитель кода и технических задач: KILO + DeepSeek.

В проекте настроены 4 проектных KILO-режима (агента):
- `erp-architect` (primary, default_agent) — главный координатор;
- `erp-uiux-designer` (subagent) — UI/UX-дизайнер;
- `erp-coder` (subagent) — исполнитель разработки;
- `erp-qa-tester` (subagent) — тестировщик.

Определения агентов: `.kilo/agent/*.md`. Конфигурация: `kilo.jsonc` (`default_agent: erp-architect`).

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи дизайнеру, кодеру и тестировщику через `task` tool.

Главное правило: не придумывать неизвестные детали. Если данных не хватает, агент обязан задать вопрос владельцу или поставить статус `NEEDS_OWNER_DECISION`.

## ОБЯЗАТЕЛЬНОЕ ПРАВИЛО ОБНОВЛЕНИЯ ЭТОГО ФАЙЛА

Каждый агент после выполнения любой задачи обязан проверить, нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

Этот файл обязательно обновляется, если изменилось хотя бы одно из следующего:
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

Если агент обновил `PROJECT_STATUS.md`, `DECISIONS_LOG.md`, `AGENT_WORK_LOG.md` или профильные архитектурные, бизнесовые или UI MD-файлы, он обязан проверить и при необходимости обновить также `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

Запрещено оставлять этот файл устаревшим.

Если агент не уверен, нужно ли обновлять файл, он должен обновить его или явно записать в `AGENT_WORK_LOG.md` и FINAL REPORT, почему обновление не требуется.

## ЖЁСТКИЙ ПРОМТ ДЛЯ СЛЕДУЮЩЕГО CHATGPT-АГЕНТА

Ты работаешь в проекте ERP PLANEX.

Считай файл `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` главным источником переносимого контекста между чатами. Ты обязан вести этот файл как живой паспорт проекта.

Перед началом работы:
- прочитай этот файл полностью;
- прочитай `README.md`;
- прочитай `AGENTS.md`;
- прочитай `docs/ai/PROJECT_STATUS.md`;
- прочитай `docs/ai/DECISIONS_LOG.md`;
- прочитай `docs/ai/AGENT_WORK_LOG.md`;
- прочитай профильные документы из `docs/architecture/`, `docs/business/`, `docs/ui/`, если задача затрагивает архитектуру, бизнес-процессы или интерфейс.

Во время работы:
- не придумывай неподтверждённые бизнес-правила, роли, юридические требования, бухгалтерскую логику, налоговую логику и процессы;
- не меняй утверждённую архитектуру без решения владельца и записи в `DECISIONS_LOG.md`;
- не пиши бизнес-код без отдельного задания;
- не создавай БД, миграции, SUPERADMIN или новые модули без отдельного задания;
- не коммить `.env` и не записывай реальные секреты в MD-файлы.

После работы ты обязан:
- обновить `AGENT_WORK_LOG.md`;
- обновить `PROJECT_STATUS.md`, если изменился статус, фокус, следующий шаг или важный результат;
- обновить `DECISIONS_LOG.md`, если принято новое решение;
- обновить профильные MD-файлы, если изменилась архитектура, бизнес-логика, UI или правила;
- проверить, нужно ли обновить этот файл;
- обновить этот файл, если появилась новая важная информация;
- удалить из этого файла устаревшие сведения, которые могут ввести следующего агента в заблуждение;
- в FINAL REPORT явно написать, обновлялся ли этот файл и почему.

Что добавлять в этот файл:
- новые утверждённые решения владельца;
- новый текущий этап проекта;
- новый текущий фокус;
- новый следующий шаг;
- новые или изменённые запреты;
- новые обязательные проверки;
- изменения архитектуры;
- изменения модели БД;
- изменения модели ролей и прав;
- изменения feature toggles;
- изменения deployment-модели;
- результаты важных задач;
- новые ключевые файлы;
- актуальные commit hash после commit;
- важные риски и блокеры;
- решения, без которых следующий чат не поймёт проект.

Что удалять или заменять в этом файле:
- устаревшие статусы;
- старые следующие шаги, если они уже выполнены;
- временные задачи, которые потеряли актуальность;
- ошибочные сведения;
- дублирующиеся фрагменты;
- детали, которые стали неверными после нового решения;
- любые инструкции, которые конфликтуют с актуальными правилами проекта.

Что запрещено хранить в этом файле:
- реальные пароли;
- MYSQL-пароль из `.env`;
- токены, ключи API, секреты;
- персональные данные, не нужные для архитектуры;
- неподтверждённые догадки как факты.

Если ты видишь, что этот файл содержит старое задание вместо актуального контекста, немедленно исправь его.

## Обязательные документы проекта

Перед работой агент обязан читать:
- `docs/ai/PROJECT_STATUS.md`;
- `docs/ai/DECISIONS_LOG.md`;
- `docs/ai/AGENT_WORK_LOG.md`;
- профильные документы из `docs/architecture/`, `docs/business/`, `docs/ui/`.

Ключевые AI-документы:
- `README.md`;
- `AGENTS.md`;
- `docs/ai/PROJECT_STATUS.md`;
- `docs/ai/DECISIONS_LOG.md`;
- `docs/ai/AGENT_WORK_LOG.md`;
- `docs/ai/AGENT_NETWORK.md`;
- `docs/ai/AGENT_LOGGING_MASTER_PROMPT.md`;
- `docs/ai/KILO_WORKFLOW.md`;
- `docs/ai/DEEPSEEK_CODER_RULES.md`;
- `docs/ai/KILO_PROJECT_RULES.md`;
- `docs/ai/TASK_TEMPLATE.md`;
- `docs/ai/QA_CHECKLIST.md`;
- `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

Правило обязательного ведения `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` продублировано в:
- `AGENTS.md`;
- `docs/ai/AGENT_LOGGING_MASTER_PROMPT.md`;
- `docs/ai/KILO_PROJECT_RULES.md`;
- `docs/ai/TASK_TEMPLATE.md`;
- `docs/ai/QA_CHECKLIST.md`.

Ключевые архитектурные документы:
- `docs/architecture/ARCHITECTURE_OVERVIEW.md`;
- `docs/architecture/MULTI_COMPANY_DEPLOYMENT.md`;
- `docs/architecture/FEATURE_TOGGLES.md`;
- `docs/architecture/PERMISSIONS_MODEL.md`;
- `docs/architecture/DATABASE_DRAFT.md`;
- `docs/architecture/MODULE_PATTERN.md`;
- `docs/architecture/DOCUMENT_STORAGE_MODEL.md`;
- `docs/architecture/PHP_APP_SKELETON.md`.

Ключевые бизнес-документы:
- `docs/business/ENTITIES_GLOSSARY.md`;
- `docs/business/LINEAR_TRIP_WORKFLOW.md`;
- `docs/business/CONTRACTOR_CREW_WORKFLOW.md`;
- `docs/business/CLIENT_WORKFLOW.md`.

Ключевые UI-документы:
- `docs/ui/UI_UX_RULES.md`;
- `docs/ui/PAGE_PATTERN.md`;
- `docs/ui/FORMS_STANDARD.md`;
- `docs/ui/TABLES_STANDARD.md`;
- `docs/ui/DESIGN_CODE_INTEGRATION.md`.

## Текущий статус проекта

Минимальный PHP-каркас создан и зафиксирован в git. Проект имеет техническую оболочку для дальнейшего наращивания бизнес-модулей.

Бизнес-код ещё не пишется.

SUPERADMIN ещё не начат.

БД и миграции ещё не созданы.

Авторизация, роли в коде, роутер и PDO-обёртка ещё не реализованы.

Базовый UI-фундамент интегрирован без бизнес-логики:
- `public/assets/css/app.css`;
- `public/assets/js/app.js`;
- `app/View/layouts/main.php`;
- `app/View/components/`;
- `app/View/pages/ui_demo.php`;
- `public/index.php` показывает техническую UI demo-страницу.

## Текущий фокус

Настроены 4 проектных KILO-агента (`.kilo/agent/`) и `kilo.jsonc` с `default_agent: erp-architect`. Агентская сеть теперь работает внутри KILO без внешнего ChatGPT-координатора. Перед активным написанием бизнес-кода нужно завершить подготовительный этап:
1. Проверить/утвердить агентскую сеть и формат задач для KILO.
2. Проверить/утвердить интегрированный UI-фундамент.
3. После этого перейти к техническому коду: PDO-обёртка и роутер.
4. После отдельного задания начать SUPERADMIN.

Важно: владелец проекта прямо указал порядок — сначала агентская сеть и дизайн-код, после этого начинать писать систему.

## Агентская сеть KILO

Агентская сеть описана в `docs/ai/AGENT_NETWORK.md`.

Утверждённый агентный цикл:

```text
Владелец проекта
→ erp-architect
→ erp-uiux-designer / erp-coder / erp-qa-tester
→ erp-architect
→ принятие результата или второй круг
```

Проектные KILO-агенты (4 режима):
- `erp-architect` — главный координатор (primary, default_agent);
- `erp-uiux-designer` — UI/UX-дизайнер (subagent);
- `erp-coder` — исполнитель разработки (subagent);
- `erp-qa-tester` — тестировщик (subagent).

Определения агентов: `.kilo/agent/erp-architect.md`, `.kilo/agent/erp-uiux-designer.md`, `.kilo/agent/erp-coder.md`, `.kilo/agent/erp-qa-tester.md`.

Конфигурация: `kilo.jsonc` с `default_agent: erp-architect`.

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи остальным агентам через `task` tool.

Запрещено запускать KILO с размытыми задачами. Каждая задача должна иметь цель, входные MD-файлы, конкретные действия, запреты, проверки, список MD для обновления, указание по commit и формат FINAL REPORT.

## Интеграция дизайн-кода

Правила интеграции дизайн-кода описаны в `docs/ui/DESIGN_CODE_INTEGRATION.md`.

Перед бизнес-страницами нужно создать единый UI-фундамент:
- базовый layout;
- навигацию;
- дизайн-токены;
- стандарт кнопок;
- стандарт форм;
- стандарт таблиц;
- стандарт статусов;
- стандарт ошибок;
- стандарт пустых состояний;
- правила подключения CSS/JS;
- место хранения UI-компонентов.

Рекомендуемая будущая структура:

```text
public/
  assets/
    css/app.css
    js/app.js
app/
  View/
    layouts/main.php
    components/
    pages/
```

UI-фундамент уже интегрирован без бизнес-логики. Следующая UI-задача — проверить/утвердить его или доработать под готовый внешний дизайн-код, если владелец его предоставит.

## Последние commits

Актуальные последние commits на момент обновления файла:

```text
ad9ae8c Configure ERP PLANEX KILO agent modes
a9ff2b7 Update portable context latest commit
2a635a7 Record agent network and UI foundation commit
473f748 Define agent network and integrate UI foundation
309469f Add portable ERP PLANEX context file and update agent rules
6443743 Update logs and status after PHP skeleton creation
dc75ab4 Create minimal PHP application skeleton
4b4d30d Update project status after initial setup commit
c83e7e8 Initial ERP PLANEX documentation and agent workflow setup
```

Важно: если появился новый commit, этот раздел нужно обновить.

## Текущая структура проекта

```text
app/
  Core/
  Http/
  Support/helpers.php
  View/
    layouts/main.php
    components/
    pages/ui_demo.php
bootstrap/app.php
config/app.php
config/database.php
database/
docs/
.kilo/
  agent/
    erp-architect.md
    erp-uiux-designer.md
    erp-coder.md
    erp-qa-tester.md
logs/
public/index.php
public/assets/css/app.css
public/assets/js/app.js
storage/
.env.example
.gitignore
AGENTS.md
kilo.jsonc
README.md
```

Локальный `.env` существует, но не должен попадать в git и не должен цитироваться в MD-файлах.

## Git-правила

- Не делать commit без явного разрешения владельца.
- Не коммитить `.env`.
- Не коммитить реальные секреты.
- После изменений выполнять `git status`.
- После commit обновлять commit hash в этом файле, если commit важен для статуса проекта.

## Как писать промты KILO

Промт KILO должен быть конкретным.

Нельзя давать размытые задачи типа:

```text
делай ERP
```

Задача должна содержать:
- цель;
- список файлов, которые нужно прочитать;
- что именно сделать;
- что запрещено;
- какие проверки выполнить;
- какие MD-файлы обновить;
- нужен commit или нет;
- формат FINAL REPORT.

## Как принимать FINAL REPORT агента

FINAL REPORT должен содержать:
- статус;
- что сделано;
- какие файлы прочитаны;
- какие файлы созданы;
- какие файлы обновлены;
- проверки;
- git status;
- что не делалось;
- риски;
- вопросы владельцу;
- следующий шаг;
- обновлялся ли `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`, а если нет — почему.

Если агент не обновил логи, статус или этот файл при изменении контекста, задача не считается полностью закрытой.

## Главные запреты

- Не писать бизнес-код без отдельного задания.
- Не начинать бизнес-код до проработки агентской сети и интеграции дизайн-фундамента.
- Не создавать БД без отдельного задания.
- Не создавать миграции без отдельного задания.
- Не начинать SUPERADMIN без отдельного задания.
- Не менять утверждённые архитектурные решения без записи в `DECISIONS_LOG.md`.
- Не создавать второй или дублирующий файл переносимого контекста.
- Не создавать отдельный `START_NEW_CHAT_PROMPT.txt`.
- Не хранить реальные секреты в MD-файлах.
- Не записывать MYSQL-пароль в этот файл.
- Не коммитить `.env`.
- Не придумывать неподтверждённые бизнес-правила.

## Последнее обновление этого файла

2026-06-11 20:23 — KILO/erp-coder: настроены 4 проектных KILO-агента (`.kilo/agent/`); создан `kilo.jsonc` с `default_agent: erp-architect`; обновлены AGENTS.md, AGENT_NETWORK.md, KILO_WORKFLOW.md, DECISIONS_LOG.md (DECISION-0016), AGENT_WORK_LOG.md. Агентская сеть перенесена внутрь KILO, внешний ChatGPT-координатор исключён из цикла.

2026-06-11 18:06 — ChatGPT/Codex: добавлены `docs/ai/AGENT_NETWORK.md` и `docs/ui/DESIGN_CODE_INTEGRATION.md`; агентская сеть проработана; базовый UI-фундамент интегрирован в PHP-каркас без бизнес-логики; commits `473f748`, `2a635a7`.

2026-06-11 17:51 — ChatGPT/Codex: правило обязательного ведения этого файла продублировано в `AGENTS.md`, `AGENT_LOGGING_MASTER_PROMPT.md`, `KILO_PROJECT_RULES.md`, `TASK_TEMPLATE.md`, `QA_CHECKLIST.md`; файл синхронизирован как главный переносимый контекст проекта; commit `309469f`.
