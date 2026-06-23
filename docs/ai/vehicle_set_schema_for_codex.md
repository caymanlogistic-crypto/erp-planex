# ВНИМАНИЕ

Этот документ подготовлен для будущего блока `/company/vehicle-sets/create`. Он не является текущей активной задачей, пока не закрыт driver edit modal.

---

# Vehicle Set — Схема данных и логики для CODEX-дизайнера

> Страница: `/company/vehicle-sets/create`  
> Дата выгрузки: 2026-06-19  
> Назначение: техническая сводка для проектирования динамической формы создания транспортного комплекта.

---

## 1. Таблицы

### 1.1 `vehicle_sets` — транспортные комплекты

| Поле | Тип | Nullable | Default | Ключ/Индекс |
|------|-----|----------|---------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NOT NULL | — | PRIMARY KEY |
| `set_type` | VARCHAR(20) | YES | NULL | INDEX `idx_set_type` |
| `primary_vehicle_unit_id` | INT UNSIGNED | NOT NULL | — | INDEX `idx_primary` |
| `secondary_vehicle_unit_id` | INT UNSIGNED | YES | NULL | INDEX `idx_secondary` |
| `status` | VARCHAR(20) | NO | `'active'` | — |
| `comments` | TEXT | YES | NULL | — |
| `created_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `created_by_role` | VARCHAR(20) | YES | NULL | — |
| `updated_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `updated_by_role` | VARCHAR(20) | YES | NULL | — |
| `created_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP | — |
| `updated_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | — |

**Внешние ключи:** НЕТ (связь с `vehicle_units` — логическая, через `primary_vehicle_unit_id` / `secondary_vehicle_unit_id`, без FK constraint).

---

### 1.2 `vehicle_units` — транспортные единицы

| Поле | Тип | Nullable | Default | Ключ/Индекс |
|------|-----|----------|---------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NOT NULL | — | PRIMARY KEY |
| `plate_number` | VARCHAR(20) | NOT NULL | — | INDEX `idx_plate` |
| `brand` | VARCHAR(100) | YES | NULL | — |
| `model` | VARCHAR(100) | YES | NULL | — |
| `vehicle_type` | VARCHAR(50) | YES | NULL | — |
| `vin` | VARCHAR(50) | YES | NULL | — |
| `sts_number` | VARCHAR(50) | YES | NULL | — |
| `pts_number` | VARCHAR(50) | YES | NULL | — |
| `capacity_tons` | DECIMAL(8,2) | YES | NULL | — |
| `volume_m3` | DECIMAL(8,2) | YES | NULL | — |
| `unit_type` | VARCHAR(20) | YES | NULL | — |
| `diagnostic_card_number` | VARCHAR(50) | YES | NULL | — |
| `diagnostic_card_date` | DATE | YES | NULL | — |
| `diagnostic_card_expire` | DATE | YES | NULL | — |
| `status` | VARCHAR(20) | NOT NULL | `'active'` | — |
| `comments` | TEXT | YES | NULL | — |
| `created_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `created_by_role` | VARCHAR(20) | YES | NULL | — |
| `updated_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `updated_by_role` | VARCHAR(20) | YES | NULL | — |
| `created_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP | — |
| `updated_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | — |

**Внешние ключи:** НЕТ.

**Особенности:**
- `vehicle_type` — устаревшее поле (осталось от первоначальной схемы).
- `sts_number` / `pts_number` — устаревшие текстовые поля на самой единице. В новой логике СТС/ПТС должны идти как документы к комплекту (см. раздел 5).
- `unit_type` — основной дискриминатор типа транспортной единицы.

---

### 1.3 `documents` — документы

