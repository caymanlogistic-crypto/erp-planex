# UI PAGE HANDOFF — Company Documents Upload (Загрузка документа)

## Status
**DESIGNER HANDOFF.** Production-grade handoff for erp-coder.

## A. Foundation

### Role and context
- **User**: Руководитель (company_owner) / Логист (logist)
- **Location**: компания, форма загрузки документа для сущности
- **Path prefix**: `/company/documents/upload`
- **Company context**: `$_SESSION['company_id']` (auth/sessions уже реализованы)
- **Route guard**: `requireRole(['company_owner', 'logist'])` — маршрут `/company/*`

### 0. LAYOUT FOUNDATION SOURCE MAPPING

Layout uses existing `main.php` shell — NO rebuild.

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr` | main.php `.app-shell` | YES |
| Topbar position | spans full grid (grid-column 1/-1) | main.php `.topbar` | YES |
| Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | main.php | YES |
| Topbar border-bottom | `1px solid var(--line)` | main.php | YES |
| User block (topbar right) | имя + роль пользователя | main.php `.topbar-right` | YES |
| Sidebar background | `var(--nav-bg)` #191816 | main.php `.app-sidebar` | YES |
| Sidebar border-right | `1px solid var(--nav-divider)` | main.php | YES |
| Nav item height | 34px | main.php `.nav-item` | YES |
| Nav item font-weight | 600 ВСЕГДА | main.php | YES |
| Nav item font-size | 12.5px | main.php | YES |
| Nav icons | SVG inline 16×16, opacity 0.45 | main.php | YES |
| Nav section label | 9px, 700, uppercase, letter-spacing .12em | main.php `.nav-sec` | YES |
| Nav active state | `::before` pseudo (2px gold left) | main.php `.nav-item.is-active` | YES |
| Nav bottom block | `.nav-spacer` + `.nav-bottom` + Настройки | main.php | YES |

**Все строки = YES. Foundation COMPLIANT, менять не нужно.**

### 0b. SIDEBAR INFORMATION ARCHITECTURE

Совпадает с `company-documents-list.md`. Активный пункт в sidebar — справочник, из которого открыта загрузка (по `entity_type`).

**Руководитель (company_owner):**
```
ОПЕРАЦИИ
  Рейсы                                    disabled
  Водители    → /company/drivers           (active если entity_type=driver)
  Транспорт   → /company/vehicles          (active если entity_type=vehicle)
  Клиенты     → /company/clients           (active если entity_type=client)
  Подрядчики  → /company/contractors       (active если entity_type=contractor)
  Экипажи     → /company/crews             (active если entity_type=crew)

СИСТЕМА
  Логисты     → /company/logists
  Настройки                                disabled
