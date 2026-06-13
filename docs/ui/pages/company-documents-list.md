# UI PAGE HANDOFF — Company Documents List (Документы сущности)

## Status
**DESIGNER HANDOFF.** Production-grade handoff for erp-coder.

## A. Foundation

### Role and context
- **User**: Руководитель (company_owner) / Логист (logist)
- **Location**: компания, раздел документов конкретной сущности
- **Path prefix**: `/company/documents`
- **Company context**: `$_SESSION['company_id']` (auth/sessions уже реализованы)
- **Route guard**: `requireRole(['company_owner', 'logist'])` — маршрут `/company/*`

### 0. LAYOUT FOUNDATION SOURCE MAPPING

Layout uses existing `main.php` shell — NO rebuild.

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr` | main.php `.app-shell` | YES |
| Topbar position | spans full grid (grid-column 1/-1) | main.php `.topbar` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | main.php | YES |
| Topbar border-bottom | `1px solid var(--line)` | main.php | YES |
| User block (topbar right) | имя + роль пользователя | main.php `.topbar-right` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | main.php `.app-sidebar` | YES |
| Sidebar border-right | `1px solid var(--nav-divider)` | main.php | YES |
| Nav item height | 34px | main.php `.nav-item` | YES |
| Nav item font-weight | 600 ВСЕГДА | main.php | YES |
| Nav item font-size | 12.5px | main.php | YES |
| Nav icons | SVG inline 16×16, opacity 0.45 | main.php | YES |
| Nav section label | 9px, 700, uppercase, letter-spacing .12em | main.php `.nav-sec` | YES |
| Nav active state | `::before` pseudo (2px gold left) | main.php `.nav-item.is-active` | YES |
| Nav bottom block | `.nav-spacer` + `.nav-bottom` + Настройки | main.php | YES |

**Все строки = YES. Foundation COMPLIANT, менять не нужно.**

### 0b. SIDEBAR INFORMATION ARCHITECTURE

Страница документов открывается из контекста справочников компании. Sidebar отображает стандартное меню роли. Активный пункт в sidebar — тот справочник, из которого открыты документы (если можно определить). Если нет — ближайший родительский раздел.

**Руководитель (company_owner):**
```
ОПЕРАЦИИ
  Рейсы                                    disabled
  Водители    → /company/drivers           (active если entity_type=driver)
  Транспорт   → /company/vehicles          (active если entity_type=vehicle)
  Клиенты     → /company/clients           (active если entity_type=client)
  Подрядчики  → /company/contractors       (active если entity_type=contractor)
  Экипажи     → /company/crews             (active если entity_type=crew)

СИСТЕМА
  Логисты     → /company/logists
  Настройки                                disabled
```

**Логист (logist):**
```
ОПЕРАЦИИ
  Рейсы                                    disabled
  Водители    → /company/drivers           (active если entity_type=driver)
  Транспорт   → /company/vehicles          (active если entity_type=vehicle)
  Клиенты     → /company/clients           (active если entity_type=client)
  Подрядчики  → /company/contractors       (active если entity_type=contractor)
  Экипажи     → /company/crews             (active если entity_type=crew)

СИСТЕМА
  Настройки                                disabled
```

**Правило:** если `entity_type` соответствует одному из пунктов «ОПЕРАЦИИ», этот пункт должен быть `.is-active`. Если `entity_type` невалидный — ни один пункт не активен.

### 0c. Topbar context

```
{EntityLabel} — Компания: {company_name}
```

Где `{EntityLabel}`:
- `entity_type=client` → «Клиенты»
- `entity_type=contractor` → «Подрядчики»
- `entity_type=driver` → «Водители»
- `entity_type=vehicle` → «Транспорт»
- `entity_type=crew` → «Экипажи»

---

## 1. Страницы модуля

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/documents?entity_type=X&entity_id=Y` | GET | Список документов сущности |
| `/company/documents/upload?entity_type=X&entity_id=Y` | GET | Форма загрузки документа |
| `/company/documents/upload?entity_type=X&entity_id=Y` | POST | Обработка загрузки |

---

## 2. Страница списка документов

### 2.1 Тип страницы

**Pattern:** PATTERN-01 Table-only registry.

Страница показывает список документов, прикреплённых к конкретной сущности (клиент, подрядчик, водитель, транспорт, экипаж).

### 2.2 Состояния (6 состояний)

