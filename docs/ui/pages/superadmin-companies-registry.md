# UI PAGE HANDOFF — SUPERADMIN Companies Registry

## Status
**FUNCTIONALLY ACCEPTED** (accelerated mode). Manual visual approval deferred. UI polish cycle later.

## A. Foundation

### FOUNDATION STATUS: COMPLIANT
Existing foundation from `superadmin-dashboard.md §0` — NO REBUILD NEEDED.

### 0. LAYOUT FOUNDATION SOURCE MAPPING (reference)

All foundation parameters are COMPLIANT per `superadmin-dashboard.md §0a`. Shell grid, topbar (светлый, 38px, brand+ crumbs+user), sidebar (тёмный, 224px, nav-groups), nav items (34px, 600, 12.5px, SVG icons 16×16, ::before active) — all COMPLIANT. No changes needed.

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

## 1. Страница

- **Route:** `/superadmin/companies`
- **View file:** `app/View/pages/superadmin_companies.php`
- **Тип:** PATTERN-01 Table-only registry + creation form
- **Роль:** SUPERADMIN
- **Задача:** Просмотр реестра компаний, создание нового экспедитора

---

## 2. Layout

- App shell: СУЩЕСТВУЮЩИЙ (main.php) — НЕ МЕНЯТЬ
- Структура страницы сверху вниз:
  1. `page-head` — заголовок + кнопка «Создать экспедитора»
  2. `filters-bar` — поиск по названию, ИНН, статусу
  3. `panel` с таблицей компаний
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
    <input type="text" class="field-input" placeholder="Поиск по названию" name="search_name" style="max-width:200px">
    <input type="text" class="field-input" placeholder="ИНН" name="search_inn" style="max-width:140px">
    <select class="field-select" name="status" style="max-width:150px">
        <option value="">Все статусы</option>
        <option value="active">Активен</option>
        <option value="provisioning">Настройка</option>
        <option value="error">Ошибка</option>
    </select>
    <button class="btn btn-toolbar">Сбросить</button>
