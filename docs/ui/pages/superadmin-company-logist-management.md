# UI PAGE HANDOFF — SUPERADMIN Company Logist Management

## Status
**HANDOFF_READY** — новая страница. Production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md §0a`. NO REBUILD NEEDED.

| Параметр | MASTER spec | Соответствие |
|----------|-------------|-------------|
| App shell grid | `grid-template-columns: 224px 1fr; grid-template-rows: 38px 1fr` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 | YES |
| Topbar border-bottom | `1px solid var(--line)` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | YES |
| Nav item height | 34px | YES |
| Nav item font-weight | 600 | YES |
| Nav item font-size | 12.5px | YES |
| Nav active state | `::before` pseudo 2px gold | YES |

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
SUPERADMIN — Логист: [full_name] · Компания: [company.name]
```

---

## 1. Страницы модуля

Модуль состоит из трёх частей:
1. **Карточка логиста (view)** — `GET /superadmin/companies/{company_id}/users/logists/{user_id}`
2. **Редактирование логиста (edit)** — `GET/POST /superadmin/companies/{company_id}/users/logists/{user_id}/edit`
3. **Действия** — статусные и сброс пароля (POST-маршруты)

### Все маршруты

| Method | Route | Назначение |
|--------|-------|-----------|
| GET | `/superadmin/companies/{company_id}/users/logists/{user_id}` | Карточка логиста |
| GET | `/superadmin/companies/{company_id}/users/logists/{user_id}/edit` | Форма редактирования |
| POST | `/superadmin/companies/{company_id}/users/logists/{user_id}/edit` | Сохранение изменений |
| POST | `/superadmin/companies/{company_id}/users/logists/{user_id}/reset-password` | Сброс пароля |
| POST | `/superadmin/companies/{company_id}/users/logists/{user_id}/activate` | Активация |
| POST | `/superadmin/companies/{company_id}/users/logists/{user_id}/block` | Блокировка |
| POST | `/superadmin/companies/{company_id}/users/logists/{user_id}/archive` | Архивирование |

---

## 2. DB

- **Источник данных:** локальная БД компании (`erp_company_{id}`), таблица `users`
- **Поля, которые РЕДАКТИРУЮТСЯ:** full_name, login, email, phone, status, comments
- **Поля, которые НЕЛЬЗЯ менять:** id, password_hash, role_code, created_at, updated_at
- **password_hash** меняется ТОЛЬКО через reset-password (bcrypt)
- **role_code** ВСЕГДА 'logist' — НЕ менять

---

## 3. Карточка логиста (view)

**Route:** `GET /superadmin/companies/{company_id}/users/logists/{user_id}`
**View file:** `app/View/pages/superadmin_company_logist_view.php` (НОВЫЙ)

### Layout

```html
<div class="page-head">
    <div>
        <h1>Логист: <?= e($logist['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company_id ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company_id ?>/users" class="btn btn-ghost">← К пользователям</a>
        <a href="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/edit" class="btn btn-primary">Редактировать</a>
    </div>
</div>
```

### Секция 1: Основные данные (KV-list)

```html
<div class="panel">
    <div class="panel-head">
        <h2>Основные данные</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>ФИО</dt>
            <dd><?= e($logist['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($logist['login']) ?></code></dd>
            <dt>Email</dt>
            <dd><?= e($logist['email'] ?? '—') ?></dd>
            <dt>Телефон</dt>
            <dd><?= e($logist['phone'] ?? '—') ?></dd>
            <dt>Роль</dt>
            <dd>Логист</dd>
            <dt>Статус</dt>
            <dd><?= userStatusBadge($logist['status']) ?></dd>
            <dt>Комментарий</dt>
            <dd><?= e($logist['comments'] ?? '—') ?></dd>
            <dt>Создан</dt>
            <dd><?= e($logist['created_at'] ?? '—') ?></dd>
            <dt>Обновлён</dt>
            <dd><?= e($logist['updated_at'] ?? '—') ?></dd>
        </dl>
    </div>
</div>
```

