# UI PAGE HANDOFF — SUPERADMIN Company Directories

## Status
**HANDOFF_READY** — новая страница. Production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md §0a`. NO REBUILD NEEDED.

| Параметр | Соответствие |
|----------|-------------|
| App shell grid | YES |
| Topbar background `var(--surface-strong)` #fefdf8 | YES |
| Sidebar background `var(--nav-bg)` #191816 | YES |
| Nav item 34px, 600, 12.5px | YES |
| Nav active `::before` 2px gold | YES |

### 0b. Sidebar IA

```
ОПЕРАЦИИ (все disabled) → [spacer] → СИСТЕМА (SUPERADMIN is-active) → [bottom] Настройки disabled
```

### 0c. Topbar context

```
SUPERADMIN — Справочники компании: [company.name]
```

---

## 1. Страница

- **Route:** `GET /superadmin/companies/{id}/directories`
- **View file:** `app/View/pages/superadmin_company_directories.php` (НОВЫЙ)
- **Тип:** PATTERN-01 Table-only registry (read-only monitoring)
- **Роль:** SUPERADMIN
- **Главная задача:** Мониторинг counts справочников компании (read-only, без управления)
- **Что нельзя менять:** local DB data, main.php

---

## 2. Page head

```html
<div class="page-head">
    <div>
        <h1>Справочники компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

---

## 3. Directories table

```html
<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Справочники</h3>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Справочник</th>
                    <th class="col-num">Всего</th>
                    <th class="col-num">Активных</th>
                    <th class="col-num">Архивированных</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Клиенты</td>
                    <td class="col-num"><?= $dirs['clients']['total'] ?></td>
                    <td class="col-num"><?= $dirs['clients']['active'] ?></td>
                    <td class="col-num"><?= $dirs['clients']['archived'] ?></td>
                    <td class="col-actions"><button class="btn btn-secondary disabled">Открыть</button></td>
                </tr>
                <tr>
                    <td>Подрядчики</td>
                    <td class="col-num"><?= $dirs['contractors']['total'] ?></td>
                    <td class="col-num"><?= $dirs['contractors']['active'] ?></td>
                    <td class="col-num"><?= $dirs['contractors']['archived'] ?></td>
                    <td class="col-actions"><button class="btn btn-secondary disabled">Открыть</button></td>
                </tr>
                <tr>
                    <td>Водители</td>
                    <td class="col-num"><?= $dirs['drivers']['total'] ?></td>
                    <td class="col-num"><?= $dirs['drivers']['active'] ?></td>
                    <td class="col-num"><?= $dirs['drivers']['archived'] ?></td>
                    <td class="col-actions"><button class="btn btn-secondary disabled">Открыть</button></td>
                </tr>
                <tr>
                    <td>Транспорт</td>
                    <td class="col-num"><?= $dirs['vehicles']['total'] ?></td>
                    <td class="col-num"><?= $dirs['vehicles']['active'] ?></td>
                    <td class="col-num"><?= $dirs['vehicles']['archived'] ?></td>
                    <td class="col-actions"><button class="btn btn-secondary disabled">Открыть</button></td>
                </tr>
                <tr>
                    <td>Экипажи</td>
                    <td class="col-num"><?= $dirs['crews']['total'] ?></td>
                    <td class="col-num"><?= $dirs['crews']['active'] ?></td>
                    <td class="col-num"><?= $dirs['crews']['archived'] ?></td>
                    <td class="col-actions"><button class="btn btn-secondary disabled">Открыть</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

### Columns specification

| # | Column | Type | Class | Description |
|---|--------|------|-------|-------------|
| 1 | Справочник | text | — | Directory name |
| 2 | Всего | integer | `.col-num` | COUNT(*) |
| 3 | Активных | integer | `.col-num` | COUNT(status='active') |
| 4 | Архивированных | integer | `.col-num` | COUNT(status='archived') |
| 5 | Действия | — | `.col-actions` | «Открыть» (disabled — SUPERADMIN не управляет локальными справочниками) |

### Кнопки «Открыть» — disabled

SUPERADMIN не управляет локальными справочниками напрямую. Это мониторинг. Кнопки помечены как `.disabled` — визуально понятно, что действие недоступно. На будущее: могут быть заменены на ссылки в локальные разделы.

---

## 4. Data source

```php
// 1. Загрузить company → если нет: company not found
$company = fetchCompany($id);