| Поле | Тип | Nullable | Default | Ключ/Индекс |
|------|-----|----------|---------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NOT NULL | — | PRIMARY KEY |
| `entity_type` | VARCHAR(20) | NOT NULL | — | INDEX `idx_entity` (entity_type, entity_id) |
| `entity_id` | INT UNSIGNED | NOT NULL | — | INDEX `idx_entity` |
| `document_type` | VARCHAR(100) | YES | NULL | — |
| `document_type_id` | INT UNSIGNED | YES | NULL | INDEX `idx_document_type_id` |
| `document_name` | VARCHAR(500) | YES | NULL | — |
| `document_number` | VARCHAR(100) | YES | NULL | — |
| `document_date` | DATE | YES | NULL | — |
| `document_expire` | DATE | YES | NULL | — |
| `original_name` | VARCHAR(500) | NOT NULL | — | — |
| `stored_name` | VARCHAR(255) | NOT NULL | — | — |
| `relative_path` | VARCHAR(1000) | NOT NULL | — | — |
| `mime_type` | VARCHAR(100) | NOT NULL | — | — |
| `file_size` | BIGINT UNSIGNED | NOT NULL | — | — |
| `status` | VARCHAR(20) | NOT NULL | `'uploaded'` | INDEX `idx_status` |
| `uploaded_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `uploaded_by_role` | VARCHAR(50) | YES | NULL | — |
| `created_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `created_by_role` | VARCHAR(20) | YES | NULL | — |
| `updated_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `updated_by_role` | VARCHAR(20) | YES | NULL | — |
| `comments` | TEXT | YES | NULL | — |
| `deleted_at` | TIMESTAMP | YES | NULL | INDEX `idx_deleted` |
| `deleted_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `delete_comment` | TEXT | YES | NULL | — |
| `created_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP | INDEX `idx_created` |
| `updated_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | — |

**Внешние ключи:** НЕТ (FK на `document_types` — логический, через `document_type_id`, без constraint).

---

### 1.4 `document_types` — справочник типов документов

