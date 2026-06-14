# UI PAGE HANDOFF — SUPERADMIN Company Access Grants

## Status
**HANDOFF_READY** — новая страница. Production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md §0a`. NO REBUILD NEEDED.

### 0b. Sidebar IA

```
ОПЕРАЦИИ (все disabled) → [spacer] → СИСТЕМА (SUPERADMIN is-active) → [bottom] Настройки disabled
```

### 0c. Topbar context

```
SUPERADMIN — Доступы компании: [company.name]
```

---

## 1. Страница

- **Route:** `GET /superadmin/companies/{id}/access-grants`
- **View file:** `app/View/pages/superadmin_company_access_grants.php` (НОВЫЙ)
- **Тип:** PATTERN-01 Table-only registry (read-only monitoring)
- **Роль:** SUPERADMIN
- **Главная задача:** Мониторинг выданных доступов (entity_access_grants) в компании
- **Что нельзя менять:** local DB data, main.php

---

## 2. Page head

```html
<div class="page-head">
    <div>
        <h1>Доступы компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

---

## 3. Access grants table

```html
<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Доступы</h3>
        <span class="badge"><?= $totalCount ?> доступов</span>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Тип сущности</th>
                    <th>ID сущности</th>
                    <th>Кому (ID)</th>
                    <th>Кому (имя)</th>
                    <th>Кем выдан (ID)</th>
                    <th>Кем выдан (имя)</th>
                    <th>Уровень</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grants as $g): ?>
                <tr>
                    <td class="col-mono"><?= $g['id'] ?></td>
                    <td><?= e($g['entity_type']) ?></td>
                    <td class="col-mono"><?= (int)$g['entity_id'] ?></td>
                    <td class="col-mono"><?= (int)$g['granted_to_user_id'] ?></td>
                    <td><?= e($g['granted_to_name'] ?? '—') ?></td>
                    <td class="col-mono"><?= (int)$g['granted_by_user_id'] ?></td>
                    <td><?= e($g['granted_by_name'] ?? '—') ?></td>
                    <td><?= e($g['access_level']) ?></td>
                    <td class="col-muted"><?= e($g['created_at']) ?></td>
                    <td class="col-actions">
                        <button class="btn btn-secondary disabled" style="font-size:11px;height:22px;padding:0 6px">Отозвать</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- pagination if needed -->
</div>
```

### Columns specification

| # | Column | Type | Class | Description |
|---|--------|------|-------|-------------|
| 1 | ID | integer | `.col-mono` | Grant ID |
| 2 | Тип сущности | text | — | entity_type |
| 3 | ID сущности | integer | `.col-mono` | entity_id |
| 4 | Кому (user_id) | integer | `.col-mono` | granted_to_user_id |
| 5 | Кому (имя) | text | — | full_name из users (JOIN) |
| 6 | Кем выдан (user_id) | integer | `.col-mono` | granted_by_user_id |
| 7 | Кем выдан (имя) | text | — | full_name из users (JOIN) |
| 8 | Уровень | text | — | access_level (обычно 'view') |
| 9 | Создан | datetime | `.col-muted` | created_at |
| 10 | Действия | — | `.col-actions` | «Отозвать» (disabled) |

### Кнопка «Отозвать» — disabled / deferred

**REVOKE DEFERRED** — не реализуется в текущем scope. Кнопка disabled. В handoff записывается: «REVOKE DEFERRED — не реализуется в текущем scope, кнопка disabled». Будет активирована в отдельной задаче.

---

## 4. Data source

```php
// 1. Загрузить company → если нет: company not found
$company = fetchCompany($id);

// 2. Подключиться к локальной БД
$localPdo = connectToLocalDb($company['db_identifier']);

// 3. Загрузить доступы с JOIN для получения имён пользователей
$stmt = $localPdo->prepare("
    SELECT 
        g.id,
        g.entity_type,
        g.entity_id,
        g.granted_to_user_id,
        g.granted_by_user_id,
        g.access_level,
        g.created_at,
        u_to.full_name as granted_to_name,
        u_by.full_name as granted_by_name
    FROM entity_access_grants g
    LEFT JOIN users u_to ON g.granted_to_user_id = u_to.id
    LEFT JOIN users u_by ON g.granted_by_user_id = u_by.id
    ORDER BY g.created_at DESC
    LIMIT 100
");
$stmt->execute();
$grants = $stmt->fetchAll();
$totalCount = count($grants);
```

