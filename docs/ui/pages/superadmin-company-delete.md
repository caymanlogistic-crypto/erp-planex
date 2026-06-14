# UI PAGE HANDOFF — SUPERADMIN Company Hard Delete

## Status
**HANDOFF_READY** (v1.0 — D3/D4 hard delete danger pattern). Production-grade handoff per `_PAGE_TEMPLATE.md`.

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING

### 0a. App Shell Foundation

**FOUNDATION STATUS: COMPLIANT.** All parameters match `superadmin-dashboard.md 0a`. NO REBUILD NEEDED.

### 0b. Sidebar IA

```
ОПЕРАЦИИ (все disabled) → [spacer] → СИСТЕМА (SUPERADMIN is-active) → [bottom] Настройки disabled
```

### 0c. Topbar context

```
SUPERADMIN — Удаление компании: [company.name]
```

---

## 1. Страница

- **Route:** `GET /superadmin/companies/{id}/delete` (confirmation page), `POST /superadmin/companies/{id}/delete` (execution)
- **View file:** `app/View/pages/superadmin_company_delete.php` (EXISTING — REFINE per D3/D4 handoff)
- **Тип:** PATTERN-05 Admin/settings — specialized danger confirmation
- **Роль:** SUPERADMIN
- **Главная задача:** Подтверждение полного удаления компании с typed confirmation
- **Что нельзя менять:** main.php, delete logic (DB drop, storage removal), backup mechanism

---

## 2. D4: confirm() SCOPE — HARD DELETE REQUIRES DEDICATED PAGE

### Browser confirm() scope rule

| Action type | Confirm method | Reason |
|-------------|---------------|--------|
| Row-level STATE_CHANGE | `confirm()` | Reversible, single entity |
| Row-level DESTRUCTIVE (archive) | `confirm()` | Data preserved, reversible |
| Card-level DESTRUCTIVE (block, archive) | `confirm()` | Data preserved, reversible |
| SECURITY (password reset) | `confirm()` | Single user, new password can be set |
| Revoke grant (when implemented) | `confirm()` | Single entity, undoable by re-granting |
| **Hard delete (company)** | **Dedicated page with typed confirmation** | Irreversible, destroys DB + storage + all data |

### Rule:
- `confirm()` is SUFFICIENT when the action is reversible or affects a single non-system entity
- A dedicated confirmation page with TYPED confirmation is REQUIRED when the action is irreversible and destroys system-critical data

---

## 3. D3: Danger Zone Pattern — Hard Delete Variant

### Layout

```html
<div class="page-head">
    <div>
        <h1>Удаление компании</h1>
        <p class="text-muted">Полное удаление компании, локальной БД, пользователей и документов</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/{id}" class="btn btn-ghost">← К карточке</a>
    </div>
</div>
```

### Section 1: Data Preview Panel

Displays all data that will be destroyed. Uses `.kv` for structured facts.

```html
<div class="panel">
    <div class="panel-head"><h2>Данные для удаления</h2></div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Название</dt><dd>[company_name]</dd>
            <dt>ИНН</dt><dd>[company_inn]</dd>
            <dt>ID</dt><dd>[company_id]</dd>
            <dt>Локальная БД</dt><dd>[db_identifier]</dd>
            <dt>Storage</dt><dd>[storage_path]</dd>
            <dt>Статус</dt><dd>[status]</dd>
            <dt>Руководитель</dt><dd>[owner_name или —]</dd>
            <dt>Логистов</dt><dd>[count]</dd>
            <dt>Клиентов</dt><dd>[count]</dd>
            <dt>Подрядчиков</dt><dd>[count]</dd>
            <dt>Водителей</dt><dd>[count]</dd>
            <dt>Транспорта</dt><dd>[count]</dd>
            <dt>Экипажей</dt><dd>[count]</dd>
            <dt>Документов</dt><dd>[count]</dd>
            <dt>Размер storage</dt><dd>[size or warning]</dd>
        </dl>
    </div>
</div>
```

### Section 2: Danger Zone — Typed Confirmation

Uses the D3 danger zone visual pattern:

```html
<div class="panel" style="border-color:var(--danger)">
    <div class="panel-head" style="background:var(--danger-bg)">
        <h2 style="color:var(--danger)">Опасная зона</h2>
    </div>
    <div class="panel-body">
        <div class="notice danger" style="margin-bottom:16px">
            Это действие полностью удалит компанию, локальную базу данных, пользователей, документы и файлы. Восстановление возможно только из резервной копии, если она была создана.
        </div>

        <form method="post" action="/superadmin/companies/{id}/delete">
            <div class="form-section">
                <p style="margin-bottom:8px">Для подтверждения введите:</p>
                <p class="col-mono" style="margin-bottom:8px;font-weight:700">DELETE COMPANY {id}</p>
                <input type="text" class="field-input" name="confirm_phrase" value="" placeholder="DELETE COMPANY {id}" style="max-width:360px" autocomplete="off">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Удалить компанию</button>
                <a href="/superadmin/companies/{id}" class="btn btn-ghost">Отмена</a>
            </div>
        </form>
    </div>
</div>
```

### Typed confirmation rules (D4 hard delete):
- **Confirm phrase template:** `DELETE COMPANY {id}` (exact, case-sensitive)
- **Validation:** Backend must compare `$_POST['confirm_phrase'] === "DELETE COMPANY {$id}"`
- **On mismatch:** Re-render page with `.notice.danger` error message: «Неверная фраза подтверждения. Введите DELETE COMPANY {id}»
- **No backup warning variant:** When backup cannot be created, show additional warning + checkbox for acknowledgment

---

## 4. Состояния

### Company not found
```html
<div class="panel"><div class="panel-body"><div class="notice warn">Компания не найдена. <a href="/superadmin/companies">← К реестру</a></div></div></div>
```

### Confirm phrase mismatch
```html
<div class="notice danger" style="margin-bottom:12px">Неверная фраза подтверждения. Введите DELETE COMPANY {id}</div>
```

### Backup warning (when backup cannot be created)
```html
<div class="notice warn" style="margin-top:12px">
    Резервная копия не создана. Локальная БД и файлы будут удалены без возможности восстановления.
</div>
<label style="display:block;margin-top:12px">
    <input type="checkbox" name="skip_backup" value="1">
    Я понимаю, что резервная копия не создана
</label>
```

### Post-delete success
- Redirect `302 → /superadmin/companies?deleted={id}`
- Show on registry: `.notice.success «Компания ID {id} полностью удалена.»`

---

## 5. CORE modules used

| ID | Module | Usage |
|----|--------|-------|
| CORE-01 | App shell | Foundation |
| CORE-02 | Sidebar navigation | SUPERADMIN is-active |
| CORE-03 | Topbar | Context |
| CORE-05 | Page header | page-head with title |
| CORE-08 | Panel | Data preview + Danger Zone panels |
| CORE-17 | Primary button | N/A (no primary on delete page) |
| CORE-19 | Ghost button | «← К карточке», «Отмена» |
| CORE-22 | Danger button | «Удалить компанию» |
| CORE-26 | Form field | Typed confirmation input |
| CORE-31 | Key-value list (KV) | Data preview facts |
| CORE-32 | Notice | System messages |
| CORE-33 | Warning notice | Danger warning, backup warning |

**COMPOSITE pattern:** PATTERN-05 Admin/settings — danger confirmation variant
**DANGER ZONE PATTERN (D3):** Applied with typed confirmation (D4 hard delete variant)

---

## 6. SOURCE MAPPING

