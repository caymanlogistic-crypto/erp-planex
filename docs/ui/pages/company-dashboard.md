# UI PAGE TEMPLATE — Company Dashboard (`/company/dashboard`)

Этот шаблон является источником истины для `erp-coder` и `erp-qa-tester`. Дизайнер (`erp-uiux-designer`) обязан заполнить все применимые секции до передачи задачи кодеру. Кодер реализует страницу строго по этому шаблону.

## Архитектурный источник

- **Route**: `GET /company/dashboard`
- **Модель**: `docs/architecture/AUTH_SESSION_MODEL.md` — DECISION-0041
- **Layout**: основной `app/View/layouts/main.php` с динамическим sidebar
- **Пользователи**: Руководитель (`company_owner`), Логист (`logist`)
- **Guard**: `role_code IN ('company_owner', 'logist')`

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING (ОБЯЗАТЕЛЬНО)

### 0a. App Shell Foundation

Страница `/company/dashboard` использует основной `main.php` layout. Foundation `main.php` уже COMPLIANT (проверен в superadmin-dashboard handoff, compliance-аудит 2026-06-13).

**Текущий `main.php` — COMPLIANT.** Требуются только точечные изменения sidebar и topbar (см. §0b, §0c).

| # | Параметр | MASTER spec | Текущая реализация в `app.css` | Соответствие |
|---|----------|-------------|-------------------------------|--------------|
| 1 | App shell grid | `grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr` | ✓ (app.css:89-95) | YES |
| 2 | Topbar position | `grid-column: 1 / -1` | ✓ (app.css:192) | YES |
| 3 | Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | ✓ (app.css:197) | YES |
| 4 | Topbar border-bottom | `1px solid var(--line)` | ✓ (app.css:198) | YES |
| 5 | Topbar internal grid | `grid-template-columns: var(--sidebar-w) 1fr auto` | ✓ (app.css:194) | YES |
| 6 | Topbar brand zone | `.topbar-brand`: тёмный фон, border-right | ✓ (app.css:227-235) | YES |
| 7 | Topbar crumbs | `.topbar-crumbs` | ✓ (app.css:261-278) | YES |
| 8 | Topbar right (user block) | `.topbar-right`, `.avatar`, `.user-info` | ✓ (app.css:281-316) | YES |
| 9 | Sidebar background | `var(--nav-bg)` #191816 | ✓ (app.css:99) | YES |
| 10 | Sidebar border-right | `1px solid var(--nav-divider)` | ✓ (app.css:100) | YES |
| 11 | Sidebar scrollbar | custom thin scrollbar | ✓ (app.css:105-120) | YES |
| 12 | Sidebar flex layout | `display: flex; flex-direction: column` | ✓ (app.css:101-104) | YES |
| 13 | Nav item height | 34px | ✓ (app.css:132) | YES |
| 14 | Nav item font-weight | 600 ВСЕГДА | ✓ (app.css:136) | YES |
| 15 | Nav item font-size | 12.5px | ✓ (app.css:135) | YES |
| 16 | Nav section label | 9px, 700, uppercase, letter-spacing .12em | ✓ (app.css:176-184) | YES |
| 17 | Nav active state | `::before` pseudo (2px gold left) | ✓ (app.css:153-162) | YES |
| 18 | Nav hover state | `background: var(--nav-hover); color: var(--nav-text-h)` | ✓ (app.css:164-167) | YES |
| 19 | Nav disabled state | `color: var(--nav-sub-text); opacity: .52` | ✓ (app.css:169-174) | YES |
| 20 | Nav icons (SVG 16x16) | `.nav-icon` | ✓ (app.css:328-334) | YES |
| 21 | Nav-spacer | `flex: 1; min-height: 8px` | ✓ (app.css:351-354) | YES |
| 22 | Nav-bottom | `border-top: 1px solid var(--nav-divider)` | ✓ (app.css:356-359) | YES |
| 23 | Content spacing | `padding: 12px 14px 28px` | ✓ (app.css:221-224) | YES |
| 24 | Page head | `.page-head` с `h1` 16px/700 | ✓ (app.css:378-397) | YES |
| 25 | Panel pattern | `padding: 0; overflow: hidden` | ✓ (app.css:409-416) | YES |

**Все 25 foundation-параметров — YES.** Foundation НЕ требует перестройки.

### 0b. SIDEBAR INFORMATION ARCHITECTURE (ОБЯЗАТЕЛЬНО)

Sidebar должен быть ДИНАМИЧЕСКИМ — три разных набора пунктов в зависимости от роли.

**НЕОБХОДИМЫЕ ИЗМЕНЕНИЯ В `main.php`**: заменить статический sidebar на PHP-условный рендеринг на основе `$_SESSION['role_code']`.

#### Вариант 1: SUPERADMIN

Не используется на `/company/dashboard` (SUPERADMIN не имеет доступа к `/company/*`), но sidebar для SUPERADMIN должен существовать для страниц `/superadmin/*`.