| Поле | Тип | Nullable | Default | Ключ/Индекс |
|------|-----|----------|---------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NOT NULL | — | PRIMARY KEY |
| `name` | VARCHAR(200) | NOT NULL | — | UNIQUE `uk_name_entity_type` (name, entity_type) |
| `code` | VARCHAR(50) | YES | NULL | — |
| `entity_type` | VARCHAR(20) | YES | NULL | INDEX `idx_entity_type` |
| `category` | VARCHAR(20) | NOT NULL | `'custom'` | INDEX `idx_category` |
| `sort_order` | INT | NOT NULL | 0 | — |
| `created_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `created_by_role` | VARCHAR(20) | YES | NULL | — |
| `created_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP | — |
| `updated_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | — |

**Допустимые значения `entity_type`:**
- `'vehicle_set'` — документ транспортного комплекта
- `'vehicle_unit'` — документ транспортной единицы
- `'driver'` — документ водителя
- `'contractor'` — документ подрядчика
- `NULL` — универсальный тип (без привязки)

**Допустимые значения `category`:**
- `'predefined'` — предопределённый системой
- `'custom'` — созданный пользователем

---

### 1.5 `driver_vehicle_blocks` — связка «Водитель + ТС»

| Поле | Тип | Nullable | Default | Ключ/Индекс |
|------|-----|----------|---------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NOT NULL | — | PRIMARY KEY |
| `driver_id` | INT UNSIGNED | NOT NULL | — | INDEX `idx_driver_id` |
| `vehicle_set_id` | INT UNSIGNED | NOT NULL | — | INDEX `idx_vehicle_set_id` |
| `status` | VARCHAR(20) | NO | `'active'` | — |
| `comments` | TEXT | YES | NULL | — |
| `created_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `created_by_role` | VARCHAR(20) | YES | NULL | — |
| `updated_by_user_id` | INT UNSIGNED | YES | NULL | — |
| `updated_by_role` | VARCHAR(20) | YES | NULL | — |
| `created_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP | — |
| `updated_at` | TIMESTAMP | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | — |

**Уникальный ключ:** `uk_driver_set (driver_id, vehicle_set_id)`.

---

### 1.6 `entity_access_grants` — доступы логистов

| Поле | Тип | Nullable | Default |
|------|-----|----------|---------|
| `entity_type` | VARCHAR(20) | NOT NULL | — |
| `entity_id` | INT UNSIGNED | NOT NULL | — |
| `granted_to_user_id` | INT UNSIGNED | NOT NULL | — |
| `access_level` | VARCHAR(20) | NOT NULL | — |
| `revoked_at` | TIMESTAMP | YES | NULL |

Используется для `entity_type = 'vehicle_set'` и `entity_type = 'vehicle_unit'`.

---

## 2. Связи

### 2.1 Комплект → Основная единица
- `vehicle_sets.primary_vehicle_unit_id` → `vehicle_units.id`
- **NOT NULL** (обязательное поле).
- Без FK-constraint.

### 2.2 Комплект → Дополнительная единица
- `vehicle_sets.secondary_vehicle_unit_id` → `vehicle_units.id`
- **NULLABLE** (опционально, только для coupling/road_train).
- Без FK-constraint.

### 2.3 Документ → Сущность (полиморфная связь)
- `documents.entity_type` + `documents.entity_id` → любая сущность.
- Фактические значения `entity_type`: `'vehicle_set'`, `'vehicle_unit'`, `'driver'`, `'contractor'`, `'crew'`, `'driver_vehicle_block'`, `'client'`.
- **Нет FK-constraint** — связь держится на уровне приложения.

### 2.4 Документ → Тип документа
- `documents.document_type_id` → `document_types.id`
- Без FK-constraint.
- Дублируется текстовым полем `documents.document_type` (может быть заполнено даже без записи в `document_types`).

### 2.5 Документ → Файл
- Физический файл: `{storage}/companies/{company_id}/documents/{entity_type}/{entity_id}/{stored_name}`
- Связь по полям `stored_name`, `relative_path`.

### 2.6 Комплект → Водители (driver_vehicle_blocks)
- `driver_vehicle_blocks.vehicle_set_id` → `vehicle_sets.id`
- Без FK-constraint.

### 2.7 Компания → Локальная БД
- НЕТ таблицы `contractor_vehicles` / `company_vehicles`.
- Все `vehicle_units`, `vehicle_sets`, `documents` живут в **отдельной БД компании** (база данных = `companies.db_identifier`).
- Привязка к компании — неявная, через факт нахождения в её БД.

---

## 3. Доменные значения

### 3.1 `vehicle_sets.set_type`

| Значение | Человекочитаемое | Где используется |
|----------|-------------------|------------------|
| `single` | Одиночка (одна единица) | `view_formatters.php:ui_set_type()`, create/edit view `<select>`, логика валидации |
| `coupling` | Сцепка (тягач + полуприцеп) | то же |
| `road_train` | Автопоезд (машина + прицеп) | то же |
| `NULL` | Не указан | допустимо в БД |

### 3.2 `vehicle_units.unit_type`

| Значение | Человекочитаемое | Где используется |
|----------|-------------------|------------------|
| `single` | Одиночное ТС | `view_formatters.php:ui_unit_type()`, `<select>` при создании/редактировании ТЕ |
| `tractor` | Тягач | то же |
| `semi_trailer` | Полуприцеп | то же |
| `truck` | Грузовик | то же |
| `trailer` | Прицеп | то же |
| `NULL` | Не указан | допустимо в БД |

### 3.3 Статусы

**`vehicle_sets.status` и `vehicle_units.status`:**
| Значение | Человекочитаемое |
|----------|-------------------|
| `active` | Активен |
| `inactive` | Неактивен |
| `archived` | Архив (скрыт из основных списков) |

**`documents.status`:**
| Значение | Человекочитаемое |
|----------|-------------------|
| `uploaded` | Загружен |
| `pending` | Ожидает проверки |
| `approved` | Принят |
| `rejected` | Отклонён |
| `archived` | Архивирован |
| `active` | Активен |

**`document_types.category`:**
| Значение | Человекочитаемое |
|----------|-------------------|
| `predefined` | Предопределённый системой |
| `custom` | Пользовательский |

### 3.4 Допустимые значения `entity_type` (для documents / document_types / entity_access_grants)

| Значение | Человекочитаемое |
|----------|-------------------|
| `vehicle_set` | Транспортный комплект |
| `vehicle_unit` | Транспортная единица |
| `driver` | Водитель |
| `contractor` | Подрядчик |
| `crew` | Экипаж |
| `driver_vehicle_block` | Водитель + ТС |
| `client` | Клиент |

### 3.5 Предопределённые коды документов (document_types.code)

| code | name | entity_type | category |
|------|------|-------------|----------|
| `passport` | Паспорт | `driver` | predefined |
| `driver_license` | Водительское удостоверение | `driver` | predefined |
| `snils` | СНИЛС | `driver` | predefined |
| `sts` | СТС | `vehicle_set` | predefined |
| `pts` | ПТС | `vehicle_set` | predefined |
| `osago` | Страховка ОСАГО | `vehicle_set` | predefined |
| `company_card` | Карточка предприятия | `contractor` | predefined |
| `inn_cert` | Свидетельство ИНН | `contractor` | predefined |
| `ogrn_cert` | Свидетельство ОГРН | `contractor` | predefined |
| `contract` | Договор | `contractor` | predefined |

---

## 4. Текущая бизнес-логика из кода

### 4.1 Валидация при создании комплекта

**Файл:** `public/index.php`, строки 8193–8211 (POST `/company/vehicle-sets/create`).

```php
// Обязательность полей:
if ($primaryId === '') { $errors['primary_vehicle_unit_id'] = 'Обязательное поле'; }
if (($setType === 'coupling' || $setType === 'road_train') && $secondaryId === '') {
    $errors['secondary_vehicle_unit_id'] = 'Обязательно для сцепки и автопоезда';
}
if ($setType === 'single') { $secondaryId = ''; }  // принудительно сбрасывается
```

**Фактическая логика:**
- Для `single`: secondary не требуется и принудительно очищается.
- Для `coupling` / `road_train`: secondary обязательно.
- При редактировании — точно такая же логика (строки 8468–8476).

### 4.2 Фильтрация vehicle_units по unit_type

**Текущее состояние: ОТСУТСТВУЕТ.**

Запрос для получения списка доступных единиц:
```php
SELECT * FROM vehicle_units WHERE status = 'active' ORDER BY plate_number
```

- **НЕТ фильтрации** по `unit_type`.
- Все активные единицы показываются и в primary, и в secondary `<select>`.
- Нет валидации совместимости: можно выбрать `single` в primary и `tractor` в secondary для `road_train`.
- Можно выбрать одну и ту же единицу как primary и secondary (нет проверки на `primary_vehicle_unit_id != secondary_vehicle_unit_id`).

### 4.3 Разрешённые unit_type для каждого set_type

**Текущее состояние: НИКАКИХ ОГРАНИЧЕНИЙ НЕТ.**

Логически ожидаемая схема (НЕ реализована в коде):
| set_type | Допустимый primary unit_type | Допустимый secondary unit_type | Обязательность secondary |
|----------|------------------------------|-------------------------------|--------------------------|
| `single` | `single`, `truck` | — | Нет |
| `coupling` | `tractor` | `semi_trailer` | Да |
| `road_train` | `truck` | `trailer` | Да |

**В коде этого нет.** Это нужно будет реализовать — либо на фронте (JS-фильтрация), либо на бэкенде (SQL WHERE + серверная валидация).

### 4.4 Проверка существования выбранной единицы

Выполняется только для primary:
```php
if (!empty($primaryId)) {
    $vuCheck = $localPdo->prepare("SELECT unit_type FROM vehicle_units WHERE id = ?");
    $vuCheck->execute([(int)$primaryId]);
    $vuRow = $vuCheck->fetch(PDO::FETCH_ASSOC);
    if (!$vuRow) { $errors['primary_vehicle_unit_id'] = 'Единица не найдена'; }
}
```

**Для secondary проверка существования НЕ выполняется.**

---

## 5. Предопределённые документы на странице создания

### 5.1 Текущий список

Из файла `app/View/pages/company_vehicle_sets_create.php`, строка 2–6:

```php
$predefDocs = [
    ['name' => 'СТС', 'code' => 'sts'],
    ['name' => 'ПТС', 'code' => 'pts'],
    ['name' => 'Страховка ОСАГО', 'code' => 'osago'],
];
```

### 5.2 Куда сохраняются

При создании комплекта документы сохраняются с `entity_type = 'vehicle_set'`, `entity_id = {new_vehicle_set_id}`.

```php
// index.php, строка 8236
$docErrors = []; $uploadedDocs = []; $entityType = 'vehicle_set';
```

**ВЫВОД:** все три предопределённых документа (СТС, ПТС, ОСАГО) сохраняются как документы КОМПЛЕКТА, а не отдельных транспортных единиц.

### 5.3 Это документы комплекта или конкретной единицы?

**По текущей реализации:** это документы комплекта (`entity_type = 'vehicle_set'`).

**По бизнес-логике:**
- **СТС** — документ на конкретную транспортную единицу (основную). Юридически принадлежит тягачу/машине.
- **ПТС** — документ на конкретную транспортную единицу. Юридически принадлежит полуприцепу/прицепу.
- **ОСАГО** — страховка на тягач. Для прицепа ОСАГО не требуется (ответственность покрывается страховкой тягача).

Таким образом, существующая привязка «все документы на комплект» — упрощение. Для правильной модели необходимо разделение: часть документов на primary unit, часть — на secondary unit, часть — на комплект в целом.

### 5.4 Документы на уровне vehicle_unit

- На странице создания транспортной единицы (`/company/vehicles/create`) загрузка документов **НЕ предусмотрена**.
- Документы можно загрузить позже через `/company/documents/upload?entity_type=vehicle_unit&entity_id=...`.
- В `document_types` есть поддержка `entity_type = 'vehicle_unit'`, но **нет предопределённых типов** для этого entity_type.

---

## 6. Что важно для динамической формы — ВЫВОД ДЛЯ CODEX

### 6.1 Какие поля надо переключать по `set_type`

| Элемент формы | single | coupling | road_train |
|---------------|--------|----------|------------|
| Блок «Дополнительная единица» | **Скрыт** | Показан, обязателен | Показан, обязателен |
| `<select>` primary unit | Показан | Показан | Показан |
| `<select>` secondary unit | Скрыт + значение очищается | Показан | Показан |

**Текущая JS-логика:** `updateSecondaryField()` в `company_vehicle_sets_create.php` (строка 691–698) — скрывает/показывает `#secondary_field` через класс `is-hidden`.

