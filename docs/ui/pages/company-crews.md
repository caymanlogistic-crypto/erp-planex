# UI PAGE HANDOFF — Company Crews (Экипажи)

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
Экипажи — Компания: [name]
```

---

## 1. Страницы

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/crews?company_id=N` | GET | Список экипажей |
| `/company/crews/create?company_id=N` | GET | Форма создания |
| `/company/crews/create?company_id=N` | POST | Обработка создания |

---

## 2. Страница списка (6 состояний)

Паттерн: как `company_vehicles.php`. Состояния:
1. `company === null` — компания не найдена (notice.warn)
2. `company['status'] !== 'active'` — компания не активна
3. `isset($dbError)` — ошибка подключения к локальной БД
4. `empty($crews)` — пустое состояние (empty-state + кнопка «Создать первый экипаж»)
5. Таблица с экипажами

**Table columns:** ID, Подрядчик, Транспорт, Водитель, Статус (badge), Создан.

**Кнопка:** «Создать экипаж» в page-head-actions.

---

## 3. Форма создания (5 состояний)

Паттерн: как `company_vehicles_create.php`, но с select вместо text. Состояния:
1. `company === null` — notice.warn
2. `company['status'] !== 'active'` — notice.warn о недоступности
3. Блокирующее состояние: нет подрядчиков / нет водителей / нет транспорта — раздельные blocking notices: «Сначала создайте подрядчика», «Сначала создайте водителя», «Сначала создайте транспорт»
4. `$success` — success page с деталями созданного экипажа (kv с названием подрядчика, госномером, ФИО водителя, статусом, кнопки «← К списку» + «Создать ещё»)
5. Форма (основное состояние) — с валидацией

**Sections:**
- Экипаж: Подрядчик* (select), Транспорт* (select), Водитель* (select)
- Дополнительно: Комментарий

---

## 4. Form fields

| # | Field | Type | Required | Validation |
|---|-------|------|----------|------------|
| 1 | `contractor_id` | select | YES | not empty, must exist in local DB |
| 2 | `vehicle_id` | select | YES | not empty, must exist in local DB |
| 3 | `driver_id` | select | YES | not empty, must exist in local DB |
| 4 | `comments` | textarea | no | — |

**Select options:**
- `contractor_id`: SELECT id, name FROM contractors WHERE status = 'active' ORDER BY name. Option label: «Название (ИНН: xxx)». Value: id.
- `vehicle_id`: SELECT id, plate_number, brand, model FROM vehicles WHERE status = 'active' ORDER BY plate_number. Option label: «plate_number — brand model». Value: id.
- `driver_id`: SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name. Option label: «full_name (phone)». Value: id.

---

## 5. Validation

| Field | Rule | Message |
|-------|------|---------|
| `contractor_id` | empty | «Выберите подрядчика» |
| `contractor_id` | not found in DB | «Подрядчик не найден» |
| `vehicle_id` | empty | «Выберите транспорт» |
| `vehicle_id` | not found in DB | «Транспорт не найден» |
| `driver_id` | empty | «Выберите водителя» |
| `driver_id` | not found in DB | «Водитель не найден» |
| `contractor_id + vehicle_id + driver_id` | duplicate combo | «Такой экипаж уже существует в этой компании» |

---

## 6. Business logic (POST)

```text
1. company_id из $_GET
2. Найти компанию в центральной БД
3. Проверить company.status === 'active'
4. Подключиться к локальной БД (db_identifier)
5. Проверить таблицу crews: query("SELECT 1 FROM crews LIMIT 1")->fetch()
   - Если нет → exec(CREATE TABLE из миграции)
6. Проверить наличие активных подрядчиков/водителей/транспорта
   - Если нет → blocking state (не давать форму)
7. Валидировать contractor_id, vehicle_id, driver_id
8. Проверить, что выбранные contractor/vehicle/driver существуют в локальной БД (SELECT COUNT)
9. Проверить уникальность комбинации: SELECT COUNT(*) FROM crews WHERE contractor_id=? AND vehicle_id=? AND driver_id=?
10. INSERT → success page
```

---

