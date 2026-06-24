# ERP PLANEX — Архитектурный план: Исполнитель рейса

## Назначение

Архитектурный план упрощения модели «Водитель+ТС» + «Экипаж» → «Исполнитель рейса».

**Статус**: АРХИТЕКТУРНЫЙ ЭТАП. Код не меняется. Миграции не выполняются. Меню не трогается.

---

## 1. Текущая модель

### 1.1 Технические сущности

```
driver_vehicle_block  (driver_id + vehicle_set_id)
crew                  (contractor_id + driver_vehicle_block_id)
```

Пользователь видит два пункта меню:

- **«Водители+ТС»** (`/company/driver-vehicle-blocks`) — список связок driver + vehicle_set.
- **«Экипажи»** (`/company/crews`) — список экипажей contractor + driver_vehicle_block.

И отдельный пункт для `company_owner`:

- **«Привязка перевозчиков»** (`/company/contractor-assignments`) — переназначение логиста-владельца contractor.

### 1.2 Таблицы

| Таблица | Назначение |
|---------|-----------|
| `driver_vehicle_blocks` | Связка driver_id + vehicle_set_id. UNIQUE(driver_id, vehicle_set_id). Миграция 018. |
| `crews` | Связка contractor_id + driver_vehicle_block_id. UNIQUE(contractor_id, driver_vehicle_block_id). Миграции 006+019. |
| `contractors` | Подрядчик. created_by_user_id — текущий логист-владелец. |
| `drivers` | Водитель. created_by_user_id — логист-владелец. |
| `vehicle_sets` | Комплект ТС (single/coupling/road_train). Миграция 017. |
| `vehicle_units` | Транспортные единицы. Миграция 016. |
| `entity_access_grants` | Права доступа: client, contractor, driver, vehicle_unit, vehicle_set, driver_vehicle_block, crew. Миграции 009+021+036. |
| `contractor_assignment_history` | История перепривязки перевозчиков. Миграция 037. |
| `documents` | Документы с entity_type + entity_id. Soft delete (deleted_at). |

### 1.3 Маршруты driver_vehicle_blocks

```
GET  /company/driver-vehicle-blocks              (list, line 12041)
GET  /company/driver-vehicle-blocks/create       (line 12115)
POST /company/driver-vehicle-blocks/create       (line 12169)
GET  /company/driver-vehicle-blocks/{id}         (line 12293)
GET  /company/driver-vehicle-blocks/{id}/edit    (line 12383)
POST /company/driver-vehicle-blocks/{id}/edit    (line 12464)
POST /company/driver-vehicle-blocks/{id}/archive (line 12548)
```

Views: `company_driver_vehicle_blocks.php`, `company_driver_vehicle_blocks_create.php`

### 1.4 Маршруты crews

```
GET  /company/crews              (list, line 9055)
GET  /company/crews/create       (line 9197)
POST /company/crews/create       (line 9282)
GET  /company/crews/{id}         (line 9459)
GET  /company/crews/{id}/edit    (line 9614)
POST /company/crews/{id}/edit    (line 9808)
POST /company/crews/{id}/archive (line 10116)
```

Views: `company_crews.php`, `company_crews_create.php`

### 1.5 Маршруты contractor-assignments

```
GET  /company/contractor-assignments           (line 14428)
POST /company/contractor-assignments/{id}/assign (line 14520)
```

View: `company_contractor_assignments.php`

### 1.6 Master-flow маршруты

```
GET/POST /company/contractors/create-full     (line 3238, 3603)
GET/POST /company/contractors/{id}/add-crew   (line 4061, 4283)
```

### 1.7 Меню (main.php)

Группа «Подрядчики» (раскрывающийся список):
- Перевозчики
- **Водители+ТС**
- Водители
- Транспорт
- **Экипажи**
- **Привязка перевозчиков** (только company_owner)

### 1.8 Плюсы текущей модели

- Рабочая система, проверенная runtime.
- Чёткое разделение: блок — техническая связка, экипаж — бизнес-связка.
- Работает contractor assignment с каскадным переносом.
- Работают entity_access_grants для всех entity_type.
- Работает master-flow create-full / add-crew.

### 1.9 Минусы текущей модели

