# UI PAGE HANDOFF — SUPERADMIN Company Documents

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
SUPERADMIN — Документы компании: [company.name]
```

---

## 1. Страница

- **Route:** `GET /superadmin/companies/{id}/documents`
- **View file:** `app/View/pages/superadmin_company_documents.php` (НОВЫЙ)
- **Тип:** PATTERN-01 Table-only registry (read-only monitoring)
- **Роль:** SUPERADMIN
- **Главная задача:** Мониторинг документов компании (read-only, просмотр + скачивание)
- **Что нельзя менять:** local DB data, storage files, main.php

---

## 2. Page head

```html
<div class="page-head">
    <div>
        <h1>Документы компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

---

## 3. Documents table

```html
<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Документы</h3>
        <span class="badge"><?= $totalCount ?> документов</span>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Тип сущности</th>
                    <th>ID сущности</th>
                    <th>Имя файла</th>
                    <th class="col-num">Размер</th>
                    <th>Тип</th>
                    <th>Статус</th>
                    <th>Загружен</th>
                    <th>Кем</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $d): ?>
                <tr>
                    <td class="col-mono"><?= $d['id'] ?></td>
                    <td><?= e($d['entity_type']) ?></td>
                    <td class="col-mono"><?= (int)$d['entity_id'] ?></td>
                    <td><?= e($d['original_name']) ?></td>
                    <td class="col-num col-mono"><?= formatFileSize($d['file_size']) ?></td>
                    <td><?= e($d['mime_type']) ?></td>
                    <td><?= docStatusBadge($d['status']) ?></td>
                    <td class="col-muted"><?= e($d['created_at']) ?></td>
                    <td><?= e($d['uploaded_by_role'] ?? '—') ?> #<?= (int)($d['uploaded_by_user_id'] ?? 0) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/documents/download?id=<?= $d['id'] ?>&company_id=<?= $id ?>" class="ra" title="Скачать">↓</a>
                        </div>
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
| 1 | ID | integer | `.col-mono` | Document ID |
| 2 | Тип сущности | text | — | entity_type (client, contractor, driver, etc.) |
| 3 | ID сущности | integer | `.col-mono` | entity_id |
| 4 | Имя файла | text | — | original_name |
| 5 | Размер | integer | `.col-num`, `.col-mono` | file_size (formatted: KB/MB) |
| 6 | Тип | text | — | mime_type |
| 7 | Статус | badge | — | document status |
| 8 | Загружен | datetime | `.col-muted` | created_at |
| 9 | Кем | text | — | uploaded_by_role + uploaded_by_user_id |
| 10 | Действия | — | `.col-actions` | Download button |

---

## 4. Document status badge

```php
function docStatusBadge(string $status): string
{
    $map = [
        'active'   => ['class' => 'badge-ok',   'label' => 'Активен'],
        'archived' => ['class' => '',            'label' => 'Архивирован'],
        'verified' => ['class' => 'badge-ok',   'label' => 'Проверен'],
        'review'   => ['class' => 'badge-warn',  'label' => 'На проверке'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
```

---

## 5. Download security

Скачивание через роут `/company/documents/download?id=N&company_id={id}`.

**Проверки безопасности:**
- `realpath()` проверка — storage вне public/
- No path traversal (запрещены `..`, `/`, `\` в stored_name)
- Проверка существования файла перед отдачей
- Content-Disposition: attachment
- Content-Type из mime_type в БД
- SUPERADMIN имеет доступ ко всем документам всех компаний (через company_id параметр)

**Примечание:** текущий download route (`/company/documents/download`) работает в контексте сессии пользователя. Для SUPERADMIN-мониторинга может потребоваться SUPERADMIN-specific download route или параметр `company_id` + проверка SUPERADMIN-роли.

---

## 6. Data source

```php
// 1. Загрузить company → если нет: company not found
$company = fetchCompany($id);

// 2. Подключиться к локальной БД
$localPdo = connectToLocalDb($company['db_identifier']);

// 3. Загрузить документы
$stmt = $localPdo->prepare("
    SELECT id, entity_type, entity_id, original_name, stored_name, 
           file_size, mime_type, status, created_at, 
           uploaded_by_user_id, uploaded_by_role
    FROM documents 
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute();
$documents = $stmt->fetchAll();
$totalCount = count($documents);
```

---

## 7. Состояния

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
    Локальная БД компании недоступна. Документы не могут быть загружены.
</div>
```

### Empty (нет документов)
```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="text-muted">Нет документов</p>
            <p class="text-muted">В компании ещё не загружены документы.</p>
        </div>
    </div>
</div>
```

---

## 8. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context |
| CORE-05 | Page header | page-head |
| CORE-08 | Panel | Table container |
| CORE-13 | Data table / ERP grid | Documents table |
| CORE-14 | Table row states | Row hover |
| CORE-15 | Table row actions | Download button |
| CORE-16 | Pagination | When > 100 docs |
| CORE-19 | Ghost button | «← К карточке» |
| CORE-24 | Status badge | Document status |
| CORE-32 | Notice | System messages |
| CORE-33 | Warning notice | DB errors |
| CORE-34 | Empty state | No documents |

**COMPOSITE pattern:** PATTERN-01 Table-only registry (read-only monitoring)

---

## 9. MODULE USAGE DECISIONS

- **Main page purpose:** Read-only monitoring of company documents.
- **Primary work object:** Documents table (10 columns).
- **Main layout selected:** table-only (PATTERN-01).
- **Primary action:** «← К карточке».
- **Secondary actions:** Download per document.
- **Table required:** YES — document list.
- **Form required:** NO — read-only.
- **Inspector required:** NO.
- **Filters required:** NO — can be deferred.
- **Modal required:** NO.

---

## 10. SOURCE MAPPING

| # | UI element | CORE module ID | Required classes | Forbidden alternatives |
|---|------------|----------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | `.page-head h1` | Demo title |
| 5 | Ghost action | CORE-19 | `.btn-ghost` | Ghost danger |
| 6 | Panel | CORE-08 | `.panel` | Decorative cards |
| 7 | Table | CORE-13 | `.tbl`, `.tbl-wrap`, `.col-mono`, `.col-num`, `.col-muted`, `.col-actions` | Bootstrap table |
| 8 | Row actions | CORE-15 | `.row-actions`, `.ra` | Always-visible |
| 9 | Status badge | CORE-24 | `.badge`, `.badge-ok`, `.badge-warn` | Bootstrap alert |
| 10 | Empty state | CORE-34 | `.empty-state` | Blank workspace |
| 11 | Notice warn | CORE-33 | `.notice.warn` | Bootstrap alert-warning |
| 12 | Notice danger | CORE-33 | `.notice.danger` | Bootstrap alert-danger |

---

## 11. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes | `.panel-body` (10px) | `h3.panel-head-title` |
| `.tbl-wrap` | `.tbl` | — | — | — | sticky thead, row hover |
| `.row-actions` | `.ra` | — | — | — | hover |

---

## 12. Coder implementation checklist

- [ ] Создать view `superadmin_company_documents.php`
- [ ] Реализовать загрузку documents из локальной БД
- [ ] Реализовать таблицу с 10 колонками
- [ ] Реализовать docStatusBadge() helper
- [ ] Реализовать formatFileSize() helper
- [ ] Реализовать download link (проверить доступ SUPERADMIN)
- [ ] Обработать company not found
- [ ] Обработать local DB unavailable
- [ ] Обработать empty state
- [ ] Добавить маршрут `GET /superadmin/companies/{id}/documents`
- [ ] Обеспечить безопасность download (realpath, no path traversal)
- [ ] `php -l` OK

---

## 13. QA formal checklist

- [ ] GET /superadmin/companies/{id}/documents → таблица документов
- [ ] Все 10 колонок видны
- [ ] Статусы документов бейджами
- [ ] Размер файла отформатирован (KB/MB)
- [ ] Кнопка «Скачать» работает (проверить download)
- [ ] Download безопасен (storage вне public, no path traversal)
- [ ] «← К карточке» работает
- [ ] Company not found → warn
- [ ] Local DB unavailable → warn
- [ ] Empty state → «Нет документов»
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 14. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}/documents`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO

---

## 15. D6: Entity documents navigation (DEFERRED)

**Problem:** «Документы» links in directory read-only pages (crews, drivers, clients, etc.) go to this general company documents list (`/superadmin/companies/{id}/documents`), not entity-specific documents.

**Decision:** DEFERRED. Current behavior is acceptable for SUPERADMIN monitoring. Entity-specific filtering with `?entity_type=driver&entity_id=123` will be implemented in a future task.

**Current state:** COMPLIANT — general documents page is sufficient for SUPERADMIN monitoring scope.

### D4: confirm() scope for document download
- Document download (`GET /company/documents/download?id=N&company_id={id}`): `confirm()` NOT REQUIRED — this is a READ action (NAVIGATION class). Download is safe and reversible.
