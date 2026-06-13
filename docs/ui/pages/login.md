# UI PAGE TEMPLATE — Login Page (`/login`)

Этот шаблон является источником истины для `erp-coder` и `erp-qa-tester`. Дизайнер (`erp-uiux-designer`) обязан заполнить все применимые секции до передачи задачи кодеру. Кодер реализует страницу строго по этому шаблону.

## Архитектурный источник

- **Route**: `GET /login`, `POST /login`
- **Модель**: `docs/architecture/AUTH_SESSION_MODEL.md` — DECISION-0041
- **Layout**: отдельный `auth-layout.php` (НЕ `main.php`)
- **Пользователи**: все роли (SUPERADMIN, Руководитель, Логист) — без sidebar

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING (ОБЯЗАТЕЛЬНО)

### 0a. Auth-Layout Shell Foundation

Страница логина использует **ОТДЕЛЬНЫЙ минимальный layout** `app/View/layouts/auth-layout.php`, а не основной `main.php`.

| # | Параметр | MASTER spec | Текущая реализация | Соответствие |
|---|----------|-------------|-------------------|--------------|
| 1 | App shell | `display: grid; grid-template-columns: 1fr; grid-template-rows: var(--topbar-h) 1fr; min-height: 100vh` | Новый файл `auth-layout.php` | TO_CREATE |
| 2 | Topbar position | `grid-column: 1 / -1; height: var(--topbar-h)` | Только brand, без навигации, без user block | TO_CREATE |
| 3 | Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | СВЕТЛЫЙ | TO_CREATE |
| 4 | Topbar border-bottom | `1px solid var(--line)` | — | TO_CREATE |
| 5 | Topbar brand zone | `.topbar-brand`: тёмный фон `var(--nav-bg)`, logo-mark 18×18, brand-name "ERP PLANEX" 13px/700 | Выровнен слева, без crumbs/user | TO_CREATE |
| 6 | Sidebar | **ОТСУТСТВУЕТ** | Нет sidebar | YES |
| 7 | Content area | Центрированная форма логина (вертикально и горизонтально) | `.auth-content { display: flex; align-items: center; justify-content: center; min-height: 100%; }` | TO_CREATE |
| 8 | Login card | `background: var(--surface-strong); border: 1px solid var(--line); border-radius: 2px; width: 380px;` | Центральная карточка | TO_CREATE |
| 9 | User block (topbar right) | **ОТСУТСТВУЕТ** | Нет | YES |
| 10 | Environment badge | **ОТСУТСТВУЕТ** | Нет | YES |

### 0b. SIDEBAR INFORMATION ARCHITECTURE

**Sidebar отсутствует на странице логина.** Пользователь не авторизован — навигация не нужна.

```
[NO SIDEBAR]
```

---

## 1. Страница

- **Route / view**: `GET /login` → `auth-layout.php` + `login_form.php`; `POST /login` → обработчик в `index.php`
- **Тип страницы**: standalone auth form (НЕ соответствует ни одному стандартному COMPOSITE PATTERN — это специальный минимальный layout)
- **Пользователь (роль)**: Все (неавторизованные)
- **Главная задача пользователя**: Войти в ERP PLANEX
- **Что нельзя менять в бизнес-логике**: Порядок аутентификации (AUTH_SESSION_MODEL.md §Порядок аутентификации), структуру сессии, маршрутные guards

---

## 2. Layout

### auth-layout.php (новый файл)

```
app/View/layouts/auth-layout.php
```

**Отличия от main.php:**
- Нет sidebar
- Topbar: только brand-зона (тёмная), без `.topbar-crumbs`, без `.topbar-right`
- Content: центрирован по вертикали и горизонтали
- Нет `grid-template-columns: var(--sidebar-w) 1fr` — только одна колонка

**CSS-спецификация auth-layout:**