- Пользователь видит 2 технические сущности вместо одной рабочей.
- «Водитель+ТС» — промежуточная сущность, которую пользователь не должен видеть.
- Чтобы создать рабочую единицу для рейса, нужно пройти: Водитель → ТС → Водитель+ТС → Экипаж. 4 шага вместо 2.
- «Привязка перевозчиков» работает только на уровне contractor, но не на уровне отдельного driver/vehicle_set/crew.

---

## 2. Целевая модель

### 2.1 Пользовательская сущность

**Исполнитель рейса** = Подрядчик + Водитель + ТС

Для пользователя это ОДНА рабочая строка. Технические детали скрыты.

### 2.2 Колонки списка «Исполнители рейса»

| Колонка | Источник |
|---------|----------|
| Подрядчик | contractors.name + ИНН |
| Водитель | drivers.full_name |
| ТС | plates (vu1 + vu2) + set_type |
| Телефон водителя | drivers.phone / driver_phones |
| Госномер | vehicle_units.plate_number |
| Ответственный логист | users.full_name (created_by_user_id crews) |
| Статус | crews.status |
| Документы | бейдж наличия docs / признак |
| Действие | кнопки |

### 2.3 Создание «Исполнителя рейса»

1. Выбрать существующего подрядчика (dropdown, с фильтром по доступности)
2. Выбрать существующего водителя (dropdown, с фильтром)
3. Выбрать существующее ТС (dropdown, с фильтром)
4. Сохранить → backend создаёт driver_vehicle_block + crew транзакционно

В будущем: inline-создание подрядчика/водителя/ТС из этой же формы (не сейчас).

---

## 3. Рекомендованный путь перехода

### ВАРИАНТ А — UI-facade (РЕКОМЕНДОВАН)

**Суть**: физические таблицы `driver_vehicle_blocks` и `crews` остаются как технический слой. UI-слой показывает одну сущность «Исполнитель рейса», а под капотом продолжает работать с crews + driver_vehicle_blocks.

**Что остаётся**:
- Таблицы: `driver_vehicle_blocks`, `crews` — без изменений.
- Маршруты: `/company/driver-vehicle-blocks/*`, `/company/crews/*` — сохраняются как технические (могут быть скрыты из меню, но доступны по прямым URL).
- Логика: все обработчики, валидация, grants, документы — без изменений.

**Что меняется**:
- Создаётся новый UI-слой: `app/View/pages/company_route_executors.php` (список).
- Новый route: `GET /company/route-executors` — читает crews с JOIN и показывает как «Исполнители рейса».
- Создание: `GET/POST /company/route-executors/create` — форма выбора contractor + driver + vehicle_set → создаёт driver_vehicle_block + crew в одной транзакции.
- Редактирование: `GET/POST /company/route-executors/{id}/edit` — редактирует crew (contractor, driver, vehicle_set).
- Меню: скрыть «Водители+ТС», «Экипажи», «Привязка перевозчиков» из основного списка; добавить «Исполнители рейса».

**Плюсы**:
- Минимальный риск — не трогаем существующие таблицы, routes, обработчики.
- Быстрое внедрение — только новые view + несколько новых routes.
- Обратная совместимость — старые URL продолжают работать.
- Можно откатить, просто скрыв новые пункты меню.

**Минусы**:
- В базе остаются две технические сущности.
- Дублирование списков: crews list и route_executors list показывают одни и те же данные под разными именами.

### ВАРИАНТ Б — новая таблица route_executors (НЕ РЕКОМЕНДОВАН СЕЙЧАС)

Создание `route_executors` с contractor_id, driver_id, vehicle_set_id и миграция данных.

**Риски**:
- Нужно переписать ВСЕ routes, views, handlers.
- Нужно переписать contractor-assignment (каскадный перенос).
- Нужно переписать entity_access_grants (новый entity_type).
- Нужно переписать документы (entity_type).
- Нужно переписать master-flow create-full / add-crew.
- Высокий риск регрессии.

**Когда может понадобиться**: если в будущем модель усложнится (например, несколько водителей на одно ТС), но не сейчас.

### ВАРИАНТ В — compat layer (НЕ РЕКОМЕНДОВАН)

Временное усложнение, не даёт выигрыша на текущем этапе.

### ИТОГОВАЯ РЕКОМЕНДАЦИЯ

**Вариант А как первый безопасный этап.**

Позже, после стабилизации UI и проверки всех сценариев, можно принять решение о физической миграции БД (Вариант Б) как отдельный этап E7.

---

## 4. Меню

### 4.1 Что убрать из основного пользовательского меню