```text
[section] ОПЕРАЦИИ
  [nav-item is-disabled] Рейсы
  [nav-item is-disabled] Водители
  [nav-item is-disabled] Транспорт
  [nav-item is-disabled] Клиенты

[nav-spacer]

[section] СИСТЕМА
  [nav-item is-active] SUPERADMIN (href="/superadmin/companies")

[nav-bottom]
  [nav-item is-disabled] Настройки
```

#### Вариант 2: Руководитель (company_owner)

```text
[section] ОПЕРАЦИИ
  [nav-item is-disabled] Рейсы
  [nav-item] Водители (href="/company/drivers?company_id=N")      ← is-active на /company/drivers
  [nav-item] Транспорт (href="/company/vehicles?company_id=N")     ← is-active на /company/vehicles
  [nav-item] Клиенты (href="/company/clients?company_id=N")        ← is-active на /company/clients
  [nav-item] Подрядчики (href="/company/contractors?company_id=N")  ← is-active на /company/contractors
  [nav-item] Экипажи (href="/company/crews?company_id=N")          ← is-active на /company/crews

[nav-spacer]

[section] СИСТЕМА
  [nav-item] Логисты (href="/company/logists?company_id=N")        ← is-active на /company/logists
  [nav-item is-disabled] Настройки

[nav-bottom]
  (пусто — Настройки в секции СИСТЕМА)
```

**Важно**: Настройки для Руководителя находятся в секции СИСТЕМА (disabled), а НЕ в `nav-bottom`.

#### Вариант 3: Логист (logist)

```text
[section] ОПЕРАЦИИ
  [nav-item is-disabled] Рейсы
  [nav-item] Водители (href="/company/drivers?company_id=N")      ← is-active на /company/drivers
  [nav-item] Транспорт (href="/company/vehicles?company_id=N")     ← is-active на /company/vehicles
  [nav-item] Клиенты (href="/company/clients?company_id=N")        ← is-active на /company/clients
  [nav-item] Подрядчики (href="/company/contractors?company_id=N")  ← is-active на /company/contractors
  [nav-item] Экипажи (href="/company/crews?company_id=N")          ← is-active на /company/crews

[nav-spacer]

[section] СИСТЕМА
  [nav-item is-disabled] Настройки

[nav-bottom]
  (пусто — Настройки в секции СИСТЕМА)
```

**Важно**: Логист НЕ видит пункт «Логисты».

#### Поэлементная спецификация SIDEBAR IA (все 3 роли):

| # | Label | SUPERADMIN | Руководитель | Логист | SVG icon 16×16 |
|---|-------|------------|-------------|--------|-----------------|
| 1 | Рейсы | is-disabled | is-disabled | is-disabled | Truck (существующий в main.php) |
| 2 | Водители | is-disabled | `/company/drivers` | `/company/drivers` | Person (существующий) |
| 3 | Транспорт | is-disabled | `/company/vehicles` | `/company/vehicles` | Vehicle (существующий) |
| 4 | Клиенты | is-disabled | `/company/clients` | `/company/clients` | Building (существующий) |
| 5 | Подрядчики | — | `/company/contractors` | `/company/contractors` | **НОВЫЙ** (briefcase/buildings icon) |
| 6 | Экипажи | — | `/company/crews` | `/company/crews` | **НОВЫЙ** (users/link icon) |
| 7 | SUPERADMIN | `/superadmin/companies` | — | — | Shield (существующий) |
| 8 | Логисты | — | `/company/logists` | — | **НОВЫЙ** (user-badge icon) |
| 9 | Настройки | is-disabled (nav-bottom) | is-disabled (СИСТЕМА) | is-disabled (СИСТЕМА) | Gear (существующий) |

#### Новые SVG-иконки (16×16, inline, `.nav-icon`)

**Подрядчики** (briefcase/buildings):

```html
<svg class="nav-icon" viewBox="0 0 16 16" fill="none">
  <rect x="1.5" y="5" width="13" height="9.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
  <path d="M5 5V3.5C5 2.9 5.4 2.5 6 2.5H10C10.6 2.5 11 2.9 11 3.5V5" stroke="currentColor" stroke-width="1.4"/>
  <path d="M1.5 9H14.5" stroke="currentColor" stroke-width="1.4"/>
  <rect x="6.5" y="6.5" width="3" height="3" rx=".5" stroke="currentColor" stroke-width="1.2"/>
</svg>
```

**Экипажи** (users paired):

```html
<svg class="nav-icon" viewBox="0 0 16 16" fill="none">
  <circle cx="5.5" cy="4" r="2" stroke="currentColor" stroke-width="1.4"/>
  <path d="M1.5 12C1.5 9.8 3.3 8 5.5 8C7.7 8 9.5 9.8 9.5 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
  <circle cx="11" cy="6.5" r="1.6" stroke="currentColor" stroke-width="1.4"/>
  <path d="M8 13.5C8 11.8 9.3 10.5 11 10.5C12.7 10.5 14 11.8 14 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
</svg>
```

**Логисты** (user with badge/shield):

