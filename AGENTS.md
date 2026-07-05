# ERP PLANEX — агентская схема

## Актуализация 2026-06-26 — Исполнители рейса / Транспорт

Статус: подготовлен пакет исправлений `ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip`; перед финальной фиксацией владелец должен применить файлы, проверить runtime и затем закоммитить результат.

Что обязательно учитывать дальше:

- `/company/route-executors` должен быть доступен `company_owner`, `senior_logist`, `logist`. Для `logist` пустой список — это не «Нет доступа», а нормальное пустое состояние с действием `Создать исполнителя рейса`.
- `Исполнитель рейса` — пользовательская сущность `Подрядчик + водитель + ТС`; технически создаются/используются `driver_vehicle_blocks` + `crews`.
- Реальная локальная схема БД: таблицы сущностей находятся в `erp_company_{id}`, а не в центральной `erp_planex`.
- `driver_vehicle_blocks` НЕ имеет поля `vehicle_id`. Запрещено писать `vehicle_id` в `driver_vehicle_blocks`.
- `driver_vehicle_blocks` хранит: `driver_id`, `vehicle_set_id`, `status`, `comments`, `created_by_user_id`, `created_by_role`, `updated_by_user_id`, `updated_by_role`.
- `crews` всё ещё имеет legacy-поля `vehicle_id` и `driver_id`; при создании исполнителя рейса `crews.vehicle_id` нужно заполнять значением `vehicle_sets.primary_vehicle_unit_id`, а `crews.driver_id` — выбранным водителем.
- Для `logist` выбор contractor/driver/vehicle_set и видимость списков должны фильтроваться по `created_by_user_id` + активным grants. Активный grant: `revoked_at IS NULL` и `access_level IN ('view','edit')`.
- На `/company/vehicle-sets` модалка создания транспорта должна быть в DOM всегда, включая пустой список, иначе кнопка `Добавить новый транспорт` визуально есть, но не работает.
- Если возникает ошибка схемы БД, сначала запускать `db_schema_route_executor.php` и сверять реальные `DESCRIBE/SHOW CREATE TABLE`, не угадывать поля.

## Главный принцип

Документация должна помогать разработке, а не заменять разработку.

## Рабочая схема

```text
1. Владелец + ChatGPT проектируют решение.
2. KILO erp-architect переводит решение в техническую задачу.
3. KILO erp-coder реализует функционал.
4. KILO erp-coder тестирует функционал.
5. KILO erp-coder применяет дизайн-базу через erp-ui.css.
6. KILO erp-architect принимает результат у кодера.
7. Внешний ChatGPT "Главный дизайнер" подключается для дизайн-полировки.
8. KILO erp-coder внедряет дизайнерские правки точечно.
```

Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев. Основной поток: erp-architect ставит задачу erp-coder и принимает результат. Если владелец случайно передал задачу кодеру напрямую, результат всё равно обязательно возвращается erp-architect на приёмку.

## KILO-агенты

Постоянно используются только:

```text
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

## Главный дизайнер

Главный дизайнер — это НЕ KILO-агент.

Это отдельный ChatGPT-чат, которому передаются промт, файлы и архивы для дизайн-аудита, подготовки CSS-системы и UI-полировки.

## CODEX-дизайнер

CODEX-дизайнер — это также НЕ KILO-агент, а внешний инструмент/чат, который выполняет точечные дизайн-правки.

CODEX-дизайнер обязан сам проверять свои изменения после правок:
- `php -l` по изменённым view-файлам;
- `git diff --check`;
- отсутствие inline-style кроме `display:none`;
- отсутствие случайных цветов/стилей;
- сохранность `input name` / `form action` / `method` / `routes`;
- подключение CSS;
- визуальная целостность страниц.

CODEX-дизайнер не должен менять функциональную логику.
Если найден функциональный баг — фиксирует его в отчёте, а исправление идёт через архитектора и кодера.

## Дизайн-система

Главный CSS:

```text
public/assets/css/erp-ui.css
```

Текстовый стандарт:

```text
docs/ui/DESIGN_STANDARD.md
```

Кодер обязан использовать эти файлы и не придумывать второй UI-kit.

## QA

Отдельный QA-агент исключён из постоянной цепочки.

Проверка встроена в обязанности:

- архитектор проверяет соответствие задаче;
- кодер запускает доступные runtime/checks;
- Главный дизайнер проверяет только дизайн-код;
- финальное решение принимает владелец с ChatGPT.

## Основные MD

```text
docs/ai/PROJECT_STATE.md
docs/ai/AGENT_RULES.md
docs/ai/CURRENT_TASK.md
docs/ai/DECISIONS.md
docs/ai/HANDOFF_FOR_NEW_CHAT.md
docs/ui/DESIGN_STANDARD.md
```

## Запрет

Нельзя создавать новые управляющие MD без прямого решения владельца + ChatGPT.

## Storage: загруженные документы

`storage/companies/*` — рабочие загруженные документы (PDF, изображения и т.д.).
При очистке проекта/подготовке архива эти директории можно исключать из архива,
но НЕЛЬЗЯ удалять из рабочей среды, иначе документы в БД станут осиротевшими
и просмотр/скачивание будут возвращать 404.


---

## Codex Desktop + DeepSeek/OpenCode — схема супервизор/исполнитель

Статус: добавлено для тестовой и рабочей связки, где Codex Desktop выступает главным контролёром, а DeepSeek через OpenCode — исполнителем точечных задач.

### Роли

```text
Codex Desktop = supervisor / architect / reviewer
OpenCode + DeepSeek = implementation worker
```

Codex Desktop не должен слепо принимать результат DeepSeek. Его задача:

1. понять задачу владельца;
2. проверить проект и текущий `git status`;
3. сформулировать короткую техническую задачу для DeepSeek;
4. сохранить задачу в `tmp/deepseek-task.md`;
5. запустить DeepSeek через OpenCode;
6. дождаться завершения работы OpenCode;
7. проверить `git diff`;
8. запустить обязательные проверки;
9. при ошибках — дать DeepSeek корректирующий промт;
10. принять результат только после проверки.

### Правило экономии контекста и актуальности документации

Codex Desktop не пишет код сам, если задачу можно безопасно перепоручить OpenCode/DeepSeek. Codex отвечает за постановку задачи, контроль diff, проверки, корректирующие промты и финальную приёмку.

После каждой значимой модификации проекта Codex обязан обновить актуальную документацию проекта. Новые важные правила, решения, workflow и ограничения фиксируются в управляющих MD, а не остаются только в чате.

### Команда запуска DeepSeek из Codex

Codex Desktop на Windows может запускать команды через PowerShell, но для ERP PLANEX это нежелательно из-за риска битой кириллицы и некорректного вывода CLI.

Все команды проекта должны запускаться через `cmd.exe /c`.

Базовая команда для запуска DeepSeek:

```text
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && opencode run --auto ""Read tmp\deepseek-task.md and implement it. After finishing, report changed files and checks performed."""
```

Если Codex создаёт или обновляет задачу для DeepSeek, файл задачи должен быть:

```text
tmp/deepseek-task.md
```

### Запрещено

Codex Desktop и DeepSeek/OpenCode не должны:

- запускать проектные Git/PHP/runtime-команды напрямую через PowerShell;
- менять основную шапку;
- менять шапку контентного блока;
- менять существующую структуру или поведение меню;
- переписывать backend/business logic, если задача только визуальная;
- придумывать несуществующие файлы, маршруты, таблицы или поля;
- игнорировать текущие правила из `docs/ai/*` и `docs/ui/DESIGN_STANDARD.md`;
- запускать `php -S` в foreground;
- запускать `start /B php -S`;
- принимать mojibake как нормальный текст.

### Обязательная защита кириллицы

Все изменяемые файлы с русским текстом должны сохраняться в UTF-8 без BOM.

Запрещены признаки битой кодировки:

```text
Рџ
РЎ
Ð
Ñ
�
```

Если Codex или DeepSeek видят такие символы в изменённых файлах, результат считается неприемлемым до исправления.

### Обязательные проверки после работы DeepSeek

Codex обязан выполнить:

```text
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git diff --check && git status --short"
```

Для каждого изменённого PHP-файла:

```text
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l path\to\changed-file.php"
```

Для визуальных задач Codex дополнительно проверяет:

- не изменены ли topbar/page-head/menu;
- нет ли случайных inline-style, кроме допустимого `display:none`;
- не изменены ли `input name`, `form action`, `method`, routes;
- не добавлены ли случайные цвета/второй UI-kit;
- используется ли `public/assets/css/erp-ui.css` и существующий дизайн-стандарт.

### Исправляющий цикл

Если после работы DeepSeek есть ошибки, Codex не должен исправлять всё молча сам.

Правильный цикл:

```text
1. Codex фиксирует конкретные нарушения.
2. Codex обновляет tmp/deepseek-task.md коротким correction prompt.
3. Codex снова запускает OpenCode/DeepSeek.
4. Codex повторяет git/php/runtime/design checks.
5. Только после этого пишет итоговый отчёт.
```

### Финальный отчёт Codex

Финальный отчёт Codex должен содержать:

- какие файлы изменены;
- что сделал DeepSeek;
- что проверил Codex;
- результат `git diff --check`;
- результат `php -l` по изменённым PHP-файлам;
- подтверждение, что кириллица не сломана;
- подтверждение, что topbar/page-head/menu не тронуты;
- статус: `ACCEPTED`, `NEEDS_CORRECTION` или `BLOCKED`.

### Подтверждённые уроки runtime-тестирования

На основе runtime-испытания связки Codex Desktop + OpenCode/DeepSeek подтверждены:

- OpenCode эффективен для: аудит, точечные правки, runtime, DB fixtures, проверки, чистка артефактов.
- Codex не должен слепо принимать отчёты OpenCode; отчёты сначала показываются владельцу, затем Codex даёт свою интерпретацию.
- Промты OpenCode: точный scope, запреты, правила команд, матрица приёмки, чистка, формат отчёта.
- OpenCode запрещено: PowerShell, curl, Unix-only, npm install, package-lock/node_modules, foreground php -S, широкие правки без разрешения.
- При нарушении правил или фиктивном runtime — correction loop до приёмки.
- Codex проверяет: git diff, git status, php -l, architecture_guard, git diff --check, mojibake, артефакты, runtime-доказательства.