</div>
```

---

## 5. Main table

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
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <!-- row per company -->
                <tr>
                    <td class="col-mono"><?= $c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="col-mono"><?= e($c['inn']) ?></td>
                    <td><?= statusBadge($c['status']) ?></td>
                    <td class="col-muted"><?= e($c['created_at']) ?></td>
                    <td class="col-actions">
                        <!-- row actions on hover -->
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
| 4 | Статус | badge | — | `.badge-ok` / `.badge-warn` / `.badge-danger` |
| 5 | Создан | datetime | `.col-muted` | created_at |
| 6 | Действия | — | `.col-actions` | View action |

---

## 6. Status badges

| Status | Badge class | Dot | Text |
|--------|------------|-----|------|
| `active` | `.badge-ok` | yes | Активен |
| `provisioning` | `.badge-warn` | yes | Настройка |
| `error` | `.badge-danger` | yes | Ошибка |

---

## 7. Creation form (separate page: `/superadmin/companies/create`)

**View file:** `app/View/pages/superadmin_companies_create.php`

### Form structure

```html
<div class="page-head">
    <div>
        <h1>Создать экспедитора</h1>
        <p class="text-muted">Новая локальная ERP-система</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<form method="post" action="/superadmin/companies/create" class="panel">
    <!-- FORM SECTIONS -->

    <!-- Section: Основные данные -->
    <div class="panel-body">
        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required value="<?= e($old['name'] ?? '') ?>">
                <div class="field-msg is-error" style="display:none">Обязательное поле</div>
            </div>
            <div class="field">
                <label class="field-label">ИНН <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input" required value="<?= e($old['inn'] ?? '') ?>">
                <div class="field-msg is-error" style="display:none">Обязательное поле</div>
            </div>
            <div class="field">
                <label class="field-label">КПП</label>
                <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? '') ?>">
            </div>
        </div>

        <!-- Section: Адреса -->
        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>
            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <input type="text" name="legal_address" class="field-input" value="<?= e($old['legal_address'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <input type="text" name="physical_address" class="field-input" value="<?= e($old['physical_address'] ?? '') ?>">
            </div>
        </div>

        <!-- Section: Контакты -->
        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input" value="<?= e($old['contact_person'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input" value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input" value="<?= e($old['contact_email'] ?? '') ?>">
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
            <button type="submit" class="btn btn-primary">Создать экспедитора</button>
            <a href="/superadmin/companies" class="btn btn-ghost">Отмена</a>
        </div>
    </div>
</form>
```

### Form fields specification

| # | Field | Type | Required | Validation | Class |
|---|-------|------|----------|------------|-------|
| 1 | `name` | text | **YES** | required, not empty | `.field-input` |
| 2 | `inn` | text | **YES** | required, not empty | `.field-input` |
| 3 | `kpp` | text | no | — | `.field-input` |
| 4 | `ogrn` | text | no | — | `.field-input` |
| 5 | `legal_address` | text | no | — | `.field-input` |
| 6 | `physical_address` | text | no | — | `.field-input` |
| 7 | `contact_person` | text | no | — | `.field-input` |
| 8 | `contact_phone` | text | no | — | `.field-input` |
| 9 | `contact_email` | email | no | valid email if filled | `.field-input` |
| 10 | `comments` | textarea | no | — | `.field-textarea` |

### Validation states (CORE-28)

- Empty `name` → field `.is-error`, message «Обязательное поле»
- Empty `inn` → field `.is-error`, message «Обязательное поле»
- Failed save → error notice at top of form

---

## 8. Empty state (CORE-34)

When no companies exist:

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

## 9. Error states

### Page load error
```html
<div class="notice warn">
    Не удалось загрузить список компаний. Попробуйте позже.
</div>
```

### Provisioning error display (in table row or detail)
- Status badge: `.badge-danger` «Ошибка»
- Technical message accessible to SUPERADMIN (e.g., error_details column/field)

### Success after creation
```html
<div class="notice">
    Экспедитор создан. Выполняется настройка инфраструктуры.
</div>
```
Redirect to `/superadmin/companies` with status visible in list.

---

## 10. Loading state (CORE-35)

Skeleton rows for table while data loads.

---

## 11. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing foundation |
| CORE-02 | Sidebar navigation | Existing — SUPERADMIN is-active |
| CORE-03 | Topbar | Existing — context «Реестр компаний» |
| CORE-05 | Page header | Page head with title + action |
| CORE-06 | Page actions | «Создать экспедитора» button |
| CORE-08 | Panel | Table container, form container |
| CORE-11 | Filter toolbar | filters-bar with search/select |
| CORE-12 | Search field | Search inputs |
| CORE-13 | Data table / ERP grid | Companies table |
| CORE-14 | Table row states | hover, selected |
| CORE-16 | Pagination | When needed |
| CORE-17 | Primary button | «Создать экспедитора» |
| CORE-18 | Secondary button | Disabled actions |
| CORE-19 | Ghost button | «Отмена», «← К реестру» |
| CORE-20 | Toolbar button | «Сбросить» filter |
| CORE-24 | Status badge | provisioning/active/error |
| CORE-26 | Form field | All form inputs |
| CORE-27 | Form section | Grouped fields |
| CORE-28 | Validation/error | Required field errors |
| CORE-32 | Notice | System messages |
| CORE-33 | Warning notice | Error messages |
| CORE-34 | Empty state | No companies |
| CORE-35 | Loading skeleton | Table loading |

**COMPOSITE pattern:** PATTERN-01 Table-only registry

---

## 12. SOURCE MAPPING (summary)

| UI element | CORE module | Classes |
|------------|-------------|---------|
| Page head | CORE-05 | `.page-head h1`, `.text-muted` |
| Primary action | CORE-06, CORE-17 | `.btn-primary` |
| Filter bar | CORE-11 | `.filters-bar`, `.field-input`, `.field-select` |
| Table | CORE-13 | `.panel`, `.tbl-wrap`, `.tbl` |
| Status badge active | CORE-24 | `.badge-ok` |
| Status badge provisioning | CORE-24 | `.badge-warn` |
| Status badge error | CORE-24 | `.badge-danger` |
| Form field | CORE-26 | `.field`, `.field-label`, `.field-input`, `.req` |
| Form section | CORE-27 | `.form-section` |
| Validation error | CORE-28 | `.is-error`, `.field-msg` |
| Empty state | CORE-34 | `.empty-state`, `.btn-primary` |
| Notice | CORE-32 | `.notice` |
| Warning | CORE-33 | `.notice.warn` |
| Ghost button | CORE-19 | `.btn-ghost` |

---

## 13. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush |
|--------|-------|---------------|-------|
| `.panel` | `.panel-head` | 0 | YES — head flush |
| `.panel` | `.panel-body` | 0 | Padding in body (10px) |
| `.panel-body` | `.form-section` | 10px | — |
| `.filters-bar` | `.field-input` | — | Controls inside bar |

---

## 14. Forbidden

- Не менять `main.php` shell/sidebar/topbar
- Не придумывать новые CSS-классы
- Не использовать `slug/key` для DB/storage генерации
- Не использовать `ERP_UI_MODULE_CATALOG.html` как primary source
- Не хранить DB-пароли в БД
- Не коммитить `.env` и секреты
- Demo-placeholder UI запрещён (псевдоиконки, SaaS-dashboard, debug badges)

---

## 15. Coder implementation checklist

- [ ] Реализовать маршруты `/superadmin/companies` и `/superadmin/companies/create`
- [ ] Создать view `superadmin_companies.php` (список)
- [ ] Создать view `superadmin_companies_create.php` (форма)
- [ ] Создать/обновить таблицу `companies` в центральной БД
- [ ] Реализовать создание экспедитора: запись → ID → DB name → create DB → storage folder → status update
- [ ] Реализовать обработку ошибок provisioning (статус error, сообщение)
- [ ] Валидация обязательных полей (name, inn)
- [ ] Соответствовать этому handoff по структуре и классам
- [ ] Не менять main.php
- [ ] Не добавлять новых CSS-классов без source в Core Kit

## 16. QA formal checklist

- [ ] Страница `/superadmin/companies` открывается
- [ ] Форма `/superadmin/companies/create` открывается
- [ ] Создание экспедитора работает (центральная запись)
- [ ] Локальная БД создаётся с именем `erp_company_{id}`
- [ ] Storage-папка создаётся `storage/companies/{id}/`
- [ ] Slug/key не используется для DB/storage
- [ ] Валидация: пустой name → ошибка
- [ ] Валидация: пустой inn → ошибка
- [ ] Статус provisioning отображается
- [ ] Статус active отображается после успешного provisioning
- [ ] Статус error отображается при ошибке provisioning
- [ ] Shell не сломан
- [ ] main.php не изменён
- [ ] Нет новых CSS-классов вне Core Kit
- [ ] Нет секретов в git diff
- [ ] `php -l` для всех изменённых PHP — OK

---

## 17. Owner review

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies`
- **Manual owner visual review required:** YES (deferred to UI polish cycle)
- **Commit allowed before owner visual approval:** NO (functional checkpoint commit allowed separately)

---

## 18. Notes

- Handoff created by erp-architect in ACCELERATED FUNCTIONAL DEVELOPMENT MODE
- UI polish (exact spacing, column widths, visual refinement) deferred to separate designer cycle
- Coder must not wait for visual approval to implement functionality
- Chief designer / KLAUD review: deferred