Из группы «Подрядчики» убрать:
- **Водители+ТС** (`/company/driver-vehicle-blocks`)
- **Экипажи** (`/company/crews`)
- **Привязка перевозчиков** (`/company/contractor-assignments`)

### 4.2 Что добавить

В группу «Подрядчики» добавить:
- **Исполнители рейса** (`/company/route-executors`)

### 4.3 Новая структура меню (company_owner)

```
ОПЕРАЦИИ
  Клиенты
  Подрядчики ▾
    Перевозчики
    Исполнители рейса       ← НОВЫЙ
    Водители
    Транспорт
    Переназначение логистов  ← НОВЫЙ (вместо «Привязка перевозчиков»)

СИСТЕМА
  Логисты
```

### 4.4 Новая структура меню (logist / senior_logist)

```
ОПЕРАЦИИ
  Клиенты
  Подрядчики ▾
    Перевозчики
    Исполнители рейса       ← НОВЫЙ
    Водители
    Транспорт
```

### 4.5 Важное правило

- Старые URL `/company/driver-vehicle-blocks`, `/company/crews`, `/company/contractor-assignments` продолжают работать.
- Они просто не показываются в меню как основные пункты.
- Это позволяет откатить изменения меню без потери функциональности.

---

## 5. Страница «Исполнители рейса»

### 5.1 Список (`GET /company/route-executors`)

**View**: `app/View/pages/company_route_executors.php`

**Запрос**: JOIN crews → contractors, driver_vehicle_blocks → drivers, vehicle_sets → vehicle_units.

```sql
SELECT c.id AS crew_id,
       ct.name AS contractor_name, ct.inn AS contractor_inn,
       d.full_name AS driver_name, d.phone AS driver_phone,
       vs.set_type,
       CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
       u.full_name AS logist_name,
       c.status, c.created_at
FROM crews c
JOIN contractors ct ON c.contractor_id = ct.id
JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
JOIN drivers d ON dvb.driver_id = d.id
JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
LEFT JOIN users u ON c.created_by_user_id = u.id
WHERE ... -- visibility filter
ORDER BY c.created_at DESC
```

**Фильтр видимости**:
- `logist`: `(c.created_by_user_id = ? OR c.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'crew' ...))`
- `senior_logist` / `company_owner`: без фильтра.

**Колонки таблицы**: Подрядчик, Водитель, ТС, Телефон, Госномер, Логист, Статус, Документы, Действие.

**Действия**: просмотр (view), редактирование (edit), архив (archive).

### 5.2 Создание (`GET/POST /company/route-executors/create`)

**View**: `app/View/pages/company_route_executors_create.php`

**Форма**:
- Выпадающий список «Подрядчик» (активные, с фильтром доступности для logist)
- Выпадающий список «Водитель» (активные, с фильтром)
- Выпадающий список «ТС» (активные vehicle_sets, с фильтром)
- Кнопка «Создать исполнителя рейса»

**Backend (POST)**:
1. Валидация: contractor_id, driver_id, vehicle_set_id — обязательные.
2. Для logist: проверка created_by_user_id + grants для каждого выбранного ID.
3. Проверка дубля: не существует ли уже driver_vehicle_block с этим driver_id + vehicle_set_id.
4. Транзакция:
   - Если driver_vehicle_block не существует → INSERT.
   - INSERT crew (contractor_id + driver_vehicle_block_id).
   - Запись created_by_user_id / created_by_role = текущий пользователь.
5. Редирект на `/company/route-executors/{crew_id}`.

### 5.3 Просмотр (`GET /company/route-executors/{id}`)

**View**: `app/View/pages/company_route_executor_view.php`

Показывает все поля исполнителя рейса:
- Подрядчик (ссылка на `/company/contractors/{id}`)
- Водитель (ссылка на `/company/drivers/{id}`)
- ТС (ссылка на `/company/vehicle-sets/{id}`)
- Документы подрядчика, водителя, ТС (только просмотр, не управление)
- Статус, комментарий
- Кнопки: Редактировать, Архивировать

### 5.4 Редактирование (`GET/POST /company/route-executors/{id}/edit`)

**View**: `app/View/pages/company_route_executor_edit.php`

Позволяет изменить:
- Подрядчика (если у текущего contractor закончился договор)
- Водителя
- ТС
- Статус
- Комментарий

