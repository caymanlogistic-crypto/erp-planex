# SUPERADMIN Company Management — Handoff v2.1

## Status
**HANDOFF_READY** (v2.1 — added D2 action classification + D3 danger zone pattern). Supersedes v2.0.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md §0a`. Shell grid, topbar (светлый, 38px, brand+crumbs+user), sidebar (тёмный, 224px), nav items (34px, 600, 12.5px) — all COMPLIANT. NO REBUILD NEEDED.

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: 224px 1fr; grid-template-rows: 38px 1fr` | `main.php` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 | `main.php` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | `main.php` | YES |
| Nav item active | `::before` pseudo 2px gold | `main.php` | YES |

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
SUPERADMIN — Компания: [company.name]
```

---

## ACTION CLASSIFICATION (D2 — MANDATORY)

All card-level actions on this page MUST follow the SUPERADMIN action classification taxonomy:

| Label | Class | Visual class | Confirm | Notes |
|-------|-------|-------------|---------|-------|
| «Редактировать» | EDIT | `.btn-primary` | NO | Primary action — navigate to edit form |
| «← К реестру» | NAVIGATION | `.btn-ghost` | NO | Back navigation |
| «Все пользователи» | NAVIGATION | `.btn-secondary` | NO | Navigate to users page |
| «Все справочники» | NAVIGATION | `.btn-secondary` | NO | Navigate to directories page |
| «Все документы» | NAVIGATION | `.btn-secondary` | NO | Navigate to documents page |
| «Все доступы» | NAVIGATION | `.btn-secondary` | NO | Navigate to access grants page |
| «Активировать» | STATE_CHANGE | `.btn-primary` or `.btn-ghost` | `confirm()` | Enable company |
| «Заблокировать» | DESTRUCTIVE | `.btn-danger` | `confirm()` | Block active company |
| «Архивировать» | DESTRUCTIVE | `.btn-danger` | `confirm()` | Archive company (keeps data) |
| «Деактивировать» | STATE_CHANGE | `.btn-danger` or `.btn-ghost` | `confirm()` | Deactivate company |
| «Полное удаление компании» | DESTRUCTIVE | `.btn-danger` | Dedicated page | Hard delete — goes to `/delete` page |

### DANGER ZONE PATTERN (D3 — MANDATORY)

Card-level destructive actions (block, archive, hard delete) MUST be grouped in a visually distinct "Danger Zone" section.

```html
<div class="panel" style="border-color:var(--danger)">
    <div class="panel-head" style="background:var(--danger-bg)">
        <h2 style="color:var(--danger)">Опасная зона</h2>
    </div>
    <div class="panel-body">
        <div class="notice danger" style="margin-bottom:16px">
            <!-- Warning text describing the consequences -->
        </div>
        <div class="form-actions">
            <!-- Destructive action buttons -->
        </div>
    </div>