```html
<svg class="nav-icon" viewBox="0 0 16 16" fill="none">
  <circle cx="6" cy="4.5" r="2.5" stroke="currentColor" stroke-width="1.4"/>
  <path d="M1.5 13.5C1.5 10.5 4 8.5 6 8.5C8 8.5 10.5 10.5 10.5 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
  <path d="M12 3L14.5 4.5V7C14.5 9.5 12.8 11.3 12 12C11.2 11.3 9.5 9.5 9.5 7V4.5L12 3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
  <path d="M10.8 7L11.5 8L13.2 5.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
```

#### Правила `.is-active` на sidebar:

Активный пункт определяется PHP-условием: `str_starts_with($_SERVER['REQUEST_URI'], $route)`. 

На странице `/company/dashboard` **ни один пункт НЕ является `.is-active`** — это заглушка-домашняя страница без собственного пункта меню.

#### HTML-структура sidebar для Руководителя (company_owner):

```html
<aside class="app-sidebar" aria-label="Основная навигация">

  <div class="nav-group" style="padding-top:8px">
    <div class="nav-section-label">ОПЕРАЦИИ</div>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
      <span class="nav-label">Рейсы</span>
    </span>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers') ? ' is-active' : '' ?>" href="/company/drivers?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
      <span class="nav-label">Водители</span>
    </a>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicles') ? ' is-active' : '' ?>" href="/company/vehicles?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
      <span class="nav-label">Транспорт</span>
    </a>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/clients') ? ' is-active' : '' ?>" href="/company/clients?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
      <span class="nav-label">Клиенты</span>
    </a>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors') ? ' is-active' : '' ?>" href="/company/contractors?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><!-- NEW: briefcase --></svg>
      <span class="nav-label">Подрядчики</span>
    </a>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/crews') ? ' is-active' : '' ?>" href="/company/crews?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><!-- NEW: users-paired --></svg>
      <span class="nav-label">Экипажи</span>
    </a>
  </div>

  <div class="nav-spacer"></div>

  <div class="nav-group">
    <div class="nav-section-label">СИСТЕМА</div>

    <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/logists') ? ' is-active' : '' ?>" href="/company/logists?company_id=<?= $companyId ?>">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><!-- NEW: user-badge --></svg>
      <span class="nav-label">Логисты</span>
    </a>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
      <span class="nav-label">Настройки</span>
    </span>
  </div>

</aside>
```

### 0c. TOPBAR DYNAMIC SPEC

Topbar в `main.php` должен иметь **два состояния**:

#### Состояние 1: Неавторизован

Только brand-зона (тёмная) + пустое светлое пространство. Используется на `/login` через `auth-layout.php` (НЕ `main.php`).

#### Состояние 2: Авторизован (основной `main.php`)

Используется на `/superadmin/*` и `/company/*`.

**Topbar crumbs (middle) — динамический:**

```php
// $pageTitle и $pageContext задаются в index.php для каждого маршрута
$pageTitle = $pageTitle ?? 'Компания';
$pageContext = $pageContext ?? 'Панель управления';
```

**Topbar right (user block) — динамический:**

```php
$userName = $_SESSION['user_name'] ?? 'Пользователь';
$roleCode = $_SESSION['role_code'] ?? '';

$roleLabel = match($roleCode) {
    'superadmin' => 'Суперадминистратор',
    'company_owner' => 'Руководитель',
    'logist' => 'Логист',
    default => 'Пользователь',
};

$initials = implode('', array_map(
    fn($word) => mb_substr($word, 0, 1),
    array_filter(explode(' ', trim($userName)))
));
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
```

**HTML topbar right для авторизованного состояния:**

```html
<div class="topbar-right">
    <div class="avatar"><?= e($initials) ?></div>
    <div class="user-info">
        <strong><?= e($userName) ?></strong>
        <span><?= e($roleLabel) ?></span>
    </div>
    <a href="/logout" class="btn btn-ghost" style="margin-left:4px">Выйти</a>
</div>
```

**НЕОБХОДИМЫЕ ИЗМЕНЕНИЯ В `app.css` для кнопки «Выйти» в topbar:**

Кнопка `.btn-ghost` в topbar-right должна быть компактной. Добавить:

```css
.topbar-right .btn-ghost {
    height: 24px;
    font-size: 11px;
    padding: 0 8px;
}
```

### 0d. FOUNDATION ACCEPTANCE CHECKLIST

| # | Критерий | Target |
|---|----------|--------|
| 1 | Foundation shell/sidebar/topbar из `app.css` не затронуты — только `main.php` | YES |
| 2 | Sidebar — 3 варианта (SUPERADMIN, Руководитель, Логист) | YES |
| 3 | Topbar user block — динамический (имя, роль, инициалы, «Выйти») | YES |
| 4 | Настройки для Руководителя/Логиста — в секции СИСТЕМА (не в nav-bottom) | YES |
| 5 | Логист не видит пункт «Логисты» | YES |
| 6 | Новые SVG-иконки: Подрядчики, Экипажи, Логисты | YES |
| 7 | `$companyId` доступен в `main.php` для формирования href | YES |
| 8 | `str_starts_with()` для `.is-active` | YES |
| 9 | Кнопка «Выйти» — `.btn-ghost` в `.topbar-right` | YES |

---

## 1. Страница

