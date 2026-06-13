# UI PAGE HANDOFF — Company Clients (Клиенты)

## Status
**ARCHITECT-CREATED (ACCELERATED MODE).** Manual visual approval deferred. UI polish cycle later.
Functional coding was NOT blocked by design approval.

## A. Foundation

### Role and context
- **User**: Логист (role_code=logist) / Руководитель (company_owner)
- **Location**: внутри локальной ERP компании
- **Path prefix**: `/company/`
- **Company context**: `?company_id=N` (temporary)

### 0. LAYOUT FOUNDATION SOURCE MAPPING

Layout uses existing `main.php` shell — NO rebuild.

| Element | Source | Status |
|---------|--------|--------|
| App shell grid | main.php `.app-shell` | COMPLIANT |
| Topbar | main.php `.topbar` 38px | COMPLIANT |
| Sidebar | main.php `.app-sidebar` 224px | COMPLIANT |
| Nav active | main.php `.is-active` | COMPLIANT |
| Sidebar IA | existing — unchanged | COMPLIANT |

### 0b. Sidebar IA (unchanged)
```
ОПЕРАЦИИ
  Рейсы        disabled
  Водители     disabled
  Транспорт    disabled
  Клиенты      disabled
[spacer]
СИСТЕМА
  SUPERADMIN
[bottom]
  Настройки    disabled
```
**Note:** Для локальной ERP sidebar menu будет создан отдельной задачей. Сейчас menu не меняем.

### 0c. Topbar context
```
Клиенты — Компания: [company_name]
```

---

## 1. Страницы модуля

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/clients?company_id=N` | GET | Список клиентов компании |
| `/company/clients/create?company_id=N` | GET | Форма создания клиента |
| `/company/clients/create?company_id=N` | POST | Обработка создания клиента |

---

## 2. Страница списка клиентов

### 2.1 Page structure

```
page-head: «Клиенты» + subtitle company name + button «Создать клиента»
↓
panel:
  panel-body:
    if no clients → empty state
    if clients exist → table: ID, Название, ИНН, Статус, Создан
```

### 2.2 Page head

```html
<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать клиента</a>
    </div>
</div>
```

### 2.3 Empty state

```html
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Клиенты ещё не созданы.</p>
            <a href="/company/clients/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать первого клиента</a>
        </div>
    </div>
</div>
```

### 2.4 Table

```html
<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>ИНН</th>
                        <th>Статус</th>
                        <th>Создан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                    <tr>
                        <td class="col-mono"><?= $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td class="col-mono"><?= e($c['inn']) ?></td>
                        <td>
                            <span class="badge<?= $c['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($c['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

### 2.5 Error states

**Company not found:**
```html
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
```

**Company not active:**
```html
<div class="notice warn">Компания находится в статусе «<?= e($company['status']) ?>». Создание клиентов недоступно.</div>
```

**DB error:**
```html
<div class="notice warn">Не удалось подключиться к базе данных компании.</div>
```

---

## 3. Форма создания клиента

### 3.1 Page structure

```html
<div class="page-head">
    <div>
        <h1>Создать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>
```

### 3.2 Form

```html
<form method="post" action="/company/clients/create?company_id=<?= $companyId ?>" class="panel">
    <div class="panel-body">

        <?php if ($formError): ?>
            <div class="notice warn"><?= e($formError) ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">ИНН <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input" required
                       value="<?= e($old['inn'] ?? '') ?>">
                <?php if (!empty($errors['inn'])): ?>
                    <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">КПП</label>
                <input type="text" name="kpp" class="field-input"
                       value="<?= e($old['kpp'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input"
                       value="<?= e($old['contact_person'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input"
                       value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input"
                       value="<?= e($old['contact_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать клиента</button>
            <a href="/company/clients?company_id=<?= $companyId ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>
```

### 3.3 Success state

```html
<div class="page-head">
    <div>
        <h1>Клиент создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Клиент успешно создан.
        </div>

        <div class="kv" style="margin-top:16px">
            <div class="kv-row">
                <span class="kv-key">Наименование</span>
                <span class="kv-value"><?= e($createdClient['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ИНН</span>
                <span class="kv-value"><code><?= e($createdClient['inn']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/clients?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку клиентов</a>
            <a href="/company/clients/create?company_id=<?= $companyId ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>
```

---

## 4. Form fields specification

| # | Field | Type | Required | Validation | Class |
|---|-------|------|----------|------------|-------|
| 1 | `name` | text | **YES** | not empty | `.field-input` |
| 2 | `inn` | text | **YES** | not empty, unique in local DB | `.field-input` |
| 3 | `kpp` | text | no | — | `.field-input` |
| 4 | `ogrn` | text | no | — | `.field-input` |
| 5 | `legal_address` | textarea | no | — | `.field-textarea` |
| 6 | `physical_address` | textarea | no | — | `.field-textarea` |
| 7 | `contact_person` | text | no | — | `.field-input` |
| 8 | `contact_phone` | text | no | — | `.field-input` |
| 9 | `contact_email` | text | no | — | `.field-input` |
| 10 | `comments` | textarea | no | — | `.field-textarea` |

---

## 5. Validation rules

| Field | Rule | Error message |
|-------|------|---------------|
| `name` | empty | «Обязательное поле» |
| `inn` | empty | «Обязательное поле» |
| `inn` | duplicate in local DB | «ИНН уже используется в этой компании» |
| `company_id` | missing or invalid | «Компания не найдена» |
| `company_id` | company not active | «Создание клиентов недоступно» |

