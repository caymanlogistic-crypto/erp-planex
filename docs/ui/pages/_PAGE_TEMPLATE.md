# UI PAGE TEMPLATE — [Название страницы]

Этот шаблон является источником истины для `erp-coder` и `erp-qa-tester`. Дизайнер (`erp-uiux-designer`) обязан заполнить все применимые секции до передачи задачи кодеру. Кодер реализует страницу строго по этому шаблону.

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

## 14. Strict prohibitions for coder

- Не придумывать layout.
- Не придумывать новые CSS-классы без решения дизайнера/архитектора.
- Не менять бизнес-логику.
- Не использовать Bootstrap/Tailwind/React/Vue/Material.
- Не добавлять inline styles, кроме разрешённых динамических PHP values.
- Не менять этот шаблон без дизайнера.
- `border-radius` не больше 4px (новые элементы).
- `box-shadow` blur не больше 8px (новые элементы).
- Не использовать browser-default input/select.
- **Не использовать demo-placeholder UI**: псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`; emoji как иконки; карточный SaaS-dashboard для admin/settings; большие пустоты; blue/white corporate UI; случайные цвета/классы; debug badges как основной визуальный элемент.

## 15. Owner visual check (обязательно для новых/изменённых экранов)

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

## 16. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён.
- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`.
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