```css
.auth-shell {
  display: grid;
  grid-template-columns: 1fr;
  grid-template-rows: var(--topbar-h) 1fr;
  min-height: 100vh;
  min-width: 0;
  background: var(--app-bg);
}

.auth-topbar {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  height: var(--topbar-h);
  background: var(--surface-strong);
  border-bottom: 1px solid var(--line);
  z-index: 100;
}

.auth-topbar-brand {
  height: 100%;
  display: flex;
  align-items: center;
  padding: 0 13px;
  background: var(--nav-bg);
  gap: 9px;
  width: 224px;
  flex-shrink: 0;
}

.auth-content {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
```

**HTML-структура auth-layout.php:**

```html
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — ERP PLANEX</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="auth-shell">
        <header class="auth-topbar">
            <div class="auth-topbar-brand">
                <div class="logo-mark"></div>
                <span class="brand-name">ERP PLANEX</span>
            </div>
        </header>

        <div class="auth-content">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
```

**Переиспользуемые классы из `app.css`:**
- `.logo-mark` — 18×18 градиентный маркер (уже есть в `app.css`)
- `.brand-name` — 13px/700 название (уже есть в `app.css`)

---

## 3. Page head

**На странице логина нет page-head.** Вместо него — заголовок внутри карточки логина (см. §8).

---

## 4. Login Card / Form

### Login card container

```html
<div class="login-card">
    <div class="login-card-head">
        <h1>Вход в систему</h1>
    </div>
    <div class="login-card-body">
        <!-- форма логина -->
    </div>
</div>
```

**CSS-спецификация login card:**

```css
.login-card {
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 2px;
  width: 380px;
  max-width: 100%;
}

.login-card-head {
  min-height: 44px;
  display: flex;
  align-items: center;
  padding: 10px 14px;
  background: var(--surface-muted);
  border-bottom: 1px solid var(--line-hair);
}

.login-card-head h1 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--text-main);
}

.login-card-body {
  padding: 14px;
}
```

### Login form

```html
<form method="post" action="/login" class="login-form" novalidate>
    <div class="field">
        <label class="field-label" for="login">Логин</label>
        <input type="text" id="login" name="login"
               class="field-input"
               value="<?= e($loginValue ?? '') ?>"
               autocomplete="username"
               required>
    </div>

    <div class="field">
        <label class="field-label" for="password">Пароль</label>
        <input type="password" id="password" name="password"
               class="field-input"
               autocomplete="current-password"
               required>
    </div>

    <div class="login-actions">
        <button type="submit" class="btn btn-primary" style="width:100%">Войти</button>
    </div>
</form>
```

**CSS-спецификация login form:**

```css
.login-form .field {
  margin-bottom: 12px;
}

.login-form .field:last-of-type {
  margin-bottom: 8px;
}

.login-actions {
  margin-top: 14px;
}
```

**Поля формы:**

| Поле | Component class | Required | Validation | Help/error text |
|------|----------------|----------|------------|-----------------|
| Логин | `.field-input` | Да | Не может быть пустым | «Введите логин» |
| Пароль | `.field-input` (type=password) | Да | Не может быть пустым | «Введите пароль» |

---

## 5. Состояния

### 5.1. Обычное состояние (пустая форма)

GET `/login` — форма без ошибок, оба поля пусты.

### 5.2. Ошибка валидации (пустые поля)

POST `/login` с пустым логином или паролем.

```html
<div class="notice danger" style="margin-bottom:12px">Заполните все поля.</div>
```

Поля с ошибкой получают класс `.is-error`:

```html
<div class="field is-error">
    <label class="field-label" for="login">Логин</label>
    <input type="text" id="login" name="login" class="field-input" value="" required>
    <div class="field-msg">Введите логин</div>
</div>
```

### 5.3. Ошибка аутентификации

POST `/login` — неверный логин или пароль (шаги 2-6 AUTH_SESSION_MODEL).

```html
<div class="notice danger" style="margin-bottom:12px">Неверный логин или пароль.</div>
```

### 5.4. Ошибка множественного логиста

POST `/login` — логин найден в нескольких компаниях (шаг 5 >1 совпадений).

```html
<div class="notice danger" style="margin-bottom:12px">Логин найден в нескольких компаниях, обратитесь к администратору.</div>
```