---

## 6. Business logic (POST handler)

```text
1. $companyId = (int)($_GET['company_id'] ?? 0)
2. Если <= 0 → formError «Компания не найдена»
3. Подключиться к центральной БД, найти компанию
4. Если не найдена или status != 'active' → formError
5. Подключиться к локальной БД компании
6. Проверить таблицу clients (query->fetch) — если нет, создать из миграции
7. Валидировать name (не пустое)
8. Валидировать inn (не пустое)
9. Проверить уникальность inn: SELECT COUNT(*) FROM clients WHERE inn = ?
   - Если > 0 → errors['inn'] = «ИНН уже используется в этой компании»
10. Если ошибки → показать форму снова
11. INSERT INTO clients (name, inn, kpp, ogrn, legal_address, physical_address,
    contact_person, contact_phone, contact_email, status, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
12. Показать success page
```

---

## 7. Local DB migration

File: `database/migrations-local/002_create_company_clients.sql`

```sql
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `inn` VARCHAR(20) NOT NULL,
    `kpp` VARCHAR(20) DEFAULT NULL,
    `ogrn` VARCHAR(20) DEFAULT NULL,
    `legal_address` VARCHAR(500) DEFAULT NULL,
    `physical_address` VARCHAR(500) DEFAULT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_inn` (`inn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing |
| CORE-02 | Sidebar | Existing (unchanged) |
| CORE-03 | Topbar | Existing |
| CORE-05 | Page header | Page head for list, form, success |
| CORE-08 | Panel | List/form container |
| CORE-13 | Data table | Client list |
| CORE-17 | Primary button | «Создать клиента» |
| CORE-19 | Ghost button | «Отмена», «Создать ещё» |
| CORE-24 | Status badge | Client status |
| CORE-26 | Form field | All inputs |
| CORE-27 | Form section | Grouped fields |
| CORE-28 | Validation/error | Field errors |
| CORE-31 | Key-value list | Success details |
| CORE-32 | Notice / success | Success message |
| CORE-33 | Warning notice | Error messages |
| CORE-34 | Empty state | No clients |

**COMPOSITE pattern:** PATTERN-01 Table-only registry + form page (same as logists).

---

## 9. SOURCE MAPPING

| UI element | CORE module | Classes |
|------------|-------------|---------|
| Page head | CORE-05 | `.page-head h1`, `.text-muted` |
| Primary button | CORE-17 | `.btn-primary` |
| Ghost button | CORE-19 | `.btn-ghost` |
| Form field | CORE-26 | `.field`, `.field-label`, `.field-input`, `.field-textarea`, `.req` |
| Form section | CORE-27 | `.form-section`, `.panel-head-title` |
| Validation | CORE-28 | `.is-error`, `.field-msg` |
| Success notice | CORE-32 | `.notice.success` |
| Warning notice | CORE-33 | `.notice.warn` |
| Panel | CORE-08 | `.panel`, `.panel-body` |
| Data table | CORE-13 | `.tbl-wrap`, `.tbl` |
| Status badge | CORE-24 | `.badge`, `.badge-ok`, `.dot` |
| KV list | CORE-31 | `.kv`, `.kv-row`, `.kv-key`, `.kv-value` |
| Empty state | CORE-34 | `.empty-state` |

---

## 10. Forbidden

- Не менять `main.php`
- Не менять `app.css`
- Не создавать клиентов в центральной БД
- Не создавать подрядчиков, транспорт, водителей, экипажи, рейсы
- Не делать edit/delete клиентов
- Не делать документы/договоры
- Не менять существующие модули (SUPERADMIN, Company Owner, Logists)
- Не менять DB/storage naming logic
- Demo-placeholder UI запрещён
- Не менять Database.php, Router.php

---

## 11. Coder checklist

- [ ] Создать миграцию `database/migrations-local/002_create_company_clients.sql`
- [ ] Добавить 3 маршрута в `public/index.php`
- [ ] Создать view `app/View/pages/company_clients.php` (5 состояний)
- [ ] Создать view `app/View/pages/company_clients_create.php` (4 состояния)
- [ ] Валидация name, inn (required + unique)
- [ ] `e()` для всех пользовательских данных
- [ ] Не ломать SUPERADMIN, Company Owner, Logists
- [ ] `php -l` для всех изменённых PHP
- [ ] Git diff без секретов

---

## 12. QA checklist

- [ ] `/company/clients?company_id=1` → 200
- [ ] `/company/clients/create?company_id=1` GET → 200
- [ ] POST создание клиента → success page
- [ ] Клиент в локальной БД, НЕ в центральной
- [ ] name/inn required validation
- [ ] duplicate inn blocked
- [ ] invalid company_id → error (not 500)
- [ ] SUPERADMIN не сломан
- [ ] Company Owner не сломан
- [ ] Logists не сломан
- [ ] Миграция идемпотентна
- [ ] main.php не изменён
- [ ] Нет секретов в git diff
- [ ] `php -l` — OK

---

## 13. Owner review

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/company/clients?company_id=1`
- **Manual owner visual review required:** YES (deferred per accelerated mode)
- **Commit allowed before owner visual approval:** YES (per ACCELERATED MODE — after QA PASS)
