# ERP PLANEX — STYLE ERP Extracted Rules

## Назначение

`C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\` — папка визуальных образцов TransportERP / ERP PLANEX.

STYLE ERP не является runtime-библиотекой, библиотекой компонентов, dependency, UI package или источником кода для копирования в проект.

STYLE ERP используется только так:

1. Codex/ChatGPT и `erp-uiux-designer` изучают образцы.
2. Значимые правила формализуются в MD-документах проекта.
3. `erp-coder` получает только MD handoff и проектные UI-документы.
4. Кодер не открывает STYLE ERP и не выбирает дизайн самостоятельно.

Если нужное правило есть только в STYLE ERP, но не формализовано в MD, дизайнер обязан сначала перенести правило в MD/handoff. После этого задача может идти кодеру.

## UI Kit Core and legacy extraction catalog

Основной компактный рабочий UI-kit для дизайнера создан здесь:

```text
docs/ui/ERP_UI_KIT_CORE.html
```

Он содержит CORE modules, COMPOSITE patterns, APPLIED examples, decision matrices и SUPERADMIN ready set. `erp-uiux-designer` должен начинать page handoff с выбора CORE modules и COMPOSITE pattern.

Большой каталог извлечения остаётся здесь:

```text
docs/ui/ERP_UI_MODULE_CATALOG.html
```

`ERP_UI_MODULE_CATALOG.html` — legacy extraction draft / история извлечения 224 пунктов из STYLE ERP. Он не является основным рабочим каталогом для дизайнера и не обновляется в обычных UI-задачах.

STYLE ERP уже изучен, и значимые модули перенесены в MD/catalog. Дизайнер не имеет права использовать новый интерфейсный блок в page handoff, пока этот блок не формализован в:

- `docs/ui/ERP_UI_KIT_CORE.html`;
- профильном MD-документе из `docs/ui/`;
- page handoff с указанием CORE module и COMPOSITE pattern.

Private applied examples не являются названиями универсальных модулей. Например, screen-specific names from a drivers example may be mentioned only in APPLIED EXAMPLES/source notes, not as module names in universal handoff.

Если нужного модуля нет, дизайнер возвращает:

```text
BLOCKED: NEEDS_UI_MODULE_EXPANSION
```

## Изученные STYLE ERP файлы

- `TransportERP_MASTER_UI_RULES.md` — главный master UI rules документ.
- `transporterp_ui_showcase.html` — общий showcase tokens/components.
- `transporterp_nav_v1.html` — navigation/sidebar/topbar patterns.
- `transporterp_tables_v1.html` — ERP-grid, toolbar, row actions, bulk actions.
- `transporterp_forms_v1.html` — form controls, fieldsets, lookup, upload, custom select, multiselect, inline edit.
- `transporterp_drivers_v19.html` — рабочий list + inspector экран.
- `transporterp_missing_v1.html` — tabs, modal, toast, filters, pagination, empty/error/loading/skeleton, stepper, context menu.
- `transporterp_charts_v1.html` — chart cards, chart tokens, legends, Chart.js patterns.

## Product Direction

Visual direction: `Industrial Graphite + Warm Accent`.

ERP PLANEX — desktop-first operational ERP, not landing page, not SaaS-dashboard, not Bootstrap admin, not Material UI.

Target feeling:

- dense;
- strict;
- tabular;
- operational;
- calm;
- admin/workbench-like;
- designed for repeated daily work on FHD desktop.

## Technical Use Rules

Allowed:

- Vanilla HTML/CSS/JS/PHP views.
- CSS custom properties.
- Simple PHP view components/partials.
- Chart.js 4.4.1 from cdnjs only when a chart task explicitly needs it.

Forbidden:

- React/Vue/Angular/Svelte.
- Tailwind/Bootstrap/Material UI.
- npm/build pipeline/webpack/vite.
- SPA architecture.
- Heavy admin templates.
- Inline styles except dynamic PHP values like `style="width: <?= $pct ?>%"`.
- Random CSS classes outside the system.
- Business logic changes for UI.

## Canonical Tokens

### Shell

- `--sidebar-w: 224px`
- `--topbar-h: 38px`
- `--app-bg: #e2dfd8`

### Navigation

