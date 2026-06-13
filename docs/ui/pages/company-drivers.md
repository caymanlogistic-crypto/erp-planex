# UI PAGE HANDOFF — Company Drivers (Водители)

## Status
**ARCHITECT-CREATED (ACCELERATED MODE).** Manual visual approval deferred. UI polish cycle later.

## A. Foundation

### 0. LAYOUT FOUNDATION — COMPLIANT (unchanged)

### 0b. Sidebar IA (unchanged)
```
ОПЕРАЦИИ — disabled
СИСТЕМА — SUPERADMIN
Настройки — disabled
```

### 0c. Topbar context
```
Водители — Компания: [name]
```

---

## 1. Страницы

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/drivers?company_id=N` | GET | Список водителей |
| `/company/drivers/create?company_id=N` | GET | Форма создания |
| `/company/drivers/create?company_id=N` | POST | Обработка создания |

---

## 2. Страница списка (5 состояний)

Паттерн: как `company_contractors.php`. Состояния:
1. `company === null` — компания не найдена (notice.warn)
2. `company['status'] !== 'active'` — компания не активна
3. `isset($dbError)` — ошибка подключения к локальной БД
4. `empty($drivers)` — пустое состояние (empty-state + кнопка «Создать первого водителя»)
5. Таблица с водителями

**Table columns:** ID, ФИО, Телефон, Дата выдачи ВУ, Дата окончания ВУ, Статус (badge), Создан.

**Кнопка:** «Создать водителя» в page-head-actions.

---

## 3. Форма создания (4 состояния)

Паттерн: как `company_contractors_create.php`. Состояния:
1. `company === null` — notice.warn
2. `company['status'] !== 'active'` — notice.warn о недоступности
3. `$success` — success page с деталями созданного водителя (поля из kv, кнопки «← К списку» + «Создать ещё»)
4. Форма (основное состояние) — с валидацией

**Sections:**
- Основные данные: ФИО*, Телефон*
- Водительское удостоверение: Номер ВУ, Категория, Дата выдачи, Дата окончания
- Дополнительно: Комментарий

---

## 4. Form fields

| # | Field | Type | Required | Validation |
|---|-------|------|----------|------------|
| 1 | `full_name` | text | YES | not empty |
| 2 | `phone` | text | YES | not empty, unique in local DB |
| 3 | `license_number` | text | no | — |
| 4 | `license_category` | text | no | — |
| 5 | `license_issue_date` | date | no | — |
| 6 | `license_expire_date` | date | no | — |
| 7 | `comments` | textarea | no | — |

---

## 5. Validation

| Field | Rule | Message |
|-------|------|---------|
| `full_name` | empty | «Обязательное поле» |
| `phone` | empty | «Обязательное поле» |
| `phone` | duplicate | «Телефон уже используется в этой компании» |

---

## 6. Business logic (POST)

```text
1. company_id из $_GET
2. Найти компанию в центральной БД
3. Проверить company.status === 'active'
4. Подключиться к локальной БД (db_identifier)
5. Проверить таблицу drivers: query("SELECT 1 FROM drivers LIMIT 1")->fetch()
   - Если нет → exec(CREATE TABLE из миграции)
6. Валидировать full_name, phone
7. Проверить уникальность phone: SELECT COUNT(*) FROM drivers WHERE phone = ?
8. INSERT → success page
```

---

## 7. Migration

`database/migrations-local/004_create_company_drivers.sql`

```sql
CREATE TABLE IF NOT EXISTS `drivers` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `license_number` VARCHAR(50) DEFAULT NULL,
    `license_category` VARCHAR(50) DEFAULT NULL,
    `license_issue_date` DATE DEFAULT NULL,
    `license_expire_date` DATE DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. CORE modules

| ID | Module | Usage |
|----|--------|-------|
| CORE-05 | Page header | List/form/success |
| CORE-08 | Panel | Container |
| CORE-13 | Data table | Driver list |
| CORE-17 | Primary button | «Создать водителя» |
| CORE-19 | Ghost button | «Отмена», «Создать ещё» |
| CORE-24 | Status badge | Status |
| CORE-26 | Form field | Inputs |
| CORE-27 | Form section | Sections |
| CORE-28 | Validation | Errors |
| CORE-31 | KV list | Success |
| CORE-32 | Notice | Success |
| CORE-33 | Warning | Errors |
| CORE-34 | Empty state | No data |

**PATTERN:** PATTERN-01 (Table-only registry + form page)

---

## 9. Coder checklist
- [ ] Migration `004_create_company_drivers.sql` in `database/migrations-local/`
- [ ] 3 routes in `public/index.php`
- [ ] `company_drivers.php` (5 states)
- [ ] `company_drivers_create.php` (4 states)
- [ ] Validation: full_name/phone required + unique phone
- [ ] Safe `query()->fetch()` (NOT exec-SELECT)
- [ ] `e()` for all user data
- [ ] Don't break SUPERADMIN, Owner, Logists, Clients, Contractors
- [ ] `php -l` all PHP files
- [ ] No secrets in git diff

## 10. QA checklist
- [ ] `/company/drivers?company_id=1` → 200
- [ ] `/company/drivers/create?company_id=1` GET → 200
- [ ] `/company/drivers/create?company_id=1` POST → creates driver
- [ ] full_name/phone required validation works
- [ ] duplicate phone blocked
- [ ] Drivers stored in local DB only, NOT central
- [ ] invalid/missing company_id → no 500
- [ ] All 5 prior modules intact (SUPERADMIN, Owner, Logists, Clients, Contractors)
- [ ] Migration idempotent
- [ ] main.php not modified
- [ ] No exec-SELECT, no secrets in git diff

## 11. Forbidden
- Don't modify main.php, app.css, Database.php, Router.php
- Don't break existing modules (SUPERADMIN, Owner, Logists, Clients, Contractors)
- Don't create vehicles, crews, trips, documents
- Don't link driver to contractor
- Don't link driver to vehicle
- Don't implement edit/delete driver
- Don't create drivers in central DB
- Demo-placeholder UI forbidden
- No `exec("SELECT` — use `query()->fetch()`