1. **entity_type не из whitelist** → ошибка «Неизвестный тип сущности»
2. **entity не найдена** → ошибка «{EntityLabel} не найден»
3. **ошибка подключения к локальной БД** → notice.warn
4. **документов нет** → empty state «Документы не загружены»
5. **документы есть** → таблица с документами
6. **entity_type валидный, entity найдена, но миграция documents не применена** → таблица пуста (empty state, при первом INSERT таблица будет создана миграцией)

### 2.3 Entity type whitelist и маппинг

| entity_type | Human label | Back route | Таблица в локальной БД |
|-------------|-------------|------------|------------------------|
| `client` | Клиент | `/company/clients` | `clients` |
| `contractor` | Подрядчик | `/company/contractors` | `contractors` |
| `driver` | Водитель | `/company/drivers` | `drivers` |
| `vehicle` | Транспорт | `/company/vehicles` | `vehicles` |
| `crew` | Экипаж | `/company/crews` | `crews` |

### 2.4 Page head

```html
<div class="page-head">
    <div>
        <a href="<?= e($backRoute) ?>" class="btn btn-ghost" style="margin-bottom:4px">← Назад к <?= e($entityLabelDative) ?></a>
        <h1>Документы: <?= e($entityName) ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> • Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить документ</a>
    </div>
</div>
```

**Где:**
- `$backRoute` — URL к списку сущности (например `/company/clients`)
- `$entityLabelDative` — название сущности в дательном падеже для ссылки «← Назад к ...»:
  - client → «клиентам»
  - contractor → «подрядчикам»
  - driver → «водителям»
  - vehicle → «транспорту»
  - crew → «экипажам»
- `$entityName` — имя сущности (например, название клиента, ФИО водителя)
- `$entityLabel` — human-readable тип сущности

### 2.5 Empty state

```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Документы не загружены.</p>
            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить первый документ</a>
        </div>
    </div>
</div>
```

### 2.6 Table (когда документы есть)

```html
<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Тип документа</th>
                        <th>Имя файла</th>
                        <th>Размер</th>
                        <th>MIME</th>
                        <th>Статус</th>
                        <th>Дата загрузки</th>
                        <th>Комментарий</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= e($doc['document_type']) ?></td>
                        <td><?= e($doc['original_name']) ?></td>
                        <td class="col-num"><?= e($doc['file_size_formatted']) ?></td>
                        <td class="col-mono"><?= e($doc['mime_type']) ?></td>
                        <td>
                            <span class="badge<?= $doc['status'] === 'uploaded' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $doc['status'] === 'uploaded' ? 'Загружен' : e($doc['status']) ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($doc['created_at']) ?></td>
                        <td class="col-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($doc['comments'] ?? '') ?></td>
                        <td class="col-actions">
                            <button class="btn btn-toolbar disabled" title="Скачивание будет доступно позже">Скачать</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

**Примечания:**
- Колонка «Скачать» — кнопка disabled с `title`. Это deferred-функция. Кодер НЕ реализует download route на первом этапе.
- `$doc['file_size_formatted']` — форматированный размер (КБ/МБ), форматирование на бэкенде.
- Колонка «Комментарий» может быть длинной — `max-width` с `text-overflow: ellipsis`.

### 2.7 Error states

**entity_type не из whitelist:**
```html
<div class="notice warn">
    Неизвестный тип сущности «<?= e($entityType) ?>». Допустимые типы: client, contractor, driver, vehicle, crew.
</div>
```

**entity не найдена:**
```html
<div class="notice warn">
    <?= e($entityLabel) ?> не найден. Проверьте, что сущность существует, и повторите попытку.
</div>
```

**Ошибка подключения к локальной БД:**
```html
<div class="notice warn">
    Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.
</div>
```

---

## 3. Форма загрузки документа (см. отдельный handoff)

Ссылка «Загрузить документ» ведёт на `/company/documents/upload?entity_type=X&entity_id=Y`.
Форма описана в `docs/ui/pages/company-documents-upload.md`.

---

## 4. Локальная БД — таблица `documents`

### 4.1 Migration

Файл: `database/migrations-local/007_create_company_documents.sql`

```sql
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `entity_type` VARCHAR(20) NOT NULL COMMENT 'client|contractor|driver|vehicle|crew',
    `entity_id` INT UNSIGNED NOT NULL,
    `document_type` VARCHAR(100) DEFAULT NULL COMMENT 'Тип документа (напр. Договор, Паспорт, СТС)',
    `original_name` VARCHAR(500) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `relative_path` VARCHAR(1000) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL COMMENT 'Размер в байтах',
    `status` VARCHAR(20) NOT NULL DEFAULT 'uploaded' COMMENT 'uploaded|verified|rejected',
    `uploaded_by_user_id` INT UNSIGNED DEFAULT NULL,
    `uploaded_by_role` VARCHAR(50) DEFAULT NULL COMMENT 'company_owner|logist',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Business logic (GET /company/documents)