### 5.5. Development-mode авто-создание SUPERADMIN

Только при первом GET `/login`, если таблица `superadmin_users` была пуста до этого запроса.

```html
<div class="notice success" style="margin-bottom:12px">
    <b>Создан аккаунт суперадминистратора</b><br>
    Логин: <code>admin@planex.local</code><br>
    Пароль: <code><?= e($tempPassword) ?></code><br>
    <span class="text-muted">Сохраните пароль. Он будет показан только один раз.</span>
</div>
```

После успешного создания пароль НЕ показывается повторно при последующих GET-запросах.

---

## 6. После успешного входа

POST `/login` — `password_verify()` OK:

- SUPERADMIN → редирект 302 на `/superadmin/companies`
- Руководитель → редирект 302 на `/company/dashboard`
- Логист → редирект 302 на `/company/dashboard`

---

## 7. CSS/classes coder must use

### Layout classes (новые, в `app.css`)
- `.auth-shell` — минимальный shell без sidebar
- `.auth-topbar` — topbar только с brand
- `.auth-topbar-brand` — brand-зона (тёмная, 224px)
- `.auth-content` — центрирующий контейнер
- `.login-card` — карточка логина (380px)
- `.login-card-head` — заголовок карточки
- `.login-card-body` — тело карточки
- `.login-form` — форма внутри карточки
- `.login-actions` — контейнер кнопки

### Переиспользуемые классы (уже в `app.css`)
- `.logo-mark` — логотип
- `.brand-name` — название
- `.field`, `.field-label`, `.field-input`, `.field-msg`, `.is-error` — поля формы
- `.btn`, `.btn-primary` — кнопка
- `.notice`, `.notice.danger`, `.notice.success` — сообщения
- `.text-muted` — muted текст
- `code` — моноширинный текст

---

## 8. CORE modules used

