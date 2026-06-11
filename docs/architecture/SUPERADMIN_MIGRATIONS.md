# ERP PLANEX — SUPERADMIN_MIGRATIONS

## Назначение

Документ описывает SQL-миграции центральной БД SUPERADMIN, созданные в рамках Stage 3.

## Статус

**Stage 3 — миграции созданы (2026-06-12).** Миграции НЕ применены к реальной MySQL. Ожидают QA-проверки и разрешения владельца на применение.

## Созданные migration-файлы

| № | Файл | Таблица | Поля | Индексы | FK |
|---|---|---|---|---|---|
| 1 | `001_create_superadmin_companies.sql` | `companies` | 12 | 5 | нет |
| 2 | `002_create_superadmin_features.sql` | `features` | 11 | 6 | 1 (self-ref, SET NULL) |
| 3 | `003_create_superadmin_company_features.sql` | `company_features` | 9 | 6 | 2 (CASCADE) |
| 4 | `004_create_superadmin_users.sql` | `superadmin_users` | 10 | 4 | нет |

Все файлы находятся в `database/migrations/`.

## Порядок применения

Миграции должны применяться строго в порядке нумерации (001 → 004):

1. **001** — `companies` (корневая таблица, нет зависимостей)
2. **002** — `features` (корневая таблица, self-reference FK создаётся ALTER TABLE после CREATE TABLE)
3. **003** — `company_features` (зависит от `companies.id` и `features.code`)
4. **004** — `superadmin_users` (независимая таблица, но логически после остальных)

## Что создаёт каждая миграция

### 001 — `companies`

Центральный реестр компаний/локальных ERP. Поля: `id`, `key` (уникальный slug), `name`, `short_name`, `entity_type`, `status`, `folder_path`, `db_identifier` (только имя БД, НЕ пароль), `storage_path`, `settings_json` (тип JSON), `created_at`, `updated_at`.

### 002 — `features`

Реестр функций (feature toggles). Поля: `id`, `code` (уникальный, конвенция `type.name`), `name`, `type` (7 типов), `description`, `parent_code` (self-reference FK → `features.code`, ON DELETE SET NULL), `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`.

### 003 — `company_features`

Связка компаний и функций. Поля: `id`, `company_id` (FK → `companies.id`, CASCADE), `feature_code` (FK → `features.code`, CASCADE), `is_enabled`, `enabled_from`, `enabled_until`, `notes`, `created_at`, `updated_at`. Уникальность пары `(company_id, feature_code)`. Default-deny модель.

### 004 — `superadmin_users`

Пользователи SUPERADMIN. Поля: `id`, `name`, `email` (уникальный), `password_hash` (bcrypt, VARCHAR, без реальных значений), `role` (VARCHAR), `is_active`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`.

## Что НЕ создают миграции

- Зарезервированные поля (logo_path, tax_id, deleted_at, reset_token, 2FA, remember_token, etc.)
- Seed-записи (INSERT)
- Таблицы локальной ERP (clients, contractors, trips, documents, etc.)
- Таблицу `local_feature_cache` (локальная БД компании)
- ENUM-типы (используется VARCHAR)
- Реальные DB credentials (пароли, хосты, порты)
- Авторизацию / сессии
- CRUD-операции
- Migration runner

## Как проверять

### Визуальная проверка (без MySQL)

1. Открыть каждый SQL-файл.
2. Сверить поля, типы, индексы, FK с `docs/architecture/SUPERADMIN_DATABASE.md`.
3. Убедиться, что нет INSERT, реальных email/паролей/host/port.
4. Проверить: `settings_json` тип JSON, `password_hash` только VARCHAR.
5. Проверить self-reference FK в `002_*`: CREATE TABLE без FK, затем ALTER TABLE.

### Dry-run на MySQL (требуется отдельное разрешение)

```sql
-- Применить миграции к тестовой БД:
SOURCE database/migrations/001_create_superadmin_companies.sql;
SOURCE database/migrations/002_create_superadmin_features.sql;
SOURCE database/migrations/003_create_superadmin_company_features.sql;
SOURCE database/migrations/004_create_superadmin_users.sql;

-- Проверить созданные таблицы:
SHOW TABLES LIKE 'superadmin%';
SHOW CREATE TABLE companies;
SHOW CREATE TABLE features;
SHOW CREATE TABLE company_features;
SHOW CREATE TABLE superadmin_users;
```

## Технические детали

### Движок и кодировка

- Engine: InnoDB
- Charset: utf8mb4
- Collation: utf8mb4_unicode_ci
- Все таблицы: `IF NOT EXISTS`

### Внешние ключи

| FK | Таблица | Ссылается | ON DELETE |
|---|---|---|---|
| `fk_features_parent` | `features` | `features.code` (self) | SET NULL |
| `fk_company_features_company` | `company_features` | `companies.id` | CASCADE |
| `fk_company_features_feature` | `company_features` | `features.code` | CASCADE |

### Self-reference FK в features

`parent_code` ссылается на `features.code`. Реализован в два шага в одном файле:

1. `CREATE TABLE features (...)` — без FK на parent_code
2. `ALTER TABLE features ADD CONSTRAINT fk_features_parent FOREIGN KEY (parent_code) REFERENCES features (code) ON DELETE SET NULL`

### .gitignore

Добавлено исключение `!database/migrations/*.sql`, чтобы миграции трекались в git. Правило `*.sql` (исключающее дампы) не должно блокировать миграции.

## Связанные документы

- `docs/architecture/SUPERADMIN_DATABASE.md` — источник истины для структуры таблиц
- `docs/architecture/SUPERADMIN.md` — общая архитектура SUPERADMIN
- `docs/ai/DECISIONS_LOG.md` — DECISION-0021 (схема БД), DECISION-0022 (MySQL 5.7+)
- `docs/ai/PROJECT_STATUS.md` — текущий статус проекта

## Принятые решения

- **DECISION-0021**: утверждена схема центральной БД SUPERADMIN (4 таблицы)
- **DECISION-0022**: целевая версия MySQL 5.7+, тип JSON для `settings_json`

## Последнее обновление

2026-06-12 — создан в рамках SUPERADMIN Stage 3.
