# UI PAGE TEMPLATE — [Название страницы]

Этот шаблон является источником истины для `erp-coder` и `erp-qa-tester`. Дизайнер (`erp-uiux-designer`) обязан заполнить все применимые секции до передачи задачи кодеру. Кодер реализует страницу строго по этому шаблону.

## 0. LAYOUT FOUNDATION SOURCE MAPPING (ОБЯЗАТЕЛЬНО)

Этот раздел обязателен для каждого handoff. Если раздел отсутствует — handoff не может быть `DONE`.

Дизайнер обязан явно подтвердить соответствие shell/foundation STYLE ERP MASTER (`TransportERP_MASTER_UI_RULES.md`) до описания компонентов страницы.

### 0a. App Shell Foundation

| Параметр | MASTER spec | Текущая реализация | Соответствие |
|----------|-------------|-------------------|--------------|
| App shell grid | `grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr` | | YES/NO |
| Topbar position | spans full grid (grid-column 1/-1) | | YES/NO |
| Topbar background | `var(--surface-strong)` #fefdf8 СВЕТЛЫЙ | | YES/NO |
| Topbar border-bottom | `1px solid var(--line)` | | YES/NO |
| User block (topbar right) | имя + роль пользователя | | YES/NO |
| Sidebar background | `var(--nav-bg)` #191816 | | YES/NO |
| Sidebar border-right | `1px solid var(--nav-divider)` | | YES/NO |
| Nav item height | 34px | | YES/NO |
| Nav item font-weight | 600 ВСЕГДА | | YES/NO |
| Nav item font-size | 12.5px | | YES/NO |
| Nav icons | SVG inline 16×16, opacity 0.45 | | YES/NO |
| Nav section label | 9px, 700, uppercase, letter-spacing .12em | | YES/NO |
| Nav active state | `::before` pseudo (2px gold left) | | YES/NO |
| Nav bottom block | `.nav-spacer` + `.nav-bottom` + Настройки | | YES/NO |

**Если хотя бы одна строка = NO:** дизайнер возвращает `BLOCKED: NEEDS_LAYOUT_FOUNDATION_FIX` до передачи handoff архитектору.

### 0b. SIDEBAR INFORMATION ARCHITECTURE (ОБЯЗАТЕЛЬНО)

Дизайнер обязан указать полную структуру навигации для данной страницы. Кодер не придумывает IA самостоятельно.

```
[section] ОПЕРАЦИИ
  [nav-item] Рейсы         (disabled / active / -)
  [nav-item] Водители      (disabled / active / -)
  [nav-item] Транспорт     (disabled / active / -)
  [nav-item] Клиенты       (disabled / active / -)

[nav-spacer]

[section] СИСТЕМА
  [nav-item] SUPERADMIN    (disabled / active / -)

[nav-bottom]
  [nav-item] Настройки     (disabled / active / -)
```

Заполни структуру выше для данной страницы. Укажи, какой пункт `.is-active`.

**Правило:** операционные модули не смешиваются с системными (SUPERADMIN). SUPERADMIN всегда в отдельной системной секции.

## 1. Страница

- Route / view:
- Тип страницы: list+inspector / table-only / master-detail / form / admin settings / report
- Пользователь (роль):
- Главная задача пользователя:
- Что нельзя менять в бизнес-логике:

## 2. Layout

- App shell: topbar 38px + sidebar 224px + content
- Content min-width: 1440px (desktop-first)
- Основная сетка:
- Правый inspector: да/нет, ширина:
- Нижняя форма/editor: да/нет
- Scroll areas:

## 3. Page head

- Eyebrow:
- Title:
- Summary counters:
- Primary action (`.btn .btn-primary`):
- Secondary actions:

## 4. Filters / toolbar

- Filters-bar: да/нет
- Поля фильтрации:
- Filter chips: да/нет
- Toolbar actions:

## 5. Main table / grid

- Component: `.tbl` inside `.tbl-wrap`
- Row height: 32px
- Thead height: 30px
- Header sticky: да/нет
- Columns:

| # | Название | Тип | Класс | Ширина/поведение | Примечание |
|---|---|---|---|---|---|
| 1 | | | | | |

- Row states: hover / selected (`.is-sel`) / empty
- Row actions (показывать на hover):
- Bulk actions:

## 6. Inspector / detail panel