- Core UI kit path: `docs/ui/ERP_UI_KIT_CORE.html`
- Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html` (reference/history only)
- COMPOSITE pattern selected: **NONE** — страница логина является специальным минимальным layout (auth-layout), не соответствующим ни одному стандартному PATTERN. Это осознанное архитектурное решение: неавторизованный пользователь не должен видеть sidebar, навигацию и user block.

### CORE modules used

| ID | Название | Причина выбора |
|----|----------|----------------|
| `CORE-01` | App shell | Базовый каркас, но в модифицированном виде: без sidebar, без навигации |
| `CORE-03` | Topbar | Верхняя полоса 38px, но только с brand-зоной, без crumbs и user block |
| `CORE-08` | Panel | Карточка логина использует panel-подобную структуру (`login-card`) с head/body |
| `CORE-17` | Primary button | Единственная кнопка «Войти» |
| `CORE-26` | Form field | Поля «Логин» и «Пароль» |
| `CORE-28` | Validation/error state | Ошибки валидации пустых полей |
| `CORE-32` | Notice / system message | Сообщения об ошибках аутентификации, dev-mode notice |
| `CORE-33` | Warning / blocking notice | Ошибка аутентификации, ошибка множественного логиста |

### Missing modules
- `none` — все необходимые модули присутствуют в Core Kit

---

## 9. MODULE USAGE DECISIONS

- **Main page purpose**: Аутентификация пользователя в ERP PLANEX
- **Primary work object**: Форма логина (login + пароль)
- **Main layout selected**: `auth-layout.php` (специальный минимальный layout)
- **Composite pattern selected**: NONE — осознанно не используется стандартный pattern (нет sidebar, нет навигации)
- **Primary action**: «Войти» (`.btn-primary`, полная ширина)
- **Secondary actions**: Нет
- **Table required**: NO — страница не содержит данных
- **Form required**: YES — форма логина из 2 полей
- **Inspector required**: NO
- **Filters required**: NO
- **Modal required**: NO
- **Empty/error/loading states required**: error states (валидация + аутентификация + множественный логист), dev-mode notice
- **Modules used with reasons**:
  - CORE-01 (modified): shell без sidebar для неавторизованного пользователя
  - CORE-03 (modified): topbar только с brand, без навигации
  - CORE-08: карточка логина как центральный визуальный контейнер
  - CORE-17: единственное действие — вход
  - CORE-26: стандартные поля формы
  - CORE-28: валидация полей
  - CORE-32, CORE-33: информационные и error-сообщения
- **Modules explicitly not used with reasons**:
  - CORE-02 (Sidebar navigation): не нужен неавторизованному пользователю
  - CORE-04 (Breadcrumbs): нет навигационного контекста
  - CORE-05 (Page header): заменён на заголовок внутри карточки
  - CORE-13 (Data table): нет данных
  - CORE-06, 10, 11, 12, 14, 15, 16: не нужны для формы логина
- **Private applied example names explicitly not used**: Нет

---

## 10. SOURCE MAPPING (MANDATORY)

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|-----------------|------------------------|
| 1 | Auth shell | CORE-01 (modified) | Production CSS Reference — модификация `.app-shell` | `.auth-shell` | `.app-shell` (уже занят), Bootstrap `.container` |
| 2 | Auth topbar | CORE-03 (modified) | Production CSS Reference > `.topbar` — упрощён до brand-only | `.auth-topbar`, `.auth-topbar-brand` | `.topbar` (содержит crumbs/user) |
| 3 | Topbar brand (logo + name) | CORE-03 sub | Production CSS Reference > `.topbar-brand`, `.logo-mark`, `.brand-name` | `.logo-mark`, `.brand-name` | Bootstrap `.navbar-brand` |
| 4 | Auth content area | — | Custom centering wrapper | `.auth-content` | `.content` (имеет другие padding) |
| 5 | Login card | CORE-08 | Production CSS Reference > `.panel` (адаптирован как `.login-card`) | `.login-card`, `.login-card-head`, `.login-card-body` | `.panel` (имеет другие контекстные отступы), SaaS card |
| 6 | Login card heading | CORE-08 sub | Production CSS Reference > `.panel-head-title` / `h2` — адаптирован как `h1` 16px/700 | `h1` (16px, 700) | `.panel-head-title` (для panel), demo/placeholder title |
| 7 | Login form | CORE-26, CORE-27 | Production CSS Reference > `.field`, `.field-label`, `.field-input` | `.login-form`, `.field`, `.field-label`, `.field-input` | Browser-default inputs, Bootstrap `.form-group` |
| 8 | Field error state | CORE-28 | Production CSS Reference > `.is-error`, `.field-msg` | `.is-error`, `.field-msg` | Red border only, raw PHP errors |
| 9 | Primary button | CORE-17 | Button Decision Matrix > Primary | `.btn`, `.btn-primary` | `.btn-secondary` as login action |
| 10 | Validation error notice | CORE-33 | Production CSS Reference > `.notice.warn` | `.notice.danger` | Blue info alert, `.notice` (neutral) |
| 11 | Auth error notice | CORE-33 | Production CSS Reference > `.notice.warn` (адаптирован danger) | `.notice.danger` | Raw error text, alert() |
| 12 | Multi-logist error notice | CORE-33 | Production CSS Reference > `.notice.warn` (адаптирован danger) | `.notice.danger` | Generic "Error" |
| 13 | Dev-mode seed notice | CORE-32 | Production CSS Reference > `.notice` | `.notice.success` | Debug banner, alert() |
| 14 | Temporary password | — | Core Kit `code` element | `code` | Plain text without visual distinction |

---

## 11. CSS COMPATIBILITY CHECK (MANDATORY)

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------|--------------|-----------------|
| `.auth-shell` | `.auth-topbar` | 0 | Yes — topbar flush to top | — | — | — |
| `.auth-shell` | `.auth-content` | 0 | No | `.auth-content` (24px) | — | flex centering |
| `.login-card` | `.login-card-head` | 0 (by rule) | Yes — head flush to top | `.login-card-body` (14px) | `--line-hair` | `h1` 16px/700 |
| `.login-card` | `.login-card-body` | 0 | — | `.login-card-body` (14px) | — | `.field`, `.notice` inside |
| `.field` | `.field-label` | — | — | — | — | 10.5px, 700, `--text-muted` |
| `.field` | `.field-input` | — | — | — | `--line-soft` | `:focus` outline |
| `.is-error` | `.field-input` | — | — | — | `#ca8880` | `background: #fff8f7` |
| `.is-error` | `.field-msg` | — | — | — | — | `color: var(--danger)` |
| `.login-actions` | `.btn-primary` | — | — | — | `--accent-deep` | `:hover` accent-hover |