- `--nav-bg: #191816`
- `--nav-hover: #272522`
- `--nav-divider: rgba(255,255,255,.055)`
- `--nav-active-bg: rgba(140,82,28,.28)`
- `--nav-gold: #c8933a`
- `--nav-text: rgba(240,238,232,.65)`
- `--nav-text-h: rgba(240,238,232,.88)`
- `--nav-text-act: #d4a254`
- `--nav-icon-op: .45`
- `--nav-sec-clr: rgba(240,238,232,.22)`

### Surfaces

- `--surface-muted: #e8e4db`
- `--surface-form: #f0ede6`
- `--surface-inspector: #f3f1eb`
- `--surface: #f5f3ee`
- `--surface-strong: #fefdf8`
- `--surface-field: #fefdf8`

Surface hierarchy must remain readable. Avoid beige mass: panels, page heads, toolbars, fields and inspector must use different surface levels.

### Lines

- `--line-hair: #dedad0`
- `--line-soft: #c9c3b8`
- `--line: #a8a196`
- `--line-strong: #827b70`

### Text

- `--text-main: #131210`
- `--text-muted: #4c4840`
- `--text-faint: #78726a`
- `--inverse: #f5f3ee`

### Accent

- `--accent: #7c4718`
- `--accent-hover: #683c13`
- `--accent-deep: #4e2d0e`
- `--accent-light: #c09060`
- `--accent-line: #a06830`
- `--accent-bg: #e8dbc8`

Copper accent is for primary action, active state, selected line and key focus. Do not paint whole pages copper.

### Controls and Density

- `--control-h: 28px`
- `--btn-h: 30px`
- `--toolbar-h: 26px`
- `--row-h: 32px`
- `--thead-h: 30px`

## Typography

- Font stack: `"IBM Plex Sans", "Segoe UI", Arial, sans-serif`.
- Body: `font-variant-numeric: tabular-nums; -webkit-font-smoothing: antialiased;`
- Page title: 15-16px, 700.
- Panel/section title: 12px, 700.
- Nav section label: 9px, 700, uppercase, letter spacing.
- Nav item: 12.5px, 600. Do not change weight for active items.
- Table header: 11px, 700, uppercase, letter spacing.
- Table body: 12-12.5px.
- Field label: 10-11px.
- Badge/status: 11px, 700.
- Button: 12px, 600.

## App Shell Rules

Desktop-first shell:

- full viewport height;
- min-width 1440px;
- grid: sidebar 224px + content;
- topbar 38px across full width;
- content scrolls vertically;
- sidebar has its own thin scrollbar;
- topbar contains brand zone, breadcrumbs/search/context, user zone.

Use page content as a dense workbench:

```text
page-head
filters/toolbar
main table / work area
right inspector when needed
bottom editor when needed
pagination / status line
```

## Sidebar / Navigation Rules

- Sidebar width: 224px.
- Background: `--nav-bg`.
- Section labels are tiny uppercase labels.
- Nav item height: 34px.
- Nav item font-weight: 600 always.
- Active nav uses `--nav-active-bg`, `--nav-text-act`, and a 2px left copper marker.
- Nav icons are 16x16, opacity `.45`, `.8` on hover/active.
- `a.nav-item` must remove decoration and outline only. Do not set `color: inherit`.
- Sidebar item for the current route must be active. Wrong active state is a QA failure.

## Topbar Rules

- Height: 38px.
- Background: `--surface-strong`.
- Border-bottom: `--line`.
- Left brand block aligns to sidebar width.
- Middle zone communicates current route context with breadcrumbs/search/context.
- Right zone may contain user/avatar/environment.
- Business/admin pages must not show `UI foundation` as topbar context.

## Page Header Rules

Page head must be compact and contextual:

- title names the real business/admin object;
- subtitle explains page purpose in one short line;
- actions are right-aligned;
- no abstract `Основное действие`;
- no demo/showcase/foundation wording on business/admin pages;
- page-head is 36-52px high depending complexity, not hero-scale.

## Layout Patterns

Use one of these:

- List + inspector: page-head, filters-bar, left table/work area, right inspector 320-380px, pagination.
- Table-only: page-head, toolbar/filter, full table, pagination.
- Master-detail: entity title/status/actions, tabs, left sections, right summary/activity.
- Form/editor: fieldsets, grouped sections, sticky/clear actions.
- Admin/settings: page-head, tabs if needed, settings/admin sections, tables/forms/panels, compact key-value info.
- Report/charts: page-head, filters, chart cards in 2/3 columns, table detail if needed.

