# ERP PLANEX — TABLES_STANDARD

## Назначение

Этот файл задаёт стандарт таблиц ERP PLANEX.

Главный UI-регламент: `docs/ui/DESIGN_CODE_INTEGRATION.md`.

Формализованные правила из STYLE ERP: `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`.

Основной компактный UI-kit: `docs/ui/ERP_UI_KIT_CORE.html`.

Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html`.

---

## Стандарт таблиц

Таблицы должны быть читаемыми и рабочими.

Минимально:

- понятные заголовки;
- основные идентификаторы сущности;
- статус;
- дата создания/изменения, если важно;
- основные действия;
- поиск/фильтр там, где записей много;
- пагинация при необходимости;
- empty state;
- error/loading state, если данные загружаются.

Из STYLE ERP извлечены обязательные ERP-grid rules:

- таблица является главным рабочим объектом списков;
- `.tbl-wrap` вокруг `.tbl`;
- sticky `thead`;
- thead height: 30px;
- row height: 32px, compact 26px только по handoff;
- table body font-size: 12-12.5px;
- header: 11px, 700, uppercase, letter spacing;
- cells use `white-space: nowrap` where table is horizontally scrollable;
- numeric columns right-aligned with tabular nums;
- code/phone/INN/date columns use mono/tabular treatment;
- row hover is subtle warm surface, not bright accent;
- selected row uses subtle copper background/line;
- row actions hidden until hover;
- bulk action bar appears only when rows selected;
- pagination required for long lists.

Каждый table/grid-модуль в handoff должен ссылаться на CORE module из `ERP_UI_KIT_CORE.html`, например `CORE-13 Data table / ERP grid`, `CORE-14 Table row states`, `CORE-15 Table row actions`, `CORE-16 Pagination`. Если нужен новый table pattern, сначала расширяется Core Kit / профильные MD:

```text
BLOCKED: NEEDS_UI_MODULE_EXPANSION
```

---

## Правило для дизайнера

Если страница содержит таблицу, дизайнер обязан описать её в MD-шаблоне страницы:

```text
docs/ui/pages/[page-name].md
```

Нужно указать колонки, порядок, классы, действия строки, массовые действия, состояния и пагинацию.

---

## Правило для кодера

Кодер реализует таблицу строго по MD-шаблону страницы.

Кодер не должен сам выбирать колонки, порядок, статусы и действия.

---

## Запрещено

- Перегружать таблицу всеми полями БД.
- Делать непонятные сокращения.
- Скрывать важные статусы.
- Заменять рабочую таблицу декоративными карточками, если нужен список сущностей.
- Писать SQL прямо во view.
- Показывать row actions постоянно, если handoff не требует обратного.
- Использовать Bootstrap table look.
- Использовать карточки вместо строк для реестров.
