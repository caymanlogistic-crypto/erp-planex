# ERP PLANEX — MASTER-FLOW ARCHITECTURE (Этап C2)

## STATUS: ARCHITECTURE_PLAN_READY

Дата: 2026-06-24
Базовая точка Git: `cf20e09` (BLOCK_C_COMPLETE)

---

## 1. Продуктовая логика master-flow

ERP PLANEX строится вокруг цепочки:

```text
Перевозчик → Водитель + Машина (связка) → Экипаж
```

**Главный пользовательский паттерн (master-flow):**

```text
Пользователь НЕ ходит по отдельным CRUD-страницам создавать сущности.
Пользователь запускает многошаговый мастер, который за один проход
создаёт всю цепочку: Перевозчик → Водитель → Машина → Связка → Экипаж.
```

**CRUD-страницы — второстепенный инструмент** для:
- просмотра созданных данных;
- ручного исправления;
- архивирования;
- диагностики.

---

## 2. Два сценария master-flow

### Сценарий A: «Добавить экипаж перевозчику» (из карточки перевозчика)

```text
/company/contractors/{id}  ← пользователь здесь
    ↓
Кнопка «Добавить экипаж» (или «+ Водитель + Машина»)
    ↓
Многошаговый мастер (modal или отдельная страница)
    ↓
Шаг 1: Выбрать или создать Водителя
Шаг 2: Выбрать или создать Машину (vehicle_set)
Шаг 3: Проверка → Создать driver_vehicle_block → Создать crew
    ↓
Возврат в карточку перевозчика (экипаж уже в списке)
```

**Исходные данные:** contractor_id уже известен (из URL).
**На выходе:** новый driver_vehicle_block + новый crew (contractor_id + block_id).

### Сценарий B: «Создать перевозчика с экипажем» (с нуля)

```text
/company/contractors → кнопка «Создать перевозчика + Водителя + Транспорт»
    ↓
Многошаговый мастер (create-full, УЖЕ СУЩЕСТВУЕТ)
    ↓
Шаг 1: Создать или выбрать Перевозчика
Шаг 2: Создать или выбрать Водителя
Шаг 3: Создать или выбрать Машину
Шаг 4: Проверка → Создать всё + block + crew
    ↓
Экран успеха со ссылками на все созданные сущности
```

**Текущее состояние:** create-full уже реализован и создаёт block + crew. 
Но success-экран не показывает crew и block отдельно. Нужно доработать отображение.

---

## 3. Карта маршрутов master-flow

### Новые маршруты (Сценарий A)

| Method | Route | Назначение |
|--------|-------|-----------|
| GET | `/company/contractors/{id}/add-crew` | Мастер: страница добавления экипажа перевозчику |
| POST | `/company/contractors/{id}/add-crew` | Обработка: создание driver + vehicle + block + crew |

Альтернативно — modal-подход:
| Method | Route | Назначение |
|--------|-------|-----------|
| GET | `/company/contractors/{id}/add-crew-modal` | AJAX: HTML мастера в модальном окне |
| POST | `/company/contractors/{id}/add-crew-modal` | AJAX: обработка, возврат success-HTML или ошибок |

### Существующие маршруты (Сценарий B)

| Method | Route | Статус |
|--------|-------|--------|
| GET | `/company/contractors/create-full` | ✅ Работает |
| POST | `/company/contractors/create-full` | ✅ Работает, создаёт block + crew. **Нужна доработка success-экрана** |

### Маршруты, которые НЕ трогать (DO_NOT_TOUCH_WORKING_CORE)

| Route | Причина |
|-------|---------|
| `/company/contractors` | Защищённая страница (list) |
| `/company/contractors/{id}` | Защищённая страница (view) — **НО можно добавить кнопку в page-head-actions** |
| `/company/contractors/{id}/edit` | Защищённая страница |
| `/company/contractors/create` | Защищённая страница |
| `/company/drivers/*` | Защищённая страница |
| `/company/vehicle-sets/*` | Защищённая страница |