```text
1. $companyId = $_SESSION['company_id'] (уже из сессии после AUTH_BLOCK)
2. $entityType = $_GET['entity_type'] ?? ''
3. $entityId = (int)($_GET['entity_id'] ?? 0)

4. Проверить entity_type из whitelist:
   ALLOWED: client, contractor, driver, vehicle, crew
   Если нет → error «Неизвестный тип сущности»

5. Подключиться к локальной БД компании ($companyId → db_identifier)

6. Проверить существование сущности:
   - Определить таблицу по entity_type (clients/contractors/drivers/vehicles/crews)
   - SELECT ... FROM {table} WHERE id = ?
   - Если не найдена → error «{EntityLabel} не найден»
   - Получить $entityName для page head

7. Проверить таблицу documents: query("SELECT 1 FROM documents LIMIT 1")->fetch()
   - Если нет → exec(CREATE TABLE из миграции)

8. SELECT * FROM documents
   WHERE entity_type = ? AND entity_id = ?
   ORDER BY created_at DESC

9. Для каждого документа:
   - Форматировать file_size (байты → читаемый размер)
   - Подготовить статус

10. Показать страницу:
    - Если $documents пуст → empty state
    - Если есть → таблица
```

---

## 6. Что НЕ реализуется на первом этапе

- Скачивание файлов (download route) — кнопка disabled
- Статусы verified / rejected — только uploaded
- Предпросмотр файлов
- Удаление / замена документов
- Массовая загрузка
- Фильтры документов
- Пагинация (на первом этапе документов мало)

---

## 7. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing layout |
| CORE-02 | Sidebar navigation | Existing, active item по entity_type |
| CORE-03 | Topbar | Existing |
| CORE-05 | Page header | Page head с именем сущности + back-link + upload button |
| CORE-06 | Page actions area | «Загрузить документ» справа в page-head |
| CORE-08 | Panel | Контейнер таблицы / empty state |
| CORE-13 | Data table / ERP grid | Таблица документов |
| CORE-17 | Primary button | «Загрузить документ» (page head), «Загрузить первый документ» (empty) |
| CORE-19 | Ghost button | «← Назад к {сущности}» |
| CORE-20 | Toolbar button | «Скачать» (disabled, deferred) |
| CORE-23 | Disabled/future action | Кнопка «Скачать» disabled |
| CORE-24 | Status badge | Статус документа (uploaded) |
| CORE-33 | Warning notice | Ошибки (entity_type, entity, DB) |
| CORE-34 | Empty state | «Документы не загружены» |

**COMPOSITE pattern:** PATTERN-01 Table-only registry.

---

## 8. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container, page-specific shell |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item, .nav-item:hover, .nav-item.is-active | `.nav-item`, `.is-active`, `:hover` mandatory | `a.nav-item { color: inherit }`, missing hover |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header, demo text |
| 4 | Page head | CORE-05 | Section > CORE-05 preview | `.page-head h1`, `.text-muted` | Hero-scale block, «Основное действие» |
| 5 | Page actions | CORE-06 | Section > CORE-06 preview | `.page-head-actions` | Multiple primary buttons |
| 6 | Panel | CORE-08 | Production CSS > PANEL RULE: padding 0, overflow hidden | `.panel`, `.panel-body` (padding inside body only) | Padding on `.panel`, Bootstrap card |
| 7 | Data table | CORE-13 | Section > CORE-13 preview | `.tbl-wrap`, `.tbl` | Bootstrap table, cards instead of rows |
| 8 | Primary button | CORE-17 | Production CSS > .btn-primary | `.btn-primary` | Multiple competing primary |
| 9 | Ghost button | CORE-19 | Production CSS > .btn-ghost | `.btn-ghost` | Ghost for danger, ghost as main action |
| 10 | Toolbar button | CORE-20 | Production CSS > .btn-toolbar | `.btn-toolbar` | Normal height button in toolbar position |
| 11 | Disabled action | CORE-23 | Production CSS > .disabled | `.disabled` | Looks clickable when not |
| 12 | Status badge | CORE-24 | Production CSS > .badge, .badge-ok | `.badge`, `.badge-ok`, `.dot` | Bootstrap badge, random colors |
| 13 | Warning notice | CORE-33 | Production CSS > .warn | `.notice.warn` | Bright alert, raw errors |
| 14 | Empty state | CORE-34 | Section > CORE-34 preview | `.empty-state` | Blank space, «Нет данных» без действия |
| 15 | Mono column | — | Table helper class | `.col-mono` | — |
| 16 | Numeric column | — | Table helper class | `.col-num` | — |
| 17 | Muted column | — | Table helper class | `.col-muted` | — |
| 18 | Actions column | — | Table helper class | `.col-actions` | Row actions visible always |