**Backend (POST)**:
1. Загрузить текущий crew + driver_vehicle_block.
2. Если driver_id или vehicle_set_id изменились → проверить/создать новый driver_vehicle_block.
3. Обновить crew (contractor_id, driver_vehicle_block_id).
4. Если старый driver_vehicle_block больше не используется ни в одном crew → можно архивировать (опционально).

### 5.5 Архивирование (`POST /company/route-executors/{id}/archive`)

Установить `crews.status = 'archived'`. Driver_vehicle_block не трогать (может использоваться в других crews).

---

## 6. Переназначение ответственного логиста

### 6.1 Новая страница: «Переназначение логистов»

**URL**: `GET /company/logist-reassignments`

**Доступ**: только `company_owner`.

**Структура**: страница с вкладками:

```
Переназначение логистов
├── Исполнители рейса
├── Подрядчики
├── Водители
└── ТС
```

### 6.2 Вкладка «Исполнители рейса»

Список crews с текущим логистом-владельцем (created_by_user_id). Возможность:
- Выбрать один crew → выбрать нового логиста → «Переназначить».
- Массовый выбор (чекбоксы) → выбрать логиста → «Переназначить выбранные».
- Фильтр по текущему логисту.

**Логика переназначения crew**:
- Меняется `created_by_user_id` / `created_by_role` у crew.
- НЕ трогает driver_vehicle_block, drivers, vehicle_sets (они могут использоваться в других crews).
- Если нужно перенести и сам driver/vehicle_set → это отдельная вкладка.

### 6.3 Вкладка «Подрядчики»

Аналог текущей `/company/contractor-assignments`, но в новом дизайне.

**Логика**: при переназначении contractor:
- Переносится contractor + все его crews + driver_vehicle_blocks + drivers + vehicle_sets (каскадно, как сейчас).
- Есть выбор: «Перенести вместе с исполнителями рейса» (чекбокс, по умолчанию ВКЛ).
- Если чекбокс СНЯТ → переносится только contractor, crews остаются у старого логиста (риск: crews без доступного contractor).
- История записывается в `contractor_assignment_history`.

### 6.4 Вкладка «Водители»

Список drivers с текущим логистом. Переназначение:
- Меняется только `created_by_user_id` drivers.
- НЕ трогает driver_vehicle_blocks и crews автоматически.
- Предупреждение: «Этот водитель используется в N исполнителях рейса. Перенести также исполнителей рейса?»
- Чекбокс: «Перенести вместе с исполнителями рейса».
- Если ВКЛ → переносятся все crews, где этот driver участвует через driver_vehicle_block.

### 6.5 Вкладка «ТС»

Аналогично водителям:
- Меняется `created_by_user_id` vehicle_sets.
- Предупреждение/чекбокс для crews.

### 6.6 Массовое переназначение

На каждой вкладке:
- Чекбоксы у строк.
- Выпадающий список «Новый логист».
- Кнопка «Переназначить выбранные (N)».
- Подтверждение: модальное окно с подсчётом затронутых сущностей.

### 6.7 История переназначений

Страница/вкладка «История»:
- Читает `contractor_assignment_history`.
- В будущем: таблица `logist_reassignment_history` для всех типов сущностей (не сейчас).

---

## 7. Правила доступа

### 7.1 company_owner

- Видит всех исполнителей рейса.
- Видит всех подрядчиков, водителей, ТС.
- Управляет переназначением логистов.
- Меню: полное.

### 7.2 senior_logist (Логист+)

- Видит всех исполнителей рейса (без фильтра).
- Видит все данные компании.
- НЕ управляет переназначением.
- Меню: без «Переназначение логистов».

### 7.3 logist (обычный)

- Видит только своих исполнителей рейса (`created_by_user_id` + grants).
- При создании исполнителя рейса:
  - Выпадающие списки contractor/driver/vehicle_set фильтруются: только свои + grants.
  - Backend отклоняет чужие ID.
- НЕ видит чужие данные.
- Меню: без «Переназначение логистов».

---

## 8. Backend-валидация

### 8.1 Создание исполнителя рейса

- `contractor_id` — обязательное, существует, active, доступен (для logist).
- `driver_id` — обязательное, существует, active, доступен.
- `vehicle_set_id` — обязательное, существует, active, доступен.
- Проверка дубля crew: `contractor_id + driver_vehicle_block_id`.
- Транзакция: INSERT driver_vehicle_block (если нет) + INSERT crew.

### 8.2 Редактирование

- Те же проверки, что при создании.
- Существующий crew должен быть доступен (для logist).