| # | UI element | CORE module ID | Required classes | Forbidden alternatives |
|---|------------|----------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | `.app-shell` | Bootstrap container |
| 2 | Sidebar nav | CORE-02 | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | `.topbar` | Hero header |
| 4 | Page head | CORE-05 | `.page-head h1`, `.text-muted` | Demo title |
| 5 | Ghost action | CORE-19 | `.btn-ghost` | Ghost danger |
| 6 | Panel | CORE-08 | `.panel`, `.panel-head`, `.panel-body` | Decorative cards |
| 7 | Danger Zone panel | CORE-08 (modified) | `.panel` + `style="border-color:var(--danger)"` | Normal panel border |
| 8 | Danger Zone head | CORE-08 (modified) | `.panel-head` + `style="background:var(--danger-bg)"` | Normal panel head bg |
| 9 | Danger Zone title | — | `h2` + `style="color:var(--danger)"` | Normal heading color |
| 10 | KV list | CORE-31 | `.kv`, `dt`, `dd` | No row dividers |
| 11 | Danger action | CORE-22 | `.btn-danger` | Using `.btn-ghost` for destructive |
| 12 | Form field | CORE-26 | `.field-input` | Browser default input |
| 13 | Notice danger | CORE-33 | `.notice.danger` | Blue info alert |
| 14 | Notice warn | CORE-33 | `.notice.warn` | Bootstrap alert-warning |

---

## 7. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Required states |
|--------|-------|---------------|-------------------|--------------------|-----------------|
| `.panel` | `.panel-head` | 0 | Yes | `.panel-body` (10px) | `h2` |
| `.panel` (danger zone) | `.panel-head` (danger zone) | 0 | Yes | `.panel-body` (10px) | `h2` with `color:var(--danger)`, `background:var(--danger-bg)` |
| `.kv` | `dt`, `dd` | — | — | Row: 5px 0 | `border-bottom` mandatory |
| `.form-section` | `.field-input` | — | — | — | focus, placeholder |
| `.form-actions` | `.btn-danger` | — | — | — | hover: darker danger bg |
| `.form-actions` | `.btn-ghost` | — | — | — | hover: subtle surface |

---

## 8. Strict prohibitions for coder

- Не менять `main.php` shell/sidebar/topbar
- **Не использовать `confirm()` вместо typed confirmation** — hard delete требует dedicated page
- **Не пропускать проверку confirm_phrase на backend**
- Не удалять компанию без typed confirmation
- Не менять порядок удаления (backup → DB drop → storage delete → central cleanup)
- Не добавлять новые CSS-классы без source в Core Kit
- Не использовать `.btn-ghost` для DESTRUCTIVE кнопки «Удалить компанию»
- `border-radius` ≤ 4px
- `box-shadow` blur ≤ 8px

---

## 9. Coder implementation checklist

- [ ] Страница `/superadmin/companies/{id}/delete` существует и использует danger zone pattern
- [ ] Typed confirmation input: `placeholder="DELETE COMPANY {id}"`, `name="confirm_phrase"`
- [ ] Backend проверяет `$_POST['confirm_phrase'] === "DELETE COMPANY {$id}"`
- [ ] On mismatch: `.notice.danger` с сообщением
- [ ] Danger Zone panel использует `border-color:var(--danger)`, head `background:var(--danger-bg)`
- [ ] Кнопка «Удалить компанию» использует `.btn-danger`
- [ ] Кнопка «Отмена» использует `.btn-ghost`
- [ ] Data preview показывает все counts (KV-list)
- [ ] Backup warning показывается когда backup недоступен
- [ ] Post-delete: redirect + `.notice.success` на реестре
- [ ] `php -l` OK

---

## 10. QA formal checklist

- [ ] GET /superadmin/companies/{id}/delete → confirmation page
- [ ] Danger Zone panel visible with red border/head
- [ ] Typed confirmation input visible
- [ ] Submit with wrong phrase → error, page re-rendered
- [ ] Submit with correct phrase → company deleted, redirect to registry with success
- [ ] «← К карточке» и «Отмена» работают
- [ ] Company not found → warn notice
- [ ] Shell не сломан
- [ ] `php -l` OK

---

## 11. Owner visual check

- **VISUAL CHECK URL:** `http://127.0.0.1:[port]/superadmin/companies/{id}/delete`
- **Что владелец должен проверить глазами:**
  - [ ] Danger Zone panel имеет красную границу и фон заголовка
  - [ ] Typed confirmation input на месте
  - [ ] Data preview показывает все counts
  - [ ] Кнопка «Удалить компанию» красная (danger)
  - [ ] Industrial Graphite + Warm Accent сохранён
- **Manual owner visual review required:** YES
- **Commit allowed before owner visual approval:** NO