- Нужен: да/нет
- Width (обычно 320–380px):
- Header:
- Status badge:
- Tabs:
- Sections:
- Quick actions:

## 7. Forms

- Где форма: modal / inline / bottom editor / separate page
- Grid: 1/2/3 columns
- Field list:

| Поле | Component class | Required | Validation | Help/error text |
|---|---|---|---|---|
| | | | | |

- Form states:
  - error (`.is-error`, `.field-msg`):
  - success (`.is-success`):
  - readonly:
  - disabled:

## 8. Modals

| Modal | Size | Trigger | Fields/content | Footer buttons |
|---|---|---|---|---|
| | | | | |

- Danger confirmations:

## 9. Toasts / alerts

- Success toast:
- Error toast:
- Warning alert:
- No permission state:

## 10. Empty / loading / error

- Empty table state:
- Loading skeleton:
- Error state:

## 11. Statuses and badges

- Используемые badges:
- Flight statuses, если есть:
  - search / found / started / completed / attention / planned_route

## 12. Charts, если есть

- Chart type:
- Data series:
- Colors: `--chart-1 … --chart-8`
- Legend:
- Empty/loading state:

## 13. CSS/classes coder must use

- Layout classes:
- Components:
- Buttons: `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-toolbar`, `.btn-icon`, `.btn-danger`
- Table: `.tbl`, `.tbl-wrap`, `.tbl-compact`, `.tbl-striped`, `.col-num`, `.col-chk`, `.col-actions`, `.col-mono`, `.col-muted`, `.col-pin`, `.tr-total`, `.row-acts`, `.ra`, `.ra.del`
- Forms: `.field`, `.field-label`, `.req`, `.field-input`, `.field-select`, `.field-textarea`, `.field-msg`, `.is-error`, `.is-success`
- States: `.is-sel`, `.is-disabled`, `.is-active`
- Inspector: `.inspector`, `.inspector-header`, `.inspector-title`, `.inspector-tabs`, `.inspector-body`, `.insp-section`, `.kv-row`

## 14. UI modules used

- Core UI kit path:
  `docs/ui/ERP_UI_KIT_CORE.html`
- Legacy extraction draft:
  `docs/ui/ERP_UI_MODULE_CATALOG.html` — reference/history only, not primary designer source
- Composite pattern selected:
  - `PATTERN-xx [название]` — причина выбора:
- CORE modules used:
  - `CORE-xx [название]` — причина выбора:
  - `CORE-xx [название]` — причина выбора:
- Missing modules:
  - `none` / описание отсутствующего модуля
- If missing module exists:
  `BLOCKED: NEEDS_UI_MODULE_EXPANSION`
- Coder may invent new UI module: **NO**
- Private/page-specific module names allowed in universal handoff: **NO**

## 15. MODULE USAGE DECISIONS

- Main page purpose:
- Primary work object:
- Main layout selected:
- Composite pattern selected:
- Primary action:
- Secondary actions:
- Table required: YES/NO, why:
- Form required: YES/NO, why:
- Inspector required: YES/NO, why:
- Filters required: YES/NO, why:
- Modal required: YES/NO, why:
- Empty/error/loading states required:
- Modules used with reasons:
- Modules explicitly not used with reasons:
- Private applied example names explicitly not used:

## 15a. SOURCE MAPPING (MANDATORY)

Каждый page handoff дизайнера обязан содержать таблицу SOURCE MAPPING, которая связывает каждый UI-элемент страницы с его источником в дизайн-системе.

Если SOURCE MAPPING отсутствует, handoff не может быть `DONE`.

Формат таблицы:

| # | UI element | CORE module ID | Exact source in ERP_UI_KIT_CORE.html | STYLE ERP ref (if any) | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------------------|------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS Reference > .app-shell | — | `.app-shell` | Bootstrap .container |
| 2 | Sidebar nav | CORE-02 | Production CSS Reference > .nav-item, .nav-item:hover, .nav-item.is-active | — | `.nav-item`, `.is-active` | `a.nav-item { color: inherit }` |
| 3 | Topbar | CORE-03 | Production CSS Reference > .topbar | — | `.topbar`, `environment-badge` (opt) | Hero header |
| ... | ... | ... | ... | ... | ... | ... |

Правила SOURCE MAPPING:

- Каждая строка обязана указывать точный CORE module ID;
- Exact source должен ссылаться на конкретную секцию `ERP_UI_KIT_CORE.html` (например, "Production CSS Reference > .panel-head");
- Required classes должны дословно совпадать с классами из Core Kit;
- Forbidden alternatives явно перечисляют, что кодеру запрещено использовать вместо указанного класса;
- Если дизайнер вводит новый CSS-класс, он должен быть сначала добавлен в Core Kit как sub-element соответствующего CORE-модуля;
- Если класс не оформлен в Core Kit — handoff не может быть `DONE`;
- Если новый child-класс вставляется внутрь существующего блока с padding, дизайнер обязан описать CSS compatibility rule.

## 15b. CSS COMPATIBILITY CHECK (MANDATORY)

Для каждого нового класса или дочернего элемента внутри существующего контейнера дизайнер обязан указать CSS compatibility notes:

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------| ------------|-----------------|
| `.panel` | `.panel-head` | 0 (by rule) | Yes — head flush to top | `.panel-body` (10px) | `--line-hair` | `h2` or `.panel-head-title` for heading |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | — | text-muted, disabled button |
| `.kv` | `dt, dd` or `div > .k` | — | — | `5px 0` per row | `--line-hair` | `border-bottom` mandatory, `font-weight: 700` on key |
| `.nav-list` | `.nav-item` | — | — | — | `--nav-gold` on active | `:hover` mandatory, `background: var(--nav-hover)` |
| `.topbar` | `.environment-badge` | — | — | — | — | Optional. Only if in handoff. |

Правила CSS COMPATIBILITY CHECK:

- Если родительский контейнер имеет padding, а дочерний элемент должен быть flush к краю — дизайнер обязан явно описать это правило;
- Для интерактивных элементов (nav-item, button) обязательны hover/focus/disabled states;
- Для key-value строк обязателен border-bottom divider;
- Если child-элемент добавляется в существующий блок без compatibility check — handoff не `DONE`.

## 16. Strict prohibitions for coder

- Не придумывать layout.
- Не придумывать новые CSS-классы без решения дизайнера/архитектора.
- Не придумывать новый UI-модуль.
- Не реализовывать модуль, которого нет в `docs/ui/ERP_UI_KIT_CORE.html` или профильных MD.
- Не реализовывать private/page-specific module names as universal modules.
- Не менять бизнес-логику.
- Не использовать Bootstrap/Tailwind/React/Vue/Material.
- Не добавлять inline styles, кроме разрешённых динамических PHP values.
- Не менять этот шаблон без дизайнера.
- `border-radius` не больше 4px (новые элементы).
- `box-shadow` blur не больше 8px (новые элементы).
- Не использовать browser-default input/select.
- **Не использовать demo-placeholder UI**: псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`; emoji как иконки; карточный SaaS-dashboard для admin/settings; большие пустоты; blue/white corporate UI; случайные цвета/классы; debug badges как основной визуальный элемент.

## 17. Owner visual check (обязательно для новых/изменённых экранов)

- **VISUAL CHECK URL**: [указать URL страницы]
- **Что владелец должен проверить глазами**:
  - [ ] Industrial Graphite + Warm Accent сохранён.
  - [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`.
  - [ ] Нет emoji как иконок.
  - [ ] Нет demo-placeholder/SaaS-dashboard вида (для admin/settings страниц).
  - [ ] Нет больших пустот.
  - [ ] Sidebar тёмный 224px, nav текст светлый.
  - [ ] Topbar 38px.
  - [ ] Плотная рабочая композиция.
  - [ ] Нет случайных цветов вне утверждённой палитры.
  - [ ] Нет случайных CSS-классов.
  - [ ] Нет blue/white corporate UI.
  - [ ] `border-radius` в пределах design code.
  - [ ] `box-shadow` blur в пределах design code.
- **Возможные визуальные блокеры**:
  - [перечислить]
- **Manual owner visual review required**: YES
- **Commit allowed before owner visual approval**: NO

