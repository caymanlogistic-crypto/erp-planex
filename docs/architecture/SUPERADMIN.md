# ERP PLANEX — SUPERADMIN

## Назначение

SUPERADMIN — отдельная центральная панель с максимальными правами. Не является пользователем локальной ERP. Предназначена для управления всей экосистемой ERP PLANEX: компаниями, локальными ERP-системами, пользователями SUPERADMIN, feature toggles, общими настройками.

## Отличие SUPERADMIN от локальной ERP

| Критерий | SUPERADMIN | Локальная ERP |
|---|---|---|
| Права | Максимальные, системный уровень | В рамках одной компании |
| Пользователи | `superadmin_users` (отдельная таблица) | `users` (локальная БД компании) |
| База данных | Центральная БД SUPERADMIN | Локальная БД компании |
| Видимость данных | Все компании и их настройки | Только данные своей компании |
| Управление | Управление структурой, компаниями, features | Управление перевозками, клиентами, подрядчиками |
| Доступ | Отдельная панель | Вход через свою ERP |

## Путь центральной панели

```
erp/superadmin/
```

На уровне файловой системы центральная панель находится в отдельной папке. На уровне кода — общий код с выделенным модулем SUPERADMIN.

## Этапы SUPERADMIN

### Stage 1 (текущий) — Минимальный каркас

Создание архитектурного и технического каркаса без бизнес-логики:

- Архитектурный документ `SUPERADMIN.md`
- UI-шаблон страницы `docs/ui/pages/superadmin-dashboard.md`
- Техническая страница-заглушка SUPERADMIN dashboard
- Маршрут `/superadmin`
- Интеграция с существующим layout/UI-фундаментом
- Базовая структура папок под будущие модули SUPERADMIN

### Stage 2 — Центральная БД

- ~~Документация центральной БД SUPERADMIN~~ **DONE (2026-06-12).** См. `docs/architecture/SUPERADMIN_DATABASE.md`.
- Создание таблиц: `companies`, `features`, `company_features`, `superadmin_users`
- Подключение SUPERADMIN к центральной БД
- Миграции

### Stage 3 — Управление компаниями (будущий)

- CRUD компаний
- Создание/настройка локальных ERP
- Управление папками и БД локальных ERP

### Stage 4 — Управление пользователями SUPERADMIN (будущий)

- CRUD пользователей SUPERADMIN
- Авторизация SUPERADMIN
- Роли внутри SUPERADMIN

### Stage 5 — Feature toggles (будущий)

- Управление доступностью функций по компаниям
- Интерфейс управления features
- Кэширование в локальных ERP

### Stage 6 — Мониторинг и отчёты (будущий)

- Обзор состояния систем
- Статистика по компаниям
- Системные отчёты

## Stage 1: что входит

| Элемент | Статус |
|---|---|
| Архитектурный документ `docs/architecture/SUPERADMIN.md` | Эта задача |
| UI-шаблон `docs/ui/pages/superadmin-dashboard.md` | Эта задача |
| Техническая страница `/superadmin` | Эта задача |
| Структура папок `app/Superadmin/` | Эта задача |
| Интеграция с существующим Router | Эта задача |
| Интеграция с существующим layout | Эта задача |
| Информационные карточки-заглушки | Эта задача |
| Навигационные пункты (disabled) | Эта задача |
| Empty states | Эта задача |

## Stage 1: что НЕ входит

| Элемент | Причина |
|---|---|
| Центральная БД | Stage 2 |
| Таблицы (companies, features, superadmin_users) | Stage 2 |
| Миграции | Stage 2 |
| Авторизация / login / logout | Stage 4 |
| Пользователи SUPERADMIN (CRUD) | Stage 4 |
| Управление компаниями (CRUD) | Stage 3 |
| Feature toggles (код и UI) | Stage 5 |
| Бизнес-модули | Отдельные задачи |
| Реальные DB credentials в коде/MD | Запрещено |
| Composer / сторонние библиотеки | Не утверждены |

## Базовая структура файлов SUPERADMIN

```
app/
  Superadmin/                    # Модуль SUPERADMIN
    .gitkeep
  View/
    pages/
      superadmin_dashboard.php   # Страница-заглушка dashboard

docs/
  architecture/
    SUPERADMIN.md                # Этот документ
  ui/
    pages/
      superadmin-dashboard.md    # UI-шаблон страницы
```

## Маршрут SUPERADMIN

Для Stage 1 выбран простой маршрут:

```
/superadmin
```

Совместим с текущим посегментным Router (`app/Http/Router.php`). Зарегистрирован в `public/index.php` наравне с существующими маршрутами `/`, `/test`, `/test-db`.

Файловая папка `erp/superadmin/` зарезервирована под будущее развёртывание центральной панели как отдельной точки входа, но на Stage 1 код SUPERADMIN находится в общем `app/` пространстве.

## Ограничения безопасности (Stage 1)

- Нет авторизации — страница открыта локально для разработки
- Нет сессий
- Нет проверки прав
- Нет доступа к реальным данным
- Только статическая заглушка
- В production НЕ разворачивать без авторизации

## Связь с центральной БД

На Stage 1 БД не создаётся и не подключается. Класс `Database` (PDO-обёртка) уже существует в `app/Core/Database.php` и будет использован на Stage 2.

**Stage 2 (документация):** точная архитектурная спецификация центральной БД SUPERADMIN создана в `docs/architecture/SUPERADMIN_DATABASE.md`. Описаны 4 таблицы:
- `companies` — зарегистрированные компании/локальные ERP
- `features` — реестр доступных функций (module/page/report/custom_report/action/integration/ui_block)
- `company_features` — включение/отключение features по компаниям (default-deny)
- `superadmin_users` — пользователи SUPERADMIN (отдельные от локальных users)

Закреплён безопасный подход к DB credentials: пароли не хранятся в БД, только логический `db_identifier`.

## Пользователи SUPERADMIN

Пользователи SUPERADMIN (`superadmin_users`) — отдельные от пользователей локальных ERP (`users`). Разные таблицы, разные БД, разные сессии.

## Связь с архитектурными решениями

- **DECISION-0005** — SUPERADMIN: центральная панель `erp/superadmin/`
- **DECISION-0013** — Feature toggles управляются из SUPERADMIN
- **DECISION-0019** — SUPERADMIN Stage 1 scope, маршрут `/superadmin`, структура папок

## Принятые решения

- **DECISION-0019**: SUPERADMIN Stage 1 — минимальный каркас. Маршрут: `/superadmin`. Структура: `app/Superadmin/`. Без БД, миграций, auth, CRUD, feature toggles. Зафиксировано в `DECISIONS_LOG.md`.
- **DECISION-0021**: SUPERADMIN Stage 2 — точная схема центральной БД (4 таблицы). Определены поля, типы, индексы, FK, статусные модели, reserved-поля. Закреплён безопасный подход к DB credentials: пароли не хранятся в БД. Feature toggles: default-deny модель. Конвенция кодов feature: `type.name`. См. `docs/architecture/SUPERADMIN_DATABASE.md`.
