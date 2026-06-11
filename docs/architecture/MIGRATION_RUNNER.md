# ERP PLANEX — MIGRATION_RUNNER

## Назначение

`scripts/migrate.php` — минимальный безопасный CLI migration runner для SQL-миграций ERP PLANEX. Применяет SQL-файлы из `database/migrations/` к целевой БД, отслеживает уже применённые миграции через служебную таблицу `schema_migrations`, предотвращает повторное выполнение.

## Статус

**Stage 4a — migration runner создан (2026-06-12).** Runner готов к использованию. Не применялся к рабочей БД.

## Как запускать

```powershell
php scripts/migrate.php
```

Запускать только из CLI. При попытке запуска через веб-сервер скрипт выведет сообщение и завершится с кодом 1.

## Какие файлы применяет

Runner сканирует `database/migrations/` и применяет все `*.sql` файлы в лексикографическом порядке по имени:

1. `001_create_superadmin_companies.sql`
2. `002_create_superadmin_features.sql`
3. `003_create_superadmin_company_features.sql`
4. `004_create_superadmin_users.sql`

Файл `.gitkeep` игнорируется (не `.sql`).

## Как работает `schema_migrations`

### Таблица

```sql
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Поля

| Поле | Тип | Назначение |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Первичный ключ |
| `migration` | VARCHAR(255) UNIQUE | Имя файла миграции |
| `checksum` | VARCHAR(64) | SHA256 хэш содержимого SQL-файла |
| `executed_at` | TIMESTAMP | Дата/время применения миграции |

### Как избежать повторного выполнения

1. Runner читает все записи из `schema_migrations` (имя + checksum).
2. Для каждого SQL-файла проверяет, есть ли его имя в таблице.
3. Если имя найдено — миграция пропускается с `[SKIP]`.
4. Если имя не найдено — вычисляется SHA256 хэш содержимого, выполняется SQL, вставляется запись в `schema_migrations`.

Присутствие поля `checksum` позволяет в будущем реализовать проверку целостности: если содержимое ранее применённого файла изменилось, можно детектировать расхождение и предупредить.

## Вывод CLI

### Первый запуск (все миграции новые)

```
=== ERP PLANEX Migration Runner ===
Found 4 migration(s), 0 already applied, 4 pending.

[OK] 001_create_superadmin_companies.sql
[OK] 002_create_superadmin_features.sql
[OK] 003_create_superadmin_company_features.sql
[OK] 004_create_superadmin_users.sql

Done: 4 applied, 0 skipped, 0 failed.
```

### Повторный запуск (все уже применены)

```
=== ERP PLANEX Migration Runner ===
Found 4 migration(s), 4 already applied, 0 pending.

[SKIP] 001_create_superadmin_companies.sql - already applied
[SKIP] 002_create_superadmin_features.sql - already applied
[SKIP] 003_create_superadmin_company_features.sql - already applied
[SKIP] 004_create_superadmin_users.sql - already applied

Done: 0 applied, 4 skipped, 0 failed.
```

## Обработка ошибок

### БД недоступна

```
Database connection failed. Check your .env configuration.
```

Код выхода: 1. Сообщение не раскрывает host, port, username или пароль.

### SQL-ошибка в файле миграции

```
[FAIL] 003_create_superadmin_company_features.sql - SQLSTATE[42S01]: Base table or view already exists: ...
```

Выполнение прекращается после первой ошибки. Код выхода: 1. Сообщение об ошибке — от PDO (без раскрытия DB credentials).

### Нет SQL-файлов

```
=== ERP PLANEX Migration Runner ===
No migration files found in database/migrations/
```

Код выхода: 0.

## Что запрещено хранить в миграциях

- Пароли (в любом виде — plaintext, хэши, bcrypt)
- Seed-записи с реальными данными (INSERT с email, именами, паролями)
- DB credentials (host, port, username, password)
- Токены, ключи API
- Production-конфигурацию

Миграции — это только структура (DDL): CREATE TABLE, ALTER TABLE, индексы, внешние ключи.

## Как проверять на Windows PowerShell

```powershell
# Синтаксическая проверка
php -l scripts/migrate.php

# Проверка без реальной БД (должен выдать сообщение об ошибке подключения, не fatal error)
php scripts/migrate.php

# Проверка на тестовой БД (только если БД доступна и это НЕ production)
php scripts/migrate.php

# Повторный запуск (должен показать все SKIP)
php scripts/migrate.php
```

## Что НЕ входит в Stage 4a

- Rollback миграций (откат)
- Генерация миграций (create migration tool)
- Web-интерфейс для миграций
- Seed runner
- Авторизация / аутентификация
- Проверка целостности checksum при повторном запуске (структура готова, логика не реализована)
- Транзакционное применение миграций (каждый файл выполняется атомарно через exec(), но без BEGIN/COMMIT обёртки для группы файлов)
- Применение миграций к рабочей/боевой БД

## Технические детали

### Загрузка конфигурации

Runner не подключает `bootstrap/app.php` целиком, чтобы не тянуть лишние зависимости. Вместо этого:

1. Определяет `BASE_PATH` и `STORAGE_PATH`.
2. Подключает `app/Support/helpers.php` (функции `env()`, `base_path()`, `storage_path()`, `e()`).
3. Загружает `.env` напрямую (парсинг ключ=значение, пропуск комментариев `#` и пустых строк).
4. Подключает `config/database.php` (возвращает массив настроек БД).
5. Подключает `app/Core/Database.php` (класс `App\Core\Database`).

### Подключение к БД

Используется существующий класс `App\Core\Database` с lazy-подключением. Настройки PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`, кодировка `utf8mb4`.

### SHA256

Вычисляется через `hash('sha256', $sql)` от полного содержимого SQL-файла. Поле `checksum` — VARCHAR(64) (ровно 64 символа hex-строки).

## Связанные документы

- `docs/architecture/SUPERADMIN_DATABASE.md` — спецификация таблиц центральной БД
- `docs/architecture/SUPERADMIN_MIGRATIONS.md` — описание созданных миграций
- `docs/ai/DECISIONS_LOG.md` — DECISION-0021 (схема БД), DECISION-0022 (MySQL 5.7+)
- `docs/ai/PROJECT_STATUS.md` — текущий статус проекта
- `app/Core/Database.php` — PDO-обёртка
- `config/database.php` — конфигурация подключения к БД
- `database/migrations/` — SQL-миграции

## Принятые решения

- **DECISION-0021**: утверждена схема центральной БД SUPERADMIN (4 таблицы)
- **DECISION-0022**: целевая версия MySQL 5.7+, тип JSON для `settings_json`

## Последнее обновление

2026-06-12 — создан в рамках SUPERADMIN Stage 4a: migration runner.
