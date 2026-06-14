# UI PAGE HANDOFF — SUPERADMIN Companies Registry

## Status
**HANDOFF_READY** (v2.0 — expanded registry). Supersedes v1.0 (FUNCTIONALLY ACCEPTED). This is a production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

All foundation parameters are COMPLIANT per `superadmin-dashboard.md §0a`. Shell grid, topbar (светлый, 38px, brand+crumbs+user), sidebar (тёмный, 224px, nav-groups), nav items (34px, 600, 12.5px, SVG icons 16×16, ::before active) — all COMPLIANT.

**FOUNDATION STATUS: COMPLIANT. NO REBUILD NEEDED.**

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr` | `main.php` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | `main.php` | YES |
| Topbar border-bottom | `1px solid var(--line)` | `main.php` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | `main.php` | YES |
| Nav item height | 34px | `main.php` | YES |
| Nav item font-weight | 600 ВСЕГДА | `main.php` | YES |
| Nav item font-size | 12.5px | `main.php` | YES |
| Nav active state | `::before` pseudo (2px gold left) | `main.php` | YES |

### 0b. Sidebar IA

```
ОПЕРАЦИИ
  Рейсы        disabled
  Водители     disabled
  Транспорт    disabled
  Клиенты      disabled
[spacer]
СИСТЕМА
  SUPERADMIN   is-active
[bottom]
  Настройки    disabled
```

### 0c. Topbar context

```
SUPERADMIN — Реестр компаний
```

---

## 1. Страница

- **Route:** `/superadmin/companies`
- **View file:** `app/View/pages/superadmin_companies.php` (существующий — ОБНОВИТЬ)
- **Тип:** PATTERN-01 Table-only registry (extended)
- **Роль:** SUPERADMIN
- **Главная задача:** Просмотр полного реестра компаний, быстрые действия по статусу, навигация к управлению
- **Что нельзя менять:** main.php shell/sidebar/topbar, DB naming от ID, business logic

### Дополнительные роуты (статусные действия):

- `POST /superadmin/companies/{id}/activate`
- `POST /superadmin/companies/{id}/block`
- `POST /superadmin/companies/{id}/archive`

---

## 2. Layout

- App shell: СУЩЕСТВУЮЩИЙ (main.php) — НЕ МЕНЯТЬ
- Структура страницы сверху вниз:
  1. `page-head` — заголовок + кнопка «Создать экспедитора»
  2. `filters-bar` — поиск по названию/ИНН (text), статус (select), provisioning status (select)
  3. `panel` с таблицей компаний (расширенные колонки)
  4. `pagination` (если записей > 1 страницы)

---

## 3. Page head

```html
<div class="page-head">
    <div>
        <h1>Реестр компаний</h1>
        <p class="text-muted">Центральный реестр локальных ERP-систем</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
    </div>
</div>
```

---

## 4. Filters / toolbar

```html
<div class="filters-bar">
    <input type="text" class="field-input" placeholder="Поиск по названию или ИНН" name="search" style="max-width:240px">
    <select class="field-select" name="status" style="max-width:150px">
        <option value="">Все статусы</option>
        <option value="active">Активен</option>
        <option value="inactive">Неактивен</option>
        <option value="blocked">Заблокирован</option>
        <option value="archived">Архивирован</option>
        <option value="provisioning">Настройка</option>
        <option value="error">Ошибка</option>
    </select>
    <select class="field-select" name="provisioning" style="max-width:160px">
        <option value="">Все provisioning</option>
        <option value="active">active</option>
        <option value="inactive">inactive</option>
        <option value="blocked">blocked</option>
        <option value="archived">archived</option>
        <option value="error">error</option>
        <option value="provisioning">provisioning</option>
    </select>
    <button class="btn btn-toolbar">Сбросить</button>