// 2. Подключиться к локальной БД через db_identifier
$localPdo = connectToLocalDb($company['db_identifier']);

// 3. Для каждого справочника:
$tables = ['clients', 'contractors', 'drivers', 'vehicles', 'crews'];
$dirs = [];
foreach ($tables as $table) {
    $stmt = $localPdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
        FROM {$table}
    ");
    $stmt->execute();
    $dirs[$table] = $stmt->fetch();
}
```

---

## 5. Состояния

### Company not found
```html
<div class="notice warn">
    Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
</div>
```

### DB error (центральная или локальная)
```html
<div class="notice danger">
    Ошибка подключения к базе данных. Попробуйте позже.
</div>
```

### Local DB unavailable
```html
<div class="notice warn">
    Локальная БД компании недоступна. Данные справочников не могут быть загружены.
</div>
```

### Empty (нормально)
Все counts = 0. Таблица показывается с нулевыми значениями. Это не empty state — данные есть (просто counts нулевые).

---

## 6. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context |
| CORE-05 | Page header | page-head |
| CORE-08 | Panel | Table container |
| CORE-13 | Data table / ERP grid | Directories count table |
| CORE-18 | Secondary button (disabled) | «Открыть» disabled |
| CORE-19 | Ghost button | «← К карточке» |
| CORE-23 | Disabled action | «Открыть» buttons |
| CORE-33 | Warning notice | DB errors |

**COMPOSITE pattern:** PATTERN-01 Table-only registry (read-only monitoring)

---

## 7. MODULE USAGE DECISIONS

- **Main page purpose:** Read-only monitoring of company directory counts.
- **Primary work object:** Directories table with counts.
- **Main layout selected:** table-only (PATTERN-01).
- **Primary action:** Navigation back («← К карточке»).
- **Table required:** YES — counts presented as table.
- **Form required:** NO — read-only.
- **Inspector required:** NO.
- **Filters required:** NO — only 5 rows.
- **Modal required:** NO.

---

## 8. SOURCE MAPPING

| # | UI element | CORE module ID | Required classes | Forbidden alternatives |
|---|------------|----------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | `.page-head h1` | Demo title |
| 5 | Ghost action | CORE-19 | `.btn-ghost` | Ghost danger |
| 6 | Panel | CORE-08 | `.panel` | Decorative cards |
| 7 | Table | CORE-13 | `.tbl`, `.tbl-wrap`, `.col-num`, `.col-actions` | Bootstrap table |
| 8 | Disabled button | CORE-23 | `.disabled` | Looking clickable |
| 9 | Notice warn | CORE-33 | `.notice.warn` | Bootstrap alert-warning |
| 10 | Notice danger | CORE-33 | `.notice.danger` | Bootstrap alert-danger |

---

## 9. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes | `.panel-body` (10px) | `h3.panel-head-title` |
| `.tbl-wrap` | `.tbl` | — | — | — | sticky thead |

---

## 10. Coder implementation checklist

- [ ] Создать view `superadmin_company_directories.php`
- [ ] Реализовать загрузку counts из локальной БД (5 таблиц)
- [ ] Реализовать таблицу с 5 строками × 4 колонками
- [ ] Кнопки «Открыть» — disabled
- [ ] Обработать company not found
- [ ] Обработать local DB unavailable
- [ ] Добавить маршрут `GET /superadmin/companies/{id}/directories`
- [ ] `php -l` OK

---

## 11. QA formal checklist

- [ ] GET /superadmin/companies/{id}/directories → таблица counts
- [ ] 5 справочников: Клиенты, Подрядчики, Водители, Транспорт, Экипажи
- [ ] Counts корректны (total, active, archived)
- [ ] Кнопки «Открыть» disabled
- [ ] «← К карточке» работает
- [ ] Company not found → warn notice
- [ ] Local DB unavailable → warn notice
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 12. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}/directories`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