- **Route / view**: `GET /company/dashboard` → `main.php` + `company_dashboard.php`
- **Тип страницы**: admin/settings (PATTERN-05) — минимальная страница-заглушка локальной ERP
- **Пользователь (роль)**: Руководитель (`company_owner`), Логист (`logist`)
- **Главная задача пользователя**: Видеть доступные разделы локальной ERP и переходить к ним
- **Что нельзя менять в бизнес-логике**: Маршруты, контекст компании из сессии, права доступа

---

## 2. Layout

- **App shell**: `main.php` — topbar 38px + sidebar 224px + content
- **Content min-width**: 1440px (desktop-first)
- **Основная сетка**: одноколоночная (page-head + sections)
- **Правый inspector**: нет
- **Scroll areas**: content scrolls vertically

---

## 3. Page head

```html
<div class="page-head">
    <div class="page-head-left">
        <h1>Компания: <?= e($companyName) ?></h1>
        <p>Панель управления</p>
    </div>
</div>
```

**Переменные PHP:**
- `$companyName` — `companies.name` из центральной БД, загружается в `index.php` по `$_SESSION['company_id']`

**Page-head CSS:** используется существующий `.page-head` (app.css:378-397).

---

## 4. Work area

### 4.1. Development notice

```html
<div class="notice" style="margin-bottom:10px">
    Система в разработке. Доступные разделы:
</div>
```

### 4.2. Navigation links section

**НЕ использовать SaaS-dashboard карточки!** Используется panel с компактным списком ссылок.

```html
<div class="panel">
    <div class="panel-head">
        <h2>Разделы</h2>
    </div>
    <div class="panel-body">

        <?php if ($roleCode === 'company_owner'): ?>
        <a href="/company/logists?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Логисты</span>
            <span class="dash-link-desc">Управление пользователями-логистами компании</span>
        </a>
        <?php endif; ?>

        <a href="/company/clients?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Клиенты</span>
            <span class="dash-link-desc">Реестр клиентов-заказчиков перевозок</span>
        </a>

        <a href="/company/contractors?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Подрядчики</span>
            <span class="dash-link-desc">Реестр подрядчиков-перевозчиков</span>
        </a>

        <a href="/company/drivers?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Водители</span>
            <span class="dash-link-desc">Реестр водителей</span>
        </a>

        <a href="/company/vehicles?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Транспорт</span>
            <span class="dash-link-desc">Реестр транспортных средств</span>
        </a>

        <a href="/company/crews?company_id=<?= $companyId ?>" class="dash-link">
            <span class="dash-link-label">Экипажи</span>
            <span class="dash-link-desc">Связки подрядчик + водитель + транспорт</span>
        </a>

    </div>
</div>
```

**CSS-спецификация dash-link (НОВЫЙ класс, добавляется в `app.css`):**

```css
.dash-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 34px;
    padding: 8px 10px;
    border-bottom: 1px solid var(--line-hair);
    text-decoration: none;
    color: var(--text-main);
    font-size: 12px;
    font-weight: 600;
}

.dash-link:last-child {
    border-bottom: none;
}

.dash-link:hover {
    background: var(--surface);
    color: var(--accent);
}

.dash-link-label {
    flex-shrink: 0;
    color: var(--text-main);
}

.dash-link:hover .dash-link-label {
    color: var(--accent);
}

.dash-link-desc {
    color: var(--text-faint);
    font-weight: 400;
    font-size: 12px;
    text-align: right;
}
```

**Почему НЕ карточки:**
- Ссылки-строки внутри panel — это строгий admin/settings стиль
- Карточный SaaS-dashboard подход (`PATTERN-02 card-grid`) явно запрещён для ERP PLANEX
- Панель с плоским списком ссылок соответствует PATTERN-05 (admin/settings screen)

---

## 5. Состояния

### 5.1. Обычное состояние

GET `/company/dashboard` — page-head + notice + panel со ссылками.

### 5.2. Company name не загружен

Если `$companyName` пуст (компания не найдена по `$_SESSION['company_id']`):

```html
<div class="page-head">
    <h1>Панель управления</h1>
    <p>Компания не найдена</p>
</div>
<div class="notice warn">Не удалось загрузить данные компании. Обратитесь к администратору.</div>
```

### 5.3. Empty state

На странице `/company/dashboard` нет empty state — список разделов всегда отображается.

---

## 6. CSS/classes coder must use

### Существующие классы (уже в `app.css`)
- `.app-shell`, `.app-sidebar`, `.app-main`, `.topbar` — shell
- `.topbar-brand`, `.logo-mark`, `.brand-name` — brand
- `.topbar-crumbs` — контекст
- `.topbar-right`, `.avatar`, `.user-info` — user block
- `.nav-group`, `.nav-section-label`, `.nav-item`, `.nav-icon`, `.nav-label` — nav
- `.nav-item.is-active`, `.nav-item.is-disabled`, `.nav-item:hover` — nav states
- `.nav-spacer`, `.nav-bottom` — nav layout
- `.content` — content wrapper
- `.page-head`, `.page-head h1`, `.page-head p` — page header
- `.panel`, `.panel-head`, `.panel-body` — panel
- `.notice` — notice
- `.btn`, `.btn-ghost` — buttons

