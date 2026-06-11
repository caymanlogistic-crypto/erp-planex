# UI PAGE TEMPLATE — [Название страницы]

## 1. Страница

- Route / view:
- Тип страницы: list+inspector / table-only / master-detail / form / admin settings / report
- Пользователь:
- Главная задача пользователя:
- Что нельзя менять в бизнес-логике:

## 2. Layout

- App shell:
- Основная сетка:
- Правый inspector: да/нет
- Нижняя форма/editor: да/нет
- Scroll areas:

## 3. Page head

- Eyebrow:
- Title:
- Summary counters:
- Primary action:
- Secondary actions:

## 4. Filters / toolbar

- Filters-bar: да/нет
- Поля фильтрации:
- Filter chips: да/нет
- Toolbar actions:

## 5. Main table / grid

- Component:
- Columns:

| # | Название | Тип | Класс | Ширина/поведение | Примечание |
|---|---|---|---|---|---|
| 1 | | | | | |

- Row states:
- Row actions:
- Bulk actions:

## 6. Inspector / detail panel

- Нужен: да/нет
- Width:
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
  - error:
  - success:
  - readonly:
  - disabled:

## 8. Modals

| Modal | Size | Trigger | Fields/content | Footer buttons |
|---|---|---|---|---|
| | | | | |

## 9. Toasts / alerts

- Success toast:
- Error toast:
- Warning alert:
- No permission state:

## 10. Empty / loading / error

- Empty state:
- Loading state:
- Error state:

## 11. Statuses and badges

- Используемые badges:
- Flight statuses, если есть:

## 12. CSS/classes coder must use

- Layout classes:
- Components:
- Buttons:
- Table:
- Forms:
- States:

## 13. Strict prohibitions for coder

- Не придумывать layout.
- Не придумывать новые CSS-классы без решения дизайнера/архитектора.
- Не менять бизнес-логику.
- Не использовать Bootstrap/Tailwind/React/Vue/Material.
- Не добавлять inline styles, кроме разрешённых динамических PHP values.
- Не менять этот шаблон без дизайнера.

## 14. Acceptance checklist

- [ ] Страница соответствует `DESIGN_CODE_INTEGRATION.md`.
- [ ] Таблицы соответствуют `TABLES_STANDARD.md`.
- [ ] Формы соответствуют `FORMS_STANDARD.md`.
- [ ] Layout соответствует `PAGE_PATTERN.md`.
- [ ] Empty/loading/error states описаны.
- [ ] Опасные действия имеют подтверждение.
- [ ] Кодер может реализовать страницу без поиска примеров.
- [ ] QA может проверить страницу по этому шаблону.
