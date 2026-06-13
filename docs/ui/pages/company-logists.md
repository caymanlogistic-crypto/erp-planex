# UI PAGE HANDOFF — Company Logist (Логист)

## Status
**ARCHITECT-CREATED (ACCELERATED MODE).** Manual visual approval deferred. UI polish cycle later.
Functional coding was NOT blocked by design approval.

## A. Foundation

### Role and context
- **User**: Руководитель (company_owner)
- **Location**: внутри локальной ERP компании
- **Path prefix**: `/company/`
- **Company context**: `?company_id=N` (temporary; will be replaced by session context)

### 0. LAYOUT FOUNDATION SOURCE MAPPING

Layout uses existing `main.php` shell — same app shell, sidebar, topbar as SUPERADMIN pages. NO rebuild needed.

| Element | Source | Status |
|---------|--------|--------|
| App shell grid | main.php `.app-shell` CSS grid | COMPLIANT |
| Topbar | main.php `.topbar` 38px, СВЕТЛЫЙ surface-strong | COMPLIANT |
| Sidebar | main.php `.app-sidebar` 224px, nav-item, SVG icons | COMPLIANT |
| Nav active state | main.php `::before` pseudo via `.is-active` | COMPLIANT |
| Sidebar IA | section labels, nav-spacer, nav-bottom with Настройки | COMPLIANT |

**IMPORTANT:** For the local ERP context, the sidebar will show different menu items. However, for this minimal module, the sidebar menu remains unchanged (all items disabled except SUPERADMIN). A separate future task will create the local ERP sidebar.

### 0b. Sidebar IA (current / unchanged)
```
ОПЕРАЦИИ
  Рейсы        disabled
  Водители     disabled  
  Транспорт    disabled
  Клиенты      disabled
[spacer]
СИСТЕМА
  SUPERADMIN   is-active (for existing SUPERADMIN pages)
  Логисты      NEW — active when on /company/logists pages
[bottom]
  Настройки    disabled
```

### 0c. Topbar context for this page
```
Логисты — Компания: [company_name]
```

---

## 1. Страницы модуля

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/logists?company_id=N` | GET | Список логистов компании |
| `/company/logists/create?company_id=N` | GET | Форма создания логиста |
| `/company/logists/create?company_id=N` | POST | Обработка создания логиста |

---

## 2. Страница списка логистов

### 2.1 Page structure (top to bottom)

```
page-head: «Логисты» + subtitle company name + button «Создать логиста»
↓
panel:
  panel-body:
    if no logists → empty state: «Логисты ещё не созданы»
    if logists exist → table: ID, ФИО, Логин, Роль, Статус, Создан
```

### 2.2 Page head

```html
<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать логиста</a>
    </div>
</div>
```

### 2.3 Empty state (no logists)

```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Логисты ещё не созданы.</p>
            <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать первого логиста</a>
        </div>
    </div>
</div>
```

### 2.4 Table (when logists exist)

```html
<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Создан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logists as $l): ?>
                    <tr>
                        <td class="col-mono"><?= $l['id'] ?></td>
                        <td><?= e($l['full_name']) ?></td>
                        <td class="col-mono"><?= e($l['login']) ?></td>
                        <td>Логист</td>
                        <td>
                            <span class="badge<?= $l['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $l['status'] === 'active' ? 'Активен' : e($l['status']) ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($l['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

### 2.5 Error states

**Company not found (invalid/missing company_id):**
```html
<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>
```

**Company in error/provisioning status:**
```html
<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание логистов недоступно.
</div>
```

**DB connection error to local company DB:**
```html
<div class="notice warn">
    Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.
</div>
```

---

## 3. Форма создания логиста

### 3.1 Route
- **GET:** `/company/logists/create?company_id=N` — форма
- **POST:** `/company/logists/create?company_id=N` — обработка

### 3.2 Page structure

```html
<div class="page-head">
    <div>
        <h1>Создать логиста</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>
```

### 3.3 Validation / error notices

```html
<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>
```

### 3.4 Form

```html
<form method="post" action="/company/logists/create?company_id=<?= $companyId ?>" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input" required
                       value="<?= e($old['full_name'] ?? '') ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input" required
                       value="<?= e($old['login'] ?? '') ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Пароль</label>
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <input type="text" name="password" class="field-input"
                           value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                           placeholder="Оставьте пустым для автогенерации"
                           style="flex:1">
                    <button type="button" class="btn btn-toolbar" onclick="generatePassword()"
                            style="white-space:nowrap;margin-top:0">
                        Сгенерировать
                    </button>
                </div>
                <div class="field-msg" style="margin-top:4px">
                    Если не заполнено — пароль будет сгенерирован автоматически.
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="field-msg is-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты (опционально)</h3>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input"
                       value="<?= e($old['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать логиста</button>
            <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let pwd = '';
    for (let i = 0; i < 10; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('input[name="password"]').value = pwd;
}
</script>
```

### 3.5 Success state