</div>
```

**Примечание:** Фильтры могут быть реализованы как client-side или deferred, но UI-скелет обязателен. В v2.0 ожидается server-side фильтрация при наличии времени.

---

## 5. ROW ACTIONS CLASSIFICATION (D1, D2 — MANDATORY)

Every table row action MUST be classified according to the ACTION CLASSIFICATION TAXONOMY (Decision D2).
The coder MUST use the exact classes specified below. The coder MUST NOT use `.btn-ghost` for DESTRUCTIVE actions.

### Action classification taxonomy for SUPERADMIN

| Class | Label | Visual class | Confirm | Examples |
|-------|-------|-------------|---------|----------|
| NAVIGATION | Link to another entity page | `.btn-ghost` (compact) | NO | "Карточка", "Руководитель", "Пользователи" |
| EDIT | Edit entity data | `.btn-ghost` (compact) | NO | "Редактировать" |
| STATE_CHANGE | Change entity status | `.btn-ghost` (compact) | `confirm()` | "Активировать", "Заблокировать", "Деактивировать" |
| DESTRUCTIVE | Irreversible data change | `.btn-danger` (compact) | `confirm()` | "Архивировать", "Отозвать" |
| SECURITY | Credential-affecting action | `.btn-ghost` (compact) | `confirm()` | "Сбросить пароль" |

### Visual differentiation

| Action class | Button class | Background | Border | Text color | Hover |
|-------------|-------------|------------|--------|------------|-------|
| NAVIGATION | `.btn-ghost` | transparent | transparent | `--text-muted` | subtle surface |
| EDIT | `.btn-ghost` | transparent | transparent | `--text-muted` | subtle surface |
| STATE_CHANGE | `.btn-ghost` | transparent | transparent | `--text-muted` | subtle surface |
| DESTRUCTIVE | `.btn-danger` | `--danger-bg` (#fcecea) | #ca8880 | `--danger` (#992e26) | darker danger bg |
| SECURITY | `.btn-ghost` | transparent | transparent | `--text-muted` | subtle surface |

### Coder MUST
- Use `.btn-danger` for ALL destructive row actions (archive, revoke, hard delete)
- Use `.btn-ghost` for ALL navigation, edit, state-change, and security actions
- Wrap destructive/state-change/security actions in `<form onsubmit="return confirm('...')">`
- Compact styling: `font-size:11px;padding:2px 6px;min-height:22px` (already in app.css `.row-actions .btn-ghost, .row-actions .btn-danger`)

### Coder MUST NOT
- Use `.btn-ghost` for destructive actions (archive, revoke, delete)
- Use `.btn-danger` for non-destructive actions (edit, navigate, activate, block)
- Use pseudo-icons `[V]`, `[E]`, `[O]`, `[U]`, `[✓]`, `[⊗]`, `[A]`, `[P]` — use text labels
- Use emoji as action icons

### Row actions reference for companies registry

| Label | Class | Action class | Route | Confirm |
|-------|-------|-------------|-------|---------|
| Карточка | NAVIGATION | `.btn-ghost` | GET `/superadmin/companies/{id}` | NO |
| Редактировать | EDIT | `.btn-ghost` | GET `/superadmin/companies/{id}/edit` | NO |
| Руководитель | NAVIGATION | `.btn-ghost` | GET `/superadmin/companies/{id}/owner` | NO |
| Пользователи | NAVIGATION | `.btn-ghost` | GET `/superadmin/companies/{id}/users` | NO |
| Активировать | STATE_CHANGE | `.btn-ghost` | POST `/superadmin/companies/{id}/activate` | YES |
| Заблокировать | STATE_CHANGE | `.btn-ghost` | POST `/superadmin/companies/{id}/block` | YES |
| Деактивировать | STATE_CHANGE | `.btn-ghost` | POST `/superadmin/companies/{id}/deactivate` | YES |
| Архивировать | DESTRUCTIVE | `.btn-danger` | POST `/superadmin/companies/{id}/archive` | YES |

---

## 6. Main table (расширенная — UPDATED row actions)

```html
<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Компании</h3>
        <span class="badge"><?= $totalCount ?> записей</span>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>ИНН</th>
                    <th>Статус</th>
                    <th>Provisioning</th>
                    <th>Локальная БД</th>
                    <th>Руководитель</th>
                    <th>Пользователей</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-mono"><?= $c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="col-mono"><?= e($c['inn']) ?></td>
                    <td><?= statusBadge($c['status']) ?></td>
                    <td><?= provisioningBadge($c['status'], $c['db_identifier']) ?></td>
                    <td class="col-mono col-muted"><?= e($c['db_identifier'] ?? '—') ?></td>
                    <td>
                        <?php if (in_array($c['status'], ['error', 'provisioning'], true)): ?>
                            <span class="col-muted">—</span>
                        <?php elseif (!empty($c['owner_name'])): ?>
                            <span class="dot" style="background:var(--success)"></span>
                            <?= e($c['owner_name']) ?>
                        <?php else: ?>
                            <a href="/superadmin/companies/<?= $c['id'] ?>/create-owner" class="btn btn-primary" style="font-size:11px;padding:2px 10px">
                                Создать
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono"><?= (int)($c['user_count'] ?? 0) ?></td>
                    <td class="col-muted"><?= e($c['created_at'] ?? '') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <!-- NAVIGATION: Карточка -->
                            <a href="/superadmin/companies/<?= $c['id'] ?>" class="btn btn-ghost">Карточка</a>
                            <!-- EDIT: Редактировать -->
                            <a href="/superadmin/companies/<?= $c['id'] ?>/edit" class="btn btn-ghost">Редактировать</a>
                            <!-- NAVIGATION: Руководитель -->
                            <a href="/superadmin/companies/<?= $c['id'] ?>/owner" class="btn btn-ghost">Руководитель</a>
                            <!-- NAVIGATION: Пользователи -->
                            <a href="/superadmin/companies/<?= $c['id'] ?>/users" class="btn btn-ghost">Пользователи</a>
                            <?php if ($c['status'] !== 'active'): ?>
                            <!-- STATE_CHANGE: Активировать -->
                            <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/activate" style="display:inline" onsubmit="return confirm('Активировать компанию?')">
                                <button type="submit" class="btn btn-ghost">Активировать</button>
                            </form>
                            <?php endif; ?>
                            <?php if (in_array($c['status'], ['active', 'inactive'], true)): ?>
                            <!-- STATE_CHANGE: Заблокировать -->
                            <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать компанию?')">
                                <button type="submit" class="btn btn-ghost">Заблокировать</button>
                            </form>
                            <?php endif; ?>
                            <!-- DESTRUCTIVE: Архивировать -->
                            <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                                <button type="submit" class="btn btn-danger">Архивировать</button>
                            </form>
                            <?php if ($c['status'] === 'active'): ?>
                            <!-- STATE_CHANGE: Деактивировать -->
                            <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/deactivate" style="display:inline" onsubmit="return confirm('Отключить компанию?')">
                                <button type="submit" class="btn btn-ghost">Отключить</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- pagination if needed -->