---

## 12. Изменения в public/index.php

### Auto-seed SUPERADMIN (development-mode)

При GET `/login`:

```php
// Auto-create first SUPERADMIN if table is empty (development-mode only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/login') {
    $count = $pdo->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
    if ((int)$count === 0) {
        $tempPassword = generatePassword(10);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO superadmin_users (email, password_hash, role, status, created_at, updated_at)
             VALUES (:email, :hash, 'admin', 'active', NOW(), NOW())"
        );
        $stmt->execute(['email' => 'admin@planex.local', 'hash' => $hash]);
        // $tempPassword передаётся во view для ОДНОКРАТНОГО показа
    }
}
```

### POST /login handler

Обработчик реализует порядок аутентификации из `AUTH_SESSION_MODEL.md` §Порядок аутентификации:
1. Проверить `superadmin_users`
2. Проверить `company_users`
3. Поиск логиста по локальным БД компаний

### Route guards

До маршрутов `/superadmin/*` и `/company/*`:

```php
// requireRole() helper — редирект 302 на /login при отсутствии сессии/неверной роли
```

---

## 13. Strict prohibitions for coder

- Не использовать `main.php` для страницы логина — только `auth-layout.php`
- Не добавлять sidebar на страницу логина
- Не добавлять навигацию, breadcrumbs или user block в topbar страницы логина
- Не придумывать новые CSS-классы без указания в этом handoff
- Не добавлять «Запомнить меня», «Забыли пароль», регистрацию, CAPTCHA
- Не показывать временный пароль SUPERADMIN более одного раза
- Не хранить открытый пароль в БД
- Не менять порядок аутентификации из AUTH_SESSION_MODEL.md
- Не использовать Bootstrap/Tailwind/React/Vue
- Не использовать demo-placeholder UI: псевдоиконки, emoji, SaaS-карточки
- Не добавлять inline styles, кроме динамических PHP-значений
- Не менять backend/auth/CRUD/migrations без отдельного задания
- `border-radius` не больше 4px (новые элементы)
- `box-shadow` blur не больше 8px (новые элементы)

---

## 14. Coder implementation checklist

- [ ] Создать `app/View/layouts/auth-layout.php`
- [ ] Создать `app/View/pages/login_form.php`
- [ ] Добавить CSS-классы `.auth-shell`, `.auth-topbar`, `.auth-topbar-brand`, `.auth-content`, `.login-card*`, `.login-form`, `.login-actions` в `app.css`
- [ ] Реализовать GET `/login` — отображение формы
- [ ] Реализовать POST `/login` — аутентификация по AUTH_SESSION_MODEL.md
- [ ] Реализовать auto-seed SUPERADMIN при первом GET `/login`
- [ ] Реализовать `session_start()` + `session_regenerate_id(true)` после успешного входа
- [ ] Реализовать редиректы после входа: SUPERADMIN → `/superadmin/companies`, остальные → `/company/dashboard`
- [ ] Реализовать route guards для `/superadmin/*` и `/company/*`
- [ ] Реализовать GET `/logout` — `session_destroy()` + редирект на `/login`
- [ ] Все состояния: empty, validation error, auth error, multi-logist error, dev-mode notice
- [ ] Все поля формы используют `.field-input`, `.field-label`, `.field-msg`
- [ ] Ошибки валидации используют `.is-error`, `.field-msg`
- [ ] Сообщения используют `.notice.danger`, `.notice.success`
- [ ] Кнопка «Войти» — `.btn.btn-primary`, полная ширина
- [ ] Временный пароль показывается ОДИН раз
- [ ] `php -l` для всех изменённых PHP-файлов — без ошибок
- [ ] Нет raw PHP/SQL ошибок в UI
- [ ] Нет demo-placeholder UI

