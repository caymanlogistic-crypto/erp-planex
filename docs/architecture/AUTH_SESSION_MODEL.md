# ERP PLANEX — AUTH_SESSION_MODEL

## Назначение

Точная архитектурная спецификация авторизации, сессий, маршрутных guards и контекста компании для MVP-этапа.

## Статус документа

**Stage: архитектурная спецификация (DECISION-0041).** Реализация — следующая фаза.

## Роли и источники пользователей

### SUPERADMIN

| Параметр | Значение |
|----------|----------|
| Таблица | `superadmin_users` (центральная БД) |
| Поле логина | `email` |
| Поле пароля | `password_hash` (bcrypt) |
| Роль в сессии | `superadmin` |
| company_id | `null` |
| Статус | `is_active = 1` |

**Авто-создание первого SUPERADMIN (development-mode):**
При первом GET `/login` проверяется: если таблица `superadmin_users` пуста (SELECT COUNT(*) = 0), автоматически создаётся пользователь:
- `email`: `admin@planex.local`
- `name`: `Super Admin`
- `password`: генерируется `generatePassword(10)`, хэшируется bcrypt
- `role`: `admin`
- Временный пароль показывается **один раз** на странице логина в notice-блоке (только если таблица была пуста до этого запроса).

### Руководитель (Company Owner)

| Параметр | Значение |
|----------|----------|
| Таблица | `company_users` (центральная БД) |
| Поле логина | `login` |
| Поле пароля | `password_hash` (bcrypt) |
| Роль в сессии | `company_owner` |
| company_id | `company_users.company_id` |
| Статус | `status = 'active'` |

### Логист

| Параметр | Значение |
|----------|----------|
| Таблица | `users` (локальная БД `erp_company_{id}`) |
| Поле логина | `login` |
| Поле пароля | `password_hash` (bcrypt) |
| Роль в сессии | `logist` |
| company_id | ID компании, в чьей локальной БД найден логист |
| Статус | `status = 'active'` И `role_code = 'logist'` |

---

## Порядок аутентификации (POST /login)

```text
1. Получить login из формы
2. Проверить superadmin_users WHERE email = :login AND is_active = 1
   → найден + password_verify() OK → сессия: role_code=superadmin, company_id=null
   
3. Проверить company_users WHERE login = :login AND status = 'active'
   → найден + password_verify() OK → сессия: role_code=company_owner, company_id=<company_id>

4. Поиск логиста по всем активным компаниям:
   a. SELECT id, db_identifier FROM companies WHERE status = 'active'
   b. Для каждой компании подключиться к её локальной БД
   c. SELECT * FROM users WHERE login = :login AND role_code = 'logist' AND status = 'active'
   d. Если найдено 0 записей → продолжить к следующей компании
   e. Если найдено 1 → сохранить кандидата, company_id
   f. Если найдено >1 в одной компании → пропустить (не должно быть по UNIQUE uk_login)
   
5. Результат поиска логиста:
   - 0 совпадений → ошибка «Неверный логин или пароль»
   - 1 совпадение → password_verify() → OK: сессия role_code=logist, company_id=<id>
   - >1 совпадений (в разных компаниях) → ошибка «Логин найден в нескольких компаниях, обратитесь к администратору»

6. Если ни один источник не вернул пользователя → ошибка «Неверный логин или пароль»
```

---

## Структура сессии

```php
$_SESSION['user_id']    // INT — ID пользователя в его таблице
$_SESSION['role_code']  // 'superadmin' | 'company_owner' | 'logist'
$_SESSION['company_id'] // INT|null — ID компании (null для SUPERADMIN)
$_SESSION['user_name']  // STRING — имя пользователя для отображения
```

---

## Безопасность сессии

| Правило | Реализация |
|---------|------------|
| `session_start()` | Вызывается в самом начале `index.php` до роутинга |
| `session_regenerate_id(true)` | Вызывается сразу после успешного `password_verify()` |
| Logout | `session_destroy()` + `session_start()` заново + редирект на `/login` |
| Cookie | `session.cookie_httponly = 1`, `session.cookie_secure = 0` (development) |
| Lifetime | Стандартный PHP session lifetime |

---

## Маршрутные guards

### Публичные маршруты (без сессии)

| Маршрут | Доступ |
|---------|--------|
| `GET /login` | Все |
| `POST /login` | Все |
| `GET /logout` | Все |
| `GET /` | Все (UI demo, временно) |
| `GET /test` | Все |
| `GET /test-db` | Все |

### SUPERADMIN маршруты

| Префикс | Требование |
|---------|------------|
| `/superadmin/*` | `role_code = 'superadmin'` |

При отсутствии сессии или неверной роли → редирект 302 на `/login`.

### Company маршруты

| Префикс | Требование |
|---------|------------|
| `/company/*` | `role_code IN ('company_owner', 'logist')` |