### Новые классы (добавить в `app.css`)
- `.dash-link` — строка-ссылка в dashboard panel
- `.dash-link-label` — название раздела
- `.dash-link-desc` — описание раздела
- `.topbar-right .btn-ghost` — компактная кнопка «Выйти»

---

## 7. CORE modules used

- Core UI kit path: `docs/ui/ERP_UI_KIT_CORE.html`
- Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html` (reference/history only)
- COMPOSITE pattern selected:
  - `PATTERN-05 Admin/settings screen` — строгая системная страница с panel, notice, компактным списком; запрещает KPI dashboard и карточную сетку

### CORE modules used

| ID | Название | Причина выбора |
|----|----------|----------------|
| `CORE-01` | App shell | Базовый каркас страницы: sidebar + topbar + content |
| `CORE-02` | Sidebar navigation | Динамическая навигация по роли (3 варианта) |
| `CORE-03` | Topbar | Верхняя полоса с brand, crumbs, user block + «Выйти» |
| `CORE-04` | Breadcrumbs / page context | Контекст в topbar: «Компания — Панель управления» |
| `CORE-05` | Page header | Page-head с названием компании и подзаголовком |
| `CORE-08` | Panel | Секция «Разделы» со списком ссылок |
| `CORE-19` | Ghost button | Кнопка «Выйти» в topbar |
| `CORE-23` | Disabled/future action | Disabled sidebar items (Рейсы, Настройки) |
| `CORE-32` | Notice / system message | Notice «Система в разработке» |

### Missing modules
- `none` — все необходимые модули присутствуют в Core Kit

---

## 8. MODULE USAGE DECISIONS

- **Main page purpose**: Домашняя страница-заглушка локальной ERP с навигацией по доступным разделам
- **Primary work object**: Список ссылок-разделов внутри panel
- **Main layout selected**: `main.php` (стандартный ERP shell с sidebar)
- **Composite pattern selected**: PATTERN-05 Admin/settings screen — строгая системная страница с notice и panel
- **Primary action**: Нет (страница-заглушка, основное действие — переход по ссылкам)
- **Secondary actions**: «Выйти» в topbar
- **Table required**: NO — нет данных для таблицы
- **Form required**: NO
- **Inspector required**: NO
- **Filters required**: NO
- **Modal required**: NO
- **Empty/error/loading states required**: error state (company_name not loaded)
- **Modules used with reasons**:
  - CORE-01, 02, 03, 04: стандартный ERP shell с динамической навигацией
  - CORE-05: page-head идентифицирует компанию
  - CORE-08: panel для группировки ссылок (строгий admin/settings подход)
  - CORE-19: ghost-кнопка «Выйти» — тихое вспомогательное действие
  - CORE-23: disabled пункты для будущих модулей
  - CORE-32: notice для сообщения о статусе разработки
- **Modules explicitly not used with reasons**:
  - CORE-13 (Data table): нет данных
  - CORE-06 (Page actions): нет действий на странице-заглушке
  - CORE-09 (Admin/settings section): заменён на простую panel со ссылками
  - CORE-30 (Right inspector): нет master-detail сценария
  - CORE-41, 42 (Charts): не нужны на странице-заглушке
- **Private applied example names explicitly not used**: Нет
- **SaaS-dashboard/card-grid explicitly not used**: ссылки-строки вместо карточек

---

## 9. SOURCE MAPPING (MANDATORY)

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS Reference > `.app-shell` | `.app-shell` | Bootstrap `.container` |
| 2 | Sidebar nav | CORE-02 | Production CSS Reference > `.nav-item`, `.nav-item:hover`, `.nav-item.is-active` | `.nav-item`, `.is-active`, `.is-disabled` | `a.nav-item { color: inherit }` |
| 3 | Nav section label | CORE-02 | Production CSS Reference — 9px/700/uppercase/.12em | `.nav-section-label` | `.nav-section` (legacy) |
| 4 | Nav icons | CORE-02 sub | Production CSS Reference > `.nav-icon` 16×16 | `.nav-icon` | `.nav-dot` |
| 5 | Topbar | CORE-03 | Production CSS Reference > `.topbar` | `.topbar`, `.topbar-brand`, `.topbar-crumbs`, `.topbar-right` | Hero header |
| 6 | Topbar brand | CORE-03 sub | Production CSS Reference > `.topbar-brand`, `.logo-mark`, `.brand-name` | `.logo-mark`, `.brand-name` | Bootstrap `.navbar-brand` |
| 7 | Topbar crumbs | CORE-04 | Production CSS Reference > `.crumbs` | `.topbar-crumbs` | Generic label |
| 8 | Topbar user block | CORE-03 sub | Production CSS Reference > `.topbar-right`, `.avatar`, `.user-info` | `.avatar`, `.user-info`, `.btn-ghost` | Environment badge |
| 9 | Page head | CORE-05 | Production CSS Reference > `.page-head` | `.page-head`, `h1`, `p` | `.page-header` (legacy alias OK) |
| 10 | Panel | CORE-08 | Production CSS Reference > `.panel` | `.panel`, `.panel-head`, `.panel-body` | Decorative card, SaaS card |
| 11 | Panel heading | CORE-08 sub | Production CSS Reference > `.panel-head-title` / `h2` | `h2` | Custom title class |
| 12 | Notice | CORE-32 | Production CSS Reference > `.notice` | `.notice` | Blue info alert |
| 13 | Warning notice | CORE-33 | Production CSS Reference > `.notice.warn` | `.notice.warn` | Debug banner |
| 14 | Ghost button | CORE-19 | Button Decision Matrix > Ghost | `.btn`, `.btn-ghost` | Danger as ghost, primary as ghost |
| 15 | Disabled nav items | CORE-23 | Production CSS Reference > `.disabled` | `.is-disabled` | Looking clickable |
| 16 | Dash link | CORE-08 sub | Panel body — custom link row (аналог `.activity-row` плотности) | `.dash-link`, `.dash-link-label`, `.dash-link-desc` | SaaS card grid, Bootstrap `.list-group` |

---

## 10. CSS COMPATIBILITY CHECK (MANDATORY)

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------|--------------|-----------------|
| `.panel` | `.panel-head` | 0 (by rule) | Yes — head flush to top | `.panel-body` (10px) | `--line-hair` | `h2` or `.panel-head-title` |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | — | `.dash-link` items inside |
| `.panel-body` | `.dash-link` | 10px | No | `.dash-link` (8px 10px) | `--line-hair` (bottom) | `:hover` → `var(--surface)` + `var(--accent)` |
| `.dash-link` | `.dash-link-label` | — | — | — | — | `color: var(--text-main)`, `:hover` → `var(--accent)` |
| `.dash-link` | `.dash-link-desc` | — | — | — | — | `color: var(--text-faint)`, 400, 12px |
| `.topbar-right` | `.btn-ghost` | — | — | `.btn-ghost` (height 24px, padding 0 8px) | — | `:hover` |
| `.nav-item` | `.nav-icon` | `0 11px` | — | — | — | opacity .45 base, .7 hover, .85 active |
| `a.nav-item` | — | — | — | — | `--nav-gold` on `::before` | `:hover`, `.is-active`, `text-decoration: none` |
| `.nav-spacer` | — | — | — | `flex: 1; min-height: 8px` | — | Pushes СИСТЕМА to bottom |

---

## 11. Изменения в main.php (спецификация для кодера)

### Файл: `app/View/layouts/main.php`

**Что изменить:**

1. **Sidebar — сделать динамическим** на основе `$_SESSION['role_code']`
   - Три варианта: `superadmin`, `company_owner`, `logist`
   - Каждый вариант — отдельный HTML-блок
   - Для `company_owner` и `logist`: ссылки с `?company_id=<?= $companyId ?>`
   - `$companyId = $_SESSION['company_id'] ?? null`

2. **Topbar crumbs — сделать динамическими**
   - `$pageTitle` и `$pageContext` передаются из `index.php`
   - По умолчанию: `$pageTitle = 'ERP PLANEX'`, `$pageContext = ''`

3. **Topbar right — сделать динамическим**
   - Если `!empty($_SESSION['user_id'])` → user block (аватар + имя + роль + «Выйти»)
   - Если сессии нет → только `text-muted` (но `main.php` не должен использоваться без сессии; для `/login` используется `auth-layout.php`)

4. **Добавить кнопку «Выйти»**
   - `<a href="/logout" class="btn btn-ghost">Выйти</a>`

5. **Добавить недостающие SVG-иконки** для новых пунктов меню

### Файл: `public/assets/css/app.css`

**Что добавить:**

```css
/* === DASHBOARD LINKS (CORE-08 sub-element) === */
.dash-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 34px;
    padding: 8px 10px;
    border-bottom: 1px solid var(--line-hair);
    text-decoration: none;
    color: var(--text-main);
    font-size: 12px;
    font-weight: 600;
}