### 6.2 Какие типы ТС должны быть доступны в primary

| set_type | Допустимые unit_type (primary) |
|----------|-------------------------------|
| `single` | `single`, `truck` |
| `coupling` | `tractor` |
| `road_train` | `truck` |

**Текущее состояние:** фильтрации нет — показываются ВСЕ активные единицы.

**Можно ли сделать только на фронте:** да, через JS-фильтрацию `<option>` по data-атрибутам или через перестройку `<select>` при смене `set_type`.

### 6.3 Какие типы ТС должны быть доступны в secondary

| set_type | Допустимые unit_type (secondary) |
|----------|----------------------------------|
| `single` | — (поле скрыто) |
| `coupling` | `semi_trailer` |
| `road_train` | `trailer` |

**Текущее состояние:** фильтрации нет — показываются ВСЕ активные единицы (те же, что и для primary).

**Можно ли сделать только на фронте:** да, аналогично primary.

### 6.4 Нужно ли разделять блок документов по единицам

**Текущее состояние:** все документы (СТС, ПТС, ОСАГО) — в едином блоке «Документы» и сохраняются на `vehicle_set`.

**Что нужно для правильной модели:**

Для полной реализации потребуется:
1. Разделить визуально: блок «Документы основной единицы» + блок «Документы дополнительной единицы» (если применимо).
2. Для каждого блока — свой набор предопределённых документов:
   - **Основная единица:** СТС, ОСАГО.
   - **Дополнительная единица:** СТС, ПТС (если `semi_trailer` / `trailer`).