При отсутствии сессии или неверной роли → редирект 302 на `/login`.

### Специальные права

| Маршрут | Требование |
|---------|------------|
| `/company/logists` | `role_code = 'company_owner'` |
| `/company/logists/create` | `role_code = 'company_owner'` |

Логист при попытке доступа к этим маршрутам → 403 Forbidden с сообщением «Доступ запрещён».

---

## Контекст компании из сессии

Все `/company/*` маршруты должны читать `company_id` из `$_SESSION['company_id']`, а не из `$_GET['company_id']`.

### Переходный период для QA

Для отладки разрешается параметр `?company_id=N` только если:
- `$_SESSION['role_code'] === 'superadmin'` (SUPERADMIN вправе просматривать любую компанию)

В обычном режиме (роль `company_owner` или `logist`) параметр `?company_id=N` игнорируется — компания всегда из сессии.

---

## Изменения в main.php (sidebar/topbar)

### Topbar (правая часть)

До авторизации (на `/login`):
```html
<div class="topbar-right">
    <span class="text-muted">ERP PLANEX</span>
</div>
```

После авторизации:
```html
<div class="topbar-right">
    <div class="avatar"><?= initials($userName) ?></div>
    <div class="user-info">
        <strong><?= e($userName) ?></strong>
        <span><?= e($roleLabel) ?></span>
    </div>
    <a href="/logout" class="btn btn-ghost">Выйти</a>
</div>
```

Где `$roleLabel`:
- `superadmin` → «Суперадминистратор»
- `company_owner` → «Руководитель»
- `logist` → «Логист»

### Sidebar (динамическая навигация)

**SUPERADMIN:**
```text
ОПЕРАЦИИ
  Рейсы (disabled)
  Водители (disabled)
  Транспорт (disabled)
  Клиенты (disabled)

СИСТЕМА
  SUPERADMIN (active)
  Настройки (disabled)
```

**Руководитель (company_owner):**
```text
ОПЕРАЦИИ
  Рейсы (disabled)
  Водители → /company/drivers
  Транспорт → /company/vehicles
  Клиенты → /company/clients
  Подрядчики → /company/contractors
  Экипажи → /company/crews

СИСТЕМА
  Логисты → /company/logists
  Настройки (disabled)
```

**Логист:**
```text
ОПЕРАЦИИ
  Рейсы (disabled)
  Водители → /company/drivers
  Транспорт → /company/vehicles
  Клиенты → /company/clients
  Подрядчики → /company/contractors
  Экипажи → /company/crews

СИСТЕМА
  Настройки (disabled)
```

(Логист не видит пункт «Логисты»)

---

## Страница Company Dashboard

Минимальная страница-заглушка `/company/dashboard`:

```text
Page title: «Компания: {company_name}»
Page context: «Панель управления»

Блоки:
1. notice: «Система в разработке. Доступные разделы:»
2. Ссылки-карточки (или простой список ссылок):
   - Логисты (только для Руководителя)
   - Клиенты
   - Подрядчики
   - Водители
   - Транспорт
   - Экипажи
```

После успешного входа:
- SUPERADMIN → редирект на `/superadmin/companies`
- Руководитель → редирект на `/company/dashboard`
- Логист → редирект на `/company/dashboard`

---

## Login page layout

Страница `/login` использует **отдельный минимальный layout** без sidebar:

```text
auth-layout.php:
- Тем же app-shell
- Topbar: только brand (ERP PLANEX), без навигации, без user block
- Нет sidebar
- Центрированная форма логина
```

---

## Изменения в public/index.php (структура)

До роутинга:
```php
session_start();

// Auth helper functions
function isAuthenticated(): bool { ... }
function requireRole(string|array $roles): void { ... }
function getSessionCompanyId(): ?int { ... }

// Auto-seed SUPERADMIN (development-mode) — на /login
```

После роутинга:
```php
// Route guards перед каждым защищённым маршрутом
```

---

## Что НЕ реализуется сейчас

- Восстановление пароля
- 2FA
- Регистрация пользователей
- Remember me
- Сложный RBAC UI
- Email-отправка/верификация
- CAPTCHA
- Rate limiting попыток входа
- Блокировка после N неудачных попыток
- Audit log входов/выходов

---

## Связанные документы

- `docs/architecture/PERMISSIONS_MODEL.md` — модель ролей
- `docs/architecture/SUPERADMIN_DATABASE.md` — центральная БД
- `docs/ai/DECISIONS_LOG.md` — DECISION-0033 (Руководитель), DECISION-0034 (Логист)
- `docs/ai/DECISIONS_LOG.md` — DECISION-0041 (настоящее решение)

---

## Принятые решения

- **DECISION-0041**: Утверждена модель авторизации и сессий (настоящий документ).

---

## Последнее обновление

2026-06-13 — создан для Auth and Sessions Block.