---

## 5. Состояния

### Company not found
```html
<div class="notice warn">
    Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
</div>
```

### DB error (central or local)
```html
<div class="notice danger">
    Ошибка подключения к базе данных. Попробуйте позже.
</div>
```

### Local DB unavailable
```html
<div class="notice warn">
    Локальная БД компании недоступна. Данные доступов не могут быть загружены.
</div>
```

### Empty (нет доступов)
```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="text-muted">Нет выданных доступов</p>
            <p class="text-muted">В компании ещё не выданы доступы к записям.</p>
        </div>
    </div>
</div>
```

---

## 6. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context |
| CORE-05 | Page header | page-head |
| CORE-08 | Panel | Table container |
| CORE-13 | Data table / ERP grid | Access grants table |
| CORE-14 | Table row states | Row hover |
| CORE-16 | Pagination | When > 100 |
| CORE-19 | Ghost button | «← К карточке» |
| CORE-23 | Disabled action | «Отозвать» (REVOKE DEFERRED) |
| CORE-33 | Warning notice | DB errors |
| CORE-34 | Empty state | No grants |

**COMPOSITE pattern:** PATTERN-01 Table-only registry (read-only monitoring)

---

## 7. MODULE USAGE DECISIONS

- **Main page purpose:** Read-only monitoring of entity access grants.
- **Primary work object:** Access grants table (10 columns).
- **Main layout selected:** table-only (PATTERN-01).
- **Primary action:** «← К карточке».
- **Secondary actions:** «Отозвать» (disabled/deferred).
- **Table required:** YES — grants list.
- **Form required:** NO — read-only.
- **Inspector required:** NO.
- **Filters required:** NO — can be deferred.
- **Modal required:** NO.
- **Modules explicitly not used:** CORE-22 Danger (revoke deferred), CORE-11 Filters.

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
| 7 | Table | CORE-13 | `.tbl`, `.tbl-wrap`, `.col-mono`, `.col-muted`, `.col-actions` | Bootstrap table |
| 8 | Disabled button | CORE-23 | `.disabled` | Looking clickable |
| 9 | Empty state | CORE-34 | `.empty-state` | Blank workspace |
| 10 | Notice warn | CORE-33 | `.notice.warn` | Bootstrap alert-warning |
| 11 | Notice danger | CORE-33 | `.notice.danger` | Bootstrap alert-danger |

---

## 9. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes | `.panel-body` (10px) | `h3.panel-head-title` |
| `.tbl-wrap` | `.tbl` | — | — | — | sticky thead, row hover |

---

## 10. Coder implementation checklist

- [ ] Создать view `superadmin_company_access_grants.php`
- [ ] Реализовать загрузку grants из локальной БД с JOIN users
- [ ] Реализовать таблицу с 10 колонками
- [ ] Кнопка «Отозвать» — disabled
- [ ] Записать в комментарий кода: «REVOKE DEFERRED — не реализуется в текущем scope»
- [ ] Обработать company not found
- [ ] Обработать local DB unavailable
- [ ] Обработать empty state
- [ ] Добавить маршрут `GET /superadmin/companies/{id}/access-grants`
- [ ] `php -l` OK

---

## 11. QA formal checklist

- [ ] GET /superadmin/companies/{id}/access-grants → таблица доступов
- [ ] Все 10 колонок видны
- [ ] Имена пользователей загружены через JOIN
- [ ] Кнопка «Отозвать» disabled
- [ ] «← К карточке» работает
- [ ] Company not found → warn
- [ ] Local DB unavailable → warn
- [ ] Empty state → «Нет выданных доступов»
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 12. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}/access-grants`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