```html
<div class="page-head">
    <div>
        <h1>Логист создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Логист успешно создан. Ниже — данные для передачи.
        </div>

        <div class="kv" style="margin-top:16px">
            <div class="kv-row">
                <span class="kv-key">Компания</span>
                <span class="kv-value"><?= e($company['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ФИО</span>
                <span class="kv-value"><?= e($createdLogist['full_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Логин</span>
                <span class="kv-value"><code><?= e($createdLogist['login']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Временный пароль</span>
                <span class="kv-value">
                    <code style="background:var(--warn-bg);padding:2px 6px;border-radius:3px"><?= e($tempPassword) ?></code>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Роль</span>
                <span class="kv-value">Логист</span>
            </div>
        </div>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его или передайте логисту сейчас.
            Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку логистов</a>
            <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>
```

---

## 4. Form fields specification

| # | Field | Type | Required | Validation | Class |
|---|-------|------|----------|------------|-------|
| 1 | `full_name` | text | **YES** | not empty | `.field-input` |
| 2 | `login` | text | **YES** | not empty, latin/digits/underscore, unique in local DB | `.field-input` |
| 3 | `password` | text | no | auto-generated if empty (10 chars) | `.field-input` |
| 4 | `email` | email | no | valid email if filled | `.field-input` |
| 5 | `phone` | text | no | — | `.field-input` |

---

## 5. Validation rules

| Field | Rule | Error message |
|-------|------|---------------|
| `full_name` | empty | «Обязательное поле» |
| `login` | empty | «Обязательное поле» |
| `login` | not latin/digits/underscore | «Только латинские буквы, цифры и подчёркивание» |
| `login` | duplicate in local DB | «Логин уже используется в этой компании» |
| `email` | filled + not valid email | «Некорректный email» |
| `company_id` | missing or invalid | «Компания не найдена» |
| `company_id` | company not active | «Создание логистов недоступно» |

---

## 6. Business logic (POST handler)

```text
1. Получить company_id из $_GET['company_id']
2. Подключиться к центральной БД, найти компанию:
   SELECT * FROM companies WHERE id = ?
   - Если нет → formError «Компания не найдена»
   - Если status != 'active' → formError «Создание логистов недоступно»
3. Получить db_identifier компании (например, erp_company_1)
4. Подключиться к локальной БД компании
5. Проверить, что таблица users существует; если нет — создать (миграция)
6. Валидировать full_name (обязательное, не пустое)
7. Валидировать login (обязательное, не пустое, только [a-zA-Z0-9_])
8. Проверить уникальность login в локальной БД:
   SELECT COUNT(*) FROM users WHERE login = ?
   - Если > 0 → errors['login'] = «Логин уже используется в этой компании»
9. Если password пустой → сгенерировать случайный (10 символов, буквы+цифры)
10. Валидировать email если заполнен
11. Если ошибки валидации → показать форму снова с ошибками
12. password_hash = password_hash($password, PASSWORD_BCRYPT)
13. INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status)
14. Показать страницу успеха с:
    - full_name, login, временный пароль
    - предупреждение «показан один раз»
15. Пароль НЕ сохраняется в:
    - БД открытым текстом (только password_hash)
    - сессии
    - логах
    - MD-файлах
    - git diff
```

---

## 7. Local DB migration