---

## 15. QA formal checklist

- [ ] Auth-layout.php создан и используется только для `/login`
- [ ] Auth-layout не содержит sidebar, навигации, user block
- [ ] Auth-layout topbar содержит только brand (логотип + «ERP PLANEX»)
- [ ] Форма логина центрирована вертикально и горизонтально
- [ ] Карточка логина 380px, `var(--surface-strong)`, `border: 1px solid var(--line)`
- [ ] Поля: «Логин» + «Пароль» + кнопка «Войти»
- [ ] Кнопка «Войти» — `.btn-primary`, полная ширина
- [ ] Все состояния работают: empty, validation error, auth error, multi-logist error
- [ ] Dev-mode SUPERADMIN notice работает (только при первом запросе)
- [ ] Временный пароль не показывается повторно
- [ ] `session_regenerate_id(true)` после успешного входа
- [ ] Редиректы после входа корректны для всех трёх ролей
- [ ] Route guards работают: `/superadmin/*` → только superadmin, `/company/*` → только company_owner/logist
- [ ] `/logout` уничтожает сессию и редиректит на `/login`
- [ ] Все CSS-классы из handoff на месте
- [ ] Нет forbidden texts/classes/patterns
- [ ] `php -l` для всех PHP-файлов — без ошибок
- [ ] Formal UI QA: PASS/FAIL

---

## 16. Architect pre-owner review checklist

- [ ] Страница логина выглядит как минимальная рабочая форма входа
- [ ] Нет sidebar, навигации, лишних элементов
- [ ] Topbar минимальный (только brand)
- [ ] Форма центрирована, не выглядит как demo/SaaS-landing
- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Не нужен повторный designer/coder/QA цикл

---

## 17. Owner visual checklist

- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`
- [ ] Нет emoji как иконок
- [ ] Нет demo-placeholder/SaaS-dashboard вида
- [ ] Нет больших пустот
- [ ] Topbar 38px, только brand (тёмная полоса 224px слева)
- [ ] Форма логина центрирована
- [ ] Карточка логина имеет строгий вид (не «красивая» landing-форма)
- [ ] Поля и кнопка используют стандартные ERP-контролы
- [ ] Сообщения об ошибках понятны
- [ ] Dev-mode notice выглядит системно, не как debug
- [ ] Нет случайных цветов вне утверждённой палитры
- [ ] Нет случайных CSS-классов
- [ ] `border-radius` в пределах design code
- [ ] `box-shadow` blur в пределах design code

**Возможные визуальные блокеры:**
- Карточка логина выглядит как SaaS/landing, а не как рабочая ERP
- Слишком большая карточка/поля/шрифты
- Неправильный цвет topbar
- Отсутствие тёмной brand-зоны
- Debug/dev текст как основной контент

**Manual owner visual review required**: YES
**Commit allowed before owner visual approval**: NO

---

## 18. VISUAL CHECK URL

**VISUAL CHECK URL**: `http://127.0.0.1:[port]/login`

---