3. При сохранении — указывать разный `entity_type` / `entity_id`:
   - Документы основной единицы: `entity_type = 'vehicle_unit'`, `entity_id = primary_vehicle_unit_id`.
   - Документы дополнительной единицы: `entity_type = 'vehicle_unit'`, `entity_id = secondary_vehicle_unit_id`.
   - Общие документы комплекта: `entity_type = 'vehicle_set'`, `entity_id = vehicle_set_id`.

### 6.5 Можно ли это сделать только на фронте

| Изменение | Только фронт? | Нужен backend? |
|-----------|---------------|-----------------|
| Фильтрация unit_type в primary/secondary `<select>` | ✅ Да (JS) | Желательно — серверная валидация |
| Показ/скрытие secondary блока по `set_type` | ✅ Да (уже работает) | Нет |
| Разделение документов по primary/secondary unit | ❌ Нет | **Обязательно** — нужно менять логику сохранения |
| Разные наборы предопределённых документов для primary/secondary | ❌ Нет | **Обязательно** — нужно переопределять `$entityType` при сохранении каждого документа |
| Визуальное разделение блоков документов | ✅ Да (вёрстка) | Нет |

**Ключевой вывод:** динамическое переключение `<select>` и видимости блоков — чистый фронт. Но корректное сохранение документов на primary/secondary unit вместо vehicle_set требует изменения backend-логики в `index.php` (POST-обработчик).