</div>
```

**Rules:**
- `.panel` border: `var(--danger)` (#992e26)
- `.panel-head` background: `var(--danger-bg)` (#fcecea)
- Panel head title (`h2`): `color: var(--danger)`
- Warning text: `.notice.danger` inside `.panel-body`
- Actions: `.btn-danger` for immediate actions, `.btn-danger` link for navigation to dedicated confirmation page
- `confirm()` scope: `DESTRUCTIVE` card-level non-delete actions (block, archive) use JS `confirm()`; hard delete navigates to a dedicated confirmation page

**Confirm phrase templates:**
- Block: `«Заблокировать компанию [name]? Пользователи не смогут войти.»`
- Archive: `«Архивировать компанию [name]? Все данные сохранятся.»`
- Hard delete: Navigate to `/superadmin/companies/{id}/delete` — uses TYPED confirmation (see superadmin-company-delete.md)

---

## Назначение
Управление компанией (экспедитором) из панели SUPERADMIN: просмотр расширенной карточки, редактирование полей, смена статуса, мониторинг пользователей, справочников, документов и доступов.

## Маршруты
- `GET /superadmin/companies/{id}` — карточка компании (view)
- `GET /superadmin/companies/{id}/edit` — форма редактирования
- `POST /superadmin/companies/{id}/edit` — сохранение изменений (redirect на view)
- `POST /superadmin/companies/{id}/activate` — активация компании
- `POST /superadmin/companies/{id}/block` — блокировка компании
- `POST /superadmin/companies/{id}/archive` — архивирование компании

## DB
- Таблица: `companies` в центральной БД
- Поля, которые РЕДАКТИРУЮТСЯ: name, inn, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, status, comments
- Поля, которые НЕЛЬЗЯ менять: id, key, db_identifier, storage_path, folder_path, settings_json, error_message, entity_type, short_name, created_at, updated_at
- Для owner info: SELECT из `company_users` WHERE company_id=? AND role='company_owner' (любой статус, не только active)

## Страница 1: Карточка компании (view)

### Layout
- page-head: «Компания: [name]», subtitle «ID: [id] · Статус: [status badge]»
- page-head-actions: «Редактировать» (btn-primary → /superadmin/companies/{id}/edit), «← К реестру» (btn-ghost → /superadmin/companies)

### Секции (внутри .panel > .panel-body)

**1. Основные данные (KV-list)**
| Ключ | Поле |
|------|------|
| Название | name |
| ИНН | inn |
| КПП | kpp или «—» |
| ОГРН | ogrn или «—» |
| Статус | status badge |
| Комментарий | comments или «—» |

**2. Адреса (KV-list)**
| Ключ | Поле |
|------|------|
| Юридический адрес | legal_address или «—» |
| Фактический адрес | physical_address или «—» |

**3. Контакты (KV-list)**
| Ключ | Поле |
|------|------|
| Контактное лицо | contact_person или «—» |
| Телефон | contact_phone или «—» |
| Email | contact_email или «—» |

**4. Техническая информация (KV-list)**
| Ключ | Поле |
|------|------|
| Company ID | id |
| Локальная БД | db_identifier или «—» |
| Локальная БД существует | YES/NO (проверка: подключение к db_identifier) |
| Storage | storage_path или «—» |
| Storage существует | YES/NO (file_exists проверка) |
| Provisioning | status, если error — error_message |
| Создана | created_at |
| Обновлена | updated_at |

**5. Руководитель (если company_users запись существует)**
| Ключ | Поле |
|------|------|
| ФИО | full_name |
| Логин | login |
| Email | email или «—» |
| Телефон | phone или «—» |
| Статус | badge |
| Управление | ссылка «Управлять Руководителем» → /superadmin/companies/{id}/owner |

Если руководителя нет: «Руководитель не создан. <a href="/superadmin/companies/{id}/create-owner">Создать</a>»

**6. Пользователи компании (NEW)**

```html
<div class="panel">
    <div class="panel-head">
        <h2>Пользователи компании</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Всего</dt>
            <dd><?= $userStats['total'] ?></dd>
            <dt>Активных</dt>
            <dd><?= $userStats['active'] ?></dd>
            <dt>Заблокированных</dt>
            <dd><?= $userStats['blocked'] ?></dd>
            <dt>Руководитель</dt>
            <dd><?= $userStats['owner_count'] ?></dd>
            <dt>Логистов</dt>
            <dd><?= $userStats['logist_count'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/users" class="btn btn-secondary">Все пользователи</a>
        </div>
    </div>
</div>
```

**Источник данных:**
```php
// Central DB:
SELECT COUNT(*) as owner_total, 
       SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as owner_active,
       SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as owner_blocked
FROM company_users WHERE company_id = ?

// Local DB (через db_identifier):
SELECT COUNT(*) as logist_total,
       SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as logist_active,
       SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as logist_blocked
FROM users WHERE role_code = 'logist'

// Aggregation:
$userStats['total'] = owner_total + logist_total;
$userStats['active'] = owner_active + logist_active;
$userStats['blocked'] = owner_blocked + logist_blocked;
$userStats['owner_count'] = owner_total;
$userStats['logist_count'] = logist_total;
```

**7. Справочники компании (NEW)**

```html
<div class="panel">
    <div class="panel-head">
        <h2>Справочники компании</h2>
    </div>
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Справочник</th>
                        <th>Всего</th>
                        <th>Активных</th>
                        <th>Архивированных</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Клиенты</td>
                        <td class="col-num"><?= $dirs['clients_total'] ?></td>
                        <td class="col-num"><?= $dirs['clients_active'] ?></td>
                        <td class="col-num"><?= $dirs['clients_archived'] ?></td>
                        <td><button class="btn btn-secondary disabled">Открыть</button></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= $dirs['contractors_total'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_active'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_archived'] ?></td>
                        <td><button class="btn btn-secondary disabled">Открыть</button></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= $dirs['drivers_total'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_active'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_archived'] ?></td>
                        <td><button class="btn btn-secondary disabled">Открыть</button></td>
                    </tr>
                    <tr>
                        <td>Транспорт</td>
                        <td class="col-num"><?= $dirs['vehicles_total'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_active'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_archived'] ?></td>
                        <td><button class="btn btn-secondary disabled">Открыть</button></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= $dirs['crews_total'] ?></td>
                        <td class="col-num"><?= $dirs['crews_active'] ?></td>
                        <td class="col-num"><?= $dirs['crews_archived'] ?></td>
                        <td><button class="btn btn-secondary disabled">Открыть</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-secondary">Все справочники</a>
        </div>
    </div>
</div>
```

**Источник данных:** локальная БД компании. Запросы:
```php
// SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
//        SUM(CASE WHEN status='archived' THEN 1 ELSE 0 END) as archived
// FROM clients / contractors / drivers / vehicles / crews
```

**8. Документы компании (NEW)**

```html
<div class="panel">
    <div class="panel-head">
        <h2>Документы компании</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Всего документов</dt>
            <dd><?= $docStats['total'] ?></dd>
            <dt>Активных (не archived)</dt>
            <dd><?= $docStats['active'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/documents" class="btn btn-secondary">Все документы</a>
        </div>
    </div>
</div>
```

**Источник данных:** локальная БД компании, таблица documents.
```php
// SELECT COUNT(*) as total, 
//        SUM(CASE WHEN status != 'archived' THEN 1 ELSE 0 END) as active
// FROM documents
```

**9. Доступы (NEW)**

```html
<div class="panel">
    <div class="panel-head">
        <h2>Доступы</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Выданных доступов</dt>
            <dd><?= $accessStats['total'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/access-grants" class="btn btn-secondary">Все доступы</a>
        </div>
    </div>
</div>
```

**Источник данных:** локальная БД компании, таблица entity_access_grants.
```php
// SELECT COUNT(*) as total FROM entity_access_grants
```

**10. Кнопки статусных действий на карточке (UPDATED — DANGER ZONE PATTERN)**

Status actions grouped by type:
- STATE_CHANGE actions in a normal "Действия" panel
- DESTRUCTIVE actions in a separate "Опасная зона" panel (D3 pattern)

```html
<!-- Normal actions panel -->
<div class="panel">
    <div class="panel-head">
        <h2>Действия</h2>
    </div>
    <div class="panel-body">
        <div class="form-actions">
            <?php if ($company['status'] !== 'active'): ?>
            <!-- STATE_CHANGE: Активировать -->
            <form method="post" action="/superadmin/companies/<?= $id ?>/activate" style="display:inline" onsubmit="return confirm('Активировать компанию?')">
                <button type="submit" class="btn btn-primary">Активировать</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Danger Zone panel -->
<div class="panel" style="border-color:var(--danger)">
    <div class="panel-head" style="background:var(--danger-bg)">
        <h2 style="color:var(--danger)">Опасная зона</h2>
    </div>
    <div class="panel-body">
        <div class="notice danger" style="margin-bottom:16px">
            Действия в этом разделе изменяют статус компании и могут ограничить доступ пользователей.
        </div>
        <div class="form-actions">
            <?php if (in_array($company['status'], ['active', 'inactive'], true)): ?>
            <!-- DESTRUCTIVE: Заблокировать -->
            <form method="post" action="/superadmin/companies/<?= $id ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать компанию? Пользователи не смогут войти.')">
                <button type="submit" class="btn btn-danger">Заблокировать</button>
            </form>
            <?php endif; ?>
            <!-- DESTRUCTIVE: Архивировать -->
            <form method="post" action="/superadmin/companies/<?= $id ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
            <?php if ($company['status'] === 'active'): ?>
            <!-- STATE_CHANGE: Деактивировать -->
            <form method="post" action="/superadmin/companies/<?= $id ?>/deactivate" style="display:inline" onsubmit="return confirm('Отключить компанию?')">
                <button type="submit" class="btn btn-danger">Отключить</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hard Delete Danger Zone -->
<div class="panel" style="border-color:var(--danger)">
    <div class="panel-head" style="background:var(--danger-bg)">
        <h2 style="color:var(--danger)">Полное удаление</h2>
    </div>
    <div class="panel-body">
        <div class="notice danger" style="margin-bottom:16px">
            Полное удаление компании удалит локальную базу данных, storage-папку, пользователей, документы и все справочники. Восстановление возможно только из резервной копии.
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/delete" class="btn btn-danger">Полное удаление компании</a>
        </div>
    </div>
</div>
```

**11. Техническая информация (дополнено)**

Дополнительные поля в существующей секции «4. Техническая информация»:
| Ключ | Поле | Примечание |
|------|------|------------|
| Локальная БД существует | YES/NO | Проверка: попытка PDO подключения к db_identifier |
| Storage существует | YES/NO | Проверка: `file_exists(storage_path)` или `is_dir(storage_path)` |

### Состояния
- **company not found**: .notice.warn «Компания не найдена. ← К реестру»
- **db error (central)**: .notice.danger с сообщением ошибки
- **db error (local)**: показывать «—» или 0 для локальных counts, отдельное .notice.warn «Локальная БД недоступна»
- **success after status change**: .notice.success «Статус компании изменён» + redirect на view

## Страница 2: Редактирование компании (edit)

**Без изменений относительно v1.0.** Полная спецификация сохранена.

### Layout
- page-head: «Редактировать компанию», subtitle «[company name]»
- page-head-actions: «← К карточке» (btn-ghost → /superadmin/companies/{id})

### Форма: POST /superadmin/companies/{id}/edit
Все поля внутри `.panel > .panel-body > form`

**Секция 1: Основные данные**
| Поле | Тип | Обязательное | Валидация |
|------|-----|-------------|-----------|
| name | field-input | Да (*) | Не пустое |
| inn | field-input | Да (*) | Не пустое; unique среди companies с другим id |
| kpp | field-input | Нет | — |
| ogrn | field-input | Нет | — |

**Секция 2: Адреса**
| Поле | Тип | Обязательное |
|------|-----|-------------|
| legal_address | field-textarea | Нет |
| physical_address | field-textarea | Нет |

**Секция 3: Контакты**
| Поле | Тип | Обязательное |
|------|-----|-------------|
| contact_person | field-input | Нет |
| contact_phone | field-input | Нет |
| contact_email | field-input (type=email) | Нет |

**Секция 4: Статус и комментарий**
| Поле | Тип | Обязательное | Опции |
|------|-----|-------------|-------|
| status | field-select | Да | active / inactive / blocked / archived |
| comments | field-textarea | Нет | — |

**Кнопки:** «Сохранить» (btn-primary, type=submit), «Отмена» (btn-ghost → /superadmin/companies/{id})

### Обработка POST
1. Загрузить company по id → если нет: formError «Компания не найдена»
2. Валидировать name (required), inn (required)
3. Проверить inn на дубликат: SELECT COUNT(*) FROM companies WHERE inn=? AND id!=? → если >0: ошибка поля inn «ИНН уже используется»
4. Если ошибки: перерендерить форму с .is-error и $errors
5. UPDATE companies SET name, inn, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, status, comments WHERE id=?
6. db_identifier, storage_path, key — НЕ обновлять
7. Redirect 302 → /superadmin/companies/{id}

### Состояния
- **company not found**: .notice.warn
- **validation errors**: .is-error на полях, .field-msg под полями
- **success**: redirect → company view

---

## Статусные действия (NEW)

### POST /superadmin/companies/{id}/activate
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='active', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{id}
4. Условно: показывать только если статус не active

### POST /superadmin/companies/{id}/block
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='blocked', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{id}
4. Условно: показывать только если статус active

### POST /superadmin/companies/{id}/archive
1. Загрузить company по id → если нет: ошибка
2. UPDATE companies SET status='archived', updated_at=NOW() WHERE id=?
3. Redirect 302 → /superadmin/companies/{id}
4. **НЕ удаляет** локальную БД, storage, company_users, справочники

---

## CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context «Компания: [name]» |
| CORE-05 | Page header | page-head with title + actions |
| CORE-06 | Page actions | «Редактировать», «← К реестру» |
| CORE-08 | Panel | All KV/table sections |
| CORE-13 | Data table / ERP grid | Directories table on card |
| CORE-17 | Primary button | «Редактировать», «Активировать» |
| CORE-18 | Secondary button | «Все пользователи», «Все справочники», «Все документы», «Все доступы» |
| CORE-19 | Ghost button | «← К реестру» |
| CORE-22 | Danger button | «Заблокировать», «Архивировать», «Полное удаление» — DESTRUCTIVE actions in Danger Zone |
| CORE-23 | Disabled action | «Открыть» (directories) — SUPERADMIN не управляет |
| CORE-24 | Status badge | Company + owner statuses |
| CORE-26 | Form field | Edit form fields |
| CORE-27 | Form section | Edit form sections |
| CORE-28 | Validation/error | Required field errors |
| CORE-31 | Key-value list (KV) | Fact sections (dl.kv or div.kv) |
| CORE-32 | Notice | System messages |
| CORE-33 | Warning notice | Error messages, danger zone warnings |

**DANGER ZONE PATTERN (D3):** Applied via `.panel` with `border-color:var(--danger)` + `.panel-head` with `background:var(--danger-bg)` + `h2` with `color:var(--danger)`. See ACTION CLASSIFICATION section above for full spec.

**COMPOSITE pattern:** PATTERN-05 Admin/settings screen + PATTERN-06 Entity card page

---

## SOURCE MAPPING

| # | UI element | CORE module ID | Exact source | Required classes | Forbidden alternatives |
|---|------------|----------------|-------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item.is-active | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | Production CSS > .page-head | `.page-head h1`, `.text-muted` | Demo title |
| 5 | Primary action | CORE-17 | Button Matrix > Primary | `.btn-primary` | Multiple primaries |
| 6 | Secondary action | CORE-18 | Button Matrix > Secondary | `.btn-secondary` | Using ghost for nav links |
| 7 | Ghost action | CORE-19 | Button Matrix > Ghost | `.btn-ghost` | Ghost danger action |
| 8 | Danger action | CORE-22 | Button Matrix > Danger | `.btn-danger` | Danger without confirm |
| 9 | Disabled action | CORE-23 | Button Matrix > Disabled | `.disabled` | Looking clickable |
| 10 | Panel | CORE-08 | Production CSS > .panel, .panel-head, .panel-body | `.panel`, `.panel-head`, `.panel-body` | Decorative cards |
| 11 | KV list | CORE-31 | Production CSS > .kv | `.kv`, `dt`/`dd`, `.k` | No row dividers |
| 12 | Table | CORE-13 | Production CSS > .tbl, .tbl-wrap | `.tbl`, `.tbl-wrap` | Bootstrap table |
| 13 | Status badge | CORE-24 | Production CSS > .badge, .badge-ok, .badge-warn, .badge-danger | `.badge`, `.dot` | Bootstrap alert |
| 14 | Form field | CORE-26 | Production CSS > .field, .field-input | `.field`, `.field-input`, `.req` | Browser defaults |
| 15 | Form section | CORE-27 | Production CSS > .form-section | `.form-section` | Unrelated groups |
| 16 | Validation | CORE-28 | Production CSS > .is-error, .field-msg | `.is-error`, `.field-msg` | Red border only |
| 17 | Notice success | CORE-32 | Production CSS > .notice | `.notice.success` | Bootstrap alert |
| 18 | Notice warn | CORE-33 | Production CSS > .notice.warn | `.notice.warn` | Bootstrap alert-warning |
| 19 | Notice danger | CORE-33 | Production CSS > .notice.danger | `.notice.danger` | Bootstrap alert-danger |
| 20 | Form actions | — | Button Matrix | `.form-actions` | Random button order |

---

## CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes — head flush | `.panel-body` (10px) | `h2` |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | text-muted, dl.kv |
| `.kv` | `dt`, `dd` | — | — | Row: 5px 0 | `border-bottom` mandatory |
| `.tbl-wrap` | `.tbl` | — | — | — | sticky thead |
| `.form-section` | `.field` | — | — | — | label, input, msg |
| `.form-actions` | `.btn` | — | — | — | gap, right-align |

---

## Forbidden

- Не менять `main.php` shell/sidebar/topbar
- Не менять db_identifier, storage_path, key при редактировании
- Не добавлять hard delete
- Не удалять локальную БД при archive/block
- Не удалять storage при archive/block
- Не удалять company_users при смене статуса
- Не добавлять новые CSS-классы без source в Core Kit
- Не менять существующие SUPERADMIN маршруты (кроме обновления ссылок)
- Не ломать существующие модули
- **Использовать Danger Zone pattern (D3) для destructive действий**
- **Не использовать `.btn-ghost` для DESTRUCTIVE действий**
- **Не смешивать destructive и non-destructive действия в одной панели**

---

## Coder implementation checklist

- [ ] Обновить `superadmin_company_view.php`: добавить секции 6-10
- [ ] Реализовать секцию «Пользователи компании» (counts из central + local DB)
- [ ] Реализовать секцию «Справочники компании» (таблица counts из local DB)
- [ ] Реализовать секцию «Документы компании» (counts из local DB)
- [ ] Реализовать секцию «Доступы» (count из local DB)
- [ ] Добавить кнопки статусных действий на карточке
- [ ] Добавить поля «Локальная БД существует» и «Storage существует» в техинфо
- [ ] Реализовать маршруты activate/block/archive/deactivate
- [ ] **Реализовать Danger Zone pattern (D3):** раздельные панели «Действия» и «Опасная зона»
- [ ] DESTRUCTIVE кнопки используют `.btn-danger` и находятся в Danger Zone панели
- [ ] Danger Zone панель имеет `border-color:var(--danger)`, head `background:var(--danger-bg)`
- [ ] Локальная БД недоступна → показывать «—» / 0, не 500
- [ ] Соответствовать handoff по структуре и классам
- [ ] `php -l` для всех изменённых PHP — OK

## QA formal checklist

- [ ] GET /superadmin/companies/{id} → карточка с секциями 1-10
- [ ] Секция «Пользователи» показывает counts
- [ ] Секция «Справочники» показывает таблицу counts
- [ ] Секция «Документы» показывает counts
- [ ] Секция «Доступы» показывает count
- [ ] Кнопка «Активировать» видна если статус не active
- [ ] Кнопка «Заблокировать» видна если статус active
- [ ] Кнопка «Архивировать» видна всегда
- [ ] Status actions работают (POST + redirect)
- [ ] Status actions требуют confirm
- [ ] **Danger Zone панель отделена от обычных действий**
- [ ] **«Заблокировать», «Архивировать», «Полное удаление» используют `.btn-danger`**
- [ ] **Danger Zone имеет красную границу и фон заголовка**
- [ ] Никакой статус не удаляет локальную БД/storage/users
- [ ] «Локальная БД существует» показывает YES/NO
- [ ] «Storage существует» показывает YES/NO
- [ ] Ссылки навигации работают (users, directories, documents, access-grants)
- [ ] Кнопка «Редактировать» работает
- [ ] Форма редактирования работает (существующий функционал не сломан)
- [ ] Shell не сломан
- [ ] `php -l` для всех изменённых PHP — OK

## Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