## 19. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`
- [ ] Страница использует CORE modules из этого handoff
- [ ] Раздел `CORE modules used` заполнен
- [ ] Раздел `MODULE USAGE DECISIONS` заполнен с причинами выбора
- [ ] Missing modules: none
- [ ] Нет private/page-specific module names в universal handoff
- [ ] Layout соответствует спецификации `auth-layout.php`
- [ ] Все состояния описаны и реализованы
- [ ] Кодер может реализовать страницу без поиска примеров
- [ ] QA может проверить страницу по этому шаблону
- [ ] Нет случайных CSS-классов
- [ ] Нет хардкода цветов
- [ ] Нет inline styles, кроме динамических PHP
- [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`, emoji как иконок
- [ ] Нет demo-placeholder/SaaS-dashboard UI
- [ ] Нет Bootstrap/Tailwind/Material классов
- [ ] `border-radius` новых элементов ≤ 4px
- [ ] `box-shadow blur` новых элементов ≤ 8px
- [ ] Runtime/browser check выполнен
- [ ] Formal UI QA: PASS
- [ ] VISUAL CHECK URL предоставлен
- [ ] Manual owner visual review required: YES
- [ ] Commit allowed before owner visual approval: NO

---

## 20. Designer production handoff

### Current visual problems
- Страница `/login` ещё не существует — создаётся с нуля.

### Target visual result
- Минимальная страница входа в ERP PLANEX.
- Тёмная brand-зона topbar (224px) + светлый остаток topbar + центрированная карточка логина.
- Карточка: строгая, panel-подобная, без украшений.
- Форма: два поля стандартной высоты (28px control, стандартные ERP field-стили).
- Кнопка «Войти»: accent (copper), полная ширина внутри карточки.
- Фон: `var(--app-bg)` = #e2dfd8 (общий фон ERP).

### Exact layout
- App shell: `1fr` single column, `var(--topbar-h) 1fr` rows
- Topbar: только brand-зона (тёмная, 224px) + пустое светлое пространство справа
- Content: flex-центрирование vertical + horizontal
- Login card: 380px, 1px `var(--line)` border, 2px radius

### Exact typography
- Card heading: 16px, 700, `--text-main`
- Field labels: 10.5px, 700, `--text-muted`
- Field inputs: 12px, `--text-main`
- Error messages: 11px, `--danger`
- Notice text: 12px, `--text-muted` / `--danger`
- Button: 12px, 600, `--inverse`

### Exact spacing
- Auth-shell: `grid-template-rows: var(--topbar-h) 1fr`
- Auth-content padding: 24px
- Login card: width 380px
- Login-card-head: padding 10px 14px, min-height 44px
- Login-card-body: padding 14px
- Field margin-bottom: 12px
- Login-actions margin-top: 14px

### Exact color tokens
- Background: `--app-bg` = #e2dfd8
- Card surface: `--surface-strong` = #fefdf8
- Card head surface: `--surface-muted` = #e8e4db
- Card border: `--line` = #a8a196
- Head border: `--line-hair` = #dedad0
- Text main: `--text-main` = #131210
- Text muted: `--text-muted` = #4c4840
- Danger text: `--danger` = #992e26
- Danger bg: `--danger-bg` = #fcecea
- Success bg: `--success-bg` = #ddeee5
- Success text: `--success` = #1f6b43
- Brand zone: `--nav-bg` = #191816
- Brand name: rgba(240,238,232,.9)
- Button bg: `--accent` = #7c4718
- Button border: `--accent-deep` = #4e2d0e
- Input border: `--line-soft` = #c9c3b8
- Input bg: `--surface-field` = #fefdf8

### Required sections
- Auth-shell (`.auth-shell`)
- Auth-topbar (`.auth-topbar` → `.auth-topbar-brand` → `.logo-mark` + `.brand-name`)
- Auth-content (`.auth-content`)
- Login card (`.login-card` → `.login-card-head` + `.login-card-body`)
- Login form (`.login-form` → поля + кнопка)

### Required states
- Empty: чистая форма, без ошибок
- Validation error: `.is-error` на пустых полях, `.field-msg` под полями, `.notice.danger` над формой
- Auth error: `.notice.danger` над формой «Неверный логин или пароль.»
- Multi-logist error: `.notice.danger` над формой «Логин найден в нескольких компаниях...»
- Dev-mode notice: `.notice.success` над формой с логином/паролем SUPERADMIN
- Hover/focus/active: стандартные состояния `.field-input:focus`, `.btn-primary:hover`

### Forbidden texts/classes/patterns
- Forbidden texts: «UI foundation», «Техническая демо-страница», «Скоро», «В разработке», debug-тексты
- Forbidden classes: Bootstrap/Tailwind/Material классы, `.app-shell`, `.app-sidebar`, `.topbar` (без префикса `auth-`)
- Forbidden visual patterns: SaaS-landing форма, карточки с тенями >8px, псевдоиконки, emoji
