# UI PAGE TEMPLATE — SUPERADMIN Dashboard

Этот шаблон является источником истины для `erp-coder` и `erp-qa-tester`. Дизайнер (`erp-uiux-designer`) заполнил все применимые секции до передачи задачи кодеру. Кодер реализует страницу строго по этому шаблону.

## 1. Страница

- Route / view: `/superadmin` → `app/View/pages/superadmin_dashboard.php`
- Тип страницы: admin settings / dashboard (центральная панель)
- Пользователь (роль): SUPERADMIN (отдельный от локальной ERP, максимальные права)
- Главная задача пользователя: обзор состояния системы и навигация к будущим модулям управления
- Что нельзя менять в бизнес-логике: Stage 1 — бизнес-логика отсутствует. Запрещено добавлять реальные данные, запросы к БД, авторизацию, CRUD, feature toggles.

## 2. Layout

- App shell: topbar 38px + sidebar 224px + content (существующий `app/View/layouts/main.php`)
- Content min-width: 1440px (desktop-first)
- Основная сетка: одноколоночная, вертикальный поток: page-header → info alert → nav placeholder → cards grid → empty states
- Правый inspector: нет
- Нижняя форма/editor: нет
- Scroll areas: вертикальный скролл внутри `.content` при переполнении

## 3. Page head

- Eyebrow: нет (Stage 1 — заглушка, eyebrow не применяется)
- Title: "SUPERADMIN — Центральная панель"
- Summary counters: 4 информационные карточки-заглушки (статичные, без реальных данных):

  | # | Название | Значение-заглушка | Иконка-заглушка (текст) |
  |---|---|---|---|
  | 1 | Компании | — | `[=]` |
  | 2 | Пользователи SUPERADMIN | — | `[#]` |
  | 3 | Активные features | — | `[~]` |
  | 4 | Статус системы | OK | `[v]` |

  Карточки реализовать через контейнер `.summary-cards` (requires new CSS) — flex/grid в один ряд, каждая карточка `.summary-card` (requires new CSS) с крупным значением и muted подписью. Все значения — прочерк `—` или `OK` (для статуса). Никаких реальных данных, запросов к БД нет.

- Primary action (`.btn .btn-primary`): отсутствует (Stage 1 — заглушка)
- Secondary actions: отсутствуют

## 4. Filters / toolbar

- Filters-bar: нет (Stage 1 — заглушка)
- Поля фильтрации: не применимо
- Filter chips: нет
- Toolbar actions: нет

## 5. Main table / grid

- Component: таблица не используется. Основная рабочая область — сетка карточек-заглушек.
- Вместо таблицы: `.cards-grid` (requires new CSS) — сетка 2×2: 2 ряда по 2 карточки.
  - Каждая карточка — `.module-card` (requires new CSS), внутри `.panel` для базового стиля.
- Row height: не применимо
- Thead height: не применимо
- Header sticky: не применимо
- Columns: не применимо
- Row states: не применимо
- Row actions: не применимо
- Bulk actions: не применимо

### Карточки модулей (2×2 grid)

| # | Название | Статус | Иконка-заглушка | Описание |
|---|---|---|---|---|
| 1 | Управление компаниями | Stage 3 | `[🏢]` | Создание и настройка локальных ERP-систем, управление папками и БД компаний |
| 2 | Пользователи SUPERADMIN | Stage 4 | `[👤]` | Управление учётными записями SUPERADMIN, авторизация, роли |
| 3 | Feature toggles | Stage 5 | `[⚙]` | Управление доступностью модулей, страниц и отчётов по компаниям |
| 4 | Системные настройки | Stage 6 | `[🔧]` | Общие параметры системы, мониторинг, логи и аудит |

Каждая карточка содержит:
- `.module-card-icon` (requires new CSS) — текстовая заглушка иконки
- `.module-card-title` (requires new CSS) — название модуля
- `.module-card-status` (requires new CSS) — статусный бейдж с текстом "Stage 2+" или "В разработке"
- `.module-card-desc` (requires new CSS) — краткое описание (muted)

Карточки статичные, не кликабельные. Курсор: default (не pointer).

## 6. Inspector / detail panel

- Нужен: нет
- Width: не применимо
- Header: не применимо
- Status badge: не применимо
- Tabs: не применимо
- Sections: не применимо
- Quick actions: не применимо

## 7. Forms

- Где форма: не применимо (Stage 1 — форм нет)
- Grid: не применимо
- Field list: не применимо
- Form states: не применимо

## 8. Modals

| Modal | Size | Trigger | Fields/content | Footer buttons |
|---|---|---|---|---|
| — | — | — | — | — |

- Danger confirmations: не применимо (Stage 1 — действий нет)