Migration file: `database/migrations-local/001_create_company_users.sql`

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `login` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_code` VARCHAR(50) NOT NULL DEFAULT 'logist',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. Connecting to local company DB

The Database class supports creating connections to any database. The coder must:

1. Get `db_identifier` from `companies` table (e.g., `erp_company_1`)
2. Create a new Database instance with the same credentials but different database:
```php
$localDbConfig = $config['database'];
$localDbConfig['database'] = $dbIdentifier;
$localDb = new \App\Core\Database($localDbConfig);
$localPdo = $localDb->connection();
```

---

## 9. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | Existing — unchanged for this module |
| CORE-03 | Topbar | Existing |
| CORE-05 | Page header | Page head for list, create form, and success |
| CORE-08 | Panel | List container, form container, success display |
| CORE-13 | Data table | Logist list table |
| CORE-17 | Primary button | «Создать логиста», «← К списку» |
| CORE-19 | Ghost button | «Отмена», «Создать ещё» |
| CORE-20 | Toolbar button | «Сгенерировать» пароль |
| CORE-24 | Status badge | Logist status (active/blocked) |
| CORE-26 | Form field | All form inputs |
| CORE-27 | Form section | Grouped fields |
| CORE-28 | Validation/error | Required field errors |
| CORE-31 | Key-value list | Success screen details |
| CORE-32 | Notice | Success message |
| CORE-33 | Warning notice | Validation/company errors |
| CORE-34 | Empty state | No logists message |

**COMPOSITE pattern:** PATTERN-01 Table-only registry + form page

---

## 10. SOURCE MAPPING

| UI element | CORE module | Classes |
|------------|-------------|---------|
| Page head | CORE-05 | `.page-head h1`, `.text-muted` |
| Primary action | CORE-17 | `.btn-primary` |
| Ghost action | CORE-19 | `.btn-ghost` |
| Toolbar button | CORE-20 | `.btn-toolbar` |
| Form field | CORE-26 | `.field`, `.field-label`, `.field-input`, `.req` |
| Form section | CORE-27 | `.form-section` |
| Validation error | CORE-28 | `.is-error`, `.field-msg` |
| Success notice | CORE-32 | `.notice.success` |
| Warning notice | CORE-33 | `.notice.warn` |
| Panel | CORE-08 | `.panel`, `.panel-body` |
| Data table | CORE-13 | `.tbl-wrap`, `.tbl` |
| Status badge | CORE-24 | `.badge`, `.badge-ok`, `.dot` |
| KV list | CORE-31 | `.kv`, `.kv-row`, `.kv-key`, `.kv-value` |
| Empty state | CORE-34 | `.empty-state` |
| Mono column | — | `.col-mono` |
| Muted column | — | `.col-muted` |

---

## 11. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush |
|--------|-------|---------------|-------|
| `.panel` | `.panel-head` | 0 | YES |
| `.panel` | `.panel-body` | 0 | Padding in body |
| `.panel-body` | `.form-section` | 10px | — |
| `.kv` | `.kv-row` | — | border-bottom divider |

---

## 12. Forbidden

- Не менять `main.php` shell/sidebar/topbar
- Не придумывать новые CSS-классы вне Core Kit
- Не хранить открытый пароль в БД, логах, MD, git diff
- Не создавать логиста в центральной БД (только в локальной)
- Не давать логисту SUPERADMIN access
- Не давать логисту доступ к другим company_id
- Не коммитить `.env` и секреты
- Не создавать клиентов, подрядчиков, транспорт, водителей, экипажи, рейсы
- Не делать email-отправку
- Не реализовывать полноценную auth/session систему
- Не ломать Companies Registry и создание Руководителя
- Не менять DB/storage naming logic от ID
- Demo-placeholder UI запрещён
- Не менять backend/auth/CRUD/business logic без отдельного разрешения

---

## 13. Coder implementation checklist

- [ ] Создать миграцию `database/migrations-local/001_create_company_users.sql` для таблицы `users` в локальной БД
- [ ] Добавить маршруты GET/POST для `/company/logists` и `/company/logists/create`
- [ ] Реализовать подключение к локальной БД компании через `db_identifier`
- [ ] Создать view `app/View/pages/company_logists.php` (список + empty state)
- [ ] Создать view `app/View/pages/company_logists_create.php` (форма + success)
- [ ] Реализовать валидацию (full_name, login required + unique)
- [ ] Реализовать автогенерацию пароля (10 chars)
- [ ] `password_hash()` — только хэш в БД
- [ ] Временный пароль показать один раз на success page
- [ ] Проверить, что создание экспедитора не сломано
- [ ] Проверить, что создание Руководителя не сломано
- [ ] Не менять main.php
- [ ] Не менять существующие SUPERADMIN views и routes
- [ ] `php -l` для всех изменённых PHP-файлов
- [ ] Проверить, что секретов нет в git diff

---

## 14. QA formal checklist

- [ ] `/company/logists?company_id=1` открывается (список или empty)
- [ ] `/company/logists/create?company_id=1` GET — форма открывается
- [ ] POST создание логиста — success page
- [ ] Пароль хранится только как `password_hash` в локальной БД
- [ ] Открытый пароль не хранится в БД, логах, MD, git diff
- [ ] `role_code = 'logist'` в локальной БД
- [ ] Логист отсутствует в центральной БД `company_users` и `superadmin_users`
- [ ] Повторный login блокируется с сообщением об ошибке
- [ ] `login` уникален в пределах локальной БД
- [ ] Миграция локальной БД применена, идемпотентна
- [ ] Companies Registry не сломан (`/superadmin/companies` → 200)
- [ ] Создание Руководителя не сломано (`/superadmin/companies/{id}/create-owner` → 200)
- [ ] Shell не сломан (main.php не изменён)
- [ ] Нет секретов в git diff
- [ ] `php -l` для всех изменённых PHP — OK
- [ ] Невалидный `company_id` → сообщение об ошибке
- [ ] Компания в статусе error/provisioning → сообщение о недоступности

---

## 15. Owner review

- **VISUAL CHECK URL (list):** `http://127.0.0.1:[port]/company/logists?company_id=1`
- **VISUAL CHECK URL (form):** `http://127.0.0.1:[port]/company/logists/create?company_id=1`
- **Manual owner visual review required:** YES (deferred to UI polish cycle per accelerated mode)
- **Commit allowed before owner visual approval:** YES (per ACCELERATED FUNCTIONAL DEVELOPMENT MODE — functional commit after QA PASS)

---

## 16. Notes

- Handoff created by erp-architect in ACCELERATED FUNCTIONAL DEVELOPMENT MODE
- UI polish deferred to separate designer cycle
- `generatePassword()` function already exists in `public/index.php`
- Route pattern: use `{id}` parameter for consistency with existing router
- Company context via `$_GET['company_id']` is temporary; will be replaced by session context
- Local migration should be applied automatically on first access if table doesn't exist
- Coder must NOT create a full auth system — only what's needed for functional verification