Do not use showcase composition as a production page.

## Panel Rules

- `.panel` / production equivalent: `--surface`, 1px `--line-hair`, radius 2px.
- Panel head height: 36px.
- Panel title: 12px, 700.
- Use panel subtext for muted context, not oversized cards.
- Use panels as work sections, not decorative cards.

## Table / ERP Grid Rules

Tables are primary ERP work objects.

Required behavior:

- `.tbl-wrap` around `.tbl`;
- sticky table head;
- thead height 30px;
- row height 32px, compact rows 26px only when explicitly designed;
- header uppercase 11px;
- numeric columns right-aligned with tabular nums;
- code/phone/INN/date columns use mono/tabular treatment;
- row hover is subtle warm surface;
- selected row uses subtle copper background/line;
- row actions are hidden until hover;
- bulk action bar appears only when rows are selected;
- pagination when list can exceed one page.

Forbidden:

- Bootstrap table look;
- cards instead of rows for lists;
- visible row actions all the time;
- bright hover colors;
- rounded table cells above 2px.

## Form Rules

Forms use unified ERP controls, not browser-default controls.

Core classes/patterns:

- `.field`
- `.field-label`
- `.req`
- `.field-input`
- `.field-select`
- `.field-textarea`
- `.field-msg`
- `.is-error`
- `.is-success`

Control height: 28px. Buttons: 30px. Toolbar controls: 26px.

Field groups:

- use fieldsets/sections for logical groups;
- 2/3 column grids are allowed for dense editors;
- required marker sits next to label;
- errors are under the field;
- readonly/disabled/error/success states must be specified in handoff.

Advanced patterns extracted from STYLE ERP:

- custom select with search;
- multiselect tags;
- entity lookup dropdown;
- file dropzone and inline file list/progress;
- input with prefix/suffix/action/clear button;
- numeric stepper;
- password strength;
- checkbox/radio groups;
- inline edit state.

These patterns must be formalized in the page handoff before coder implementation.

## Filter Rules

Filters are compact toolbar-like controls:

- `.filters-bar`;
- 36px bar height or compact equivalent;
- search input first when relevant;
- selects/date ranges after search;
- filter chips only when active filters are visible;
- clear action when chips exist;
- no giant filter cards.

## Inspector Rules

Inspector is used when selecting a table row should reveal operational details.

- Width: 320-380px.
- Background: `--surface-inspector`.
- Header includes entity title, secondary info and status.
- Compact tabs inside inspector: about 30px high.
- Body is divided into sections: operational info, documents, activity/log, linked objects.
- Key-value rows are preferred for facts.
- Inspector is not a dumping area for paragraphs.

## Key-Value Rules

Use key-value structure for system/admin facts:

- label: faint/muted, small;
- value: main text, tabular for ids/dates/numbers;
- rows separated by hairline or compact grid;
- no accent colors unless the value is a real status.

## Admin / Settings Rules

Admin/settings pages use dense sections and key-value/table/form blocks.

For SUPERADMIN and settings:

- no KPI dashboard cards unless owner explicitly approves;
- no SaaS card grid;
- no debug badges as main visual elements;
- no pseudo-icons;
- reserved modules appear as system sections with title, muted description and neutral status;
- page must communicate central admin purpose immediately.

## Status / Badge Rules

Semantic statuses:

- success: `#1f6b43`, bg `#ddeee5`, border `#7eb89b`;
- warning: `#7a5210`, bg `#fdf0d5`, border `#c9a450`;
- danger: `#992e26`, bg `#fcecea`, border `#ca8880`;
- neutral: `#625d57`, bg `#e3e0da`.

Flight statuses:

- `search`
- `found`
- `started`
- `completed`
- `attention`
- `planned_route`

Statuses are compact and muted, often with marker dot. Do not use acidic Bootstrap colors.

## Empty / Loading / Error Rules

Empty state:

- centered or compact inside panel;
- title 14px/700;
- description 12px;
- optional action only if it makes sense;
- explains what happened and what to do next.

Error state:

- clear human message;
- no raw PHP/SQL/debug errors;
- code like `ERR 404` only for technical/admin context.

Loading:

- skeleton rows for tables;
- shimmer cells with radius 2px;
- spinner only for short focused waits.

## Tabs, Modals, Toasts, Pagination

Tabs:

- page tabs: 36px;
- inspector tabs: 30px compact;
- active tab uses copper line/background, not bright colors.

Modals:

- sizes: small about 400px, medium about 600px, large about 820px;
- header 44px;
- footer with clear primary/secondary actions;
- danger confirmations require explicit title/body/action.

Toasts:

- bottom-right stack;
- 280-380px width;
- status-colored border/background;
- auto-dismiss with progress when appropriate.

Pagination:

- compact 26px controls;
- shown range text;
- page-size select when useful.

## Charts

Charts are allowed only for reporting/analytics tasks.

Rules:

- Chart.js 4.4.1 from cdnjs only;
- use `--chart-1` ... `--chart-8` for series;
- do not use semantic status colors for chart series;
- chart cards must include title/meta/legend;
- chart cards do not replace operational tables when the user needs records.

Allowed chart types from STYLE ERP:

- line with optional area;
- dual Y-axis line;
- grouped/stacked bar;
- horizontal bar;
- donut with center label;
- pie up to 8 segments;
- 100% stacked horizontal bar.

## Density and Spacing Rules

- Desktop/FHD first.
- Avoid hero spacing and landing-page hierarchy.
- Page padding: compact, commonly 10-20px depending shell.
- Section gaps: 6-16px, not large marketing gaps.
- Panel padding: 8-16px.
- Toolbar height: 36px section toolbar, 26px controls.
- Table row: 32px.
- Form control: 28px.
- Button: 30px.
- Radius standard: 2px.
- Radius maximum for new elements: 4px.
- Shadow blur maximum for new elements: 8px. Note: STYLE ERP examples contain a modal/context shadow with larger blur; ERP PLANEX rule remains max 8px unless owner explicitly approves a local exception.

## Forbidden Visual Patterns

- white/blue corporate UI;
- SaaS dashboard cards for admin/settings pages;
- landing page hero spacing;
- mobile-first layout;
- Bootstrap/Material look;
- glassmorphism/neumorphism;
- random gradients;
- random colors/classes;
- pseudo-icons `[=]`, `[#]`, `[~]`, `[v]`;
- emoji as icons;
- debug badges as primary visual elements;
- empty unused workspace;
- demo/foundation/showcase wording on business/admin pages.

## What Designer Must Do

Designer uses this document and project UI docs to create production-grade page handoff.

For each important UI screen, handoff must specify:

- screen type/pattern;
- exact layout;
- exact sections and order;
- exact title/subtitle/topbar/sidebar state;
- exact tables/forms/inspector/key-value blocks;
- exact classes/components/tokens;
- exact spacing/density;
- states: empty/loading/error/disabled/readonly/selected/hover/focus;
- forbidden texts/classes/patterns;
- coder checklist;
- QA checklist;
- architect pre-owner checklist;
- owner visual checklist.

Designer must not write: "посмотри STYLE ERP", "сделай как в STYLE ERP", "выбери подходящий блок".

## What Coder Must Not Do

Coder must not:

- open STYLE ERP to choose layout/components;
- copy HTML/CSS from STYLE ERP;
- invent design decisions;
- add random classes/tokens;
- use STYLE ERP as runtime library or component source;
- change backend/auth/CRUD/migrations in UI tasks;
- treat showcase examples as production page composition.

If handoff requires STYLE ERP lookup or admits several interpretations, coder returns:

```text
BLOCKED: NEEDS_DESIGNER_REWORK
```

## What QA Must Check

QA checks:

- implementation follows page handoff;
- handoff uses this extracted rule document;
- no direct coder dependency on STYLE ERP;
- page is not demo/showcase/foundation;
- title/subtitle/topbar/sidebar are page-specific;
- no forbidden classes/texts/colors/patterns;
- density, tokens, tables, forms, panels and states match MD;
- `Formal UI QA: PASS/FAIL`;
- `Architect pre-owner review required: YES`;
- `Manual owner visual review required: YES`;
- `Commit allowed before owner visual approval: NO`.

Formal QA PASS is not visual acceptance.