## 9. Toasts / alerts

- Success toast: не применимо (Stage 1 — действий нет)
- Error toast: не применимо
- Warning alert: не применимо
- No permission state: не применимо (Stage 1 — авторизации нет, страница открыта для локальной разработки)

### Информационный alert (над карточками модулей)

Перед сеткой карточек — информационный блок `.alert.alert-info`:

> **SUPERADMIN — центральная панель управления ERP PLANEX.**
> Здесь вы сможете управлять компаниями, пользователями SUPERADMIN, доступностью функций (feature toggles) и системными настройками.
> Функционал находится в разработке и будет добавляться поэтапно.

## 10. Empty / loading / error

- Empty table state: не применимо (таблицы нет)

### Empty state для навигации

Под заголовком "Будущие разделы SUPERADMIN" — список навигационных пунктов. Если пунктов нет (все disabled):

```
Будущие разделы SUPERADMIN пока не доступны. Функционал появится в следующих этапах.
```

### Empty state для карточек модулей

Если сетка карточек пуста (не должно быть в Stage 1, но предусмотреть):

```
Модули SUPERADMIN ещё не реализованы. Следите за обновлениями.
```

- Loading skeleton: не применимо (Stage 1 — статическая заглушка, загрузки нет)
- Error state: не применимо (Stage 1 — нет операций, которые могут вызвать ошибку)

## 11. Statuses and badges

- Используемые badges:
  - `.status-neutral` — для бейджа "Скоро" в навигационных пунктах
  - `.status-neutral` — для бейджа "Stage 2+" или "В разработке" в карточках модулей
  - `.status` (базовый класс) — для summary-карточки "Статус системы" со значением "OK"
- Flight statuses: не применимо (SUPERADMIN не имеет рейсов)

## 12. Charts, если есть

- Chart type: не применимо (Stage 1 — графиков нет)
- Data series: не применимо
- Colors: не применимо
- Legend: не применимо
- Empty/loading state: не применимо

## 13. CSS/classes coder must use

### Существующие классы (из `app.css`)

- Layout classes: `.app-shell`, `.app-sidebar`, `.app-main`, `.content`, `.topbar`, `.brand`, `.brand-mark`, `.environment-badge`
- Page header: `.page-header`
- Grid: `.grid`, `.two-columns`
- Cards/panels: `.panel`
- Alert: `.alert`, `.alert-info`
- Status badges: `.status`, `.status-neutral`
- Sidebar nav: `.nav-list`, `.nav-item`, `.nav-section`, `.nav-dot`, `.is-disabled`
- Buttons: `.btn`, `.btn-primary` (только если понадобятся — в Stage 1 кнопок нет)
- Empty states: `.empty-state`
- States: `.is-disabled`

### Новые классы (requires new CSS — кодер должен добавить в `app.css`)

Все новые классы наследуют существующие токены (цвета, радиусы, отступы) из `:root`.

| Класс | Назначение | Стиль |
|---|---|---|
| `.summary-cards` | Контейнер для 4 summary-карточек в page-header | `display: flex; gap: var(--space-4); flex-wrap: wrap; margin-top: var(--space-5);` |
| `.summary-card` | Одна summary-карточка | `min-width: 200px; flex: 1; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4);` |
| `.summary-card-icon` | Иконка-заглушка в summary-карточке | `font-size: 20px; color: var(--color-muted); margin-bottom: var(--space-2);` |
| `.summary-card-value` | Значение в summary-карточке | `font-size: 28px; font-weight: 700; color: var(--color-text); line-height: 1.2;` |
| `.summary-card-label` | Подпись в summary-карточке | `font-size: 12px; color: var(--color-muted); margin-top: var(--space-1);` |
| `.placeholder-nav` | Список будущих разделов (secondary nav) | `display: flex; flex-direction: column; gap: var(--space-2); margin-bottom: var(--space-5);` |
| `.placeholder-nav-item` | Элемент списка будущих разделов | `display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-muted);` |
| `.placeholder-nav-icon` | Иконка-заглушка в nav-элементе | `font-size: 16px; opacity: 0.5;` |
| `.badge-soon` | Бейдж "Скоро" | `display: inline-flex; align-items: center; min-height: 22px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #eef2f7; color: var(--color-muted);` |
| `.cards-grid` | Сетка 2×2 для карточек модулей | `display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-5); margin-bottom: var(--space-5);` |
| `.module-card` | Карточка модуля | `background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-5);` |
| `.module-card-icon` | Иконка-заглушка в карточке модуля | `font-size: 28px; margin-bottom: var(--space-3); opacity: 0.6;` |
| `.module-card-title` | Название модуля | `font-size: 16px; font-weight: 700; margin-bottom: var(--space-2); color: var(--color-text);` |
| `.module-card-status` | Статусный бейдж внутри карточки | `display: inline-block; margin-bottom: var(--space-3);` — наследует `.status.status-neutral` |
| `.module-card-desc` | Описание модуля | `font-size: 13px; color: var(--color-muted); line-height: 1.5;` |