### 6.6 Что можно реализовать прямо сейчас (без backend-изменений)

1. **Фильтрация primary/secondary `<select>` по `unit_type` в зависимости от `set_type`** — только JS.
2. **Визуальное выделение блоков** «Документы основной единицы» и «Документы дополнительной единицы» с соответствующими подписями — только верстка.
3. **Показ разных предопределённых документов** в зависимости от того, какая секция (primary/secondary) — но сохранение останется на `vehicle_set`.

---

## 7. Источники

### Файлы кода

| Файл | Что изучено |
|------|-------------|
| `public/index.php` (строки 8107–8292) | GET/POST `/company/vehicle-sets/create` — полная бизнес-логика создания комплекта и загрузки документов |
| `public/index.php` (строки 8434–8505) | POST `/company/vehicle-sets/{id}/edit` — логика редактирования |
| `public/index.php` (строки 8294–8388) | GET `/company/vehicle-sets/{id}` — просмотр комплекта |
| `public/index.php` (строки 6336–6480) | POST `/company/vehicles/create` — создание транспортной единицы (документы не загружаются) |
| `app/View/pages/company_vehicle_sets_create.php` | Полная вёрстка формы создания (включая JS-логику) |
| `app/View/pages/company_vehicle_set_edit.php` | Форма редактирования (поля и логика аналогичны create) |
| `app/View/pages/company_vehicle_set_view.php` | Карточка просмотра комплекта |
| `app/View/pages/company_vehicles_create.php` | Форма создания транспортной единицы |
| `app/View/pages/company_documents.php` | Список документов (entity_type-aware) |
| `app/View/pages/company_documents_upload.php` | Форма загрузки документа |
| `app/View/components/view_formatters.php` | Функции `ui_set_type()`, `ui_unit_type()`, `ui_entity_type()` — маппинги значений |

### Миграции БД

| Файл | Таблица |
|------|---------|
| `database/migrations-local/005_create_company_vehicles.sql` | `vehicles` → `vehicle_units` (исходная) |
| `database/migrations-local/007_create_company_documents.sql` | `documents` |
| `database/migrations-local/008_add_ownership_columns.sql` | `created_by_user_id/role` для vehicles, documents |
| `database/migrations-local/016_rename_vehicles_to_vehicle_units.sql` | Добавление `unit_type`, diagnostic_card_*, переименование |
| `database/migrations-local/017_create_vehicle_sets.sql` | `vehicle_sets` |
| `database/migrations-local/018_create_driver_vehicle_blocks.sql` | `driver_vehicle_blocks` |
| `database/migrations-local/020_update_documents.sql` | Расширение `documents` (document_name, number, date, expire, soft-delete) |
| `database/migrations-local/024_create_document_types.sql` | `document_types` |
| `database/migrations-local/025_add_document_type_id.sql` | FK `document_type_id` + seed предопределённых типов |
