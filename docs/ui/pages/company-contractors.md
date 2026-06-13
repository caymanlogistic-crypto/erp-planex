# UI PAGE HANDOFF — Company Contractors (Подрядчики)

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
Подрядчики — Компания: [name]
```

---

## 1. Страницы

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/contractors?company_id=N` | GET | Список подрядчиков |
| `/company/contractors/create?company_id=N` | GET | Форма создания |
| `/company/contractors/create?company_id=N` | POST | Обработка создания |

---

## 2. Страница списка (5 состояний)

Как `company_clients.php`, замена: clients→contractors, клиент→подрядчик.

**Table columns:** ID, Название, ИНН, Статус (badge), Создан.

---

## 3. Форма создания (4 состояния)

Как `company_clients_create.php`, замена имён.

**Sections:**
- Основные данные: Наименование*, ИНН*, КПП, ОГРН
- Адреса: Юридический адрес, Фактический адрес
- Контакты: Контактное лицо, Телефон, Email
- Дополнительно: Комментарий

---

## 4. Form fields

| # | Field | Type | Required | Validation |
|---|-------|------|----------|------------|
| 1 | `name` | text | YES | not empty |
| 2 | `inn` | text | YES | not empty, unique in local DB |
| 3 | `kpp` | text | no | — |
| 4 | `ogrn` | text | no | — |
| 5 | `legal_address` | textarea | no | — |
| 6 | `physical_address` | textarea | no | — |
| 7 | `contact_person` | text | no | — |
| 8 | `contact_phone` | text | no | — |
| 9 | `contact_email` | text | no | — |
| 10 | `comments` | textarea | no | — |

---

## 5. Validation

| Field | Rule | Message |
|-------|------|---------|
| `name` | empty | «Обязательное поле» |
| `inn` | empty | «Обязательное поле» |
| `inn` | duplicate | «ИНН уже используется в этой компании» |

---

## 6. Business logic (POST)

```text
1. company_id из $_GET
2. Найти компанию в центральной БД
3. Подключиться к локальной БД
4. Проверить таблицу contractors: query("SELECT 1 FROM contractors LIMIT 1")->fetch()
   - Если нет → exec(CREATE TABLE из миграции)
5. Валидировать name, inn
6. Проверить уникальность inn: SELECT COUNT(*) FROM contractors WHERE inn = ?
7. INSERT → success page
```

---

## 7. Migration

`database/migrations-local/003_create_company_contractors.sql`

```sql
CREATE TABLE IF NOT EXISTS `contractors` (
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

## 8. CORE modules

| ID | Module | Usage |
|----|--------|-------|
| CORE-05 | Page header | List/form/success |
| CORE-08 | Panel | Container |
| CORE-13 | Data table | Contractor list |
| CORE-17 | Primary button | «Создать подрядчика» |
| CORE-19 | Ghost button | «Отмена», «Создать ещё» |
| CORE-24 | Status badge | Status |
| CORE-26 | Form field | Inputs |
| CORE-27 | Form section | Sections |
| CORE-28 | Validation | Errors |
| CORE-31 | KV list | Success |
| CORE-32 | Notice | Success |
| CORE-33 | Warning | Errors |
| CORE-34 | Empty state | No data |

**PATTERN:** PATTERN-01

---

## 9. Coder checklist
- [ ] Migration `003_create_company_contractors.sql`
- [ ] 3 routes in `public/index.php`
- [ ] `company_contractors.php` (5 states)
- [ ] `company_contractors_create.php` (4 states)
- [ ] Validation: name/inn required + unique
- [ ] Safe `query()->fetch()` (NOT exec-SELECT)
- [ ] `e()` for all user data
- [ ] Don't break SUPERADMIN, Owner, Logists, Clients
- [ ] `php -l` all PHP files
- [ ] No secrets in git diff

## 10. QA checklist
- [ ] `/company/contractors?company_id=1` → 200
- [ ] form GET/POST work
- [ ] name/inn required + duplicate
- [ ] Local DB only, not central
- [ ] All 4 prior modules intact
- [ ] Migration idempotent
- [ ] main.php not modified
- [ ] No exec-SELECT, no secrets

## 11. Forbidden
- Don't modify main.php, app.css, Database.php, Router.php
- Don't break existing modules
- Don't split contractor/carrier/expeditor into separate entities
- Don't create drivers, vehicles, crews, trips, documents
- Demo-placeholder UI forbidden
- No `exec("SELECT` — use `query()->fetch()`