## 18. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён.
- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`.
- [ ] Страница использует CORE modules и COMPOSITE pattern из `docs/ui/ERP_UI_KIT_CORE.html` и профильных MD.
- [ ] Раздел `UI modules used` заполнен CORE module IDs и выбранным PATTERN.
- [ ] Раздел `MODULE USAGE DECISIONS` заполнен с причинами выбора.
- [ ] Missing modules: `none` или задача остановлена с `BLOCKED: NEEDS_UI_MODULE_EXPANSION`.
- [ ] Нет private/page-specific module names в universal handoff.
- [ ] Таблицы соответствуют `TABLES_STANDARD.md`.
- [ ] Формы соответствуют `FORMS_STANDARD.md`.
- [ ] Layout соответствует `PAGE_PATTERN.md`.
- [ ] Empty/loading/error states описаны и реализованы.
- [ ] Опасные действия имеют подтверждение.
- [ ] Кодер может реализовать страницу без поиска примеров.
- [ ] QA может проверить страницу по этому шаблону.
- [ ] Нет случайных CSS-классов.
- [ ] Нет хардкода цветов.
- [ ] Нет inline styles, кроме динамических PHP.
- [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`, emoji как иконок.
- [ ] Нет demo-placeholder/SaaS-dashboard UI.
- [ ] Нет Bootstrap/Tailwind/Material классов.
- [ ] `border-radius` новых элементов ≤ 4px.
- [ ] `box-shadow blur` новых элементов ≤ 8px.
- [ ] Runtime/browser check выполнен.
- [ ] Formal UI QA: PASS.
- [ ] VISUAL CHECK URL предоставлен.
- [ ] Manual owner visual review required: YES (для новых/изменённых экранов).
- [ ] Commit allowed before owner visual approval: NO (для новых/изменённых экранов).

## 19. Designer production handoff

Этот раздел обязателен для важных UI-экранов. Если он не заполнен конкретно, handoff считается `NOT DONE`.

Перед заполнением дизайнер обязан использовать:

- `docs/ui/DESIGN_CODE_INTEGRATION.md`
- `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`
- `docs/ui/ERP_UI_KIT_CORE.html`
- `docs/ui/PAGE_PATTERN.md`
- `docs/ui/FORMS_STANDARD.md`
- `docs/ui/TABLES_STANDARD.md`

Запрещено писать кодеру "посмотри STYLE ERP" или "сделай как в STYLE ERP". Все нужные правила должны быть здесь, в page handoff.

### Current visual problems

- [описать текущие визуальные проблемы, если экран уже существует]

### Target visual result

- [описать целевой результат: какой рабочий business/admin экран должен получиться]

### Exact layout

- App shell:
- Grid / sections order:
- Scroll areas:
- Sidebar active state:
- Topbar context:
- Production pattern: list+inspector / table-only / master-detail / form/editor / admin-settings / report-charts

### Exact typography

- Page title:
- Subtitle:
- Section headings:
- Body text:
- Status text:

### Exact spacing

- Page/header spacing:
- Panel padding:
- Section gap:
- Table row height / key-value row height:

### Exact color tokens

- Background:
- Surface:
- Text:
- Muted:
- Accent:
- Borders:
- Statuses:

### Required sections

- [перечислить все обязательные блоки сверху вниз]

### Required states

- Empty:
- Loading:
- Error:
- Disabled/readonly:
- Hover/focus/active:
- Selected:

### Forbidden texts/classes/patterns

- Forbidden texts:
- Forbidden classes:
- Forbidden visual patterns:

### Coder implementation checklist

- [ ] Реализовать только по этому handoff.
- [ ] Не добавлять новые классы/цвета/sections без дизайнера.
- [ ] Не добавлять demo/foundation/showcase wording.
- [ ] Не менять backend/business logic без отдельного задания.

### QA formal checklist

- [ ] Required sections на месте.
- [ ] Forbidden texts/classes/patterns отсутствуют.
- [ ] Page title/subtitle/topbar/sidebar соответствуют handoff.
- [ ] Formal UI QA: PASS/FAIL.

### Architect pre-owner review checklist

- [ ] Исходная визуальная проблема устранена.
- [ ] Экран не похож на demo/foundation/showcase/SaaS-dashboard.
- [ ] Экран сообщает конкретное business/admin назначение.
- [ ] Не нужен повторный designer/coder/QA цикл.

### Owner visual checklist

- [ ] Экран выглядит как рабочая ERP/admin страница.
- [ ] Плотность, палитра, sidebar/topbar и sections соответствуют master UI-kit.
- [ ] Нет visual blockers.