### 8.3 Переназначение

- `new_logist_id` — обязательное, существует, `role_code = 'logist'`, `status = 'active'`.
- Целевая сущность доступна текущему company_owner.
- Запрет переназначения на самого себя (для contractor assignment — не актуально, company_owner не логист).

---

## 9. Пошаговый план реализации

### E1 (текущий этап) — Архитектурный план
- [x] Изучить код и таблицы.
- [x] Создать `ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`.
- [x] Обновить управляющие MD.
- [ ] Commit `docs(architecture): plan route executor simplification`.

### E2 — UI/menu facade «Исполнители рейса»
- Создать view `company_route_executors.php` (список).
- Создать route `GET /company/route-executors`.
- Изменить меню в `main.php`: скрыть «Водители+ТС», «Экипажи», «Привязка перевозчиков»; добавить «Исполнители рейса».
- Старые routes НЕ удалять.

### E3 — CRUD для исполнителей рейса
- `GET/POST /company/route-executors/create`
- `GET /company/route-executors/{id}` (просмотр)
- `GET/POST /company/route-executors/{id}/edit`
- `POST /company/route-executors/{id}/archive`
- Backend-валидация доступности для logist.

### E4 — Переназначение ответственных
- Новая страница `GET /company/logist-reassignments`.
- Вкладки: Исполнители рейса, Подрядчики, Водители, ТС.
- Массовое переназначение.
- Интеграция с `contractor_assignment_history`.

### E5 — Зачистка старых пунктов
- Оценить использование старых routes `/company/driver-vehicle-blocks`, `/company/crews`.
- Если не используются → убрать из кодовой базы (оставить таблицы).
- Убрать старую `/company/contractor-assignments`, заменив на новую вкладку.

### E6 — Документы и master-flow
- Документы на странице просмотра исполнителя рейса.
- Адаптировать master-flow create-full / add-crew (возможно, направить на новый create route).

### E7 — Возможная миграция БД (Вариант Б)
- Только после полной стабилизации E2–E6.
- Отдельное решение владельца.

---

## 10. Риски

| Риск | Описание | Митигация |
|------|----------|-----------|
| Документы | entity_type='crew'/'driver_vehicle_block' — нужно проверить, не сломается ли отображение на новой странице. | Не менять entity_type, показывать документы через существующие связи. |
| Grants | entity_access_grants для crew/driver_vehicle_block должны продолжать работать. | Новый route_executors list использует тот же фильтр, что и crews list. |
| История | contractor_assignment_history пишется при переназначении contractor. Нужна аналогичная таблица для driver/vehicle_set/crew. | Добавить `logist_reassignment_history` на этапе E4. |
| Старые routes | Если оставить старые routes рабочими, пользователь может запутаться. | Скрыть из меню, но оставить для совместимости. Добавить редирект со старых страниц на новые (опционально). |
| Shared drivers/vehicle_sets | Один driver может быть в нескольких crews у разных логистов. При переназначении driver нужно решить, переносить ли все crews. | Чекбокс «Перенести вместе с исполнителями рейса» — по умолчанию СНЯТ для driver/vehicle_set. |

---

## 11. Что точно НЕ делать сейчас

- НЕ удалять таблицы `driver_vehicle_blocks`, `crews` физически.
- НЕ удалять старые routes без отдельного решения.
- НЕ ломать protected pages (`/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`).
- НЕ переписывать `public/index.php` целиком — только добавлять новые routes.
- НЕ менять формы создания водителей/ТС/подрядчиков.
- НЕ делать UI-полировку на архитектурном этапе.
- НЕ создавать миграции БД.
- НЕ коммитить изменения кода (только MD).

---

## 12. Связь с существующей документацией

- `docs/ai/DECISIONS.md` #18 — текущая архитектура driver_vehicle_blocks + crews.
- `docs/ai/DECISIONS.md` #52 — contractor assignment как основной механизм.
- `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md` — DO_NOT_TOUCH_WORKING_CORE.
- `docs/ai/MASTER_FLOW_ARCHITECTURE.md` — сценарии create-full / add-crew.

---

## 13. Версия документа

- **Версия**: 1.0
- **Дата**: 2026-06-24
- **Статус**: АРХИТЕКТУРНЫЙ ПЛАН. Ожидает утверждения владельцем.
- **Следующий шаг**: E2 — UI/menu facade «Исполнители рейса».
