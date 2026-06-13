# UI PAGE HANDOFF — SUPERADMIN Company Owner User (Руководитель)

## Status
**ARCHITECT-CREATED (ACCELERATED MODE).** Manual visual approval deferred. UI polish cycle later.

## A. Foundation

### FOUNDATION STATUS: COMPLIANT
Existing foundation from `superadmin-dashboard.md §0` and `superadmin-companies-registry.md §A` — NO REBUILD NEEDED.

### 0. LAYOUT FOUNDATION SOURCE MAPPING (reference)

All parameters COMPLIANT per existing. Shell grid, topbar (светлый, 38px, brand+crumbs+user), sidebar (тёмный, 224px), nav items (34px, 600, 12.5px, SVG icons 16×16, ::before active) — all unchanged.

### 0b. Sidebar IA (unchanged)

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

### 0c. Topbar context for this page

```
SUPERADMIN — Реестр компаний
```

---

## 1. Страницы модуля

Модуль состоит из двух частей:
1. **Компания → действие «Создать Руководителя»** — интеграция в существующий реестр компаний.
2. **Форма создания Руководителя** — отдельная страница.

---

## 2. Интеграция в реестр компаний (`superadmin_companies.php`)

### 2.1 Изменения в таблице компаний

В таблицу компаний добавляется колонка «Руководитель» после колонки «Статус».

| # | Column | Type | Class | Description |
|---|--------|------|-------|-------------|
| 1 | ID | integer | `.col-mono` | Company ID |
| 2 | Название | text | — | Company name |
| 3 | ИНН | text | `.col-mono` | Tax ID |
| 4 | Статус | badge | — | `.badge-ok` / `.badge-warn` / `.badge-danger` |
| 5 | Руководитель | text/action | — | Owner status or action link |
| 6 | Создан | datetime | `.col-muted` | created_at |
| 7 | Действия | — | `.col-actions` | Row actions |

### 2.2 Логика колонки «Руководитель»

```php
// Для каждой компании в таблице:
// SELECT id, full_name FROM company_users 
//   WHERE company_id = ? AND role = 'company_owner' AND status = 'active' LIMIT 1
```

**Если Руководитель НЕ создан (нет активной записи):**

```html
<td>
    <a href="/superadmin/companies/<?= $c['id'] ?>/create-owner" class="btn btn-primary" style="font-size:11px;padding:2px 10px">
        Создать Руководителя
    </a>
</td>
```

**Если Руководитель СОЗДАН:**

```html
<td>
    <span class="dot" style="background:var(--ok)"></span>
    <?= e($c['owner_name']) ?>
    <a href="/superadmin/companies/<?= $c['id'] ?>/create-owner" class="btn btn-ghost" style="font-size:11px;padding:2px 6px;margin-left:6px">
        Просмотреть
    </a>
</td>
```

**Если компания в статусе error/provisioning — действие заблокировано:**

```html
<td class="col-muted">
    —
</td>
```

### 2.3 Загрузка данных для таблицы

```php
// В маршруте GET /superadmin/companies после получения $companies:
// Для каждой компании выполнить запрос owner status и добавить в массив:
foreach ($companies as &$c) {
    $stmt = $pdo->prepare(
        'SELECT id, full_name FROM company_users 
         WHERE company_id = ? AND role = ? AND status = ? LIMIT 1'
    );
    $stmt->execute([$c['id'], 'company_owner', 'active']);
    $owner = $stmt->fetch();
    $c['owner_id'] = $owner ? $owner['id'] : null;
    $c['owner_name'] = $owner ? $owner['full_name'] : null;
}
```

---

## 3. Форма создания Руководителя (`superadmin_company_owner_create.php`)

### 3.1 Route
- **GET:** `/superadmin/companies/{id}/create-owner` — форма
- **POST:** `/superadmin/companies/{id}/create-owner` — обработка

### 3.2 Page structure

```html
<div class="page-head">
    <div>
        <h1>Создать Руководителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>
```

### 3.3 Validation / error notices

Перед формой — блок ошибок валидации и бизнес-ошибок:

```html
<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<?php if ($ownerExists): ?>
    <div class="notice warn">
        Руководитель для этой компании уже создан: <strong><?= e($existingOwner['full_name']) ?></strong> (логин: <?= e($existingOwner['login']) ?>).
        Дублирование невозможно.
    </div>
<?php endif; ?>
```

### 3.4 Success state

После успешного создания — отдельный экран результата (НЕ редирект сразу):