---

## 4. Где разместить кнопку запуска мастера

### Карточка перевозчика: `/company/contractors/{id}`

Файл: `app/View/pages/company_contractor_view.php`

Текущий page-head-actions (строка 97–101):
```php
<a href="/company/contractors/<?= $contractor['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
<a href="/company/contractors" class="btn btn-ghost">← К списку</a>
<a href="/company/documents?entity_type=contractor&entity_id=<?= $contractor['id'] ?>" class="btn btn-ghost">Документы</a>
```

**Добавить после кнопки «Редактировать»:**
```php
<a href="/company/contractors/<?= $contractor['id'] ?>/add-crew" class="btn btn-primary">+ Водитель + Машина</a>
```
Или (modal-подход):
```php
<button type="button" class="btn btn-primary" onclick="openCrewMasterModal(<?= $contractor['id'] ?>)">+ Водитель + Машина</button>
```

**Важно:** это изменение `company_contractor_view.php`. Поскольку файл относится к защищённой странице `/company/contractors`, требуется **явное разрешение владельца** на добавление кнопки. Правило AGENT_RULES.md §8.5: «Разрешено только добавлять новые кнопки/пункты/действия без изменения существующей структуры и поведения» — кнопка НЕ меняет существующую структуру, только добавляет новое действие. Архитектор должен подтвердить это с владельцем.

### Список перевозчиков: `/company/contractors`

Текущая кнопка «Создать перевозчика + Водителя + Транспорт» уже ведёт на create-full. Дополнительных кнопок не требуется.

---

## 5. Структура мастера (Сценарий A): шаг за шагом

### Шаг 1: Водитель

**Режимы:**
- «Выбрать существующего» — select из drivers WHERE status = 'active'
- «Создать нового» — поля: ФИО, Телефон, Email

**Поля (новый водитель):**
- `driver_full_name` (обязательное)
- `driver_phone`
- `driver_email`
- `driver_mode` = 'new' | 'existing'
- `driver_id` (если existing)

**Валидация:**
- Если existing: driver_id не пустой, водитель существует
- Если new: ФИО не пустое, формат телефона

### Шаг 2: Машина / Транспорт

**Режимы:**
- «Выбрать существующий» — select из vehicle_sets WHERE status = 'active'
- «Создать новый» — поля: тип комплекта, госномер, марка, модель

**Поля (новый транспорт):**
- `set_type` = 'single' | 'coupling' | 'road_train'
- `plate_number` (обязательное)
- `brand`, `model`
- `secondary_plate_number` (если coupling/road_train)
- `vehicle_mode` = 'new' | 'existing'
- `vehicle_set_id` (если existing)

**Валидация:**
- Если existing: vehicle_set_id не пустой, комплект существует
- Если new: госномер не пустой, не дублируется

### Шаг 3: Проверка и создание

**Отображает:**
- Выбранный/созданный водитель (имя, телефон)
- Выбранный/созданный транспорт (госномер, тип)
- Перевозчик: имя, ИНН (уже известен из URL)

**При подтверждении (POST):**
1. `beginTransaction()`
2. Если driver_mode = 'new' → INSERT drivers
3. Если vehicle_mode = 'new' → INSERT vehicle_units + vehicle_sets
4. Проверить существующий driver_vehicle_block (driver_id + vehicle_set_id)
5. Если нет → INSERT driver_vehicle_blocks
6. Проверить существующий crew (contractor_id + block_id)
7. Если нет → INSERT crews
8. `commit()`
9. Если ошибка → `rollBack()`, показать ошибку

**После успеха:**
- Редирект на `/company/contractors/{id}` (пользователь видит новый экипаж в списке)
- Или показать success-экран со ссылками

---

## 6. Данные, создаваемые на каждом шаге