---

## 9. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------|-------------|-----------------|
| `.panel` | `.panel-body` | 0 (by rule) | — | `.panel-body` (10px) | — | — |
| `.panel-body` | `.tbl-wrap` | 10px | — | `.tbl-wrap` full width | — | — |
| `.page-head` | `.page-head-actions` | — | — | — | — | — |
| `.page-head` | `h1` | — | — | — | — | — |
| `.tbl` | `.col-num` | — | — | — | — | right-align, tabular-nums |
| `.tbl` | `.col-mono` | — | — | — | — | monospace font |
| `.tbl` | `.col-muted` | — | — | — | — | muted color |
| `.tbl` | `.col-actions` | — | — | — | — | right-align actions |

---

## 10. MODULE USAGE DECISIONS

- **Main page purpose**: просмотр документов, прикреплённых к сущности справочника (клиент, подрядчик, водитель, транспорт, экипаж).
- **Primary work object**: таблица документов.
- **Main layout selected**: PATTERN-01 Table-only registry.
- **Primary action**: «Загрузить документ» → форма загрузки.
- **Secondary actions**: «← Назад к {сущности}» (возврат в справочник).
- **Table required**: YES — список документов сущности.
- **Form required**: YES — форма загрузки (отдельная страница, handoff `company-documents-upload.md`).
- **Inspector required**: NO — для списка документов не нужен.
- **Filters required**: NO — на первом этапе документов мало.
- **Modal required**: NO.
- **Empty state required**: YES — «Документы не загружены» + кнопка загрузки.
- **Error states required**: YES — невалидный entity_type, сущность не найдена, ошибка БД.
- **Modules NOT used and why**:
  - CORE-11 (Filter toolbar) — нет фильтрации на первом этапе
  - CORE-14/15 (Row states/actions) — нет hover-actions строк (только disabled кнопка «Скачать»)
  - CORE-16 (Pagination) — документов мало, пагинация не нужна
  - CORE-29 (Upload input) — загрузка на отдельной странице, не inline
  - CORE-30 (Inspector) — не нужен для списка документов
  - CORE-43 (Document section) — используется паттерн таблицы, не doc-list с doc-row
  - CORE-37 (Danger confirmation) — удаление не реализуется
  - CORE-39 (Toast) — не используется

---

## 11. Strict prohibitions for coder