.dash-link:last-child {
    border-bottom: none;
}

.dash-link:hover {
    background: var(--surface);
    color: var(--accent);
}

.dash-link-label {
    flex-shrink: 0;
    color: var(--text-main);
}

.dash-link:hover .dash-link-label {
    color: var(--accent);
}

.dash-link-desc {
    color: var(--text-faint);
    font-weight: 400;
    font-size: 12px;
    text-align: right;
}

/* === TOPBAR LOGOUT BUTTON === */
.topbar-right .btn-ghost {
    height: 24px;
    font-size: 11px;
    padding: 0 8px;
}
```

### Файл: `public/index.php`

**Что изменить/добавить:**

1. `session_start()` в начале файла (до роутинга)
2. Helper-функции:
   - `isAuthenticated(): bool` — проверка `!empty($_SESSION['user_id'])`
   - `requireRole(string|array $roles): void` — редирект 302 на `/login` при несовпадении
   - `getSessionCompanyId(): ?int` — `$_SESSION['company_id'] ?? null`
3. Route guards перед `/superadmin/*` и `/company/*`
4. Маршрут `GET /company/dashboard`:
   - guard: `requireRole(['company_owner', 'logist'])`
   - Загрузить `$companyName` из `companies` WHERE `id = $_SESSION['company_id']`
   - `$pageTitle = 'Компания'`, `$pageContext = 'Панель управления'`
5. Маршрут `GET /logout`:
   - `session_destroy()` + `session_start()` + редирект на `/login`

---

## 12. Файлы для создания/изменения

| Файл | Действие | Описание |
|------|----------|----------|
| `app/View/layouts/auth-layout.php` | **СОЗДАТЬ** | Минимальный layout для `/login` |
| `app/View/layouts/main.php` | **ИЗМЕНИТЬ** | Динамический sidebar (3 роли), динамический topbar (user block + «Выйти»), динамические crumbs |
| `app/View/pages/company_dashboard.php` | **СОЗДАТЬ** | Контент страницы `/company/dashboard` |
| `public/assets/css/app.css` | **ИЗМЕНИТЬ** | Добавить `.dash-link*`, `.topbar-right .btn-ghost` |
| `public/index.php` | **ИЗМЕНИТЬ** | Session, guards, `/company/dashboard`, `/logout`, auth helpers |

---

## 13. Strict prohibitions for coder

- Не придумывать layout за пределами этого handoff
- Не использовать SaaS-dashboard карточки для списка разделов
- Не использовать псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`
- Не использовать emoji как иконки
- Не добавлять debug badges
- Не менять порядок пунктов sidebar
- Не смешивать операционные и системные пункты в одной группе
- Не добавлять пункт «Логисты» для роли `logist`
- Не использовать `color: inherit` на `a.nav-item`
- Не менять `font-weight` на `.nav-item.is-active`
- Не менять backend/auth/CRUD/migrations без отдельного задания
- Не использовать Bootstrap/Tailwind/React/Vue/Material
- Не добавлять inline styles, кроме динамических PHP-значений
- Не придумывать новые CSS-классы без указания в этом handoff
- `border-radius` не больше 4px (новые элементы)
- `box-shadow` blur не больше 8px (новые элементы)

---

## 14. Coder implementation checklist

- [ ] Создать `auth-layout.php` (см. `login.md`)
- [ ] Изменить `main.php`: sidebar — 3 варианта по `$_SESSION['role_code']`
- [ ] Изменить `main.php`: topbar right — user block с аватаром, именем, ролью, «Выйти»
- [ ] Изменить `main.php`: topbar crumbs — динамические `$pageTitle` / `$pageContext`
- [ ] Создать `company_dashboard.php` с page-head + notice + panel/dash-link
- [ ] Добавить CSS: `.dash-link`, `.dash-link-label`, `.dash-link-desc`, `.topbar-right .btn-ghost`
- [ ] Добавить недостающие SVG-иконки: Подрядчики, Экипажи, Логисты (16×16, inline)
- [ ] Реализовать `session_start()` + `session_regenerate_id(true)` в `index.php`
- [ ] Реализовать helper-функции `isAuthenticated()`, `requireRole()`, `getSessionCompanyId()`
- [ ] Реализовать route guards для `/superadmin/*` и `/company/*`
- [ ] Реализовать `GET /company/dashboard`
- [ ] Реализовать `POST /login` (см. `login.md`)
- [ ] Реализовать `GET /logout`
- [ ] Sidebar links используют `?company_id=` из сессии
- [ ] Ссылки `.dash-link` используют `?company_id=` из сессии
- [ ] `php -l` для всех изменённых PHP-файлов — без ошибок
- [ ] Нет raw PHP/SQL ошибок в UI
- [ ] Нет demo-placeholder UI

---

## 15. QA formal checklist

- [ ] `main.php` sidebar — 3 варианта: SUPERADMIN, Руководитель, Логист
- [ ] Sidebar Руководителя содержит «Логисты» в СИСТЕМА
- [ ] Sidebar Логиста НЕ содержит «Логисты»
- [ ] Sidebar Руководителя/Логиста: Настройки в секции СИСТЕМА (disabled)
- [ ] Sidebar SUPERADMIN: Настройки в nav-bottom (disabled)
- [ ] Все активные ссылки sidebar содержат `?company_id=`
- [ ] `.is-active` корректно определяется через `str_starts_with()`
- [ ] Topbar right: аватар (инициалы) + имя + роль + «Выйти»
- [ ] Кнопка «Выйти» — `.btn-ghost`, ведёт на `/logout`
- [ ] Topbar crumbs: «Компания — Панель управления»
- [ ] Page head: «Компания: {company_name}» / «Панель управления»
- [ ] Notice: «Система в разработке. Доступные разделы:»
- [ ] Panel «Разделы» содержит ссылки `.dash-link`
- [ ] «Логисты» виден только для Руководителя
- [ ] Все 6 ссылок-разделов присутствуют (Логист: 5 ссылок)
- [ ] Нет SaaS-dashboard карточек
- [ ] Нет псевдоиконок, emoji, debug badges
- [ ] Все CSS-классы из handoff на месте
- [ ] Route guards работают
- [ ] `/logout` уничтожает сессию
- [ ] `php -l` для всех PHP-файлов — без ошибок
- [ ] Formal UI QA: PASS/FAIL

---

## 16. Architect pre-owner review checklist

- [ ] Страница выглядит как рабочая ERP-заглушка, а не SaaS-dashboard
- [ ] Sidebar соответствует роли
- [ ] Topbar показывает реального пользователя
- [ ] Ссылки-строки внутри panel, а не карточки
- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Не нужен повторный designer/coder/QA цикл

---

## 17. Owner visual checklist

- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`
- [ ] Нет emoji как иконок
- [ ] Нет demo-placeholder/SaaS-dashboard вида
- [ ] Нет карточек вместо ссылок-строк
- [ ] Нет больших пустот
- [ ] Sidebar тёмный 224px, nav текст светлый
- [ ] Sidebar содержит правильные пункты для роли
- [ ] Topbar 38px, user block справа
- [ ] Плотная рабочая композиция
- [ ] Нет случайных цветов вне утверждённой палитры
- [ ] Нет случайных CSS-классов
- [ ] Нет blue/white corporate UI
- [ ] `border-radius` в пределах design code
- [ ] `box-shadow` blur в пределах design code

**Возможные визуальные блокеры:**
- Ссылки-разделы выглядят как SaaS-карточки
- Sidebar не соответствует роли (не те пункты)
- Topbar пустой или с demo-данными
- Панель «Разделы» слишком большая/декоративная
- Отсутствует кнопка «Выйти»

**Manual owner visual review required**: YES
**Commit allowed before owner visual approval**: NO

---

## 18. VISUAL CHECK URL

**VISUAL CHECK URL**: `http://127.0.0.1:[port]/company/dashboard`

---

## 19. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён
- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`
- [ ] Страница использует CORE modules и COMPOSITE pattern
- [ ] Раздел `CORE modules used` заполнен
- [ ] Раздел `MODULE USAGE DECISIONS` заполнен с причинами выбора
- [ ] Missing modules: none
- [ ] Нет private/page-specific module names в universal handoff
- [ ] Sidebar IA соответствует роли
- [ ] Topbar user block динамический
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
- Страница `/company/dashboard` ещё не существует — создаётся с нуля.
- `main.php` имеет статический sidebar (только SUPERADMIN) — требуется динамизация.

### Target visual result
- Рабочая ERP-страница-заглушка для Руководителя и Логиста.
- Sidebar соответствует роли пользователя.
- Topbar показывает реального пользователя с аватаром и кнопкой «Выйти».
- Page-head идентифицирует компанию.
- Notice информирует о статусе разработки.
- Panel «Разделы» содержит компактный список ссылок (строки, НЕ карточки).

### Exact layout
- App shell: стандартный `main.php` (sidebar 224px + topbar 38px + content)
- Page-head: «Компания: {name}» / «Панель управления»
- Work area: notice + panel с dash-link строками

### Exact typography
- Page title: 16px, 700, `--text-main`
- Page subtitle: 13px, `--text-muted`
- Panel heading: 12px, 700, uppercase, 0.04em
- Dash-link label: 12px, 600, `--text-main` → hover `--accent`
- Dash-link desc: 12px, 400, `--text-faint`
- Notice: 12px, `--text-muted`
- Nav items: 12.5px, 600
- Nav section labels: 9px, 700, uppercase, .12em

### Exact spacing
- Content padding: 12px 14px 28px
- Page-head margin-bottom: 10px
- Notice margin-bottom: 10px (до panel)
- Panel-body padding: 10px
- Dash-link: min-height 34px, padding 8px 10px
- Dash-link gap: 12px
- Dash-link border-bottom: 1px solid `--line-hair`

### Exact color tokens
- Background: `--app-bg` = #e2dfd8
- Panel surface: `--surface-strong` = #fefdf8
- Panel head: `--surface-muted` = #e8e4db
- Panel border: `--line-hair` = #dedad0
- Notice bg: `--surface-muted`
- Notice border: `--line-soft`
- Link label: `--text-main` → hover `--accent`
- Link desc: `--text-faint`
- Sidebar: `--nav-bg` = #191816
- Active nav: `--nav-active-bg`, `--nav-text-act` = #d4a254, `--nav-gold` left marker

### Required sections
- App-shell с динамическим sidebar (3 варианта)
- Topbar с динамическим user block
- Page-head: «Компания: {name}» / «Панель управления»
- Notice: «Система в разработке. Доступные разделы:»
- Panel «Разделы» с dash-link строками

### Required states
- Normal: страница загружена, компания найдена
- Company not found: warning notice вместо списка разделов
- Hover: dash-link → surface background + accent color
- Sidebar active: gold left marker + copper bg + gold text
- Sidebar disabled: subdued text, opacity .52

### Forbidden texts/classes/patterns
- Forbidden texts: «UI foundation», «Техническая демо-страница», «Скоро», «В разработке» (как основной элемент), debug-тексты
- Forbidden classes: Bootstrap/Tailwind/Material классы, `.card`, `.card-grid`, `.dashboard-grid`
- Forbidden visual patterns: SaaS-dashboard карточки, KPI-виджеты, псевдоиконки, emoji, debug badges