| Шаг | Создаётся | Таблица | Ключевые поля |
|-----|----------|--------|--------------|
| 1 (new) | Водитель | drivers | full_name, phone, status='active' |
| 1 (existing) | — | — | используется driver_id |
| 2 (new) | ТС + комплект | vehicle_units, vehicle_sets | plate_number, unit_type, set_type |
| 2 (existing) | — | — | используется vehicle_set_id |
| 3 | Связка | driver_vehicle_blocks | driver_id + vehicle_set_id |
| 3 | Экипаж | crews | contractor_id + driver_vehicle_block_id |

---

## 7. Rollback / валидация

**Транзакционная модель (уже реализована в create-full):**

```php
$localPdo->beginTransaction();
try {
    // Step 1: INSERT driver (если new)
    // Step 2: INSERT vehicle_units + vehicle_sets (если new)
    // Step 3: INSERT driver_vehicle_blocks
    // Step 4: INSERT crews
    $localPdo->commit();
} catch (\Exception $e) {
    $localPdo->rollBack();
    // Показать ошибку пользователю
}
```

**Частичный откат не требуется** — если любой шаг падает, откатывается всё.

**Валидация до транзакции:**
- Все поля проверяются ДО beginTransaction()
- Дубли проверяются ДО (driver_id + vehicle_set_id, contractor_id + block_id)
- Если ошибка — возврат на форму с подсветкой полей

---

## 8. Использование существующих CRUD

Мастер НЕ дублирует логику создания сущностей. Он использует:

| Сущность | Как создаётся | Где логика |
|----------|--------------|-----------|
| driver | INSERT drivers | Встроено в мастер (упрощённо: ФИО + телефон) |
| vehicle_set | INSERT vehicle_units + vehicle_sets | Встроено в мастер (упрощённо: госномер + тип) |
| driver_vehicle_block | INSERT driver_vehicle_blocks | Встроено в мастер |
| crew | INSERT crews | Встроено в мастер |

**Почему не использовать существующие CRUD-контроллеры:**
- Существующие create-формы (drivers/create, vehicle-sets/create) спроектированы для полного ввода всех полей
- Мастер использует упрощённый набор полей (минимально необходимый)
- Мастер должен работать как единая транзакция

**Альтернатива (более чистая архитектура):**
Вынести логику создания driver/vehicle_set в сервисы и вызывать их из мастера. Но это потребует рефакторинга DO_NOT_TOUCH_WORKING_CORE, что запрещено на данном этапе.

**Рекомендация:** для Сценария A использовать inline-логику (как в create-full), для Сценария B — доработать существующий create-full.

---

## 9. UI: modal или отдельная страница

### Вариант 1: Отдельная страница (рекомендуется)

**Плюсы:**
- Полный контроль над stepper UI
- Нет проблем с AJAX/модалками
- Можно использовать существующий stepper-компонент из create-full
- Надёжнее для транзакционной логики

**Минусы:**
- Отдельный route
- Уход со страницы перевозчика

**Маршрут:** `GET /company/contractors/{id}/add-crew`

### Вариант 2: Модальное окно

**Плюсы:**
- Пользователь остаётся в карточке перевозчика
- После создания — обновление списка экипажей на месте

**Минусы:**
- Сложный AJAX-степпер в модалке
- Проблемы с file upload (если будут документы)
- JS-инициализация каждого шага

**Маршруты:** `GET|POST /company/contractors/{id}/add-crew-modal`

**Рекомендация:** Начать с отдельной страницы (Вариант 1). Модальное окно — опциональное улучшение после acceptance страничного варианта.

---

## 10. Доработка существующего create-full (Сценарий B)

### Что уже работает:
- Создание contractor (new или existing)
- Создание driver (new или existing)
- Создание vehicle_set (new или existing)
- Создание driver_vehicle_block (с проверкой дубля)
- Создание crew (с проверкой дубля)
- Транзакционность (commit/rollback)

### Что нужно доработать:

**1. Success-экран (`company_contractors_create_full.php`, строка 23–99):**
Добавить строку для экипажа в таблицу результатов:
```php
<tr>
    <td>Экипаж</td>
    <td>Перевозчик + Водитель + Транспорт</td>
    <td class="col-actions">
        <a href="/company/crews/<?= $createdCrew['id'] ?? '' ?>" class="btn btn-toolbar">Просмотр</a>
    </td>
</tr>
```
Нужна переменная `$createdCrew` с id. Сейчас в POST-обработчике (строка 3603) переменная `$createdBlock` создаётся, но `$createdCrew` — нет. Нужно добавить.

**2. Stepper UI (опционально):**
Сейчас create-full — это одна большая форма без шагов. Можно добавить stepper-компонент (как в driver_vehicle_blocks_create.php) для улучшения UX. Но это объёмная работа, не критичная для первого этапа.

**3. Кнопка запуска:**
Уже есть на странице `/company/contractors` → «Создать перевозчика + Водителя + Транспорт». Работает.

---

## 11. Что потребует явного разрешения владельца

| Изменение | Файл | Почему нужно разрешение |
|----------|------|------------------------|
| Добавление кнопки «+ Водитель + Машина» | `app/View/pages/company_contractor_view.php` | Файл защищённой страницы `/company/contractors` |
| Доработка success-экрана create-full | `app/View/pages/company_contractors_create_full.php` | Файл защищённой страницы |
| Новый route `/company/contractors/{id}/add-crew` | `public/index.php` | Требует добавления в index.php, но НЕ меняет существующие маршруты |
| Любое изменение `/company/drivers` | — | DO_NOT_TOUCH_WORKING_CORE |
| Любое изменение `/company/vehicle-sets` | — | DO_NOT_TOUCH_WORKING_CORE |

**Важно по кнопке в contractor_view.php:**
AGENT_RULES.md §8.5 разрешает «добавлять новые кнопки/пункты/действия без изменения существующей структуры и поведения». Кнопка «+ Водитель + Машина» в page-head-actions НЕ меняет существующую структуру — только добавляет новое действие. По букве правил это разрешено. Но владелец должен подтвердить.

---

## 12. Файлы, которые потребуется создать/изменить

### Новые файлы

| Файл | Назначение |
|------|-----------|
| `app/View/pages/company_contractor_add_crew.php` | Страница мастера «Добавить экипаж» (Сценарий A) |

### Изменяемые файлы (требуют разрешения владельца)

| Файл | Что изменить |
|------|------------|
| `public/index.php` | Добавить 2 route (GET + POST `/company/contractors/{id}/add-crew`) |
| `app/View/pages/company_contractor_view.php` | Кнопка «+ Водитель + Машина» в page-head-actions |
| `app/View/pages/company_contractors_create_full.php` | Доработка success-экрана (добавить строку «Экипаж») |
| `public/index.php` (create-full POST) | Добавить `$createdCrew` в переменные success-экрана |

### Файлы, которые НЕ трогать

```
app/View/pages/company_drivers.php
app/View/pages/company_vehicle_sets.php
app/View/pages/company_clients.php
app/View/pages/company_contractors.php       (list — не менять)
app/View/pages/company_contractors_create.php (отдельная create — не менять)
app/View/partials/* (все protected partials)
public/assets/js/app.js
public/assets/css/app.css
public/assets/css/erp-ui.css
app/Service/AccessControlService.php (можно использовать, но не обязательно)
app/Service/DocumentService.php      (можно использовать, но не обязательно)
```

---

## 13. Порядок реализации

### Подэтап C2a: Доработка create-full (Сценарий B) — ~30 мин

1. В `public/index.php` (POST create-full): добавить `$createdCrew = ['id' => ...]` после создания crew
2. В `company_contractors_create_full.php`: добавить строку «Экипаж» в success-таблицу
3. Проверить: php -l, runtime

**Это безопасно:** не ломает существующую логику, только дополняет success-экран.