```

**Логист (logist):** без пункта «Логисты».

### 0c. Topbar context

```
{EntityLabel} — Загрузка документа — Компания: {company_name}
```

---

## 1. Страница

- **Route GET:** `/company/documents/upload?entity_type=X&entity_id=Y`
- **Route POST:** `/company/documents/upload?entity_type=X&entity_id=Y`
- **Тип страницы:** form page (отдельная страница с формой)
- **Пользователь (роль):** company_owner, logist
- **Главная задача:** загрузить документ для сущности справочника

---

## 2. Состояния (5 состояний)

1. **entity_type не из whitelist** → ошибка «Неизвестный тип сущности»
2. **entity не найдена** → ошибка «{EntityLabel} не найден»
3. **ошибка подключения к локальной БД** → notice.warn
4. **форма загрузки** — основное состояние (GET)
5. **success** — документ загружен (POST success) — страница с сообщением и ссылками

---

## 3. Entity type whitelist и маппинг

| entity_type | Human label | Back route |
|-------------|-------------|------------|
| `client` | Клиент | `/company/clients` |
| `contractor` | Подрядчик | `/company/contractors` |
| `driver` | Водитель | `/company/drivers` |
| `vehicle` | Транспорт | `/company/vehicles` |
| `crew` | Экипаж | `/company/crews` |

---

## 4. Форма загрузки (основное состояние)

### 4.1 Page structure

```html
<div class="page-head">
    <div>
        <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost" style="margin-bottom:4px">← Назад к документам</a>
        <h1>Загрузить документ</h1>
        <p class="text-muted"><?= e($entityLabel) ?> «<?= e($entityName) ?>» • Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn" style="margin-bottom:8px"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post"
      action="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>"
      enctype="multipart/form-data"
      class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Документ</h3>

            <div class="field">
                <label class="field-label">Тип документа <span class="req">*</span></label>
                <input type="text" name="document_type" class="field-input" required
                       value="<?= e($old['document_type'] ?? '') ?>"
                       placeholder="Например: Договор, Паспорт, СТС, Свидетельство">
                <div class="field-msg">Укажите тип документа (договор, паспорт, доверенность и т.д.)</div>
                <?php if (!empty($errors['document_type'])): ?>
                    <div class="field-msg is-error"><?= e($errors['document_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Файл <span class="req">*</span></label>
                <input type="file" name="document_file" class="field-input" required
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <div class="field-msg">
                    Допустимые форматы: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX. Максимальный размер: 10 МБ.
                </div>
                <?php if (!empty($errors['document_file'])): ?>
                    <div class="field-msg is-error"><?= e($errors['document_file']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"
                          placeholder="Примечание к документу (необязательно)"><?= e($old['comments'] ?? '') ?></textarea>
                <div class="field-msg">Необязательное поле. Например: «Скан договора за 2025 год».</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Загрузить</button>
            <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost">← Назад к документам</a>
        </div>

    </div>
</form>
```

### 4.2 Form fields specification

| # | Field | Name | Type | Required | Validation | Class |
|---|-------|------|------|----------|------------|-------|
| 1 | Тип документа | `document_type` | text | **YES** | not empty | `.field-input` |
| 2 | Файл | `document_file` | file | **YES** | file selected, extension whitelist, size ≤ 10 MB | `.field-input` |
| 3 | Комментарий | `comments` | textarea | no | — | `.field-textarea` |

### 4.3 Разрешённые расширения (whitelist)

| Расширение | MIME типы |
|------------|-----------|
| `.pdf` | application/pdf |
| `.jpg`, `.jpeg` | image/jpeg |
| `.png` | image/png |
| `.doc` | application/msword |
| `.docx` | application/vnd.openxmlformats-officedocument.wordprocessingml.document |
| `.xls` | application/vnd.ms-excel |
| `.xlsx` | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |

Максимальный размер: **10 МБ** (10 * 1024 * 1024 = 10 485 760 байт).

### 4.4 File input note

Используется нативный `<input type="file">` с атрибутами `required` и `accept`. Стилизация: стандартный `.field-input`. Дропзона не нужна на первом этапе.

---

## 5. Validation rules

| Field | Rule | Error message |
|-------|------|---------------|
| `document_type` | empty | «Укажите тип документа» |
| `document_file` | не выбран | «Выберите файл для загрузки» |
| `document_file` | неверное расширение | «Недопустимый формат файла. Разрешены: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX» |
| `document_file` | размер > 10 МБ | «Размер файла превышает 10 МБ» |
| `document_file` | ошибка загрузки PHP (UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE) | «Размер файла превышает допустимый лимит» |
| `document_file` | ошибка загрузки PHP (другая) | «Ошибка при загрузке файла. Попробуйте ещё раз.» |
| `entity_type` | не из whitelist | «Неизвестный тип сущности» |
| `entity_id` | сущность не найдена | «{EntityLabel} не найден» |

---

## 6. Успешная загрузка (success state)

```html
<div class="page-head">
    <div>
        <h1>Документ загружен</h1>
        <p class="text-muted"><?= e($entityLabel) ?> «<?= e($entityName) ?>» • Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">← К списку документов</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Документ успешно загружен.
        </div>

        <div class="kv" style="margin-top:16px">
            <div class="kv-row">
                <span class="kv-key">Тип документа</span>
                <span class="kv-value"><?= e($createdDoc['document_type']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Имя файла</span>
                <span class="kv-value"><?= e($createdDoc['original_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Размер</span>
                <span class="kv-value"><?= e($createdDoc['file_size_formatted']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">MIME</span>
                <span class="kv-value"><?= e($createdDoc['mime_type']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">
                    <span class="badge badge-ok"><span class="dot"></span>Загружен</span>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Сущность</span>
                <span class="kv-value"><?= e($entityLabel) ?> «<?= e($entityName) ?>»</span>
            </div>
            <?php if (!empty($createdDoc['comments'])): ?>
            <div class="kv-row">
                <span class="kv-key">Комментарий</span>
                <span class="kv-value"><?= e($createdDoc['comments']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">← К списку документов</a>
            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost">Загрузить ещё</a>
        </div>
    </div>
</div>
```

---

## 7. Error states (GET)

**entity_type не из whitelist:**
```html
<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    Неизвестный тип сущности «<?= e($entityType) ?>». Допустимые типы: client, contractor, driver, vehicle, crew.
</div>
```

**entity не найдена:**
```html
<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    <?= e($entityLabel) ?> не найден. Проверьте, что сущность существует, и повторите попытку.
</div>
```

**Ошибка подключения к локальной БД:**
```html
<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.
</div>
```

---

## 8. Business logic (POST /company/documents/upload)

```text
1. $companyId = $_SESSION['company_id']
2. $entityType = $_POST['entity_type'] ?? $_GET['entity_type'] ?? ''
3. $entityId = (int)($_POST['entity_id'] ?? $_GET['entity_id'] ?? 0)

4. Проверить entity_type из whitelist
5. Подключиться к локальной БД компании

6. Проверить существование сущности:
   - Определить таблицу по entity_type
   - SELECT ... FROM {table} WHERE id = ?
   - Если не найдена → formError

7. Проверить таблицу documents (query->fetch), если нет → exec(CREATE TABLE из миграции 007)

8. Валидация полей:
   a. document_type: required, не пустое
   b. document_file: проверка $_FILES['document_file']

9. Валидация файла:
   a. UPLOAD_ERR_NO_FILE → errors['document_file'] = «Выберите файл»
   b. UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE → errors['document_file'] = «Размер превышает лимит»
   c. UPLOAD_ERR_* (другие) → errors['document_file'] = «Ошибка загрузки»
   d. Проверить расширение:
      - $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION))
      - ALLOWED: pdf, jpg, jpeg, png, doc, docx, xls, xlsx
      - Не в списке → errors['document_file'] = «Недопустимый формат»
   e. Проверить размер: filesize <= 10 * 1024 * 1024 (10 МБ)
      - Превышен → errors['document_file'] = «Размер превышает 10 МБ»

10. Генерация stored_name и пути:
    - $ext = pathinfo($originalName, PATHINFO_EXTENSION)
    - $storedName = uniqid('doc_', true) . '.' . $ext
    - $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId
    - $relativePath = $relativeDir . '/' . $storedName
    - $absoluteDir = storage_path($relativeDir)  // → storage/companies/{id}/documents/{type}/{eid}/
    - Создать папку если не существует: mkdir($absoluteDir, 0755, true)

11. Переместить файл:
    - $tmpPath = $_FILES['document_file']['tmp_name']
    - $destPath = $absoluteDir . '/' . $storedName
    - move_uploaded_file($tmpPath, $destPath)
    - Если ошибка → formError «Не удалось сохранить файл»

12. INSERT INTO documents:
    (entity_type, entity_id, document_type, original_name, stored_name,
     relative_path, mime_type, file_size, status,
     uploaded_by_user_id, uploaded_by_role, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?, ?)

    - uploaded_by_user_id = $_SESSION['user_id']
    - uploaded_by_role = $_SESSION['role_code']
    - mime_type = $_FILES['document_file']['type']
    - file_size = $_FILES['document_file']['size']

13. Показать success page с деталями загруженного документа

14. SECURITY:
    - original_name: проверить на отсутствие path traversal (../, ..\\, /, \)
    - stored_name: всегда генерируется, не из пользовательского ввода
    - relative_path: всегда строится из проверенных значений
    - Файлы НЕ сохраняются в public/
    - Доступ к storage/ только через файловую систему
```

---

## 9. Storage path

```text
storage/
  companies/
    {company_id}/
      documents/
        {entity_type}/        ← client | contractor | driver | vehicle | crew
          {entity_id}/
            doc_677a1b2c3d4e5.pdf
            doc_677a1b3f5g6h7.jpg
```

Пример:
```text
storage/companies/1/documents/client/5/doc_677a1b2c3d4e5.pdf
```

---

## 10. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Existing layout |
| CORE-02 | Sidebar navigation | Existing, active item по entity_type |
| CORE-03 | Topbar | Existing |
| CORE-05 | Page header | Page head для формы загрузки и success |
| CORE-06 | Page actions area | Кнопка «← К списку документов» на success page |
| CORE-08 | Panel | Контейнер формы / success KV |
| CORE-17 | Primary button | «Загрузить» (submit), «← К списку документов» (success) |
| CORE-19 | Ghost button | «← Назад к документам» (возврат из формы), «Загрузить ещё» (success) |
| CORE-24 | Status badge | Статус «Загружен» на success page |
| CORE-26 | Form field | Все поля формы |
| CORE-27 | Form section | Группировка полей |
| CORE-28 | Validation/error | Сообщения об ошибках под полями |
| CORE-31 | Key-value list | Детали загруженного документа на success page |
| CORE-32 | Notice / success | Сообщение «Документ успешно загружен» |
| CORE-33 | Warning notice | Ошибки валидации и бизнес-логики |

**COMPOSITE pattern:** PATTERN-01 Table-only registry + form page (форма как отдельная страница, success с KV).

---

## 11. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS > .app-shell | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | Production CSS > .nav-item, .nav-item:hover, .nav-item.is-active | `.nav-item`, `.is-active`, `:hover` mandatory | Missing hover state |
| 3 | Topbar | CORE-03 | Production CSS > .topbar | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | Section > CORE-05 preview | `.page-head h1`, `.text-muted` | Hero-scale block |
| 5 | Page actions | CORE-06 | Section > CORE-06 preview | `.page-head-actions` | Multiple primary buttons |
| 6 | Panel | CORE-08 | Production CSS > PANEL RULE: padding 0, overflow hidden | `.panel`, `.panel-body` | Padding on `.panel` |
| 7 | Primary button | CORE-17 | Production CSS > .btn-primary | `.btn-primary` | Multiple competing primary |
| 8 | Ghost button | CORE-19 | Production CSS > .btn-ghost | `.btn-ghost` | Ghost for main action |
| 9 | Status badge | CORE-24 | Production CSS > .badge, .badge-ok | `.badge`, `.badge-ok`, `.dot` | Bootstrap badge |
| 10 | Form field | CORE-26 | Production CSS > .field, .field-label, .field-input, .field-textarea, .req | `.field`, `.field-label`, `.field-input`, `.field-textarea`, `.req` | Browser-default input |
| 11 | Form section | CORE-27 | Section > CORE-27 preview | `.form-section`, `.panel-head-title` | Unrelated fields mixed |
| 12 | Validation error | CORE-28 | Production CSS > .is-error, .field-msg | `.is-error`, `.field-msg` (color: danger) | Red border without message |
| 13 | Key-value list | CORE-31 | Production CSS > KEY-VALUE RULE: grid 94px 1fr, border-bottom mandatory | `.kv`, `.kv-row`, `.kv-key`, `.kv-value`, `border-bottom` on rows | Missing row dividers |
| 14 | Success notice | CORE-32 | Production CSS > .notice | `.notice.success` (success bg + border) | Blue alert, toast-only |
| 15 | Warning notice | CORE-33 | Production CSS > .notice.warn | `.notice.warn` | Bright alert style |
| 16 | Form actions | — | Standard pattern from existing handoffs | `.form-actions` | — |
| 17 | File input | — | `<input type="file">` with `.field-input` class | `.field-input` on file input | Custom dropzone (not in scope) |

---

## 12. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------|-------------|-----------------|
| `.panel` | `.panel-body` | 0 (by rule) | — | `.panel-body` (10px) | — | — |
| `.panel-body` | `.form-section` | 10px | — | `.form-section` margin-bottom | — | `.panel-head-title` for heading |
| `.form-section` | `.field` | — | — | `.field` margin-bottom: 6px | — | — |
| `.field` | `.field-label` | — | — | — | — | `.req` for required marker |
| `.field` | `.field-input` | — | — | — | `--line-soft` | `:focus` border accent |
| `.field` | `.field-textarea` | — | — | — | `--line-soft` | min-height: 50px |
| `.field` | `.field-msg` | — | — | — | — | `.is-error` color danger |
| `.field.is-error` | `.field-input` | — | — | — | `#ca8880` | background `#fff8f7` |
| `.panel-body` | `.form-actions` | 10px | — | — | — | buttons with gap |
| `.kv` | `.kv-row` | — | — | `border-bottom: 1px solid var(--line-hair)` mandatory | `--line-hair` | `.kv-key` font-weight 700 |
| `.page-head` | `h1` | — | — | — | — | — |

---

## 13. MODULE USAGE DECISIONS

- **Main page purpose**: загрузка файла документа для сущности справочника.
- **Primary work object**: форма загрузки файла.
- **Main layout selected**: Form page (отдельная страница).
- **Composite pattern**: PATTERN-01 + form page (таблица на list, форма на отдельной странице, success KV).
- **Primary action**: «Загрузить» (submit формы).
- **Secondary actions**: «← Назад к документам» (возврат без сохранения), «Загрузить ещё» (success).
- **Form required**: YES — три поля: тип документа, файл, комментарий.
- **Table required**: NO — на этой странице только форма.
- **Inspector required**: NO.
- **Modal required**: NO.
- **Empty state required**: NO — форма всегда показывается (GET).
- **Error states required**: YES — валидация полей, невалидный entity_type, entity не найдена.
- **Modules NOT used and why**:
  - CORE-11 (Filter toolbar) — форма, не список
  - CORE-13 (Data table) — форма, не таблица
  - CORE-14/15 (Row states/actions) — нет таблицы
  - CORE-16 (Pagination) — нет списка
  - CORE-20 (Toolbar button) — нет тулбара
  - CORE-23 (Disabled) — нет disabled-действий на форме
  - CORE-29 (Upload/document input с doc-row) — используем нативный file input, не drag-and-drop дропзону
  - CORE-34 (Empty state) — не применимо к форме
  - CORE-37 (Danger confirmation) — удаление не реализуется
  - CORE-39 (Toast) — используем success page вместо toast
  - CORE-43 (Document section с doc-row) — не применимо к форме загрузки

---

## 14. Strict prohibitions for coder

- Не менять `main.php`, `app.css`, `Database.php`, `Router.php` без отдельного разрешения.
- Не сохранять загруженные файлы в `public/`.
- Не использовать оригинальное имя файла как stored_name (генерировать `uniqid()`).
- Не использовать пользовательский ввод для построения путей (entity_type/entity_id проверены).
- Не допускать path traversal в original_name.
- Не реализовывать drag-and-drop дропзону.
- Не реализовывать предпросмотр загруженного файла.
- Не реализовывать множественную загрузку (`multiple`).
- Не реализовывать статусы verified / rejected.
- Не менять бизнес-логику существующих справочников.
- Не создавать документы в центральной БД.
- Не использовать browser-default input/select.
- Не использовать demo-placeholder UI.
- Не добавлять псевдоиконки и emoji.
- Не добавлять inline styles кроме динамических PHP.
- `border-radius` новых элементов ≤ 4px.
- `box-shadow` blur новых элементов ≤ 8px.

---

## 15. Coder implementation checklist

- [ ] Добавить маршруты GET + POST `/company/documents/upload` в `public/index.php`
- [ ] Реализовать валидацию entity_type (whitelist) перед показом формы
- [ ] Реализовать поиск сущности в локальной БД для page head контекста
- [ ] Создать view `app/View/pages/company_documents_upload.php` (форма + success)
- [ ] Реализовать валидацию document_type (required, not empty)
- [ ] Реализовать валидацию файла: наличие, расширение, размер (10 МБ)
- [ ] Реализовать генерацию stored_name через `uniqid('doc_', true)`
- [ ] Реализовать создание папки `storage/companies/{id}/documents/{type}/{eid}/`
- [ ] Реализовать `move_uploaded_file()` с проверкой ошибок
- [ ] Реализовать INSERT в таблицу documents локальной БД
- [ ] Реализовать форматирование file_size для success page
- [ ] Реализовать success page с KV-деталями загруженного документа
- [ ] Проверить: файл физически сохранён в storage, не в public
- [ ] Проверить: original_name очищен от path traversal
- [ ] Sidebar: активный пункт меню по entity_type
- [ ] Topbar: контекст «{EntityLabel} — Загрузка документа — Компания: {name}»
- [ ] `e()` для всех пользовательских данных
- [ ] `enctype="multipart/form-data"` на форме
- [ ] `php -l` для всех изменённых PHP-файлов
- [ ] Git diff без секретов
- [ ] Не ломать существующие модули

---

## 16. QA formal checklist

- [ ] GET `/company/documents/upload?entity_type=client&entity_id=1` → 200 (форма)
- [ ] GET `/company/documents/upload?entity_type=invalid&entity_id=1` → error
- [ ] GET `/company/documents/upload?entity_type=client&entity_id=99999` → error «Клиент не найден»
- [ ] Форма содержит 3 поля: Тип документа, Файл, Комментарий
- [ ] Поля document_type и file отмечены required
- [ ] File input имеет `accept` с разрешёнными расширениями
- [ ] Форма имеет `enctype="multipart/form-data"`
- [ ] Кнопка «← Назад к документам» ведёт на список документов сущности
- [ ] POST без файла → ошибка «Выберите файл для загрузки»
- [ ] POST без document_type → ошибка «Укажите тип документа»
- [ ] POST с файлом .exe → ошибка «Недопустимый формат файла»
- [ ] POST с файлом > 10 МБ → ошибка «Размер файла превышает 10 МБ»
- [ ] POST с валидным PDF → success page «Документ загружен»
- [ ] Файл физически сохранён в `storage/companies/{id}/documents/{type}/{eid}/`
- [ ] Файл имеет сгенерированное имя (не оригинальное)
- [ ] Запись в таблице documents с корректными полями
- [ ] `uploaded_by_user_id` и `uploaded_by_role` из сессии
- [ ] Status = 'uploaded'
- [ ] Success page показывает KV с деталями
- [ ] «← К списку документов» → список документов
- [ ] «Загрузить ещё» → форма загрузки
- [ ] `main.php` не изменён
- [ ] `app.css` не изменён
- [ ] Нет файлов в `public/`
- [ ] Нет секретов в git diff
- [ ] `php -l` для всех изменённых PHP — OK

---

## 17. Owner review

- **VISUAL CHECK URL (form):** `http://127.0.0.1:[port]/company/documents/upload?entity_type=client&entity_id=1`
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO

---

## 18. Designer production handoff

### Target visual result

Строгая ERP-форма загрузки: page-head с контекстом сущности + back-link, форма в panel с двумя секциями (документ + дополнительно), кнопки «Загрузить» и «← Назад к документам». Success — страница с сообщением «Документ загружен» и KV-деталями.

### Exact layout

- App shell: sidebar 224px + topbar 38px + content
- Content: page-head → panel с формой
- Форма: form-section «Документ» → form-section «Дополнительно» → form-actions
- Scroll: content scrolls vertically

### Exact typography

- Page title: 15-16px, 700 — «Загрузить документ» / «Документ загружен»
- Subtitle: 12px, muted — тип сущности + имя + компания
- Back-link: 12px, 600, ghost button style
- Section title: 12px, 700, uppercase (`.panel-head-title`)
- Field label: 10.5px, 700
- Field input: 12px
- Field message: 11px, muted
- KV key: 12px, faint, 700
- KV value: 12px

### Exact spacing

- Page padding: 12px 14px 28px (content area)
- Panel padding: 0 (panel), 10px (panel-body)
- Form section gap: margin-top 14px
- Field margin-bottom: 6px
- Control height: 28px
- Button height: 30px

### Exact color tokens

- Page title: `--text-main` (#131210)
- Subtitle: `--text-muted` (#4c4840)
- Back-link: `--text-muted`
- Field border: `--line-soft` (#c9c3b8)
- Field background: `--surface-field` (#fefdf8)
- Error border: #ca8880, bg #fff8f7
- Success: `--success` (#1f6b43), bg `--success-bg` (#ddeee5)
- Upload button: `--accent` (#7c4718)
- Warning: `--warning` (#7a5210), bg `--warning-bg` (#fdf0d5)
- KV row divider: `--line-hair` (#dedad0)