### Кнопки

Не используются. Если в шаблоне встретится кнопка — она должна быть disabled (атрибут `disabled` + класс `.is-disabled`).

### Текст

- Заголовки: обычный цвет (`var(--color-text)`), без класса
- Описания / muted текст: `color: var(--color-muted)` через класс `.module-card-desc`, `.summary-card-label` или inline `<small>`

## 14. Strict prohibitions for coder

- Не придумывать layout.
- Не придумывать новые CSS-классы без решения дизайнера/архитектора — только перечисленные выше новые классы.
- Не менять бизнес-логику (Stage 1 — её нет).
- Не использовать Bootstrap/Tailwind/React/Vue/Material.
- Не добавлять inline styles, кроме разрешённых динамических PHP values.
- Не менять этот шаблон без дизайнера.
- `border-radius` не больше 4px — исключение: существующие переменные `--radius-sm: 6px`, `--radius-md: 8px` уже используются в `app.css`, разрешено наследовать их.
- `box-shadow` blur не больше 8px — Stage 1 без box-shadow.
- Не использовать browser-default input/select — Stage 1 без input/select.
- Не подключаться к БД (ни к центральной, ни к локальной).
- Не добавлять авторизацию, сессии, проверку прав.
- Не добавлять реальные данные или компании.
- Не делать навигационные пункты кликабельными.
- Не добавлять формы.
- Не добавлять интерактивность (JS-обработчики), кроме уже существующего `app.js`.

## 15. Secondary navigation (будущие разделы SUPERADMIN)

Располагается между информационным alert и сеткой карточек модулей.

Заголовок: "Будущие разделы SUPERADMIN"

Список (все элементы disabled, серые, не кликабельные):

| # | Иконка-заглушка | Название раздела | Пометка |
|---|---|---|---|
| 1 | `[🏢]` | Управление компаниями | Скоро |
| 2 | `[👤]` | Пользователи SUPERADMIN | Скоро |
| 3 | `[⚙]` | Feature toggles | Скоро |
| 4 | `[🔧]` | Системные настройки | Скоро |
| 5 | `[📊]` | Мониторинг | Скоро |
| 6 | `[📋]` | Логи и аудит | Скоро |

Каждый пункт:
- Контейнер: `.placeholder-nav-item.is-disabled`
- Иконка-заглушка (текстовая, в квадратных скобках)
- Название раздела (muted)
- Бейдж "Скоро" (`.badge-soon`)
- Курсор: default (не pointer)
- Атрибут: `disabled` (или `aria-disabled="true"`)

## 16. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён — используются существующие токены из `app.css`.
- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`.
- [ ] Таблицы — не применимо (нет таблиц).
- [ ] Формы — не применимо (нет форм).
- [ ] Layout соответствует `PAGE_PATTERN.md`.
- [ ] Empty/loading/error states описаны и реализованы.
- [ ] Опасные действия — не применимо (нет действий).
- [ ] Кодер может реализовать страницу без поиска примеров.
- [ ] QA может проверить страницу по этому шаблону.
- [ ] Нет случайных CSS-классов — только перечисленные существующие и новые.
- [ ] Нет хардкода цветов — только через CSS-переменные.
- [ ] Нет inline styles, кроме динамических PHP (не требуются).
- [ ] Runtime/browser check выполнен: страница открывается по `/superadmin`, layout `main.php` используется, заголовок корректный, все элементы статичные.
- [ ] Все навигационные пункты — disabled (серые, не кликабельные).
- [ ] Все карточки модулей — статичные, не кликабельные.
- [ ] Информационный alert отображается.
- [ ] 4 summary-карточки отображаются с прочерками и "OK".
- [ ] Новые CSS-классы добавлены в `public/assets/css/app.css`.
- [ ] `php -l` для `app/View/pages/superadmin_dashboard.php` — без ошибок.
- [ ] Маршрут `/superadmin` зарегистрирован в `public/index.php`.
- [ ] Структура папок `app/Superadmin/` создана.
- [ ] Нет обращений к БД.
- [ ] Нет авторизации / сессий.
- [ ] Нет реальных данных.
- [ ] `docs/ai/AGENT_WORK_LOG.md` обновлён.
- [ ] `docs/ai/PROJECT_STATUS.md` обновлён при необходимости.