## 7. Migration

`database/migrations-local/006_create_company_crews.sql`

```sql
CREATE TABLE IF NOT EXISTS `crews` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `contractor_id` INT UNSIGNED NOT NULL,
    `vehicle_id` INT UNSIGNED NOT NULL,
    `driver_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_crew` (`contractor_id`, `vehicle_id`, `driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. CORE modules

| ID | Module | Usage |
|----|--------|-------|
| CORE-05 | Page header | List/form/success |
| CORE-08 | Panel | Container |
| CORE-13 | Data table | Crew list |
| CORE-17 | Primary button | «Создать экипаж» |
| CORE-19 | Ghost button | «Отмена», «Создать ещё» |
| CORE-24 | Status badge | Status |
| CORE-26 | Form field | Inputs (select) |
| CORE-27 | Form section | Sections |
| CORE-28 | Validation | Errors |
| CORE-31 | KV list | Success |
| CORE-32 | Notice | Success |
| CORE-33 | Warning | Errors |
| CORE-34 | Empty state | No data |

**PATTERN:** PATTERN-01 (Table-only registry + form page)

**MODULE USAGE DECISIONS:** Экипажи — стандартный справочный реестр. Отличие от предыдущих: поля — select из существующих справочников, а не text. Используется PATTERN-01 (таблица со ссылками).

---

## 9. Coder checklist
- [ ] Migration `006_create_company_crews.sql` in `database/migrations-local/`
- [ ] 3 routes in `public/index.php`
- [ ] `company_crews.php` (6 states: null-company, inactive, db-error, empty, table)
- [ ] `company_crews_create.php` (5 states: null-company, inactive, blocking-deps, success, form)
- [ ] Select-option загрузка: активные подрядчики/водители/транспорт из локальной БД
- [ ] Validation: contractor_id/vehicle_id/driver_id required + exist in DB + unique combo
- [ ] Duplicate crew blocked: contractor+vehicle+driver
- [ ] Crew list показывает имена подрядчика/водителя и госномер (JOIN или отдельные SELECT)
- [ ] Safe `query()->fetch()` (NOT exec-SELECT)
- [ ] `e()` for all user data
- [ ] Don't break SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers, Vehicles
- [ ] `php -l` all PHP files
- [ ] No secrets in git diff

## 10. QA checklist
- [ ] `/company/crews?company_id=1` → 200
- [ ] `/company/crews/create?company_id=1` GET → 200
- [ ] `/company/crews/create?company_id=1` POST → creates crew
- [ ] contractor_id/vehicle_id/driver_id required validation works
- [ ] duplicate contractor+vehicle+driver blocked
- [ ] Crews stored in local DB only, NOT central
- [ ] invalid/missing company_id → no 500
- [ ] Blocking state when no contractors/drivers/vehicles
- [ ] Crew list shows contractor name, driver name, plate number (not just IDs)
- [ ] All 7 prior modules intact (SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers, Vehicles)
- [ ] Migration idempotent
- [ ] main.php not modified
- [ ] No exec-SELECT, no secrets in git diff

## 11. Forbidden
- Don't modify main.php, app.css, Database.php, Router.php
- Don't break existing modules (SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers, Vehicles)
- Don't create trips, orders, documents
- Don't implement edit/delete crew
- Don't create crews in central DB
- Don't add FK constraints on first stage (app-level check only)
- Don't link crew to trips
- Demo-placeholder UI forbidden
- No `exec("SELECT` — use `query()->fetch()`
- No inline styles beyond pattern (only margin-top:16px for kv/actions spacing)

## 12. Success page KV
- Название подрядчика: «ООО Ромашка»
- Госномер: «А999АА99»
- Водитель: «Иванов Иван Иванович»
- Статус: «Активен»

## 13. ARCHITECT PRE-OWNER REVIEW
- Visual check deferred (accelerated mode)
- Functional scope matches DECISION-0039
- Migration idempotent with UNIQUE KEY uk_crew
- Select-option pattern correct for crews
- No edit/delete/trips in scope
- Previous modules protected: main.php, app.css, Database.php, Router.php not modified