```html
<div class="page-head">
    <div>
        <h1>Руководитель создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-primary">← К реестру</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Главный пользователь успешно создан. Ниже — данные для передачи Руководителю.
        </div>

        <div class="kv" style="margin-top:16px">
            <div class="kv-row">
                <span class="kv-key">Компания</span>
                <span class="kv-value"><?= e($company['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ФИО</span>
                <span class="kv-value"><?= e($createdOwner['full_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Логин</span>
                <span class="kv-value"><code><?= e($createdOwner['login']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Временный пароль</span>
                <span class="kv-value">
                    <code style="background:var(--warn-bg);padding:2px 6px;border-radius:3px"><?= e($tempPassword) ?></code>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Роль</span>
                <span class="kv-value">Руководитель</span>
            </div>
        </div>

        <div class="notice warn" style="margin-top:16px">
            ⚠️ Временный пароль показан только один раз. Сохраните его или передайте Руководителю сейчас.
            Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-primary">← К реестру компаний</a>
        </div>
    </div>
</div>
```

### 3.5 Form (creation)

```html
<form method="post" action="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="panel">
    <div class="panel-body">

        <!-- Section: Основные данные -->
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

        <!-- Section: Контакты -->
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
                <?php if (!empty($errors['phone'])): ?>
                    <div class="field-msg is-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: Дополнительно -->
        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Form actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать Руководителя</button>
            <a href="/superadmin/companies" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>
```

---

## 4. Form fields specification

| # | Field | Type | Required | Validation | Class |
|---|-------|------|----------|------------|-------|
| 1 | `full_name` | text | **YES** | not empty | `.field-input` |
| 2 | `login` | text | **YES** | not empty, latin/digits/underscore | `.field-input` |
| 3 | `password` | text | no | auto-generated if empty | `.field-input` |
| 4 | `email` | email | no | valid email if filled | `.field-input` |
| 5 | `phone` | text | no | — | `.field-input` |
| 6 | `comments` | textarea | no | — | `.field-textarea` |

---

## 5. Validation rules

| Field | Rule | Error message |
|-------|------|---------------|
| `full_name` | empty | «Обязательное поле» |
| `login` | empty | «Обязательное поле» |
| `login` | not latin/digits/underscore | «Только латинские буквы, цифры и подчёркивание» |
| `email` | filled + not valid email | «Некорректный email» |
| `company_id` | not exists | «Компания не найдена» |
| `company_id` | owner exists (active) | «Руководитель уже создан» |

---

## 6. Business logic (POST handler)

```php
// Псевдокод обработчика POST /superadmin/companies/{id}/create-owner

1. Получить company_id из URL параметра
2. Проверить, что компания существует (SELECT FROM companies WHERE id = ?)
   - Если нет → 404 или formError «Компания не найдена»
3. Проверить, что нет активного Руководителя:
   SELECT COUNT(*) FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active'
   - Если > 0 → $ownerExists = true, показать блок с инфой о существующем
4. Валидировать full_name (обязательное, не пустое)
5. Валидировать login (обязательное, не пустое, только [a-zA-Z0-9_])
6. Если password пустой → сгенерировать случайный (10 символов, буквы+цифры)
7. Валидировать email если заполнен
8. Если ошибки валидации → показать форму снова с ошибками
9. password_hash = password_hash($password, PASSWORD_BCRYPT)
10. INSERT INTO company_users (company_id, full_name, login, email, phone, password_hash, role, status, comments)
11. Показать страницу успеха с:
    - full_name
    - login
    - временный пароль (открытый текст, только в $tempPassword переменной)
    - предупреждение «показан один раз»
12. Пароль НЕ сохраняется в:
    - БД открытым текстом
    - сессии (сессий пока нет)
    - логах
    - MD-файлах
    - git diff
```

---

## 7. Password generation

```php
function generatePassword(int $length = 10): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
```

---

## 8. JavaScript for password generation

```html
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

---

## 9. Empty / error states

### Company not found
```html
<div class="notice warn">
    Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
</div>
```

### Owner already exists
```html
<div class="notice warn">
    Руководитель для этой компании уже создан: <strong>Иванов Иван</strong> (логин: ivanov).
    Дублирование невозможно.
</div>
<div class="form-actions" style="margin-top:16px">
    <a href="/superadmin/companies" class="btn btn-primary">← К реестру компаний</a>