### Подэтап C2b: Мастер из карточки перевозчика (Сценарий A) — ~2–3 часа

1. Создать `app/View/pages/company_contractor_add_crew.php` — stepper-страница
2. Добавить 2 route в `public/index.php`: GET + POST `/company/contractors/{id}/add-crew`
3. В `company_contractor_view.php`: добавить кнопку «+ Водитель + Машина»
4. Логика POST: транзакционное создание driver + vehicle_set + block + crew
5. Проверить: php -l, runtime, 4 защищённые страницы не сломаны

**Требует разрешения владельца** на изменение `company_contractor_view.php` (защищённая страница).

### Подэтап C2c: Stepper UI для create-full (Сценарий B, улучшение) — ~2 часа

1. Переработать `company_contractors_create_full.php` с плоской формы на stepper
2. Использовать существующий stepper-компонент из `company_driver_vehicle_blocks_create.php`
3. Шаги: Перевозчик → Водитель → Машина → Проверка

**Опционально,** не блокирует production-готовность.

---

## 14. Схема master-flow (визуальная)

```text
╔══════════════════════════════════════════════════════════╗
║                   MASTER-FLOW ERP PLANEX                 ║
╠══════════════════════════════════════════════════════════╣
║                                                          ║
║  СЦЕНАРИЙ A (из карточки перевозчика)                    ║
║  ─────────────────────────────────────                   ║
║  /company/contractors/{id}                               ║
║    │                                                     ║
║    ├─ [Кнопка: + Водитель + Машина]                      ║
║    │                                                     ║
║    ▼                                                     ║
║  /company/contractors/{id}/add-crew                      ║
║    │                                                     ║
║    ├─ Шаг 1: Водитель (выбрать / создать)                ║
║    ├─ Шаг 2: Машина   (выбрать / создать)                ║
║    ├─ Шаг 3: Проверка → Создать Block → Создать Crew     ║
║    │                                                     ║
║    ▼                                                     ║
║  ← Возврат в /company/contractors/{id}                   ║
║    (экипаж уже в списке)                                 ║
║                                                          ║
║  ─────────────────────────────────────────────           ║
║                                                          ║
║  СЦЕНАРИЙ B (с нуля)                                     ║
║  ─────────────────                                       ║
║  /company/contractors                                    ║
║    │                                                     ║
║    ├─ [Кнопка: Создать перевозчика + Водителя + Транспорт]║
║    │                                                     ║
║    ▼                                                     ║
║  /company/contractors/create-full                        ║
║    │                                                     ║
║    ├─ Шаг 1: Перевозчик (выбрать / создать)              ║
║    ├─ Шаг 2: Водитель    (выбрать / создать)              ║
║    ├─ Шаг 3: Машина      (выбрать / создать)              ║
║    ├─ Шаг 4: Проверка → Создать ВСЁ + Block + Crew       ║
║    │                                                     ║
║    ▼                                                     ║
║  Экран успеха:                                           ║
║    Перевозчик → Просмотр                                 ║
║    Водитель   → Просмотр                                 ║
║    Транспорт  → Просмотр                                 ║
║    Связка     → Просмотр                                 ║
║    Экипаж     → Просмотр   ← ДОБАВИТЬ                    ║
║                                                          ║
╚══════════════════════════════════════════════════════════╝
```

---

## 15. Связанные документы

- `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md` — полный архитектурный план, список DO_NOT_TOUCH
- `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md` — foundation-сервисы
- `docs/ai/AGENT_RULES.md` — раздел 8 (UI LOCK), раздел 9 (PROTECTED WORKING CORE)

---

## NEXT_STEP

1. Владелец подтверждает план master-flow
2. Владелец даёт разрешение на:
   - Добавление кнопки в `company_contractor_view.php`
   - Доработку `company_contractors_create_full.php` (success-экран)
   - Новый route `/company/contractors/{id}/add-crew`
3. Реализация: сначала C2a (create-full success fix), затем C2b (мастер из карточки)