</div>
```

### Columns specification

| # | Column | Type | Class | Description |
|---|--------|------|-------|-------------|
| 1 | ID | integer | `.col-mono` | Company ID |
| 2 | Название | text | — | Company name |
| 3 | ИНН | text | `.col-mono` | Tax ID |
| 4 | Статус | badge | — | `.badge-ok` / `.badge-warn` / `.badge-danger` / `.badge` |
| 5 | Provisioning | badge | — | DB identifier + provisioning status badge |
| 6 | Локальная БД | text | `.col-mono`, `.col-muted` | db_identifier value or «—» |
| 7 | Руководитель | text/action | — | Owner name + green dot OR «Создать» button OR «—» |
| 8 | Пользователей | integer | `.col-mono` | owner (1) + logists count from local DB |
| 9 | Создан | datetime | `.col-muted` | created_at |
| 10 | Действия | — | `.col-actions` | Row actions (V/E/O/U + status buttons) |

### Данные для колонки «Пользователей»

```php
// Для каждой компании:
// 1. SELECT COUNT(*) as owner_count FROM company_users WHERE company_id=? AND role='company_owner'
// 2. Подключиться к локальной БД через db_identifier
//    SELECT COUNT(*) as logist_count FROM users WHERE role_code='logist'
// 3. $c['user_count'] = $owner_count + $logist_count
```

---

## 7. Status badges (расширенные)

| Status | Badge class | Dot | Text |
|--------|------------|-----|------|
| `active` | `.badge-ok` | yes | Активен |
| `inactive` | `.badge` | yes | Неактивен |
| `blocked` | `.badge-danger` | yes | Заблокирован |
| `archived` | `.badge` | yes | Архивирован |
| `provisioning` | `.badge-warn` | yes | Настройка |
| `error` | `.badge-danger` | yes | Ошибка |

```php
function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'     => ['class' => '',             'label' => 'Архивирован'],
        'provisioning' => ['class' => 'badge-warn',   'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger', 'label' => 'Ошибка'],
        'suspended'    => ['class' => 'badge-warn',   'label' => 'Приостановлен'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}
