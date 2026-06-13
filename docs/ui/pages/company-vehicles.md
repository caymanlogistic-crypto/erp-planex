# UI PAGE HANDOFF — Company Vehicles (Транспорт)

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
Транспорт — Компания: [name]
```

---

## 1. Страницы

| Маршрут | Метод | Назначение |
|----------|-------|------------|
| `/company/vehicles?company_id=N` | GET | Список транспорта |
| `/company/vehicles/create?company_id=N` | GET | Форма создания |
| `/company/vehicles/create?company_id=N` | POST | Обработка создания |

---

## 2. Страница списка (5 состояний)

Паттерн: как `company_drivers.php`. Состояния:
1. `company === null` — компания не найдена (notice.warn)
2. `company['status'] !== 'active'` — компания не активна
3. `isset($dbError)` — ошибка подключения к локальной БД
4. `empty($vehicles)` — пустое состояние (empty-state + кнопка «Добавить первый транспорт»)
5. Таблица с транспортом

**Table columns:** ID, Госномер, Марка, Модель, Тип, Грузоподъёмность (т), Статус (badge), Создан.

**Кнопка:** «Добавить транспорт» в page-head-actions.

---

## 3. Форма создания (4 состояния)

Паттерн: как `company_drivers_create.php`. Состояния:
1. `company === null` — notice.warn
2. `company['status'] !== 'active'` — notice.warn о недоступности
3. `$success` — success page с деталями созданного транспорта (поля из kv, кнопки «← К списку» + «Добавить ещё»)
4. Форма (основное состояние) — с валидацией

**Sections:**
- Основные данные: Госномер*
- Характеристики ТС: Марка, Модель, Тип ТС, Грузоподъёмность (т), Объём кузова (м³)
- Документы ТС: VIN, СТС, ПТС
- Дополнительно: Комментарий

---

## 4. Form fields

| # | Field | Type | Required | Validation |
|---|-------|------|----------|------------|
| 1 | `plate_number` | text | YES | not empty, unique in local DB |
| 2 | `brand` | text | no | — |
| 3 | `model` | text | no | — |
| 4 | `vehicle_type` | text | no | — |
| 5 | `vin` | text | no | — |
| 6 | `sts_number` | text | no | — |
| 7 | `pts_number` | text | no | — |
| 8 | `capacity_tons` | number (step=0.01) | no | — |
| 9 | `volume_m3` | number (step=0.01) | no | — |
| 10 | `comments` | textarea | no | — |

---

## 5. Validation

| Field | Rule | Message |
|-------|------|---------|
| `plate_number` | empty | «Обязательное поле» |
| `plate_number` | duplicate | «Госномер уже используется в этой компании» |

---

## 6. Business logic (POST)

```text
1. company_id из $_GET
2. Найти компанию в центральной БД
3. Проверить company.status === 'active'
4. Подключиться к локальной БД (db_identifier)
5. Проверить таблицу vehicles: query("SELECT 1 FROM vehicles LIMIT 1")->fetch()
   - Если нет → exec(CREATE TABLE из миграции)
6. Валидировать plate_number
7. Проверить уникальность plate_number: SELECT COUNT(*) FROM vehicles WHERE plate_number = ?
8. INSERT → success page
```

---

## 7. Migration

`database/migrations-local/005_create_company_vehicles.sql`

```sql
CREATE TABLE IF NOT EXISTS `vehicles` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `plate_number` VARCHAR(20) NOT NULL,
    `brand` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `vehicle_type` VARCHAR(50) DEFAULT NULL,
    `vin` VARCHAR(50) DEFAULT NULL,
    `sts_number` VARCHAR(50) DEFAULT NULL,
    `pts_number` VARCHAR(50) DEFAULT NULL,
    `capacity_tons` DECIMAL(8,2) DEFAULT NULL,
    `volume_m3` DECIMAL(8,2) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plate_number` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. CORE modules

| ID | Module | Usage |
|----|--------|-------|
| CORE-05 | Page header | List/form/success |
| CORE-08 | Panel | Container |
| CORE-13 | Data table | Vehicle list |
| CORE-17 | Primary button | «Добавить транспорт» |
| CORE-19 | Ghost button | «Отмена», «Добавить ещё» |
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
- [ ] Migration `005_create_company_vehicles.sql` in `database/migrations-local/`
- [ ] 3 routes in `public/index.php`
- [ ] `company_vehicles.php` (5 states)
- [ ] `company_vehicles_create.php` (4 states)
- [ ] Validation: plate_number required + unique
- [ ] Safe `query()->fetch()` (NOT exec-SELECT)
- [ ] `e()` for all user data
- [ ] Don't break SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers
- [ ] `php -l` all PHP files
- [ ] No secrets in git diff

## 10. QA checklist
- [ ] `/company/vehicles?company_id=1` → 200
- [ ] `/company/vehicles/create?company_id=1` GET → 200
- [ ] `/company/vehicles/create?company_id=1` POST → creates vehicle
- [ ] plate_number required validation works
- [ ] duplicate plate_number blocked
- [ ] Vehicles stored in local DB only, NOT central
- [ ] invalid/missing company_id → no 500
- [ ] All 6 prior modules intact (SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers)
- [ ] Migration idempotent
- [ ] main.php not modified
- [ ] No exec-SELECT, no secrets in git diff

## 11. Forbidden
- Don't modify main.php, app.css, Database.php, Router.php
- Don't break existing modules (SUPERADMIN, Owner, Logists, Clients, Contractors, Drivers)
- Don't create crews, trips, documents
- Don't link vehicle to driver
- Don't link vehicle to contractor
- Don't implement edit/delete vehicle
- Don't create vehicles in central DB
- Demo-placeholder UI forbidden
- No `exec("SELECT` — use `query()->fetch()`