### Секция 2: Созданные записи (KV-list)

```html
<div class="panel">
    <div class="panel-head">
        <h2>Созданные записи</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Клиенты</dt>
            <dd><?= (int)($counts['clients'] ?? 0) ?></dd>
            <dt>Подрядчики</dt>
            <dd><?= (int)($counts['contractors'] ?? 0) ?></dd>
            <dt>Водители</dt>
            <dd><?= (int)($counts['drivers'] ?? 0) ?></dd>
            <dt>Транспорт</dt>
            <dd><?= (int)($counts['vehicles'] ?? 0) ?></dd>
            <dt>Экипажи</dt>
            <dd><?= (int)($counts['crews'] ?? 0) ?></dd>
            <dt>Документы</dt>
            <dd><?= (int)($counts['documents'] ?? 0) ?></dd>
        </dl>
    </div>
</div>
```

**Источник данных:** локальная БД компании.
```php
$counts = [];
$tables = ['clients', 'contractors', 'drivers', 'vehicles', 'crews', 'documents'];
foreach ($tables as $table) {
    $stmt = $localPdo->prepare("SELECT COUNT(*) FROM {$table} WHERE created_by_user_id = ?");
    $stmt->execute([$user_id]);
    $counts[$table] = $stmt->fetchColumn();
}
```

Для таблицы documents используем `uploaded_by_user_id` вместо `created_by_user_id`:
```php
$stmt = $localPdo->prepare("SELECT COUNT(*) FROM documents WHERE uploaded_by_user_id = ?");
$stmt->execute([$user_id]);
$counts['documents'] = $stmt->fetchColumn();
```

### Секция 3: Доступы (KV-list)

```html
<div class="panel">
    <div class="panel-head">
        <h2>Доступы</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Выдано доступов</dt>
            <dd><?= (int)($counts['grants'] ?? 0) ?></dd>
        </dl>
    </div>
</div>
```

```php
$stmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ?");
$stmt->execute([$user_id]);
$counts['grants'] = $stmt->fetchColumn();
```

### Секция 4: Действия

```html
<div class="panel">
    <div class="panel-head">
        <h2>Действия</h2>
    </div>
    <div class="panel-body">
        <div class="form-actions">
            <?php if ($logist['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/activate" style="display:inline" onsubmit="return confirm('Активировать логиста?')">
                <button type="submit" class="btn btn-primary">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($logist['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать логиста?')">
                <button type="submit" class="btn btn-danger">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать логиста?')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
            <form method="post" action="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль логиста? Текущий пароль будет заменён. Новый пароль будет показан только один раз.')">
                <button type="submit" class="btn btn-danger">Сбросить пароль</button>
            </form>
        </div>
    </div>
</div>
```

---

## 4. Редактирование логиста (edit)

**Route:** `GET/POST /superadmin/companies/{company_id}/users/logists/{user_id}/edit`
**View file:** `app/View/pages/superadmin_company_logist_edit.php` (НОВЫЙ)

### Layout