</div>
```

### Company has error/provisioning status — кнопка создания заблокирована в таблице

---

## 10. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | Existing — SUPERADMIN is-active |
| CORE-03 | Topbar | Existing |
| CORE-05 | Page header | Page head for creation form and success |
| CORE-08 | Panel | Form container, success display |
| CORE-17 | Primary button | «Создать Руководителя», «← К реестру» |
| CORE-19 | Ghost button | «Отмена», «Просмотреть» |
| CORE-20 | Toolbar button | «Сгенерировать» пароль |
| CORE-26 | Form field | All form inputs |
| CORE-27 | Form section | Grouped fields |
| CORE-28 | Validation/error | Required field errors |
| CORE-32 | Notice | Success message |
| CORE-33 | Warning notice | Owner exists, validation errors |
| CORE-13 | Data table | Companies table (modified) |
| CORE-24 | Status badge | Company statuses (existing) |
| CORE-36 | KV list | Success screen details |

**COMPOSITE pattern:** PATTERN-02 Form page + table integration

---

## 11. SOURCE MAPPING (summary)

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
| KV list | CORE-36 | `.kv`, `.kv-row`, `.kv-key`, `.kv-value` |
| Owner dot | CORE-24 | `.dot`, `background:var(--ok)` |

---

## 12. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush |
|--------|-------|---------------|-------|
| `.panel` | `.panel-head` | 0 | YES |
| `.panel` | `.panel-body` | 0 | Padding in body |
| `.panel-body` | `.form-section` | 10px | — |
| `.kv` | `.kv-row` | — | border-bottom divider |

---

## 13. Forbidden

- Не менять `main.php` shell/sidebar/topbar
- Не придумывать новые CSS-классы вне Core Kit
- Не хранить открытый пароль в БД
- Не писать пароль в логи, MD, git diff
- Не коммитить `.env` и секреты
- Не создавать логиста, клиентов, подрядчиков
- Не делать глобальную авторизацию
- Не менять existing Companies Registry provisioning
- Не менять DB/storage naming logic от ID
- Demo-placeholder UI запрещён

---

## 14. Coder implementation checklist

- [ ] Создать миграцию 006: таблица `company_users`
- [ ] Применить миграцию (идемпотентно)
- [ ] Добавить маршруты GET/POST `/superadmin/companies/{id}/create-owner`
- [ ] Модифицировать `superadmin_companies.php`: добавить колонку «Руководитель», загружать owner status
- [ ] Создать view `superadmin_company_owner_create.php` (форма + success)
- [ ] Реализовать валидацию (full_name, login required)
- [ ] Реализовать автогенерацию пароля
- [ ] Реализовать проверку существующего Руководителя (запрет дубля)
- [ ] password_hash() — только хэш в БД
- [ ] Временный пароль показать один раз на success page
- [ ] Не ломать создание экспедитора
- [ ] Не менять main.php
- [ ] php -l для всех PHP-файлов

---

## 15. QA formal checklist

- [ ] `/superadmin/companies` открывается, колонка «Руководитель» видна
- [ ] Кнопка «Создать Руководителя» видна для компании без owner
- [ ] Кнопка «Создать Руководителя» не видна для provisioning/error компаний
- [ ] Форма `/superadmin/companies/{id}/create-owner` открывается
- [ ] Создание Руководителя работает (INSERT + success page)
- [ ] Пароль хранится только как hash (проверить в БД: SELECT password_hash)
- [ ] Открытый пароль не хранится в БД
- [ ] Временный пароль показан на success page
- [ ] Повторное создание для той же компании блокируется
- [ ] user.company_id соответствует правильной компании
- [ ] role = 'company_owner'
- [ ] Миграция идемпотентна
- [ ] Companies Registry не сломан
- [ ] DB/storage по ID не сломаны
- [ ] Shell не сломан (main.php не изменён)
- [ ] Нет секретов в git diff
- [ ] php -l для всех изменённых PHP — OK

---

## 16. Owner review

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies`
- **VISUAL CHECK URL (form):** `http://127.0.0.1:[port]/superadmin/companies/1/create-owner`
- **Manual owner visual review required:** YES (deferred to UI polish cycle)
- **Commit allowed before owner visual approval:** NO (functional checkpoint commit allowed per accelerated mode)

---

## 17. Notes

- Handoff created by erp-architect in ACCELERATED FUNCTIONAL DEVELOPMENT MODE
- UI polish deferred to separate designer cycle
- KV-list (CORE-36) classes `.kv`, `.kv-row`, `.kv-key`, `.kv-value` already in `app.css`
- Temporary password generation: inline JS function `generatePassword()`
- Coder must not implement login/auth — only user record creation