```

### Provisioning badge (отдельная функция)

```php
function provisioningBadge(string $status, ?string $dbIdentifier): string
{
    if (!empty($dbIdentifier)) {
        $label = e($dbIdentifier);
    } else {
        $label = '—';
    }
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'active'],
        'inactive'     => ['class' => '',             'label' => 'inactive'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'blocked'],
        'archived'     => ['class' => '',             'label' => 'archived'],
        'error'        => ['class' => 'badge-danger', 'label' => 'error'],
        'provisioning' => ['class' => 'badge-warn',   'label' => 'provisioning'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '" style="margin-left:4px"><span class="dot"></span>' . e($item['label']) . '</span>';
    // Above row also shows db_identifier as text before the provisioning badge
}
```

---

## 8. Status action routes (NEW)

### POST /superadmin/companies/{id}/activate

```php
// Обработчик:
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='active', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies
// Только если текущий статус не active
```

### POST /superadmin/companies/{id}/block

```php
// Обработчик:
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='blocked', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies
// Только если текущий статус active
```

### POST /superadmin/companies/{id}/archive

```php
// Обработчик:
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='archived', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies
// Всегда доступно, confirm перед POST
// НЕ удаляет локальную БД, storage, company_users — только статус
```

---

## 9. Creation form (существующая)

**Route:** `/superadmin/companies/create`
**View file:** `app/View/pages/superadmin_companies_create.php`
**Без изменений** относительно v1.0 handoff. Полная спецификация формы сохранена из предыдущей версии.

Краткий перечень: name*, inn*, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, comments.

---

## 10. Empty state (CORE-34)

```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="text-muted">Нет созданных компаний</p>
            <p class="text-muted">Создайте первого экспедитора для начала работы системы.</p>
            <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
        </div>
    </div>
</div>
```

---

## 11. Error states

### Page load error
```html
<div class="notice warn">
    Не удалось загрузить список компаний. Попробуйте позже.
</div>
```

### DB error (центральная или локальная)
```html
<div class="notice danger">
    Ошибка подключения к базе данных. <?= e($errorMessage) ?>
</div>
```

### Success after status change
```html
<div class="notice success">
    Статус компании изменён.
</div>
```

---

## 12. Loading state (CORE-35)

Skeleton rows for table while data loads:
```html
<div class="tbl-wrap">
    <table class="tbl">
        <tbody>
            <?php for ($i = 0; $i < 5; $i++): ?>
            <tr><td colspan="10"><div class="sk" style="width:<?= rand(60,95) ?>%"></div></td></tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>
