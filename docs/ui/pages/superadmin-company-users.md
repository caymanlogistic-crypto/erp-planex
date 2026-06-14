# UI PAGE HANDOFF — SUPERADMIN Company Users

## Status
**HANDOFF_READY** (v1.1 — added D1/D2 row action classification). Production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md §0a`. Shell grid, topbar (светлый, 38px, brand+crumbs+user), sidebar (тёмный, 224px), nav items (34px, 600, 12.5px, SVG icons 16×16, ::before active) — all COMPLIANT. NO REBUILD NEEDED.

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: 224px 1fr; grid-template-rows: 38px 1fr` | `main.php` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 | `main.php` | YES |
| Topbar border-bottom | `1px solid var(--line)` | `main.php` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | `main.php` | YES |
| Nav item height | 34px | `main.php` | YES |
| Nav item font-weight | 600 | `main.php` | YES |
| Nav item font-size | 12.5px | `main.php` | YES |
| Nav active state | `::before` pseudo 2px gold | `main.php` | YES |

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
SUPERADMIN — Пользователи компании: [company.name]
```

---

## 1. Страница

- **Route:** `GET /superadmin/companies/{id}/users`
- **View file:** `app/View/pages/superadmin_company_users.php` (НОВЫЙ)
- **Тип:** PATTERN-01 Table-only registry
- **Роль:** SUPERADMIN
- **Главная задача:** Просмотр всех пользователей компании (Руководитель + Логисты) в одной таблице
- **Что нельзя менять:** main.php, permissions model, DB structure

---

## 2. Layout

- App shell: СУЩЕСТВУЮЩИЙ (main.php) — НЕ МЕНЯТЬ
- Структура страницы сверху вниз:
  1. `page-head` — заголовок + кнопка «← К карточке»
  2. `panel` с объединённой таблицей пользователей
  3. `pagination` (если записей > 1 страницы)

---

## 3. Page head

```html
<div class="page-head">
    <div>
        <h1>Пользователи компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $company['id'] ?> · Руководитель + Логисты</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

---

## 4. Main table