- Не менять `main.php`, `app.css`, `Database.php`, `Router.php` без отдельного разрешения.
- Не реализовывать download route (кнопка «Скачать» — disabled).
- Не реализовывать статусы verified / rejected.
- Не реализовывать предпросмотр файлов.
- Не реализовывать удаление / замену документов.
- Не реализовывать массовую загрузку.
- Не менять бизнес-логику существующих справочников.
- Не создавать документы в центральной БД (только в локальной `erp_company_{id}`).
- Не менять DB/storage naming logic.
- Не использовать browser-default input/select.
- Не использовать demo-placeholder UI.
- Не добавлять псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` и emoji.
- Не добавлять inline styles кроме динамических PHP.
- `border-radius` новых элементов ≤ 4px.
- `box-shadow` blur новых элементов ≤ 8px.
- Не использовать Bootstrap/Tailwind/Material классы.

---

## 12. Coder implementation checklist

- [ ] Создать миграцию `database/migrations-local/007_create_company_documents.sql`
- [ ] Добавить маршрут GET `/company/documents` в `public/index.php`
- [ ] Реализовать валидацию entity_type (whitelist: client, contractor, driver, vehicle, crew)
- [ ] Реализовать поиск сущности по entity_type + entity_id в локальной БД
- [ ] Реализовать форматирование file_size (байты → «X.XX КБ» / «X.XX МБ»)
- [ ] Создать view `app/View/pages/company_documents.php` (6 состояний)
- [ ] Sidebar: активный пункт меню по entity_type
- [ ] Topbar: контекст «{EntityLabel} — Компания: {name}»
- [ ] Кнопка «Скачать» — disabled с title-подсказкой
- [ ] Кнопка «Загрузить документ» ведёт на `/company/documents/upload?...`
- [ ] Back-link «← Назад к {сущности}» корректный для каждого entity_type
- [ ] `e()` для всех пользовательских данных
- [ ] `php -l` для всех изменённых PHP-файлов
- [ ] Git diff без секретов
- [ ] Не ломать существующие модули (SUPERADMIN, логисты, справочники)

---

## 13. QA formal checklist

- [ ] `/company/documents?entity_type=client&entity_id=1` → 200
- [ ] `/company/documents?entity_type=contractor&entity_id=1` → 200
- [ ] `/company/documents?entity_type=driver&entity_id=1` → 200
- [ ] `/company/documents?entity_type=vehicle&entity_id=1` → 200
- [ ] `/company/documents?entity_type=crew&entity_id=1` → 200
- [ ] `/company/documents?entity_type=invalid&entity_id=1` → error «Неизвестный тип сущности» (не 500)
- [ ] `/company/documents?entity_type=client&entity_id=99999` → error «Клиент не найден» (не 500)
- [ ] Пустой список → empty state «Документы не загружены»
- [ ] Документы отображаются в таблице
- [ ] Форматирование размера файла корректное
- [ ] Статус «Загружен» с badge-ok
- [ ] Кнопка «Скачать» disabled с title
- [ ] Кнопка «Загрузить документ» активна, ссылка корректна
- [ ] Back-link «← Назад к ...» корректный для каждого entity_type
- [ ] Миграция documents идемпотентна
- [ ] Документы в локальной БД, НЕ в центральной
- [ ] `main.php` не изменён
- [ ] `app.css` не изменён
- [ ] Нет секретов в git diff
- [ ] `php -l` для всех изменённых PHP — OK

---

## 14. Owner review

- **VISUAL CHECK URL (list):** `http://127.0.0.1:[port]/company/documents?entity_type=client&entity_id=1`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO

---

## 15. Designer production handoff

### Target visual result

Строгая ERP-страница: page-head с именем сущности + back-link + кнопка загрузки, ниже — таблица документов с читаемыми колонками, статусами и комментарием. Плотная композиция без декоративных элементов.

### Exact layout

- App shell: sidebar 224px + topbar 38px + content
- Content: page-head → panel с таблицей (или empty state)
- Scroll: content scrolls vertically
- No right inspector, no bottom editor

### Exact typography

- Page title: 15-16px, 700 — «Документы: {entity_name}»
- Subtitle: 12px, muted — тип сущности + компания
- Back-link: 12px, 600, ghost button style
- Table header: 11px, 700, uppercase
- Table body: 12px
- Status badge: 11px, 700

### Exact spacing

- Page padding: 12px 14px 28px (content area)
- Panel padding: 0 (panel), 10px (panel-body)
- Table row: 32px
- Table header: 30px

### Exact color tokens

- Page title: `--text-main` (#131210)
- Subtitle: `--text-muted` (#4c4840)
- Back-link: `--text-muted`
- Table border: `--line-hair` (#dedad0)
- Status uploaded: `--success` (#1f6b43), bg `--success-bg` (#ddeee5)
- Upload button: `--accent` (#7c4718)
- Warning: `--warning` (#7a5210), bg `--warning-bg` (#fdf0d5)

---

## 16. Изменения существующих страниц справочников

На страницах справочников компании (clients, contractors, drivers, vehicles, crews) в колонку действий каждой строки таблицы добавить ссылку «Документы»:

```html
<a href="/company/documents?entity_type=<?= $entityTypeCode ?>&entity_id=<?= $row['id'] ?>" class="btn btn-toolbar">Документы</a>
```

Где `$entityTypeCode`:
- `company_clients.php` → `client`
- `company_contractors.php` → `contractor`
- `company_drivers.php` → `driver`
- `company_vehicles.php` → `vehicle`
- `company_crews.php` → `crew`

**Это точечное изменение:** добавить одну кнопку-ссылку в существующую колонку действий (`.col-actions` или `.row-actions`) каждой строки. Никакие другие элементы страницы не менять. Кодер должен изменить 5 существующих view-файлов.