```html
<div class="page-head">
    <div>
        <h1>Редактировать логиста</h1>
        <p class="text-muted"><?= e($logist['full_name']) ?> · Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

### Форма: POST /superadmin/companies/{company_id}/users/logists/{user_id}/edit

```html
<form method="post" action="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input" required
                       value="<?= e($old['full_name'] ?? $logist['full_name']) ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input" required
                       value="<?= e($old['login'] ?? $logist['login']) ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input"
                       value="<?= e($old['email'] ?? $logist['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? $logist['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус и комментарий</h3>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select">
                    <option value="active" <?= ($old['status'] ?? $logist['status']) === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="blocked" <?= ($old['status'] ?? $logist['status']) === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                    <option value="archived" <?= ($old['status'] ?? $logist['status']) === 'archived' ? 'selected' : '' ?>>Архивирован</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $logist['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>
```

### Form fields specification

| # | Field | Type | Required | Validation | Class |
|---|-------|------|----------|------------|-------|
| 1 | `full_name` | text | **YES** | not empty | `.field-input` |
| 2 | `login` | text | **YES** | not empty, regex `/^[a-zA-Z0-9_]+$/`, unique excluding current id | `.field-input` |
| 3 | `email` | email | no | valid email if filled | `.field-input` |
| 4 | `phone` | text | no | — | `.field-input` |
| 5 | `status` | select | **YES** | active / blocked / archived | `.field-select` |
| 6 | `comments` | textarea | no | — | `.field-textarea` |

### Обработка POST

```php
1. Загрузить company по company_id → если нет: formError «Компания не найдена»
2. Подключиться к локальной БД через db_identifier → если нет: formError «Локальная БД недоступна»
3. Найти логиста: SELECT * FROM users WHERE id=? AND role_code='logist' → если нет: formError «Логист не найден»
4. Валидировать full_name (required, not empty)
5. Валидировать login (required, regex /^[a-zA-Z0-9_]+$/)
6. Проверить login на дубликат (в локальной БД): SELECT COUNT(*) FROM users WHERE login=? AND id!=? → если >0: ошибка «Логин уже используется»
7. Валидировать email если заполнен
8. Если ошибки → перерендерить форму с .is-error и $errors
9. UPDATE users SET full_name=?, login=?, email=?, phone=?, status=?, comments=?, updated_at=NOW() WHERE id=?
10. role_code НЕ обновлять (всегда 'logist')
11. Redirect 302 → /superadmin/companies/{company_id}/users/logists/{user_id}
12. Показать .notice.success «Данные логиста сохранены»
```

---

## 5. Сброс пароля логиста (POST)

**Route:** `POST /superadmin/companies/{company_id}/users/logists/{user_id}/reset-password`

### Обработка

```php
1. Загрузить company → если нет: ошибка
2. Подключиться к локальной БД → если нет: ошибка
3. Найти логиста: SELECT * FROM users WHERE id=? AND role_code='logist' → если нет: ошибка
4. Сгенерировать новый пароль: generatePassword(10) (функция уже есть в index.php)
5. $hash = password_hash($newPassword, PASSWORD_BCRYPT)
6. UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?
7. Показать success page (на том же route, render view):
```

### Success page после сброса пароля

```html
<div class="page-head">
    <div>
        <h1>Пароль сброшен</h1>
        <p class="text-muted">Логист: <?= e($logist['full_name']) ?> · Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company_id ?>/users/logists/<?= $user_id ?>" class="btn btn-ghost">← К карточке логиста</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пароль успешно сброшен.
        </div>

        <dl class="kv" style="margin-top:16px">
            <dt>ФИО</dt>
            <dd><?= e($logist['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($logist['login']) ?></code></dd>
            <dt>Новый временный пароль</dt>
            <dd><code style="background:var(--warning-bg);padding:2px 6px;border-radius:2px"><?= e($newPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd>Логист</dd>
        </dl>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>
    </div>
</div>
```

**Что запрещено при сбросе пароля:**
- Показывать текущий пароль (его нет в открытом виде)
- Хранить открытый пароль в БД, логах, MD, git diff
- Сохранять пароль в сессии

---

## 6. Статусные действия

### POST activate

```php
1. Загрузить company, подключиться к local DB, найти логиста
2. UPDATE users SET status='active', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{company_id}/users/logists/{user_id}
4. Сообщение: .notice.success «Логист активирован»
```

### POST block

```php
1. Загрузить company, подключиться к local DB, найти логиста
2. UPDATE users SET status='blocked', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{company_id}/users/logists/{user_id}
4. Сообщение: .notice.success «Логист заблокирован»
```

**Условно:** только если статус active.

### POST archive

```php
1. Загрузить company, подключиться к local DB, найти логиста
2. UPDATE users SET status='archived', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{company_id}/users/logists/{user_id}
4. Сообщение: .notice.success «Логист архивирован»
5. НЕ удаляет записи, созданные логистом
```

---

## 7. User status badge helper

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

## 8. Состояния

| Состояние | UI | Действие |
|-----------|----|----------|
| company not found | `.notice.warn` «Компания не найдена. ← К реестру» | Показать ссылку на /superadmin/companies |
| local DB unavailable | `.notice.danger` «Локальная БД компании недоступна» | Показать на всех страницах модуля |
| logist not found | `.notice.warn` «Логист не найден. ← К пользователям» | Показать ссылку на users list |
| validation errors | `.is-error` на полях, `.field-msg` | Перерендерить форму |
| success after save | `.notice.success` + redirect | Redirect на карточку |
| success after password reset | `.notice.success` + временный пароль | Показать KV + warning |
| success after status change | `.notice.success` + redirect | Redirect на карточку |
| db error | `.notice.danger` с сообщением | Не показывать raw SQL |

---

## 9. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context |
| CORE-05 | Page header | page-head |
| CORE-08 | Panel | All sections |
| CORE-17 | Primary button | «Редактировать», «Активировать», «Сохранить» |
| CORE-19 | Ghost button | «← К карточке», «← К пользователям», «Отмена» |
| CORE-22 | Danger button | «Заблокировать», «Архивировать», «Сбросить пароль» |
| CORE-24 | Status badge | User status |
| CORE-26 | Form field | Edit form fields |
| CORE-27 | Form section | Edit form sections |
| CORE-28 | Validation/error | Form field errors |
| CORE-31 | Key-value list (KV) | Facts display (dl.kv) |
| CORE-32 | Notice | System messages, success |
| CORE-33 | Warning notice | Warnings, errors |

**COMPOSITE pattern:** PATTERN-05 Admin/settings screen + PATTERN-06 Entity card page

---

## 10. MODULE USAGE DECISIONS

- **Main page purpose:** SUPERADMIN monitors and manages a specific logist user.
- **Primary work object:** Logist card with KV sections + edit form + status/password actions.
- **Main layout selected:** admin/settings + entity card (PATTERN-05 + PATTERN-06).
- **Primary action:** «Редактировать» (view) / «Сохранить» (edit).
- **Secondary actions:** Status changes, password reset, navigation.
- **Table required:** NO — single entity detail.
- **Form required:** YES (edit page).
- **Inspector required:** NO.
- **Filters required:** NO.
- **Modal required:** NO — confirm via JS.
- **Modules explicitly not used:** CORE-13 Table (single entity, not list), CORE-11 Filters.

---

## 11. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source | Required classes | Forbidden alternatives |
|---|------------|----------------|-------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item.is-active | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | Production CSS > .page-head | `.page-head h1`, `.text-muted` | Demo title |
| 5 | Primary action | CORE-17 | Button Matrix > Primary | `.btn-primary` | Multiple primaries |
| 6 | Ghost action | CORE-19 | Button Matrix > Ghost | `.btn-ghost` | Ghost danger |
| 7 | Danger action | CORE-22 | Button Matrix > Danger | `.btn-danger` | Danger without confirm |
| 8 | Panel | CORE-08 | Production CSS > .panel | `.panel`, `.panel-head`, `.panel-body` | Decorative cards |
| 9 | KV list | CORE-31 | Production CSS > .kv | `.kv` (dl.kv or div.kv), `.k` | No row dividers |
| 10 | Status badge | CORE-24 | Production CSS > .badge, .badge-ok, .badge-danger | `.badge`, `.dot` | Bootstrap alert |
| 11 | Form field | CORE-26 | Production CSS > .field, .field-input, .field-select, .field-textarea | `.field`, `.field-input`, `.req` | Browser defaults |
| 12 | Form section | CORE-27 | Production CSS > .form-section | `.form-section` | Unrelated groups |
| 13 | Validation | CORE-28 | Production CSS > .is-error, .field-msg | `.is-error`, `.field-msg` | Red border only |
| 14 | Notice success | CORE-32 | Production CSS > .notice.success | `.notice.success` | Bootstrap alert |
| 15 | Notice warn | CORE-33 | Production CSS > .notice.warn | `.notice.warn` | Bootstrap alert-warning |
| 16 | Notice danger | CORE-33 | Production CSS > .notice.danger | `.notice.danger` | Bootstrap alert-danger |
| 17 | Form actions | — | Button Matrix | `.form-actions` | Random button order |

---

## 12. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes — head flush | `.panel-body` (10px) | `h2` |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | text-muted, dl.kv |
| `.kv` | `dt`, `dd` | — | — | Row: 5px 0 | `border-bottom` mandatory |
| `.form-section` | `.field` | — | — | — | label, input, msg |
| `.form-actions` | `.btn` | — | — | — | gap, right-align |

---

## 13. Strict prohibitions for coder

- Не менять `main.php` shell/sidebar/topbar
- Не менять `role_code` логиста (всегда 'logist')
- Не давать логисту SUPERADMIN-доступ
- Не видеть/показывать текущий пароль
- Не хранить открытый пароль в БД, логах, MD, git
- Не удалять записи логиста при archive
- Не придумывать новые CSS-классы без source в Core Kit
- Не использовать emoji/pseudo-icons
- `border-radius` ≤ 4px для новых элементов
- `box-shadow` blur ≤ 8px для новых элементов

---

## 14. Coder implementation checklist

- [ ] Создать view `superadmin_company_logist_view.php` (карточка)
- [ ] Создать view `superadmin_company_logist_edit.php` (форма)
- [ ] Создать view/path для success после reset-password
- [ ] Реализовать загрузку logist из локальной БД
- [ ] Реализовать counts created records (clients, contractors, drivers, vehicles, crews, documents)
- [ ] Реализовать count entity_access_grants
- [ ] Реализовать форму редактирования (6 полей)
- [ ] Реализовать валидацию (full_name required, login required + regex + unique)
- [ ] Реализовать POST edit → UPDATE в локальной БД
- [ ] Реализовать POST reset-password → генерация + bcrypt + показ один раз
- [ ] Реализовать POST activate/block/archive
- [ ] Реализовать маршруты (7 штук)
- [ ] Не менять role_code
- [ ] `php -l` для всех изменённых PHP — OK

---

## 15. QA formal checklist

- [ ] GET logist view → карточка логиста с секциями 1-4
- [ ] Секция «Созданные записи» показывает корректные counts
- [ ] Секция «Доступы» показывает count entity_access_grants
- [ ] GET edit form → форма с 6 полями, предзаполнена
- [ ] POST edit → 302 redirect, данные обновлены
- [ ] Пустой full_name → ошибка валидации
- [ ] Пустой login → ошибка валидации
- [ ] Дубликат login → ошибка валидации
- [ ] POST reset-password → success page, новый пароль показан один раз
- [ ] password_hash в локальной БД — bcrypt ($2y$)
- [ ] Plaintext пароль НЕ в БД
- [ ] POST activate → статус active
- [ ] POST block → статус blocked
- [ ] POST archive → статус archived (не удаляет записи)
- [ ] role_code остался 'logist' после всех операций
- [ ] Company not found → warn notice
- [ ] Local DB unavailable → danger notice
- [ ] Logist not found → warn notice
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 16. Owner visual check

- **VISUAL CHECK URL (view):** `http://127.0.0.1:[port]/superadmin/companies/{company_id}/users/logists/{user_id}`
- **VISUAL CHECK URL (edit):** `http://127.0.0.1:[port]/superadmin/companies/{company_id}/users/logists/{user_id}/edit`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