```html
<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Пользователи</h3>
        <span class="badge"><?= $totalCount ?> пользователей</span>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Тип</th>
                    <th>ФИО</th>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="col-mono"><?= $u['id'] ?></td>
                    <td>
                        <?php if ($u['type'] === 'owner'): ?>
                        <span class="badge" style="background:var(--accent-bg);color:var(--accent);border-color:var(--accent-line)"><span class="dot"></span>Руководитель</span>
                        <?php else: ?>
                        <span class="badge"><span class="dot"></span>Логист</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($u['full_name']) ?></td>
                    <td class="col-mono"><?= e($u['login']) ?></td>
                    <td><?= e($u['email'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($u['phone'] ?? '—') ?></td>
                    <td><?= e($u['role_label']) ?></td>
                    <td><?= userStatusBadge($u['status']) ?></td>
                    <td class="col-muted"><?= e($u['created_at'] ?? '') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <?php if ($u['type'] === 'owner'): ?>
                            <!-- NAVIGATION: Просмотр -->
                            <a href="/superadmin/companies/<?= $id ?>/owner" class="btn btn-ghost">Просмотр</a>
                            <!-- EDIT: Редактировать -->
                            <a href="/superadmin/companies/<?= $id ?>/owner/edit" class="btn btn-ghost">Редактировать</a>
                            <!-- SECURITY: Сбросить пароль -->
                            <form method="post" action="/superadmin/companies/<?= $id ?>/owner/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль Руководителя?')">
                                <button type="submit" class="btn btn-ghost">Сбросить пароль</button>
                            </form>
                            <?php else: ?>
                            <!-- NAVIGATION: Просмотр -->
                            <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>" class="btn btn-ghost">Просмотр</a>
                            <!-- EDIT: Редактировать -->
                            <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/edit" class="btn btn-ghost">Редактировать</a>
                            <?php if ($u['status'] !== 'active'): ?>
                            <!-- STATE_CHANGE: Активировать -->
                            <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/activate" style="display:inline" onsubmit="return confirm('Активировать логиста?')">
                                <button type="submit" class="btn btn-ghost">Активировать</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($u['status'] === 'active'): ?>
                            <!-- STATE_CHANGE: Заблокировать -->
                            <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать логиста?')">
                                <button type="submit" class="btn btn-ghost">Заблокировать</button>
                            </form>
                            <?php endif; ?>
                            <!-- DESTRUCTIVE: Архивировать -->
                            <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать логиста?')">
                                <button type="submit" class="btn btn-danger">Архивировать</button>
                            </form>
                            <!-- SECURITY: Сбросить пароль -->
                            <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль логиста?')">
                                <button type="submit" class="btn btn-ghost">Сбросить пароль</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

### Columns specification

| # | Column | Type | Class | Description |
|---|--------|------|-------|-------------|
| 1 | ID | integer | `.col-mono` | User ID |
| 2 | Тип | badge | — | «Руководитель» (accent badge) или «Логист» (neutral badge) |
| 3 | ФИО | text | — | full_name |
| 4 | Логин | text | `.col-mono` | login |
| 5 | Email | text | — | email или «—» |
| 6 | Телефон | text | `.col-mono` | phone или «—» |
| 7 | Роль | text | — | «Руководитель» / «Логист» |
| 8 | Статус | badge | — | `.badge-ok` (active) / `.badge-danger` (blocked) / `.badge` (archived) |
| 9 | Создан | datetime | `.col-muted` | created_at |
| 10 | Действия | — | `.col-actions` | Row actions (набор зависит от типа) |

---

## 5. User type badges

```php
function userStatusBadge(string $status): string
{
    $map = [
        'active'  => ['class' => 'badge-ok',    'label' => 'Активен'],
        'blocked' => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'=> ['class' => '',             'label' => 'Архивирован'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}
```

---

## 6. Data sources

### Объединённый список пользователей

```php
// 1. Загрузить company → если нет: company not found
$company = fetchCompany($id);

// 2. Руководитель из central DB:
$owner = pdo_central()->prepare(
    'SELECT id, full_name, login, email, phone, role, status, created_at 
     FROM company_users WHERE company_id = ? AND role = ?'
);
$owner->execute([$id, 'company_owner']);
$ownerRow = $owner->fetch();

// 3. Логисты из local DB:
$logists = [];
if (!empty($company['db_identifier'])) {
    try {
        $localPdo = connectToLocalDb($company['db_identifier']);
        $stmt = $localPdo->prepare(
            'SELECT id, full_name, login, email, phone, role_code, status, created_at 
             FROM users WHERE role_code = ? ORDER BY created_at DESC'
        );
        $stmt->execute(['logist']);
        $logists = $stmt->fetchAll();
    } catch (\PDOException $e) {
        // local DB unavailable — logists = empty array, show warning
        $localDbError = true;
    }
}

// 4. Объединить в один список:
$users = [];
if ($ownerRow) {
    $users[] = [
        'type' => 'owner',
        'id' => $ownerRow['id'],
        'full_name' => $ownerRow['full_name'],
        'login' => $ownerRow['login'],
        'email' => $ownerRow['email'],
        'phone' => $ownerRow['phone'],
        'role_label' => 'Руководитель',
        'status' => $ownerRow['status'],
        'created_at' => $ownerRow['created_at'],
    ];
}
foreach ($logists as $l) {
    $users[] = [
        'type' => 'logist',
        'id' => $l['id'],
        'full_name' => $l['full_name'],
        'login' => $l['login'],
        'email' => $l['email'],
        'phone' => $l['phone'],
        'role_label' => 'Логист',
        'status' => $l['status'],
        'created_at' => $l['created_at'],
    ];
}

$totalCount = count($users);
```

### Row actions logic

| User type | Status | Available actions |
|-----------|--------|-------------------|
| owner | any | V (view), E (edit), P (reset password) |
| logist | any | V (view), E (edit), P (reset password), A (archive) |
| logist | not active | V, E, ✓ (activate), A, P |
| logist | active | V, E, ⊗ (block), A, P |
| logist | blocked | V, E, ✓ (activate), A, P |
| logist | archived | V, E, ✓ (activate), P |

---

## 7. Row actions reference (UPDATED — action-classified)

| Label | Class | Visual class | Route | Confirm |
|-------|-------|-------------|-------|---------|
| Просмотр (V) | NAVIGATION | `.btn-ghost` | owner → `/superadmin/companies/{id}/owner`; logist → `/superadmin/companies/{id}/users/logists/{user_id}` | NO |
| Редактировать (E) | EDIT | `.btn-ghost` | owner → `/superadmin/companies/{id}/owner/edit`; logist → `/superadmin/companies/{id}/users/logists/{user_id}/edit` | NO |
| Активировать (✓) | STATE_CHANGE | `.btn-ghost` | `POST /superadmin/companies/{id}/users/logists/{user_id}/activate` | `confirm()` |
| Заблокировать (⊗) | STATE_CHANGE | `.btn-ghost` | `POST /superadmin/companies/{id}/users/logists/{user_id}/block` | `confirm()` |
| Архивировать (A) | DESTRUCTIVE | `.btn-danger` | `POST /superadmin/companies/{id}/users/logists/{user_id}/archive` | `confirm()` |
| Сбросить пароль (P) | SECURITY | `.btn-ghost` | owner → `POST /superadmin/companies/{id}/owner/reset-password`; logist → `POST /superadmin/companies/{id}/users/logists/{user_id}/reset-password` | `confirm()` |

### Coder implementation notes:
- **DESTRUCTIVE (Архивировать):** Use `.btn-danger` — NOT `.btn-ghost`
- **SECURITY (Сбросить пароль):** Use `.btn-ghost` + `confirm()` 
- **NAVIGATION/EDIT/STATE_CHANGE:** Use `.btn-ghost`
- **Buttons must use TEXT labels** («Просмотр», «Редактировать», etc.) — NOT pseudo-icons [V][E][P][✓][⊗][A]
- Compact styling is handled by `.row-actions .btn-ghost, .row-actions .btn-danger` in `app.css`

---

## 8. Empty / error states

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

### Local DB unavailable (logists not loaded)
```html
<div class="notice warn" style="margin-bottom:12px">
    Локальная БД компании недоступна. Отображаются только данные Руководителя.
</div>
```

### Empty state (нет ни owner, ни logists)
```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="text-muted">Нет пользователей</p>
            <p class="text-muted">В компании ещё не созданы пользователи.</p>
        </div>
    </div>
</div>
```

Только если и owner отсутствует, и logists пуст. Если owner есть — таблица показывается (с 1 строкой).

---

## 9. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context «Пользователи компании: [name]» |
| CORE-05 | Page header | page-head with title + action |
| CORE-08 | Panel | Table container |
| CORE-13 | Data table / ERP grid | Users table |
| CORE-14 | Table row states | Row hover |
| CORE-15 | Table row actions | Quick actions per user |
| CORE-16 | Pagination | When needed |
| CORE-19 | Ghost button | «← К карточке» |
| CORE-24 | Status badge | User statuses + type badge |
| CORE-31 | Key-value list (KV) | If needed for summary |
| CORE-32 | Notice | System messages |
| CORE-33 | Warning notice | DB errors, local DB unavailable |
| CORE-34 | Empty state | No users |

**COMPOSITE pattern:** PATTERN-01 Table-only registry

---

## 10. MODULE USAGE DECISIONS

- **Main page purpose:** View all users of a company (owner + logists) in one unified table.
- **Primary work object:** Unified users list from two data sources.
- **Main layout selected:** table-only (PATTERN-01).
- **Primary action:** Navigation back to company card («← К карточке»).
- **Secondary actions:** Row-level user management (view, edit, status change, reset password).
- **Table required:** YES — central monitoring view.
- **Form required:** NO on this page (actions are via navigation to separate pages or POST).
- **Inspector required:** NO — detail via navigation to owner/logist card.
- **Filters required:** NO — manageable user counts per company. Can be added later.
- **Modal required:** NO — confirm via JS confirm().
- **Modules explicitly not used:** CORE-11 Filters (not needed for small user lists), CORE-30 Inspector (detail via navigation).

---

## 11. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source | Required classes | Forbidden alternatives |
|---|------------|----------------|-------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item.is-active | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | Production CSS > .page-head | `.page-head h1`, `.text-muted` | Demo title |
| 5 | Ghost action | CORE-19 | Button Matrix > Ghost | `.btn-ghost` | Ghost danger |
| 6 | Panel | CORE-08 | Production CSS > .panel | `.panel`, `.panel-head`, `.panel-body` | Decorative cards |
| 7 | Table | CORE-13 | Production CSS > .tbl, .tbl-wrap | `.tbl`, `.tbl-wrap`, `.col-mono`, `.col-muted`, `.col-actions` | Bootstrap table |
| 8 | Row actions (NAVIGATION/EDIT/STATE_CHANGE/SECURITY) | CORE-19 | Button Matrix > Ghost + app.css `.row-actions .btn-ghost` | `.btn-ghost`, `.row-actions` | Using `.btn-danger` for non-destructive |
| 9 | Row actions (DESTRUCTIVE: Архивировать) | CORE-22 | Button Matrix > Danger + app.css `.row-actions .btn-danger` | `.btn-danger`, `.row-actions` | Using `.btn-ghost` for destructive |
| 10 | Status badge (ok) | CORE-24 | Production CSS > .badge-ok | `.badge-ok`, `.dot` | Bootstrap alert |
| 10 | Status badge (danger) | CORE-24 | Production CSS > .badge-danger | `.badge-danger`, `.dot` | Bootstrap alert-danger |
| 11 | Status badge (neutral) | CORE-24 | Production CSS > .badge | `.badge`, `.dot` | Random color |
| 12 | Empty state | CORE-34 | CORE-34 module card > .empty-state | `.empty-state` | Blank workspace |
| 13 | Notice warn | CORE-33 | Production CSS > .notice.warn | `.notice.warn` | Bootstrap alert-warning |
| 14 | Notice danger | CORE-33 | Production CSS > .notice.danger | `.notice.danger` | Bootstrap alert-danger |

---

## 12. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes — head flush | `.panel-body` (10px) | `h3.panel-head-title` |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | text-muted |
| `.tbl-wrap` | `.tbl` | — | — | — | sticky thead, row hover |
| `.row-actions` | `.btn-ghost` | — | — | — | hover: subtle surface; compact: font-size 11px, padding 2px 6px, min-height 22px |
| `.row-actions` | `.btn-danger` | — | — | — | hover: darker danger bg; compact: same as .btn-ghost |
| `.nav-item` | `.nav-icon` | — | — | — | hover opacity .7, active .85 |

---

## 13. Strict prohibitions for coder

- Не менять `main.php` shell/sidebar/topbar
- Не придумывать новые CSS-классы без source в Core Kit
- Не менять роль Руководителя (role всегда 'company_owner')
- Не менять role_code логиста (всегда 'logist')
- Не давать логисту SUPERADMIN-доступ
- Не хранить пароли в открытом виде
- Не писать SQL внутри view
- Не использовать emoji/pseudo-icons
- **НЕ ИСПОЛЬЗОВАТЬ pseudo-icons [V][E][P][✓][⊗][A] — только текстовые метки**
- **НЕ ИСПОЛЬЗОВАТЬ `.btn-ghost` для DESTRUCTIVE (архивировать) — только `.btn-danger`**
- `border-radius` ≤ 4px для новых элементов
- `box-shadow` blur ≤ 8px для новых элементов

---

## 14. Coder implementation checklist

- [ ] Создать view `superadmin_company_users.php`
- [ ] Реализовать загрузку данных: company + owner (central DB) + logists (local DB)
- [ ] Объединить owner и logists в единый массив $users
- [ ] Реализовать таблицу с 10 колонками
- [ ] Реализовать условные row actions (разный набор для owner/logist)
- [ ] Реализовать userStatusBadge() helper
- [ ] Обработать company not found
- [ ] Обработать local DB unavailable (warning + только owner)
- [ ] Обработать empty state (нет ни owner ни logists)
- [ ] Добавить маршрут `GET /superadmin/companies/{id}/users`
- [ ] `php -l` для всех изменённых PHP — OK

---

## 15. QA formal checklist

- [ ] GET /superadmin/companies/{id}/users → таблица пользователей
- [ ] Руководитель в таблице с типом «Руководитель» (accent badge)
- [ ] Логисты в таблице с типом «Логист» (neutral badge)
- [ ] Row actions для owner: V, E, P
- [ ] Row actions для logist: V, E, ✓/⊗, A, P (в зависимости от статуса)
- [ ] Company not found → warn notice
- [ ] Local DB error → warn notice, owner data показан
- [ ] Empty state → «Нет пользователей»
- [ ] Кнопка «← К карточке» работает
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 16. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}/users`
- **Что владелец должен проверить глазами:**
  - [ ] Таблица плотная, 10 колонок.
  - [ ] Тип «Руководитель» визуально выделен (accent badge).
  - [ ] Тип «Логист» нейтральным бейджем.
  - [ ] Row actions видны на hover.
  - [ ] Нет псевдоиконок, emoji, demo-placeholder.
  - [ ] Industrial Graphite + Warm Accent сохранён.
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