```

---

## 13. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | Existing — SUPERADMIN is-active |
| CORE-03 | Topbar | Existing — context «Реестр компаний» |
| CORE-05 | Page header (page-head) | Title + «Создать экспедитора» |
| CORE-06 | Page actions | «Создать экспедитора» btn-primary |
| CORE-08 | Panel | Table container |
| CORE-11 | Filter toolbar (filters-bar) | Search + status select |
| CORE-12 | Search field | Search input |
| CORE-13 | Data table / ERP grid | Companies table |
| CORE-14 | Table row states | Row hover |
| CORE-15 | Table row actions | Classified row actions per D1/D2 |
| CORE-16 | Pagination | When needed |
| CORE-17 | Primary button | «Создать экспедитора» |
| CORE-18 | Secondary button | Navigation links |
| CORE-19 | Ghost button | NAVIGATION / EDIT / STATE_CHANGE / SECURITY row actions |
| CORE-20 | Toolbar button | «Применить» / «Сбросить» filters |
| CORE-22 | Danger button | DESTRUCTIVE row actions («Архивировать») |
| CORE-24 | Status badge | Company + provisioning statuses |
| CORE-26 | Form field | Creation form fields |
| CORE-27 | Form section | Creation form sections |
| CORE-28 | Validation/error | Required field errors |
| CORE-32 | Notice | System messages, success |
| CORE-33 | Warning notice | DB errors |
| CORE-34 | Empty state | No companies |
| CORE-35 | Loading skeleton | Table loading |

**COMPOSITE pattern:** PATTERN-01 Table-only registry (extended with action-classified row actions)

---

## 14. MODULE USAGE DECISIONS

- **Main page purpose:** Central registry of all local ERP systems with monitoring and quick status actions.
- **Primary work object:** Companies table with 10 columns.
- **Main layout selected:** table-only (PATTERN-01).
- **Primary action:** «Создать экспедитора» (CORE-17 btn-primary).
- **Secondary actions:** Row-level classified actions per D2 taxonomy:
  - NAVIGATION: «Карточка», «Руководитель», «Пользователи» (`.btn-ghost`)
  - EDIT: «Редактировать» (`.btn-ghost`)
  - STATE_CHANGE: «Активировать», «Заблокировать», «Деактивировать» (`.btn-ghost` + `confirm()`)
  - DESTRUCTIVE: «Архивировать» (`.btn-danger` + `confirm()`)
- **Table required:** YES — central monitoring view.
- **Form required:** NO on this page (creation is separate page, unchanged).
- **Inspector required:** NO — detail is via navigation to company card.
- **Filters required:** YES — search by name/INN + status select.
- **Modal required:** NO — confirm via JS confirm() for status/security/destructive actions.
- **Modules explicitly not used:** CORE-30 Inspector (detail via card page, not inline), CORE-36 Modal (simple confirm for now).

---

## 15. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item, .nav-item:hover, .nav-item.is-active | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | Production CSS > .page-head | `.page-head h1` | Demo title |
| 5 | Primary action | CORE-06, CORE-17 | Button Decision Matrix > Primary | `.btn-primary` | Multiple primaries |
| 6 | Filter bar | CORE-11 | CORE-11 module card > .filters-bar | `.filters-bar`, `.field-input`, `.field-select` | Giant filter cards |
| 7 | Search field | CORE-12 | CORE-12 module card | `.field-input` | Decorative topbar search |
| 8 | Table | CORE-13 | Production CSS > .tbl, .tbl-wrap | `.panel`, `.tbl-wrap`, `.tbl` | Bootstrap table, cards |
| 9 | Table row | CORE-14 | CORE-14 module card | `tr` hover | Bright hover colors |
| 10 | Row actions (NAVIGATION/EDIT/STATE_CHANGE/SECURITY) | CORE-19 | Button Decision Matrix > Ghost | `.btn-ghost` (in `.row-actions`) | Using `.btn-danger` for non-destructive |
| 11 | Row actions (DESTRUCTIVE: Архивировать) | CORE-22 | Button Decision Matrix > Danger | `.btn-danger` (in `.row-actions`) | Using `.btn-ghost` for destructive |
| 12 | Status badge (active) | CORE-24 | Production CSS > .badge-ok | `.badge-ok`, `.dot` | Bootstrap alert-success |
| 12 | Status badge (provisioning) | CORE-24 | Production CSS > .badge-warn | `.badge-warn`, `.dot` | Bootstrap alert-warning |
| 13 | Status badge (error/blocked) | CORE-24 | Production CSS > .badge-danger | `.badge-danger`, `.dot` | Bootstrap alert-danger |
| 14 | Status badge (archived) | CORE-24 | Production CSS > .badge (neutral) | `.badge`, `.dot` | Random color |
| 15 | Empty state | CORE-34 | CORE-34 module card > .empty-state | `.empty-state`, `.btn-primary` | Blank workspace |
| 16 | Notice success | CORE-32 | Production CSS > .notice | `.notice.success` | Bootstrap alert |
| 17 | Notice warn | CORE-33 | Production CSS > .notice.warn | `.notice.warn` | Bootstrap alert-warning |
| 18 | Notice danger | CORE-33 | Production CSS > .notice | `.notice.danger` | Bootstrap alert-danger |
| 19 | Toolbar button | CORE-20 | Button Decision Matrix > Toolbar | `.btn-toolbar` | Normal .btn in toolbar |
| 20 | Ghost button (page-level) | CORE-19 | Button Decision Matrix > Ghost | `.btn-ghost` | Ghost danger action |

---

## 16. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------|-------------|-----------------|
| `.panel` | `.panel-head` | 0 (by rule) | Yes — head flush to top | `.panel-body` (10px) | `--line-hair` | `h3.panel-head-title` |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | — | text-muted, btn |
| `.filters-bar` | `.field-input` | — | — | Internal bar padding | — | focus, placeholder |
| `.filters-bar` | `.field-select` | — | — | Internal bar padding | — | focus |
| `.tbl-wrap` | `.tbl` | — | — | — | `--line-hair` | sticky thead, row hover |
| `.row-actions` | `.btn-ghost` | — | — | — | `--line-hair` | hover: subtle surface; compact: font-size 11px, padding 2px 6px, min-height 22px |
| `.row-actions` | `.btn-danger` | — | — | — | `--danger` (#ca8880) | hover: darker danger bg; compact: same as .btn-ghost |
| `.nav-item` | `.nav-icon` | — | — | — | `--nav-gold` on active | hover: opacity .7, active: .85 |

---

## 17. Strict prohibitions for coder

- Не менять `main.php` shell/sidebar/topbar
- Не придумывать новые CSS-классы без source в Core Kit
- Не использовать `slug/key` для DB/storage генерации
- Не хранить DB-пароли в БД
- Не коммитить `.env` и секреты
- Не удалять локальную БД при archive/block
- Не удалять storage при archive/block
- Не удалять company_users при смене статуса
- **НЕ ИСПОЛЬЗОВАТЬ `.btn-ghost` для DESTRUCTIVE действий (archive)** — только `.btn-danger`
- **НЕ ИСПОЛЬЗОВАТЬ `.btn-danger` для НЕ-DESTRUCTIVE действий** — только `.btn-ghost`
- **НЕ ИСПОЛЬЗОВАТЬ псевдоиконки** `[V]`, `[E]`, `[O]`, `[U]`, `[✓]`, `[⊗]`, `[A]`, `[P]` — использовать текстовые метки
- Demo-placeholder UI запрещён (псевдоиконки, SaaS-dashboard, debug badges)
- Не использовать emoji как иконки
- `border-radius` ≤ 4px для новых элементов
- `box-shadow` blur ≤ 8px для новых элементов

---

## 18. Coder implementation checklist

- [ ] Обновить view `superadmin_companies.php`: расширенные колонки (10 вместо 7)
- [ ] **Заменить все row actions на classified buttons per D2:**
  - [ ] «Карточка», «Редактировать», «Руководитель», «Пользователи» → `.btn-ghost` (NAVIGATION/EDIT)
  - [ ] «Активировать», «Заблокировать», «Деактивировать» → `.btn-ghost` + `confirm()` (STATE_CHANGE)
  - [ ] «Архивировать» → `.btn-danger` + `confirm()` (DESTRUCTIVE)
- [ ] **Заменить псевдоиконки [V][E][O][U][✓][⊗][A] на текстовые метки**
- [ ] Добавить колонку «Локальная БД» (db_identifier)
- [ ] Добавить колонку «Provisioning» (provisioningBadge)
- [ ] Добавить колонку «Пользователей» (owner_count + logist_count)
- [ ] Добавить фильтр «provisioning status» в filters-bar
- [ ] Реализовать loading skeleton для таблицы
- [ ] Реализовать маршруты `POST /superadmin/companies/{id}/activate`
- [ ] Реализовать маршруты `POST /superadmin/companies/{id}/block`
- [ ] Реализовать маршруты `POST /superadmin/companies/{id}/archive`
- [ ] Реализовать маршруты `POST /superadmin/companies/{id}/deactivate`
- [ ] Добавить `confirm()` на статусные действия
- [ ] Обновить `statusBadge()` — добавить inactive/blocked/archived
- [ ] Создать `provisioningBadge()` функцию
- [ ] Соответствовать этому handoff по структуре, классам и action classification
- [ ] Не менять main.php
- [ ] Не добавлять новых CSS-классов без source в Core Kit
- [ ] `php -l` для всех изменённых PHP — OK

---

## 19. QA formal checklist

- [ ] Страница `/superadmin/companies` открывается с расширенными колонками
- [ ] Колонка «ID» видна и корректна
- [ ] Колонка «Название» видна и кликабельна в карточку
- [ ] Колонка «ИНН» видна
- [ ] Колонка «Статус» показывает badge с dot
- [ ] Колонка «Provisioning» показывает db_identifier + provisioning badge
- [ ] Колонка «Локальная БД» показывает db_identifier или «—»
- [ ] Колонка «Руководитель» показывает имя + dot или «Создать» или «—»
- [ ] Колонка «Пользователей» показывает число (owner + logists)
- [ ] Колонка «Создан» показывает дату
- [ ] Колонка «Действия» содержит V/E/O/U кнопки
- [ ] Кнопка «Карточка» использует `.btn-ghost` (NAVIGATION)
- [ ] Кнопка «Редактировать» использует `.btn-ghost` (EDIT)
- [ ] Кнопка «Руководитель» использует `.btn-ghost` (NAVIGATION)
- [ ] Кнопка «Пользователи» использует `.btn-ghost` (NAVIGATION)
- [ ] Кнопка «Активировать» использует `.btn-ghost` (STATE_CHANGE) + confirm
- [ ] Кнопка «Заблокировать» использует `.btn-ghost` (STATE_CHANGE) + confirm
- [ ] Кнопка «Архивировать» использует `.btn-danger` (DESTRUCTIVE) + confirm
- [ ] Нет псевдоиконок [V][E][O][U][✓][⊗][A] — только текст
- [ ] Нет `.btn-danger` на не-destructive действиях
- [ ] Нет `.btn-ghost` на destructive действиях
- [ ] Фильтр «Все статусы» работает
- [ ] Фильтр «Все provisioning» работает
- [ ] Фильтр поиска работает (client-side или server-side)
- [ ] Empty state показывает «Нет созданных компаний»
- [ ] Кнопка «Создать экспедитора» в page-head работает
- [ ] Кнопка «Сбросить» в filters-bar работает
- [ ] Shell не сломан (main.php не изменён)
- [ ] Нет новых CSS-классов вне Core Kit
- [ ] Нет секретов в git diff
- [ ] `php -l` для всех изменённых PHP — OK
- [ ] Все предыдущие SUPERADMIN-маршруты работают

---

## 20. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies`
- **Что владелец должен проверить глазами:**
  - [ ] Industrial Graphite + Warm Accent сохранён.
  - [ ] Таблица плотная, читаемая, 10 колонок.
  - [ ] Статусные бейджи с точками.
  - [ ] Кнопки действий видны на hover строки.
  - [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`.
  - [ ] Нет emoji как иконок.
  - [ ] Нет demo-placeholder/SaaS-dashboard вида.
  - [ ] Нет больших пустот.
  - [ ] Нет случайных цветов вне утверждённой палитры.
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO

---

## 20. Design notes

- Handoff v2.0 расширяет v1.0 (FUNCTIONALLY ACCEPTED). Сохранена обратная совместимость с созданием экспедитора.
- Provisioning badge показывает как статус provisioning, так и db_identifier.
- Колонка «Пользователей» требует запроса к двум БД (центральной и локальной). Падение локальной БД не должно ломать всю таблицу — показывать «—» или 0.
- Фильтры могут быть client-side (JS filter по загруженным данным) на v2.0 с deferred server-side фильтрацией.
- Статусные кнопки (активировать/заблокировать/архивировать) показываются условно в зависимости от текущего статуса.
- Row actions скрыты до hover (стандарт CORE-15).
