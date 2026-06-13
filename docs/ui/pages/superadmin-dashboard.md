# UI PAGE TEMPLATE — SUPERADMIN Dashboard

## A. Rejected previous implementation

Этот раздел хранит историю визуально отклонённого варианта. Он не является актуальным handoff для кодера.

**Предыдущий реализованный вариант (Stage 1, commit `acd5009`) отклонён владельцем визуально.**

**Причина отклонения**: не соответствует утверждённому дизайн-коду TransportERP / ERP PLANEX (Industrial Graphite + Warm Accent, строгий desktop-first ERP).

**Конкретные нарушения отклонённого варианта**:
- выглядит как сырая demo-заглушка, а не рабочая ERP-панель;
- использует карточный SaaS-dashboard подход (`.summary-cards`, `.cards-grid`, `.module-card`);
- использует псевдоиконки `[=]`, `[#]`, `[~]`, `[v]`, `[🏢]`, `[👤]`, `[⚙]`, `[🔧]`, `[📊]`, `[📋]`;
- имеет слабую композицию и визуальную пустоту справа;
- topbar/side navigation не соответствуют смыслу SUPERADMIN;
- использует не тот визуальный язык (карточки вместо admin/settings секций);
- debug badges как основной визуальный элемент.

**Решение**: отклонённый вариант НЕ используется как основа для следующего этапа. Следующий этап — production-grade SUPERADMIN central admin panel по актуальному handoff ниже.

---

## B. Current target production handoff

**Актуальный статус `/superadmin`: PARTIALLY COMPLIANT / NEEDS_CODER_REWORK.**

Compliance-аудит завершён (2026-06-13): 81 проверка, 76 COMPLIANT, 5 отклонений, 0 BLOCKER. Foundation/shell/sidebar/topbar/IA — COMPLIANT, не требуют перестройки. Результат аудита зафиксирован в разделе C.

Следующий шаг — точечный coder rework по 5 отклонениям (4 строки `app.css` + 1 строка `index.php`). См. раздел C для точного scope.

### Production loop status

- Designer compliance audit: **DONE** (2026-06-13, см. раздел C)
- Designer production handoff/rework spec required before coder: **ALREADY EXISTS** (раздел B + 0.3)
- UI kit core required: YES
- Architect handoff review before coder: REQUIRED
- Coder may start before architect handoff review: NO
- Coder scope: 4 строки `app.css` + 1 строка `index.php` (точечный rework, см. раздел C)
- Foundation/shell/sidebar/topbar/IA rebuild: NOT REQUIRED
- Formal UI QA is visual acceptance: NO
- Architect pre-owner review required: YES
- Owner visual review required: YES
- Commit allowed before owner approval: NO

---

## C. Compliance audit result — 2026-06-13

**Аудит выполнил:** erp-uiux-designer (задача от erp-architect).
**Итоговый verdict:** `PARTIALLY COMPLIANT`.
**Объём:** 81 проверка по 12 слоям. 76 — COMPLIANT. 5 отклонений. 0 BLOCKER.

### Что COMPLIANT (ключевые результаты)

- **App shell**: grid, min-width, topbar position — полностью соответствуют STYLE ERP MASTER.
- **Sidebar**: border-right, flex-column, scrollbar, nav-item все параметры (height 34px, font-weight 600, font-size 12.5px, padding 0 11px, gap 9px), `::before` active state, hover state, disabled state — COMPLIANT.
- **Sidebar IA**: ОПЕРАЦИИ (4 disabled) → nav-spacer → СИСТЕМА (SUPERADMIN is-active) → nav-bottom (Настройки disabled) — COMPLIANT.
- **Topbar**: светлый фон `#fefdf8`, 3-колоночный grid, brand/user block, crumbs — COMPLIANT.
- **SVG иконки** 16×16 `.nav-icon` (заменили `.nav-dot`) — COMPLIANT.
- **Panel pattern**: padding:0, head flush, body padding, KV dividers — COMPLIANT.
- **Нет demo-placeholder UI**: нет псевдоиконок, карточек SaaS-dashboard, debug badges — COMPLIANT.
- **`main.php`** — централизованный shell; `superadmin_dashboard.php` — только page content — COMPLIANT.
- **PHP syntax**: `php -l` для обоих файлов — без ошибок.
- **Foundation/shell/sidebar/topbar/IA НЕ требуют перестройки**.

### 5 отклонений (точечные, без структурных перестроек)

| # | Элемент | Источник нормы | Что сейчас | Статус | Severity | Что привести к норме |
|---|---------|---------------|------------|--------|----------|---------------------|
| 1 | Page-head subtitle color | `superadmin-dashboard.md` §0.3 typography: `--text-muted` (#4c4840) | `app.css` — `.page-head p { color: var(--color-muted); }` (#78726a) — subtitle слишком блёклый | NON-COMPLIANT | **MAJOR** | Заменить `var(--color-muted)` → `var(--text-muted)` в `.page-head p` |
| 2 | Body line-height | `ERP_UI_KIT_CORE.html:65` — `line-height: 1.35` | `app.css:84` — `line-height: 1.45` | NON-COMPLIANT | MINOR | Заменить `line-height: 1.45` → `1.35` в `body` |
| 3 | `$pageContext` не задан явно | `superadmin-dashboard.md` §0c — `$pageContext = 'Центральная панель управления'` | `index.php:56-64` — переменная не задана | PARTIALLY COMPLIANT | MINOR | Добавить `$pageContext = 'Центральная панель управления';` в маршрут `/superadmin` |
| 4 | Body background token | `ERP_UI_KIT_CORE.html:11` — `--app-bg` | `app.css:80` — `var(--color-bg)` (legacy token) | PARTIALLY COMPLIANT | MINOR | Заменить `var(--color-bg)` → `var(--app-bg)` в `body` |
| 5 | `.text-muted` inconsistency | Core Kit — `--text-muted: #4c4840` | page-head p → #78726a, panel-body .text-muted → #4c4840 — разный цвет | PARTIALLY COMPLIANT | MINOR | Удалить `panel-body .text-muted` override (app.css строки 629-631) |

### Scope будущего кодера

**Только 2 файла:**
- `public/assets/css/app.css` — 4 строки (page-head color, body line-height, body bg token, .text-muted unification)
- `public/index.php` — 1 строка ($pageContext)

**Что кодеру НЕ трогать:**
- Foundation/shell/sidebar/topbar/IA — они COMPLIANT
- `main.php` — не менять
- `superadmin_dashboard.php` — не менять
- Backend/auth/CRUD/database/scripts — не менять
- Core Kit / legacy catalog — не менять

### Production loop status (post-audit)

- Designer compliance audit: **DONE** → `PARTIALLY COMPLIANT`
- Coder targeted rework required: **YES** (5 deviations, 0 blockers)
- Coder scope: 4 строки `app.css` + 1 строка `index.php`
- Foundation/shell/sidebar/topbar/IA rebuild: **NOT REQUIRED**
- Architect handoff review before coder: **REQUIRED**
- Owner visual review after rework: **YES**
- Formal UI QA before owner visual review: **NO**
- Commit before owner approval: **NO**

---

## 0. LAYOUT FOUNDATION SOURCE MAPPING — HISTORICAL FOUNDATION HANDOFF

**Audit date:** 2026-06-13
**Historical Layout Foundation Gate before rework:** FAIL → требовал полной перестройки shell/sidebar/topbar/menu.
**Foundation rework cycle after this handoff:** DONE, допущен к Manual owner visual review, но не является final approval.
**Current owner visual status:** PARTIALLY COMPLIANT / NEEDS_UI_REWORK.
**Компонентный уровень (panel/kv/badge/hover/page-head): не считать final visual approval без нового compliance-аудита.**
**Следующее действие:** designer compliance-аудит текущей реализации по источникам нормы, не кодинг.

STYLE ERP reference screen: `transporterp_nav_v1.html` (shell grid, topbar grid, sidebar, nav items with SVG icons and ::before active state).
STYLE ERP MASTER: `TransportERP_MASTER_UI_RULES.md` разделы 7 (App Shell), 8 (Sidebar/Navigation), 5 (Typography).

### 0a. App Shell Foundation — PRODUCTION TABLE

| # | Layer | Required STYLE ERP reference | Core Kit module | Required classes | Current issue | Target implementation |
|---|-------|------------------------------|-----------------|------------------|---------------|----------------------|
| 1 | App shell grid | MASTER §7: `display: grid; grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr; min-height: 100vh; min-width: 1440px` | CORE-01 | `.app-shell` | Нет `grid-template-rows`, нет `min-width` | `.app-shell { display: grid; grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr; min-height: 100vh; min-width: 1440px; }` |
| 2 | App shell min-width | MASTER §7: `min-width: 1440px` | CORE-01 | `.app-shell` | Не задан | `min-width: 1440px` на `.app-shell` |
| 3 | Sidebar width | MASTER §7,8: `var(--sidebar-w) = 224px` | CORE-02 | `.app-sidebar` | Задан через grid-template-columns, но sidebar внутри имеет `padding: var(--space-4)` | `.app-sidebar { width: var(--sidebar-w); grid-row: 2; }` — ширина неявно задана гридом. Убрать `padding: var(--space-4)` — заменить на `display: flex; flex-direction: column;` |
| 4 | Sidebar background | MASTER §7: `background: var(--nav-bg) = #191816` | CORE-02 | `.app-sidebar` | ✓ `var(--nav-bg)` — OK | Сохранить `background: var(--nav-bg)` |
| 5 | Sidebar border-right | MASTER §7: `border-right: 1px solid var(--nav-divider)` | CORE-02 | `.app-sidebar` | Отсутствует | `.app-sidebar { border-right: 1px solid var(--nav-divider); }` Добавить `--nav-divider: rgba(255,255,255,.055)` в `:root` |
| 6 | Sidebar scrollbar | MASTER §10: `scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.08) transparent` | CORE-02 | `.app-sidebar` | Стандартный браузерный | `.app-sidebar { overflow-y: auto; overflow-x: hidden; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.08) transparent; } .app-sidebar::-webkit-scrollbar { width: 3px; } .app-sidebar::-webkit-scrollbar-track { background: transparent; } .app-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 0; }` |
| 7 | Brand block (logo + name) | MASTER §7 topbar-brand: logo-mark 18×18 + brand-name 13px/700 | CORE-03 (topbar) | `.topbar-brand`, `.logo-mark`, `.brand-name` | Текущий `.brand` внутри `.app-sidebar`: brand-mark 40×40, нет логотипа | Переместить brand в `.topbar-brand` внутри `.topbar` (см. topbar spec ниже). В sidebar: удалить `.brand` блок. Brand отображается ТОЛЬКО в topbar (как в STYLE ERP `nav_v1.html:358-362`). |
| 8 | Nav section label | MASTER §5,8: `9px, 700, uppercase, letter-spacing .12em, color: rgba(240,238,232,.22)` | CORE-02 | `.nav-section-label` | Текущий `.nav-section`: 12px, color `var(--color-sidebar-muted)`, нет letter-spacing | `.nav-section-label { padding: 0 13px 5px; font-size: 9px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(240,238,232,.22); user-select: none; }` Заменить `.nav-section` на `.nav-section-label`. |
| 9 | Nav group разделители | MASTER §8: `.nav-group + .nav-group { border-top: 1px solid var(--nav-divider); padding-top: 8px; }` | CORE-02 | `.nav-group` | Отсутствует | `.nav-group { padding: 4px 0 2px; } .nav-group + .nav-group { border-top: 1px solid var(--nav-divider); padding-top: 8px; }` Обернуть группы nav-items в `.nav-group`. |
| 10 | Nav item height | MASTER §8: `height: 34px` | CORE-02 | `.nav-item` | Не задан; полагается на padding | `.nav-item { height: 34px; }` (уже не через padding 8px 10px) |
| 11 | Nav item font-size | MASTER §5,8: `12.5px` | CORE-02 | `.nav-item` | 12px | `.nav-item { font-size: 12.5px; }` |
| 12 | Nav item font-weight | MASTER §8: `font-weight: 600` ВСЕГДА — не менять при active | CORE-02 | `.nav-item` | Не задан (наследует 400 от body) | `.nav-item { font-weight: 600; }` — единый для всех состояний. `.nav-item.is-active` НЕ должен менять font-weight. |
| 13 | Nav item gap | MASTER §8: `gap: 9px` между иконкой и текстом | CORE-02 | `.nav-item` | `gap: var(--space-2)` = 8px — близко, но точнее | `.nav-item { gap: 9px; }` |
| 14 | Nav item padding | MASTER §8: `padding: 0 11px` | CORE-02 | `.nav-item` | `padding: 8px 10px` | `.nav-item { padding: 0 11px; }` (высота задана явно 34px, padding только горизонтальный) |
| 15 | Nav item border-radius | MASTER: 2px (standard) | CORE-02 | `.nav-item` | `var(--radius-sm)` = 2px — OK | Сохранить `border-radius: var(--radius-sm)` = 2px |
| 16 | Nav item hover state | MASTER §8: `background: var(--nav-hover); color: var(--nav-text-h) = rgba(240,238,232,.88)` | CORE-02 | `.nav-item:hover` | Частично: `background: var(--nav-hover); color: var(--nav-text-act)` — должно быть `var(--nav-text-h)` для hover, `var(--nav-text-act)` для active | `.nav-item:hover { background: var(--nav-hover); color: var(--nav-text-h); }` исправить `color` на `var(--nav-text-h)`. Добавить `--nav-text-h: rgba(240,238,232,.88)` в `:root`. |
| 17 | Nav item active state — псевдоэлемент | MASTER §8: `a.nav-item { text-decoration: none; outline: none; }` + `.nav-item.is-active { background: var(--nav-active-bg); color: var(--nav-text-act); }` + `.nav-item.is-active::before { content: ""; position: absolute; left: 0; top: 7px; bottom: 7px; width: 2px; background: var(--nav-gold); border-radius: 0 1px 1px 0; }` | CORE-02 | `.nav-item.is-active`, `.nav-item.is-active::before` | Сейчас: `border-left: 2px solid var(--nav-gold)` на ЭЛЕМЕНТЕ — НЕ ::before. При `border-radius: 2px` на элементе левый border визуально режется. | `.nav-item { position: relative; }` (обязательно для ::before). `.nav-item.is-active { background: var(--nav-active-bg); color: var(--nav-text-act); }` — БЕЗ border-left. `.nav-item.is-active::before { content: ""; position: absolute; left: 0; top: 7px; bottom: 7px; width: 2px; background: var(--nav-gold); border-radius: 0 1px 1px 0; }` Удалить `border-left` из `.nav-item.is-active`. |
| 18 | Nav item disabled state | MASTER: disabled items должны визуально отличаться; subdued text, no interaction | CORE-02, CORE-23 | `.nav-item.is-disabled` | `color: var(--color-sidebar-muted)` — не системный токен | `.nav-item.is-disabled { color: var(--nav-sub-text); opacity: .52; cursor: default; pointer-events: none; }` Добавить `--nav-sub-text: rgba(240,238,232,.52)` в `:root`. |
| 19 | Nav icons (SVG inline 16×16) | MASTER §8: `width: 16px; height: 16px; opacity: .45; flex-shrink: 0` | CORE-02 | `.nav-icon` | `nav-dot` (6px gold dot) — НЕ иконка | `.nav-icon { width: 16px; height: 16px; flex-shrink: 0; opacity: .45; transition: opacity 90ms; } .nav-item:hover .nav-icon { opacity: .7; } .nav-item.is-active .nav-icon { opacity: .85; }` Заменить ВСЕ `.nav-dot` на `<svg class="nav-icon">…</svg>`. Удалить `.nav-dot` из разметки. |
| 20 | Nav counters | MASTER §8: `.nav-count` — gold-tinted counter badge | CORE-02 | `.nav-count` | Не используется (отсутствует) | `.nav-count { height: 16px; min-width: 22px; padding: 0 5px; display: inline-flex; align-items: center; justify-content: center; background: rgba(200,147,58,.14); border: 1px solid rgba(200,147,58,.24); color: rgba(200,147,58,.65); font-size: 9.5px; font-weight: 700; letter-spacing: .02em; border-radius: 2px; flex-shrink: 0; }` На текущем этапе counter НЕ ИСПОЛЬЗУЕТСЯ для пунктов `/superadmin` (все значения = none). CSS предоставляется для будущего использования. |
| 21 | Nav-spacer | MASTER §8: `.nav-spacer { flex: 1; min-height: 8px; }` | CORE-02 | `.nav-spacer` | Отсутствует | `.nav-spacer { flex: 1; min-height: 8px; }` Разместить между группами ОПЕРАЦИИ и СИСТЕМА. |
| 22 | Nav-bottom | MASTER §8: `.nav-bottom { border-top: 1px solid var(--nav-divider); padding: 4px 0 6px; }` | CORE-02 | `.nav-bottom` | Отсутствует | `.nav-bottom { border-top: 1px solid var(--nav-divider); padding: 4px 0 6px; }` Разместить ПОСЛЕ nav-spacer, содержать пункт "Настройки". |
| 23 | Topbar grid position | MASTER §7: `grid-column: 1 / -1` — full width | CORE-03 | `.topbar` | `.topbar` внутри `.app-main`, а не на уровне `.app-shell` | Переместить `.topbar` как прямой дочерний элемент `.app-shell` (ПЕРЕД `.app-sidebar` и `.app-main`). `.topbar { grid-column: 1 / -1; }` |
| 24 | Topbar height | MASTER §7: `height: var(--topbar-h) = 38px` | CORE-03 | `.topbar` | 38px задан — OK | Сохранить `height: 38px` |
| 25 | Topbar background | MASTER §7: `background: var(--surface-strong) = #fefdf8` СВЕТЛЫЙ | CORE-03 | `.topbar` | `var(--color-sidebar)` = #191816 ТЁМНЫЙ | `.topbar { background: var(--surface-strong); }` |
| 26 | Topbar border-bottom | MASTER §7: `border-bottom: 1px solid var(--line) = #a8a196` | CORE-03 | `.topbar` | `rgba(255,255,255,.08)` | `.topbar { border-bottom: 1px solid var(--line); }` |
| 27 | Topbar internal grid | MASTER §7: `display: grid; grid-template-columns: var(--sidebar-w) 1fr auto; align-items: center;` | CORE-03 | `.topbar` | `display: flex; justify-content: space-between` | `.topbar { display: grid; grid-template-columns: var(--sidebar-w) 1fr auto; align-items: center; }` |
| 28 | Topbar brand zone (left) | MASTER §7, nav_v1:358-362: `.topbar-brand { height: 100%; display: flex; align-items: center; padding: 0 13px; background: var(--nav-bg); gap: 9px; border-right: 1px solid var(--nav-divider); }` | CORE-03 | `.topbar-brand` | Brand находится в sidebar, а не в topbar | `.topbar-brand { height: 100%; display: flex; align-items: center; padding: 0 13px; background: var(--nav-bg); gap: 9px; border-right: 1px solid var(--nav-divider); }` Содержит `.logo-mark` (18×18), `.brand-name` (13px, 700), опционально `.brand-tag`. Удалить `.brand` из `.app-sidebar`. |
| 29 | Logo mark | MASTER nav_v1:84-93: `.logo-mark { width: 18px; height: 18px; background: linear-gradient(140deg, #c07830 0%, #3d2010 100%); }` с `::after` псевдо-внутренней рамкой | CORE-03 sub | `.logo-mark` | Текущий `.brand-mark`: 40×40, `border: 1px solid var(--nav-gold)`, `background: rgba(255,255,255,.08)`, текст "PX" | `.logo-mark { width: 18px; height: 18px; background: linear-gradient(140deg, #c07830 0%, #3d2010 100%); position: relative; flex-shrink: 0; } .logo-mark::after { content: ""; position: absolute; inset: 5px; border: 1.5px solid rgba(255,255,255,.68); }` Заменить `.brand-mark` на `.logo-mark`. |
| 30 | Brand name in topbar | MASTER nav_v1:95-99: `.brand-name { font-size: 13px; font-weight: 700; letter-spacing: -.02em; color: rgba(240,238,232,.9); }` | CORE-03 sub | `.brand-name` | Текущий: текст "ERP PLANEX" в sidebar brand | `.brand-name { font-size: 13px; font-weight: 700; letter-spacing: -.02em; color: rgba(240,238,232,.9); flex: 1; }` Разместить в `.topbar-brand`. |
| 31 | Topbar breadcrumbs / context (middle) | MASTER nav_v1:109-113: `.topbar-crumbs { padding: 0 16px; display: flex; align-items: center; gap: 6px; color: var(--text-faint); font-size: 12px; font-weight: 500; }` + `.topbar-crumbs b { color: var(--text-main); font-weight: 700; }` | CORE-04 | `.topbar-crumbs` | Текущий: `<div><span class="topbar-label">…</span><strong>…</strong></div>` внутри `.topbar` | `.topbar-crumbs { padding: 0 16px; display: flex; align-items: center; gap: 6px; color: var(--text-faint); font-size: 12px; font-weight: 500; } .topbar-crumbs b { color: var(--text-main); font-weight: 700; } .topbar-crumbs .sep { color: #b0a898; }` HTML: `<div class="topbar-crumbs"><span>SUPERADMIN</span><span class="sep">—</span><b>Центральная панель управления</b></div>` Удалить `.topbar-label`. |
| 32 | Topbar right zone (user block) | MASTER nav_v1:116-140: `.topbar-right { display: flex; align-items: center; gap: 8px; padding: 0 12px; height: 100%; border-left: 1px solid var(--line-hair); }` + `.avatar { width: 26px; height: 26px; border-radius: 2px; background: var(--nav-bg); color: rgba(240,238,232,.8); font-size: 10px; font-weight: 700; display: grid; place-items: center; flex-shrink: 0; }` + `.user-info strong { display: block; font-size: 12px; font-weight: 700; line-height: 14px; color: var(--text-main); }` + `.user-info span { display: block; font-size: 11px; line-height: 13px; color: var(--text-faint); }` | CORE-03 | `.topbar-right`, `.avatar`, `.user-info` | Только `environment-badge "dev"` | `.topbar-right { display: flex; align-items: center; gap: 8px; padding: 0 12px; height: 100%; border-left: 1px solid var(--line-hair); }` Содержит `.avatar` (26×26) + `.user-info` (strong: "ERP Admin", span: "Суперадминистратор"). Заглушка — статический HTML, не из БД. |
| 33 | Environment badge | MASTER: `.environment-badge` — OPTIONAL (CORE-03 sub-element). Только если в handoff. | CORE-03 sub | `.environment-badge` | Присутствует | **Убрать** `.environment-badge` из `main.php`. На странице `/superadmin` не отображается. |
| 34 | Page context / page header | MASTER: title 15-16px, 700, конкретное business/admin название | CORE-05 | `.page-head`, `h1` | ✓ Принят кодером — OK | Сохранить текущий `.page-head` с `h1` (16px, 700) и subtitle (13px, muted). |
| 35 | Content / work area spacing | MASTER: `padding: 12px 14px 28px` | CORE-07 | `.content` | 12px 14px 28px — OK | Сохранить `padding: 12px 14px 28px`. |

### 0b. SIDEBAR INFORMATION ARCHITECTURE — FULL PRODUCTION SPEC

Целевая HTML-структура sidebar для `/superadmin`:

```html
<aside class="app-sidebar" aria-label="Основная навигация">

  <!-- ГРУППА: ОПЕРАЦИИ -->
  <div class="nav-group" style="padding-top:8px">
    <div class="nav-section-label">ОПЕРАЦИИ</div>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <rect x="1" y="8.5" width="9.5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/>
        <path d="M10.5 11H13C13.8 11 14.5 10.4 14.5 9.5C14.5 8.6 13.8 8.5 13 8.5H10.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        <circle cx="3.5" cy="13.5" r="1.3" fill="currentColor"/>
        <circle cx="8.5" cy="13.5" r="1.3" fill="currentColor"/>
        <path d="M1 8.5V6.5C1 6 1.4 5.5 2 5.5H6.5L9 2.5H10.5C11 2.5 11.5 3 11.5 3.5V8.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
      </svg>
      <span class="nav-label">Рейсы</span>
    </span>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <circle cx="8" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/>
        <path d="M2 14C2 11.2 4.7 9 8 9C11.3 9 14 11.2 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <span class="nav-label">Водители</span>
    </span>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <rect x="1" y="6.5" width="14" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/>
        <path d="M1 9H15" stroke="currentColor" stroke-width="1.4"/>
        <path d="M5 6.5V5C5 4.4 5.4 4 6 4H10C10.6 4 11 4.4 11 5V6.5" stroke="currentColor" stroke-width="1.4"/>
        <circle cx="4.5" cy="12.5" r="1.3" fill="currentColor"/>
        <circle cx="11.5" cy="12.5" r="1.3" fill="currentColor"/>
      </svg>
      <span class="nav-label">Транспорт</span>
    </span>

    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
        <path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/>
        <path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/>
        <path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <span class="nav-label">Клиенты</span>
    </span>
  </div>

  <!-- РАЗДЕЛИТЕЛЬ: nav-spacer -->
  <div class="nav-spacer"></div>

  <!-- ГРУППА: СИСТЕМА -->
  <div class="nav-group">
    <div class="nav-section-label">СИСТЕМА</div>

    <a class="nav-item is-active" href="/superadmin">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <path d="M8 2L14 4.5V8C14 11.5 11 14 8 15C5 14 2 11.5 2 8V4.5L8 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        <path d="M5.5 8L7.5 10L10.5 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="nav-label">SUPERADMIN</span>
    </a>
  </div>

  <!-- BOTTOM: Настройки -->
  <div class="nav-bottom">
    <span class="nav-item is-disabled">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
        <circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/>
        <path d="M8 1.5V3.5M8 12.5V14.5M2.5 8H4.5M11.5 8H13.5M3.4 3.4L4.8 4.8M11.2 11.2L12.6 12.6M3.4 12.6L4.8 11.2M11.2 4.8L12.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <span class="nav-label">Настройки</span>
    </span>
  </div>

</aside>
```

**Поэлементная спецификация SIDEBAR IA:**

| # | Label | Route | Group | State | Counter | Icon source (SVG inline 16×16) | Visual state description |
|---|-------|-------|-------|-------|---------|-------------------------------|--------------------------|
| 1 | Рейсы | disabled | ОПЕРАЦИИ | is-disabled | none | Truck icon: MASTER nav_v1:418-424 (rect cabin + wheels + trailer path) | Текст `color: var(--nav-sub-text)` (#rgba 240,238,232,.52), opacity .52, cursor default. Иконка opacity .45, без hover-эффекта. |
| 2 | Водители | disabled | ОПЕРАЦИИ | is-disabled | none | Person icon: MASTER nav_v1:491-494 (circle head + body path) | Текст `color: var(--nav-sub-text)`, opacity .52, cursor default. Иконка opacity .45. |
| 3 | Транспорт | disabled | ОПЕРАЦИИ | is-disabled | none | Vehicle icon: MASTER nav_v1:525-531 (rect body + wheels + cabin) | Текст `color: var(--nav-sub-text)`, opacity .52, cursor default. Иконка opacity .45. |
| 4 | Клиенты | disabled | ОПЕРАЦИИ | is-disabled | none | Building icon: MASTER nav_v1:558-563 (building rect + roof + door) | Текст `color: var(--nav-sub-text)`, opacity .52, cursor default. Иконка opacity .45. |
| — | nav-spacer | — | — | — | — | — | `flex: 1; min-height: 8px` — разделяет ОПЕРАЦИИ и СИСТЕМА. |
| 5 | SUPERADMIN | /superadmin | СИСТЕМА | **is-active** | none | Shield icon: custom 16×16 (shield path + checkmark) | `background: var(--nav-active-bg)` (#rgba 140,82,28,.28), `color: var(--nav-text-act)` (#d4a254). `::before` pseudo: 2px `var(--nav-gold)` (#c8933a) левый маркер. Иконка opacity .85. Это `<a href="/superadmin">`. Единственный активный пункт. |
| — | nav-bottom | — | BOTTOM | — | — | — | `border-top: 1px solid var(--nav-divider); padding: 4px 0 6px` — контейнер для нижних пунктов. |
| 6 | Настройки | disabled | BOTTOM | is-disabled | none | Gear icon: custom 16×16 (circle + 8 spokes) | Текст `color: var(--nav-sub-text)`, opacity .52, cursor default. Иконка opacity .45. |

**IA правила:**
- Операционные модули (Рейсы, Водители, Транспорт, Клиенты) и системный модуль (SUPERADMIN) НЕ смешиваются в одной группе.
- SUPERADMIN ВСЕГДА в отдельной системной секции.
- `nav-spacer` гарантирует, что секция СИСТЕМА прижата к низу, а Настройки — к самому низу sidebar.
- "Навигация" как пункт меню — **УДАЛИТЬ**.
- "Управление" как группа — **УДАЛИТЬ** (заменяется на ОПЕРАЦИИ и СИСТЕМА).

### 0c. TOPBAR FOUNDATION SPEC

**Target HTML-структура topbar:**

```html
<header class="topbar">
  <div class="topbar-brand">
    <div class="logo-mark"></div>
    <span class="brand-name">ERP PLANEX</span>
  </div>

  <div class="topbar-crumbs">
    <span>SUPERADMIN</span>
    <span class="sep">—</span>
    <b>Центральная панель управления</b>
  </div>

  <div class="topbar-right">
    <div class="avatar">EA</div>
    <div class="user-info">
      <strong>ERP Admin</strong>
      <span>Суперадминистратор</span>
    </div>
  </div>
</header>
```

**CSS-спецификация topbar:**

```css
.topbar {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: var(--sidebar-w) 1fr auto;
  align-items: center;
  height: var(--topbar-h);
  background: var(--surface-strong);
  border-bottom: 1px solid var(--line);
  z-index: 100;
}

.topbar-brand {
  height: 100%;
  display: flex;
  align-items: center;
  padding: 0 13px;
  background: var(--nav-bg);
  gap: 9px;
  border-right: 1px solid var(--nav-divider);
}

.logo-mark {
  width: 18px;
  height: 18px;
  background: linear-gradient(140deg, #c07830 0%, #3d2010 100%);
  position: relative;
  flex-shrink: 0;
}

.logo-mark::after {
  content: "";
  position: absolute;
  inset: 5px;
  border: 1.5px solid rgba(255,255,255,.68);
}

.brand-name {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -.02em;
  color: rgba(240,238,232,.9);
  flex: 1;
}

.topbar-crumbs {
  padding: 0 16px;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-faint);
  font-size: 12px;
  font-weight: 500;
}

.topbar-crumbs b {
  color: var(--text-main);
  font-weight: 700;
}

.topbar-crumbs .sep {
  color: #b0a898;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 100%;
  border-left: 1px solid var(--line-hair);
}

.avatar {
  width: 26px;
  height: 26px;
  border-radius: 2px;
  background: var(--nav-bg);
  color: rgba(240,238,232,.8);
  font-size: 10px;
  font-weight: 700;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.user-info strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  line-height: 14px;
  color: var(--text-main);
}

.user-info span {
  display: block;
  font-size: 11px;
  line-height: 13px;
  color: var(--text-faint);
}
```

**Topbar параметры:**

| Параметр | Значение | Токен |
|----------|----------|-------|
| Height | 38px | `var(--topbar-h)` |
| Background | `var(--surface-strong)` = #fefdf8 | СВЕТЛЫЙ |
| Border-bottom | `1px solid var(--line)` = #a8a196 | — |
| Grid position | `grid-column: 1 / -1` | Полная ширина `.app-shell` |
| Internal grid | `grid-template-columns: var(--sidebar-w) 1fr auto` | 224px + fluid + auto |
| Left zone (brand) | `.topbar-brand`: `background: var(--nav-bg)` (#191816), `border-right: 1px solid var(--nav-divider)` | Выравнивается по ширине sidebar |
| Middle zone (crumbs) | `.topbar-crumbs`: `SUPERADMIN — Центральная панель управления` | — |
| Right zone (user) | `.topbar-right`: avatar (26×26, "EA") + имя "ERP Admin" + роль "Суперадминистратор" | Статическая заглушка |
| Environment badge | **Убрать** `.environment-badge` из `main.php` | Не отображается |
| z-index | 100 | Выше sidebar/content |

### 0d. SHELL FOUNDATION SPEC

**Target HTML-структура app shell (`main.php`):**

```html
<body>
  <div class="app-shell">

    <!-- TOPBAR — прямой дочерний элемент .app-shell, grid-column: 1/-1 -->
    <header class="topbar">
      <div class="topbar-brand">
        <div class="logo-mark"></div>
        <span class="brand-name">ERP PLANEX</span>
      </div>
      <div class="topbar-crumbs">
        <span>SUPERADMIN</span>
        <span class="sep">—</span>
        <b>Центральная панель управления</b>
      </div>
      <div class="topbar-right">
        <div class="avatar">EA</div>
        <div class="user-info">
          <strong>ERP Admin</strong>
          <span>Суперадминистратор</span>
        </div>
      </div>
    </header>

    <!-- SIDEBAR — grid-row: 2 -->
    <aside class="app-sidebar" aria-label="Основная навигация">
      ... (см. раздел 0b)
    </aside>

    <!-- CONTENT — grid-row: 2 -->
    <main class="app-main">
      <div class="content">
        <?= $content ?>
      </div>
    </main>

  </div>
</body>
```

**CSS-спецификация shell:**

```css
:root {
  --sidebar-w: 224px;
  --topbar-h: 38px;
  /* добавить недостающие токены: */
  --nav-divider: rgba(255,255,255,.055);
  --nav-text-h: rgba(240,238,232,.88);
  --nav-sub-text: rgba(240,238,232,.52);
  --nav-icon-op: .45;
  --nav-sec-clr: rgba(240,238,232,.22);
}

.app-shell {
  display: grid;
  grid-template-columns: var(--sidebar-w) 1fr;
  grid-template-rows: var(--topbar-h) 1fr;
  min-height: 100vh;
  min-width: 1440px;
}

.app-sidebar {
  grid-row: 2;
  background: var(--nav-bg);
  border-right: 1px solid var(--nav-divider);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
  /* padding удалён — sidebar не имеет внутренних отступов;
     отступы обеспечиваются .nav-group и .nav-section-label */
}

.app-sidebar {
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.08) transparent;
}

.app-sidebar::-webkit-scrollbar {
  width: 3px;
}

.app-sidebar::-webkit-scrollbar-track {
  background: transparent;
}

.app-sidebar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,.1);
  border-radius: 0;
}

.app-main {
  grid-row: 2;
  min-width: 0;
}

.content {
  padding: 12px 14px 28px;
  /* background наследуется от body: var(--app-bg) = #e2dfd8 */
}
```

**Shell-параметры:**

| Параметр | CSS | Значение |
|----------|-----|----------|
| Grid columns | `grid-template-columns: var(--sidebar-w) 1fr` | 224px + fluid |
| Grid rows | `grid-template-rows: var(--topbar-h) 1fr` | 38px + fluid |
| Min height | `min-height: 100vh` | Полная высота viewport |
| Min width | `min-width: 1440px` | Desktop-first |
| Topbar span | `grid-column: 1 / -1` на `.topbar` | Полная ширина |
| Sidebar grid row | `grid-row: 2` на `.app-sidebar` | Вторая строка, первая колонка |
| Content grid row | `grid-row: 2` на `.app-main` | Вторая строка, вторая колонка |
| Sidebar layout | `display: flex; flex-direction: column` | Вертикальный flex для nav-spacer |
| Sidebar scroll | `overflow-y: auto; overflow-x: hidden` | Тонкий кастомный скроллбар |

### 0e. FOUNDATION ACCEPTANCE CHECKLIST

Для architect / coder / QA. Все пункты должны быть YES перед приёмкой foundation.

| # | Критерий | Target |
|---|----------|--------|
| 1 | Topbar height = 38px | YES |
| 2 | Topbar background = `var(--surface-strong)` = #fefdf8 (СВЕТЛЫЙ, не тёмный) | YES |
| 3 | Topbar border-bottom = `1px solid var(--line)` = #a8a196 (не rgba white .08) | YES |
| 4 | Topbar grid: левая brand-зона (тёмная, ширина sidebar, border-right `var(--nav-divider)`) + crumbs + user block | YES |
| 5 | Topbar brand zone содержит `.logo-mark` (18×18, градиентный) + `.brand-name` (13px, 700) | YES |
| 6 | Topbar crumbs: `SUPERADMIN — Центральная панель управления` | YES |
| 7 | Topbar right zone: avatar (26×26, "EA") + `ERP Admin` (12px, 700) + `Суперадминистратор` (11px, faint) | YES |
| 8 | `.environment-badge` удалён из topbar | YES |
| 9 | Sidebar background = `var(--nav-bg)` = #191816 | YES |
| 10 | Sidebar border-right = `1px solid var(--nav-divider)` | YES |
| 11 | Sidebar = `display: flex; flex-direction: column` | YES |
| 12 | Sidebar scrollbar: тонкий, кастомный, 3px | YES |
| 13 | Brand `.brand` блок удалён из sidebar (теперь brand в topbar) | YES |
| 14 | Menu hierarchy: ОПЕРАЦИИ → [nav-spacer] → СИСТЕМА → [nav-bottom] Настройки | YES |
| 15 | SUPERADMIN изолирован в секции СИСТЕМА (не смешан с операционными пунктами) | YES |
| 16 | "Навигация" как пункт меню удалён | YES |
| 17 | "Управление" как группа удалена | YES |
| 18 | Bottom settings (Настройки) присутствует | YES |
| 19 | Nav section label: 9px, 700, uppercase, letter-spacing .12em, color `rgba(240,238,232,.22)` | YES |
| 20 | Nav groups разделены `border-top: 1px solid var(--nav-divider)` | YES |
| 21 | Nav item height = 34px | YES |
| 22 | Nav item font-size = 12.5px | YES |
| 23 | Nav item font-weight = 600 ВСЕГДА (не меняется при .is-active) | YES |
| 24 | Nav item padding = `0 11px` | YES |
| 25 | Nav item gap = 9px (между иконкой и текстом) | YES |
| 26 | Nav item border-radius = 2px | YES |
| 27 | Nav item hover: `background: var(--nav-hover); color: var(--nav-text-h)` | YES |
| 28 | Nav item active state: `background: var(--nav-active-bg); color: var(--nav-text-act)` + `::before` pseudo (2px `var(--nav-gold)`, left 0, top 7px, bottom 7px) | YES |
| 29 | Nav item НЕ использует `border-left` для active state (только `::before`) | YES |
| 30 | Nav item disabled: `color: var(--nav-sub-text)`, opacity .52, cursor default, pointer-events none | YES |
| 31 | Nav icons: SVG inline 16×16, класс `.nav-icon`, opacity .45; hover → .7; active → .85 | YES |
| 32 | `.nav-dot` удалён из разметки (заменён на SVG `.nav-icon`) | YES |
| 33 | `a.nav-item` имеет `text-decoration: none; outline: none;` — БЕЗ `color: inherit` | YES |
| 34 | App shell grid: `grid-template-rows: var(--topbar-h) 1fr` | YES |
| 35 | App shell min-width: 1440px | YES |
| 36 | `.topbar` — прямой дочерний элемент `.app-shell` (не внутри `.app-main`) | YES |
| 37 | `.topbar` имеет `grid-column: 1 / -1` | YES |
| 38 | Page head: title `SUPERADMIN` (16px, 700), subtitle корректный | YES |
| 39 | Content padding: `12px 14px 28px` | YES |
| 40 | Content background: `var(--app-bg)` = #e2dfd8 | YES |

### 0f. CODER FOUNDATION TASK SPEC

**Файлы для изменения:**

| Файл | Что изменить | Почему |
|------|-------------|--------|
| `app/View/layouts/main.php` | Полностью переписать HTML-структуру shell/sidebar/topbar/nav | Foundation-уровень не соответствует STYLE ERP MASTER |
| `public/assets/css/app.css` | Исправить/добавить CSS для shell/sidebar/topbar/nav | Отсутствуют критичные токены и классы |
| `app/View/pages/superadmin_dashboard.php` | Не трогать (компонентный уровень принят) | Компоненты OK |

**Добавить в `:root` файла `app.css`:**

```css
--sidebar-w: 224px;
--topbar-h: 38px;
--nav-divider: rgba(255,255,255,.055);
--nav-text-h: rgba(240,238,232,.88);
--nav-sub-text: rgba(240,238,232,.52);
--nav-icon-op: .45;
--nav-sec-clr: rgba(240,238,232,.22);
```

**CSS-классы, которые кодер должен ИСПРАВИТЬ в `app.css`:**

| Текущий селектор | Текущая проблема | Исправление |
|------------------|------------------|-------------|
| `.app-shell` | Нет `grid-template-rows`, нет `min-width` | Добавить `grid-template-rows: var(--topbar-h) 1fr; min-width: 1440px;` |
| `.app-sidebar` | `padding: var(--space-4)`, нет `border-right`, нет flex-column, нет scrollbar | Заменить на: `grid-row: 2; background: var(--nav-bg); border-right: 1px solid var(--nav-divider); display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden;` + scrollbar-стили. Убрать `padding`, `color: #fff`. |
| `.topbar` | Тёмный фон, border неправильный, flex вместо grid | Заменить на: `grid-column: 1 / -1; display: grid; grid-template-columns: var(--sidebar-w) 1fr auto; align-items: center; height: 38px; background: var(--surface-strong); border-bottom: 1px solid var(--line); z-index: 100;` Убрать `justify-content: space-between; color: #fff;`. |
| `.brand`, `.brand-mark` | Brand в sidebar — переместить в topbar | Удалить текущие стили `.brand` и `.brand-mark`. Добавить новые: `.topbar-brand`, `.logo-mark`, `.brand-name` (см. раздел 0c). |
| `.nav-list` | `display: grid; gap: var(--space-2)` | Заменить на: `display: flex; flex-direction: column;` (не используется как отдельный контейнер — nav-group'ы управляют структурой). |
| `.nav-item` | `padding: 8px 10px; display: flex; align-items: center; gap: var(--space-2);` — нет height, нет position relative, нет font-size/font-weight | Заменить на: `position: relative; display: flex; align-items: center; gap: 9px; height: 34px; padding: 0 11px; color: var(--nav-text); font-size: 12.5px; font-weight: 600; cursor: default; user-select: none; border-radius: 2px; text-decoration: none;` |
| `a.nav-item` | Нет явного сброса | Добавить: `a.nav-item { text-decoration: none; outline: none; }` БЕЗ `color: inherit`. |
| `.nav-item:hover` | `color: var(--nav-text-act)` — должно быть `var(--nav-text-h)` | Исправить на: `background: var(--nav-hover); color: var(--nav-text-h);` |
| `.nav-item.is-active` | `border-left: 2px solid var(--nav-gold)` — должно быть ::before | Заменить на: `background: var(--nav-active-bg); color: var(--nav-text-act);` БЕЗ border-left. |
| `.nav-item.is-disabled` | `color: var(--color-sidebar-muted)` | Заменить на: `color: var(--nav-sub-text); opacity: .52; cursor: default; pointer-events: none;` |
| `.nav-section` | Неправильный размер/цвет, не та роль | Заменить на `.nav-section-label`: `padding: 0 13px 5px; font-size: 9px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--nav-sec-clr); user-select: none;` |
| `.nav-dot` | Больше не используется | Удалить `.nav-dot` из CSS (или оставить как fallback, но из разметки убрать). |
| `.app-main` | Нет `grid-row: 2` | Добавить `grid-row: 2;` |
| `.topbar-label`, `.environment-badge` | Больше не используются | Удалить из разметки. CSS для `.environment-badge` оставить (может пригодиться для других страниц). `.topbar-label` — удалить. |

**CSS-классы, которые кодер должен ДОБАВИТЬ в `app.css`:**

```css
/* === TOPBAR BRAND === */
.topbar-brand {
  height: 100%;
  display: flex;
  align-items: center;
  padding: 0 13px;
  background: var(--nav-bg);
  gap: 9px;
  border-right: 1px solid var(--nav-divider);
}

.logo-mark {
  width: 18px;
  height: 18px;
  background: linear-gradient(140deg, #c07830 0%, #3d2010 100%);
  position: relative;
  flex-shrink: 0;
}

.logo-mark::after {
  content: "";
  position: absolute;
  inset: 5px;
  border: 1.5px solid rgba(255,255,255,.68);
}

.brand-name {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -.02em;
  color: rgba(240,238,232,.9);
  flex: 1;
}

/* === TOPBAR CRUMBS === */
.topbar-crumbs {
  padding: 0 16px;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-faint);
  font-size: 12px;
  font-weight: 500;
}

.topbar-crumbs b {
  color: var(--text-main);
  font-weight: 700;
}

.topbar-crumbs .sep {
  color: #b0a898;
}

/* === TOPBAR RIGHT / USER === */
.topbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 100%;
  border-left: 1px solid var(--line-hair);
}

.avatar {
  width: 26px;
  height: 26px;
  border-radius: 2px;
  background: var(--nav-bg);
  color: rgba(240,238,232,.8);
  font-size: 10px;
  font-weight: 700;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.user-info strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  line-height: 14px;
  color: var(--text-main);
}

.user-info span {
  display: block;
  font-size: 11px;
  line-height: 13px;
  color: var(--text-faint);
}

/* === NAV SIDEBAR === */
.nav-group {
  padding: 4px 0 2px;
}

.nav-group + .nav-group {
  border-top: 1px solid var(--nav-divider);
  padding-top: 8px;
}

.nav-section-label {
  padding: 0 13px 5px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--nav-sec-clr);
  user-select: none;
}

.nav-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: var(--nav-icon-op);
  transition: opacity 90ms;
}

.nav-item:hover .nav-icon {
  opacity: .7;
}

.nav-item.is-active .nav-icon {
  opacity: .85;
}

.nav-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.nav-item.is-active::before {
  content: "";
  position: absolute;
  left: 0;
  top: 7px;
  bottom: 7px;
  width: 2px;
  background: var(--nav-gold);
  border-radius: 0 1px 1px 0;
}

.nav-spacer {
  flex: 1;
  min-height: 8px;
}

.nav-bottom {
  border-top: 1px solid var(--nav-divider);
  padding: 4px 0 6px;
}

.nav-count {
  height: 16px;
  min-width: 22px;
  padding: 0 5px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(200,147,58,.14);
  border: 1px solid rgba(200,147,58,.24);
  color: rgba(200,147,58,.65);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: .02em;
  border-radius: 2px;
  flex-shrink: 0;
}
```

**Что кодер должен сделать в `main.php`:**

1. Удалить блок `<aside class="app-sidebar">` полностью — переписать заново.
2. Переместить `<header class="topbar">` из `.app-main` на уровень `.app-shell` (прямой дочерний, ПЕРЕД `.app-sidebar`).
3. Полностью переписать HTML topbar по спецификации из раздела 0c.
4. Полностью переписать HTML sidebar по спецификации из раздела 0b (ВСЕ SVG иконки включены).
5. Убрать `.environment-badge` из topbar.
6. Убрать `.brand` блок из sidebar (теперь brand в topbar).
7. Убедиться, что `.content` внутри `.app-main` остаётся на месте.
8. Проверить: `php -l app/View/layouts/main.php` — без ошибок.

**Что кодеру ЗАПРЕЩЕНО:**

- Менять backend/auth/CRUD/database/scripts/migrations/routing.
- Менять Core Kit или legacy catalog.
- Менять компоненты (`.panel`, `.kv`, `.badge`, `.btn`, `.notice`, `.page-head`) без крайней необходимости — они ПРИНЯТЫ.
- Придумывать menu IA — использовать точную структуру из раздела 0b.
- Придумывать новые CSS-классы без указания в этом handoff.
- Менять имена/маршруты или их порядок.
- Менять `superadmin_dashboard.php` (страница принята на компонентном уровне).
- Добавлять JS-интерактивность.
- Использовать `color: inherit` на `a.nav-item`.
- Менять `font-weight` на `.nav-item.is-active`.
- Использовать `border-left` на `.nav-item.is-active` вместо `::before`.
- Использовать `.nav-dot` вместо SVG `.nav-icon`.

---

## 0.1. CORE modules used

- Core UI kit path: `docs/ui/ERP_UI_KIT_CORE.html`
- Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html` (reference/history only)
- COMPOSITE pattern selected:
  - `PATTERN-05 Admin/settings screen` — строгая админ-панель с секциями, статусами, key-value; запрещает KPI dashboard и карточную сетку
  - `PATTERN-10 Empty first-stage module` — зарезервированные модули без реализации, выглядящие как production-grade

### Final CORE modules used (11 из SUPERADMIN READY SET)

| ID | Название | Причина выбора |
|----|----------|----------------|
| `CORE-01` | App shell | Базовый каркас страницы: sidebar + topbar + content. Используется для каждой страницы ERP. |
| `CORE-02` | Sidebar navigation | Левая навигация с активным пунктом `SUPERADMIN` (`.is-active`). |
| `CORE-03` | Topbar | Верхняя контекстная полоса 38px с breadcrumbs и контекстом страницы. |
| `CORE-04` | Breadcrumbs / page context | Контекстный путь в topbar: `SUPERADMIN — Центральная панель управления`. |
| `CORE-05` | Page header | Компактный заголовок страницы: title `SUPERADMIN` + subtitle. |
| `CORE-08` | Panel | Фреймированный блок для каждой admin section и системной информации. |
| `CORE-09` | Admin/settings section | Каждая секция (компании, пользователи, toggles, настройки) — панель с заголовком, описанием и статусом. |
| `CORE-23` | Disabled/future action | Кнопка `Настроить позже` (disabled) в каждой admin section. |
| `CORE-24` | Status badge | Нейтральный бейдж `В разработке` в заголовке каждой секции. |
| `CORE-31` | Key-value list | Компактный блок системной информации (система, среда, БД). |
| `CORE-32` | Notice / system message | Нейтральное уведомление под page head: `Центральная панель управления ERP PLANEX. Функционал находится в разработке.` |

### Modules from SUPERADMIN READY SET NOT used (reserved for future)

| ID | Название | Причина НЕиспользования |
|----|----------|------------------------|
| `CORE-06` | Page actions area | На текущем этапе нет page-level действий. Зарезервирован для будущих primary/secondary кнопок. |
| `CORE-13` | Data table / ERP grid | Таблица компаний/пользователей появится на следующих этапах. Сейчас не рендерится. |
| `CORE-17` | Primary button | Нет реального primary action на placeholder-этапе. |
| `CORE-18` | Secondary button | Disabled-кнопки используют CORE-23, а не CORE-18. CORE-18 зарезервирован для будущих реальных secondary actions. |
| `CORE-33` | Warning / blocking notice | Нет системного риска или блокирующего условия для отображения на текущем этапе. |

- Missing modules: **none** — все нужные модули присутствуют в SUPERADMIN READY SET Core Kit.
- If missing module exists: `BLOCKED: NEEDS_UI_MODULE_EXPANSION`
- Coder may invent new UI module: **NO**
- Private applied example names allowed: **NO**

## 0.2. MODULE USAGE DECISIONS

### Общие решения

- Main page purpose: central SUPERADMIN control panel for ERP PLANEX.
- Primary work object: central system/company/feature management overview (placeholder stage).
- Main layout selected: **admin/settings compact section layout** (одноколоночная плотная вертикальная композиция).
- Composite pattern selected: `PATTERN-05 Admin/settings screen` + `PATTERN-10 Empty first-stage module`.
- Primary action: **none** — на текущем placeholder-этапе реальных действий нет.
- Secondary actions: **none** — только disabled/future кнопки через CORE-23.
- Table required: NO на текущем этапе. CORE-13 зарезервирован для будущих таблиц компаний/пользователей, но не рендерится сейчас.
- Form required: NO — до утверждения владельцем конкретного SUPERADMIN CRUD-этапа.
- Inspector required: NO — для центральной панели shell не нужен.
- Filters required: NO — до появления реальных записей компаний/пользователей.
- Modal required: NO — до появления реальных опасных/системных действий.
- Empty/error/loading states required: не применимо для admin shell placeholder. Страница статическая.
- Private applied example names not used: никакие page-specific названия из STYLE ERP examples (Drivers bottom editor, Drivers selected row, etc.).

### Почему выбран каждый CORE модуль (used)

| CORE ID | Почему выбран | Где на странице | Роль |
|---------|--------------|-----------------|------|
| CORE-01 | Каждая страница ERP использует app shell. Без него нет каркаса. | Корневой layout `main.php`. | Фрейм страницы. |
| CORE-02 | Необходима навигация с активным пунктом SUPERADMIN. | Левая панель 224px. | Навигация и контекст роли. |
| CORE-03 | Верхняя полоса с контекстом обязательна для каждой страницы. | Верх экрана, 38px. | Контекст текущей секции. |
| CORE-04 | Показывает путь/положение страницы в системе. | Внутри topbar. | Пространственная ориентация. |
| CORE-05 | Заголовок и назначение страницы — обязательный элемент. | Верх `.content`. | Идентификация страницы. |
| CORE-08 | Каждая admin section и системная информация упакованы в panel. | Основное содержимое `.content`. | Фрейминг рабочих секций. |
| CORE-09 | Это и есть основной паттерн секций SUPERADMIN: panel head с заголовком и badge + panel body с описанием. | 4 admin sections в `.content`. | Ядро страницы — admin/settings секции. |
| CORE-23 | Каждая секция на placeholder-этапе имеет disabled-кнопку, сообщающую "будет доступно позже". | Внутри каждой admin section panel body. | Маркер зарезервированного будущего действия. |
| CORE-24 | Нейтральный бейдж "В разработке" в каждой section head — скромный статус, не основной элемент. | В panel head каждой admin section. | Статус разработки модуля. |
| CORE-31 | Системная информация — идеальный use-case для key-value: короткие факты. | Панель "Системная информация". | Компактные системные факты. |
| CORE-32 | Нейтральное уведомление о статусе разработки — информирует, не привлекает внимание. | Между page head и admin sections. | Контекстное сообщение. |

### Почему НЕ выбраны модули из SUPERADMIN READY SET (not used)

| CORE ID | Почему НЕ выбран | Что использовано вместо |
|---------|-----------------|------------------------|
| CORE-06 | На placeholder-этапе нет page-level действий (создать компанию, экспорт и т.д.). | Ничего. Зона actions не рендерится. |
| CORE-13 | Таблица компаний появится на следующем этапе, когда владелец утвердит CRUD. | Ничего. Таблица не рендерится. Зарезервирована. |
| CORE-17 | Primary button требует реального primary action. На placeholder-этапе его нет. | CORE-23 (disabled button) для будущих действий. |
| CORE-18 | Secondary button также требует реального secondary action. | CORE-23 для disabled-кнопок "Настроить позже". |
| CORE-33 | Warning/blocking notice требует реального системного риска или блокирующего условия. | CORE-32 (neutral notice) для информационного сообщения. |

### Почему НЕ выбраны другие CORE модули

| CORE ID | Причина отказа |
|---------|---------------|
| CORE-07 Work area | Не нужен как отдельный контейнер — admin sections идут прямо в `.content`. |
| CORE-10 Toolbar | Нет таблицы/списка → нет тулбара для действий над данными. |
| CORE-11 Filter toolbar | Нет данных для фильтрации. |
| CORE-12 Search field | Нет данных для поиска. |
| CORE-14..16 (table states, row actions, pagination) | Нет таблицы → не нужны. |
| CORE-19..22 (ghost, toolbar, icon, danger buttons) | Нет действий → не нужны. |
| CORE-25 Operational status | Flight-статусы не применимы к admin shell. |
| CORE-26..29 (forms, validation, upload) | Нет форм на текущем этапе. |
| CORE-30 Right inspector | Центральная панель shell не использует list-detail. |
| CORE-34 Empty state | Admin shell — не список; empty state для таблиц появится на будущих этапах. |
| CORE-35 Loading / skeleton | Статическая страница без загрузки данных. |
| CORE-36..37 Modals | Нет действий, требующих модального подтверждения. |
| CORE-38 Tabs | Нет подобъектов для переключения. |
| CORE-39 Toast | Нет действий, результат которых нужно уведомлять. |
| CORE-40 Context menu | Нет right-click/secondary действий. |
| CORE-41..42 Charts | SUPERADMIN — admin/settings, не отчёты/аналитика. |
| CORE-43 Document section | Нет документов. |
| CORE-44 Activity log | Нет истории действий. |
| CORE-45 Bottom editor | Нет list-detail workflow.

## 0.3. Designer production handoff details

### Current visual diagnosis

Текущий экран `/superadmin` (реализация из Stage 1, commit `acd5009`) имеет следующие визуальные проблемы:

1. **Demo-заглушка вместо рабочей ERP-панели**: экран выглядит как `UI foundation` / техническая демо-страница, а не центральная административная панель SUPERADMIN.
2. **Карточный SaaS-dashboard подход**: используются классы `.summary-cards`, `.cards-grid`, `.module-card`, `.module-card-icon`, `.module-card-title`, `.module-card-desc` — это паттерн SaaS-дашборда с KPI-плитками, запрещённый для admin/settings страниц.
3. **Псевдоиконки**: `[=]`, `[#]`, `[~]`, `[v]`, emoji `[🏢]`, `[👤]`, `[⚙]`, `[🔧]`, `[📊]`, `[📋]` используются как временные иконки — запрещены дизайн-кодом.
4. **Текст `UI foundation`** в shell/контексте создаёт ощущение demo foundation, а не production-панели.
5. **Большая пустая область справа**: неиспользуемая рабочая область снижает плотность и создаёт визуальную пустоту.
6. **Debug badges как основной визуальный элемент**: `.badge-soon` и другие debug-элементы доминируют визуально.
7. **Несоответствие Industrial Graphite + Warm Accent**: цветовая схема, композиция и плотность не соответствуют утверждённому дизайн-коду STYLE ERP / Core Kit.
8. **Слабая композиция**: элементы разбросаны без чёткой вертикальной иерархии; нет surface hierarchy (page background → panels → alert → key-value).
9. **Topbar/sidebar не соответствуют смыслу SUPERADMIN**: контекст topbar и активный пункт sidebar не отражают назначение центральной админ-панели.

### Target visual result

Целевой экран `/superadmin` — **строгая admin/settings центральная панель SUPERADMIN**:

1. **Плотная вертикальная композиция без карточек**: одноколоночный layout с admin section panels, neutral notice и key-value блоком.
2. **Admin sections как `.panel`**: каждая секция имеет заголовок (panel head), muted описание (panel body) и нейтральный текстовый статус "В разработке" (badge). Никаких иконок, эмодзи, карточек.
3. **Compact key-value блок системной информации**: системные факты (система, среда, БД) в сжатом key-value формате, без акцентных цветов.
4. **Поверхностная иерархия**: page background (`--app-bg: #e2dfd8`) → panels (`--surface-strong: #fefdf8`) → neutral notice (`--surface-muted: #e8e4db`) → key-value. Поверхности не сливаются в бежевую массу.
5. **Никаких пустых неиспользуемых рабочих областей**: весь `.content` используется осмысленно.
6. **Экран сообщает**: "Центральная панель управления ERP PLANEX" — через title, subtitle, topbar context, sidebar active state и notice.
7. **Industrial Graphite + Warm Accent** сохранён во всех элементах.

### Exact layout

- **App shell**: topbar 38px + sidebar 224px + content, min-width 1440px (существующий `app/View/layouts/main.php`).
- **Grid**: одноколоночная плотная вертикальная композиция. Без multi-column, без card grid, без sidebar-right.
- **Sections order (сверху вниз)**:
  1. **Page head**: title `SUPERADMIN` (h1, 16px, 700) + subtitle `Центральная панель управления ERP PLANEX` (13px, muted).
  2. **Neutral notice** (`CORE-32`): `Центральная панель управления ERP PLANEX. Функционал находится в разработке.` — `.notice` на `--surface-muted`.
  3. **Admin section panel** (`CORE-09`): `Управление компаниями` — создание, настройка и управление локальными ERP-системами. Статус: "В разработке".
  4. **Admin section panel** (`CORE-09`): `Пользователи SUPERADMIN` — управление учётными записями администраторов системы. Статус: "В разработке".
  5. **Admin section panel** (`CORE-09`): `Feature toggles` — управление доступностью модулей, страниц и отчётов по компаниям. Статус: "В разработке".
  6. **Admin section panel** (`CORE-09`): `Системные настройки` — общие параметры системы, мониторинг, логи, аудит. Статус: "В разработке".
  7. **Key-value system info panel** (`CORE-31`): `Системная информация` — версия, среда, статус БД.
- **Sidebar active state**: пункт `SUPERADMIN` активен (класс `.is-active`, фон `--nav-active-bg`, цвет `--nav-text-act`, левый border 2px `--nav-gold`).
- **Topbar context**: breadcrumbs `SUPERADMIN — Центральная панель управления` (CORE-04).
- **Правый inspector**: нет.
- **Нижняя форма/editor**: нет.
- **Scroll**: вертикальный скролл внутри `.content` при переполнении (естественное поведение).
- **Production pattern**: **admin/settings** (`PATTERN-05` + `PATTERN-10`).

### Exact typography scale

| Элемент | Размер | Weight | Transform | Letter-spacing | Color |
|---------|--------|--------|-----------|----------------|-------|
| Page title (`h1`) | 16px | 700 | none | 0 | `--text-main` (#131210) |
| Subtitle | 13px | 400 | none | 0 | `--text-muted` (#4c4840) |
| Section panel heading | 12px | 700 | uppercase | 0.04em | `--text-main` (#131210) |
| Section panel description | 12px | 400 | none | 0 | `--text-muted` (#4c4840) |
| Status badge text ("В разработке") | 11px | 700 | none | 0 | `--neutral` (#625d57) |
| Notice text | 12px | 400 | none | 0 | `--text-muted` (#4c4840) |
| Key-value key | 12px | 700 | none | 0 | `--text-faint` (#78726a) |
| Key-value value | 12px | 400 | none | 0 | `--text-main` (#131210) |
| Nav items | 12px | 600 | none | 0 | `--nav-text` / `--nav-text-act` |
| Topbar breadcrumbs | 12px | 400 | none | 0 | `--text-faint` / `--text-main` |
| Body font | 13px | 400 | none | 0 | `--text-main` (#131210) |
| Font family | IBM Plex Sans, Segoe UI, Arial, sans-serif | — | — | — | — |

### Exact spacing scale

| Область | Значение |
|---------|----------|
| Content padding | 12px 14px 28px (top right/left bottom) |
| Section gap (между admin panels) | 8px |
| Panel padding (panel body) | 10px |
| Panel head height | 36px (min-height) |
| Panel head padding | 8px 10px |
| Notice margin-bottom | 8px |
| Key-value row padding | 5px 0 |
| Key-value grid gap | 94px + 1fr (`.kv` grid) |
| Page head margin-bottom | 10px |
| Sidebar width | 224px |
| Topbar height | 38px |
| Button height (disabled) | 30px (`--btn-h`) |

### Exact color tokens (только утверждённые CSS variables)

| Токен | Значение | Где используется |
|-------|----------|------------------|
| `--app-bg` | #e2dfd8 | Фон страницы / `.content` |
| `--surface-strong` | #fefdf8 | Фон панелей (`.panel`) |
| `--surface-muted` | #e8e4db | Фон panel head, notice |
| `--line` | #a8a196 | Граница панелей |
| `--line-hair` | #dedad0 | Внутренние разделители, panel head border-bottom |
| `--line-soft` | #c9c3b8 | Граница notice, badge border, button border |
| `--text-main` | #131210 | Заголовки, основной текст, key-value value |
| `--text-muted` | #4c4840 | Subtitle, описания, notice text |
| `--text-faint` | #78726a | Key-value key, breadcrumbs |
| `--neutral` | #625d57 | Цвет текста badge |
| `--neutral-bg` | #e3e0da | Фон badge |
| `--nav-bg` | #191816 | Фон sidebar |
| `--nav-text` | rgba(240,238,232,.65) | Текст nav items |
| `--nav-text-act` | #d4a254 | Текст активного nav item |
| `--nav-active-bg` | rgba(140,82,28,.28) | Фон активного nav item |
| `--nav-gold` | #c8933a | Левый border активного nav item + brand-mark |
| `--nav-hover` | #272522 | Фон nav item при hover |
| `--accent` | #7c4718 | Не используется на текущем этапе (нет primary action) |
| `--inverse` | #f5f3ee | Текст brand в sidebar |

### Required sections (порядок сверху вниз, без перестановок)

1. Page head: title `SUPERADMIN` + subtitle `Центральная панель управления ERP PLANEX`
2. Neutral notice: `Центральная панель управления ERP PLANEX. Функционал находится в разработке.`
3. Admin section panel: `Управление компаниями` — "Создание, настройка и управление локальными ERP-системами" + badge `В разработке` + disabled button `Настроить позже`
4. Admin section panel: `Пользователи SUPERADMIN` — "Управление учётными записями администраторов системы" + badge `В разработке` + disabled button `Настроить позже`
5. Admin section panel: `Feature toggles` — "Управление доступностью модулей, страниц и отчётов по компаниям" + badge `В разработке` + disabled button `Настроить позже`
6. Admin section panel: `Системные настройки` — "Общие параметры системы, мониторинг, логи, аудит" + badge `В разработке` + disabled button `Настроить позже`
7. Key-value system info panel: `Системная информация` — key-value rows (Система, Среда, Статус БД)

### Required states

| Состояние | Описание | Реализация |
|-----------|----------|------------|
| **Default/static** | Все панели видны, статусы "В разработке", кнопки disabled. | Стандартный рендер без условной логики. |
| **Hover на nav items** | Пункт навигации подсвечивается при наведении. | CSS: `background: var(--nav-hover); color: var(--nav-text-act)` |
| **Active nav item** | Пункт `SUPERADMIN` визуально выделен как текущий. | CSS: `.is-active` → `background: var(--nav-active-bg); color: var(--nav-text-act); border-left: 2px solid var(--nav-gold)` |
| **Disabled button** | Кнопка "Настроить позже" неактивна. | CSS: `.disabled` → `opacity: 0.45; cursor: not-allowed` |
| **Badge/status** | Нейтральный бейдж "В разработке". | CSS: `.badge` (без `badge-ok`/`badge-warn`/`badge-danger`) → `background: var(--neutral-bg); color: var(--neutral); border: 1px solid var(--line-soft)` |
| **Empty state** | Не применимо. Это admin shell, не список сущностей. | — |
| **Loading** | Не применимо. Страница статическая, без загрузки данных. | — |
| **Error** | Не применимо на текущем этапе. Нет операций, которые могут упасть. | — |

### Forbidden texts/classes/patterns

#### Forbidden texts

- `UI foundation`
- `Техническая демо-страница`
- `Основное действие`
- `Demo`, `showcase`, `foundation`, `example`, `sample`, `test`, `placeholder` (в контексте страницы)
- Любые формулировки, намекающие на демо/шоукейс/заглушку
- Псевдоиконки: `[=]`, `[#]`, `[~]`, `[v]`, `[>]`, `[<]`, `[+]`, `[-]`, `[*]`, `[x]`
- Emoji: `[🏢]`, `[👤]`, `[⚙]`, `[🔧]`, `[📊]`, `[📋]` и любые другие

#### Forbidden classes

- `.summary-cards`, `.summary-card`, `.summary-card-icon`, `.summary-card-value`, `.summary-card-label`
- `.cards-grid`
- `.module-card`, `.module-card-icon`, `.module-card-title`, `.module-card-status`, `.module-card-desc`
- `.placeholder-nav`, `.placeholder-nav-item`, `.placeholder-nav-icon`
- `.badge-soon`
- Любые Bootstrap-классы (`container`, `row`, `col-*`, `btn-*`, `alert-*`)
- Любые Tailwind-классы
- Любые Material-классы
- Любые классы, не утверждённые в Core Kit / `app.css` / этом handoff

#### Forbidden visual patterns

- Карточная сетка / KPI-плитки / summary counters
- SaaS-dashboard layout (многоколоночные карточки, крупные иконки, hero-секции)
- Большие пустоты / неиспользуемые рабочие области
- Blue/white corporate UI
- `border-radius > 4px` для новых элементов
- `box-shadow blur > 8px` для новых элементов
- Inline styles (кроме динамических PHP-значений)
- Browser-default controls (input, select, button без ERP-классов)
- Debug badges как основной визуальный элемент
- Яркие цветовые акценты вне утверждённой палитры
- Случайные цвета без токенов

### Coder implementation checklist

- [ ] Реализовать только по этому handoff, раздел B.
- [ ] НЕ использовать rejected previous implementation (раздел A) как основу.
- [ ] Убрать ВСЕ признаки `UI foundation` / demo / showcase с `/superadmin`.
- [ ] Убрать ВСЕ классы из forbidden list (`.summary-cards`, `.cards-grid`, `.module-card`, etc.).
- [ ] Page title: `SUPERADMIN` (16px, 700).
- [ ] Page subtitle: `Центральная панель управления ERP PLANEX` (13px, muted).
- [ ] Topbar context: `SUPERADMIN — Центральная панель управления`.
- [ ] Sidebar: пункт `SUPERADMIN` активен (класс `.is-active`).
- [ ] Required sections реализованы в указанном порядке (7 секций: page head → notice → 4 admin panels → key-value panel).
- [ ] Каждая admin section — `.panel` с panel head (заголовок + badge) и panel body (muted описание + disabled button).
- [ ] Panel head: `min-height: 36px`, `padding: 8px 10px`, `background: var(--surface-muted)`, `border-bottom: 1px solid var(--line-hair)`.
- [ ] Panel head title: 12px, 700, uppercase, letter-spacing 0.04em.
- [ ] Badge: класс `.badge` (нейтральный), текст `В разработке`, 11px, 700.
- [ ] Panel body description: 12px, `color: var(--text-muted)`.
- [ ] Disabled button: классы `.btn .btn-secondary .disabled`, текст `Настроить позже`, `opacity: 0.45`, `cursor: not-allowed`.
- [ ] Системная информация — compact key-value блок: `.kv` grid, 94px + 1fr.
- [ ] Ключи: `Система` → `ERP PLANEX`, `Среда` → `development`, `Статус БД` → `не проверялся`.
- [ ] Notice: класс `.notice`, `background: var(--surface-muted)`, `border: 1px solid var(--line-soft)`, `padding: 8px`, `border-radius: 2px`.
- [ ] НЕ использовать НИ ОДИН forbidden class.
- [ ] НЕ использовать псевдоиконки, emoji.
- [ ] НЕ использовать card-grid/KPI подход.
- [ ] НЕ добавлять новые CSS-классы без дизайнера.
- [ ] НЕ менять `layout/main.php`.
- [ ] НЕ менять `app.css` без явного указания дизайнера.
- [ ] НЕ подключаться к БД.
- [ ] НЕ добавлять JS-интерактивность.
- [ ] Все элементы статичные.
- [ ] `php -l` для изменённых PHP-файлов — без ошибок.
- [ ] Маршрут `/superadmin` возвращает 200.
- [ ] Content padding: `12px 14px 28px`.
- [ ] Gap между admin panels: 8px.
- [ ] Page head margin-bottom: 10px.
- [ ] Notice margin-bottom: 8px.

### QA formal checklist

- [ ] `UI foundation` отсутствует на `/superadmin` во всём контексте (page title, topbar, sidebar, content).
- [ ] `Техническая демо-страница` отсутствует.
- [ ] `Основное действие` отсутствует.
- [ ] Demo/showcase/foundation wording отсутствует.
- [ ] Page title = `SUPERADMIN`, subtitle = `Центральная панель управления ERP PLANEX`.
- [ ] Topbar context = `SUPERADMIN — Центральная панель управления`.
- [ ] Sidebar active state: пункт `SUPERADMIN` имеет класс `.is-active`.
- [ ] Все 7 required sections на месте в правильном порядке.
- [ ] Forbidden classes (`.summary-cards`, `.cards-grid`, `.module-card`, `.badge-soon`, `.placeholder-nav`, etc.) отсутствуют в HTML.
- [ ] Псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` и emoji отсутствуют.
- [ ] SaaS-card/dashboard pattern отсутствует (визуально и по классам).
- [ ] Пустая неиспользуемая рабочая область отсутствует.
- [ ] Backend/auth/CRUD/migrations/runner не тронуты.
- [ ] CORE modules used соответствуют Core Kit (11 модулей из SUPERADMIN READY SET).
- [ ] COMPOSITE pattern указан корректно: PATTERN-05 + PATTERN-10.
- [ ] MODULE USAGE DECISIONS заполнены для всех used/not-used модулей.
- [ ] Panel head имеет корректные стили (min-height: 36px, padding, background, border-bottom).
- [ ] Badge использует только класс `.badge` (нейтральный), без `badge-ok`/`badge-warn`/`badge-danger`.
- [ ] Disabled button имеет классы `.btn .btn-secondary .disabled` и стили `opacity: 0.45; cursor: not-allowed`.
- [ ] Key-value блок использует класс `.kv` с grid `94px 1fr`.
- [ ] Notice использует класс `.notice` с нейтральным фоном `var(--surface-muted)`.
- [ ] Цвета используют только утверждённые CSS variables.
- [ ] `border-radius` новых элементов ≤ 4px.
- [ ] `box-shadow blur` новых элементов ≤ 8px.
- [ ] Inline styles отсутствуют (кроме динамических PHP).
- [ ] `php -l` для `superadmin_dashboard.php` — без ошибок.
- [ ] Маршрут `/superadmin` возвращает HTTP 200.
- [ ] Formal UI QA: PASS / FAIL.
- [ ] Architect pre-owner review required: YES.
- [ ] Manual owner visual review required: YES.
- [ ] Commit allowed before owner visual approval: NO.

### Architect pre-owner review checklist

- [ ] Handoff был принят архитектором до передачи кодеру.
- [ ] Результат кодера устраняет ВСЕ причины предыдущего visual rejection (раздел A).
- [ ] Экран не выглядит как demo/foundation/showcase/web-page/SaaS-dashboard.
- [ ] Экран визуально сообщает назначение: production SUPERADMIN central admin panel.
- [ ] Нет пустой неиспользуемой рабочей области.
- [ ] Нет причин запускать ещё один designer/coder/QA cycle до owner review.
- [ ] Тексты соответствуют handoff (нет "UI foundation", "Техническая демо-страница", "Основное действие").
- [ ] Sidebar active state корректен.
- [ ] Topbar context корректен.
- [ ] Все 7 required sections на месте в правильном порядке.
- [ ] Поверхностная иерархия читается: page background → panels → notice → key-value.

### Owner visual checklist

- **VISUAL CHECK URL**: `http://127.0.0.1:[port]/superadmin`
- **Что владелец должен проверить глазами**:
  - [ ] Страница выглядит как строгая админ-панель, а не SaaS-dashboard.
  - [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`, emoji.
  - [ ] Нет карточной сетки / KPI-плиток.
  - [ ] Нет больших пустот.
  - [ ] Sidebar 224px тёмный (`#191816`), nav текст светлый.
  - [ ] Topbar 38px.
  - [ ] Плотная рабочая композиция без декоративных элементов.
  - [ ] Цветовая схема Industrial Graphite + Warm Accent сохранена.
  - [ ] Нет случайных цветов вне утверждённой палитры.
  - [ ] Нет blue/white corporate стиля.
  - [ ] Нет debug badges как основного визуального элемента.
  - [ ] `border-radius` ≤ 4px (новые элементы).
  - [ ] `box-shadow` blur ≤ 8px (новые элементы).
  - [ ] Admin sections читаются как зарезервированные production-модули, а не demo-заглушки.
  - [ ] Системная информация выглядит как компактный key-value блок, а не showcase paragraphs.
- **Возможные визуальные блокеры**:
  - Остатки карточного подхода от предыдущего варианта.
  - Псевдоиконки или emoji.
  - Слишком разреженная композиция.
  - Несоответствие цветовой схеме.
  - Текст `UI foundation` или demo/showcase wording.
  - Пустая неиспользуемая область.
- **Manual owner visual review required**: **YES**
- **Commit allowed before owner visual approval**: **NO**

### Failure signs

Признаки, при которых результат НЕ принят и возвращается в цикл (designer → coder → QA):

1. На странице видны псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` или emoji.
2. Используется карточная сетка (`.cards-grid`, `.summary-cards`, `.module-card`) или KPI-плитки.
3. Текст `UI foundation` присутствует на странице в любом контексте.
4. Page title НЕ равен `SUPERADMIN` или subtitle НЕ равен `Центральная панель управления ERP PLANEX`.
5. Topbar context НЕ соответствует `SUPERADMIN — Центральная панель управления`.
6. Sidebar active state НЕ установлен на `SUPERADMIN`.
7. Есть большие пустые неиспользуемые области.
8. Используются forbidden classes (`.summary-cards`, `.cards-grid`, `.module-card`, `.badge-soon`, `.placeholder-nav`, etc.).
9. Страница выглядит как demo/SaaS-dashboard/foundation, а не admin/settings панель.
10. Задеты backend/auth/CRUD/migrations без отдельного задания.
11. Изменён `layout/main.php` или `app.css` без указания дизайнера.
12. Добавлены новые CSS-классы без утверждения дизайнером.
13. Нарушен порядок required sections.
14. Отсутствует любой из 7 required sections.
15. `php -l` возвращает ошибку.
16. Маршрут `/superadmin` не возвращает 200.
17. Используются inline styles (кроме разрешённых динамических PHP).
18. `border-radius > 4px` или `box-shadow blur > 8px` для новых элементов.
19. Используются Bootstrap/Tailwind/Material классы.

### 0.3. SOURCE MAPPING

| # | UI element | CORE module ID | Exact source in Core Kit | Required classes | Forbidden alternatives |
|---|------------|----------------|--------------------------|-----------------|------------------------|
| 1 | App shell | CORE-01 | Production CSS Reference > `layout: grid 224px 1fr` | `.app-shell`, `.app-sidebar`, `.app-main` | Bootstrap .container, landing layout |
| 2 | Sidebar nav | CORE-02 | Production CSS Reference > `.nav-item`, `.nav-item:hover`, `.nav-item.is-active`, `.nav-dot` | `.nav-list`, `.nav-item`, `.nav-section`, `.nav-dot`, `.is-active` | `a.nav-item { color: inherit }`, no hover |
| 3 | Topbar | CORE-03 | Production CSS Reference > `.topbar`, `.environment-badge` | `.topbar`, `.topbar-label`, `.environment-badge` (handoff approved) | Hero header, UI foundation text |
| 4 | Breadcrumbs | CORE-04 | Core Kit > `.crumbs`, `.crumbs strong` | `.crumbs` | Fake breadcrumbs, demo labels |
| 5 | Page head | CORE-05 | Production CSS Reference > PAGE-HEAD RULE | `.page-head` (official), `h1` (16px, 700), `.text-muted` | `.page-header` for new pages, hero-scale block |
| 6 | Admin section panel | CORE-08 + CORE-09 | Production CSS Reference > `.panel`, `.panel-head`, `.panel-body`, `.panel-head-title` | `.panel` (padding:0), `.panel-head`, `.panel-body`, `.panel-head-title` or `h2` | `.panel` with padding, card grid, decorative shadow |
| 7 | Panel head | CORE-08 | Production CSS Reference > `.panel-head` | `.panel-head` (min-height:34px, flush to top) | Head with gap from panel border |
| 8 | Panel body | CORE-08 | Production CSS Reference > `.panel-body` | `.panel-body` (padding:10px) | Padding on `.panel` instead of body |
| 9 | Panel head title | CORE-08 sub | Production CSS Reference > `.panel-head-title` | `.panel-head-title` or `h2` (12px, 700, uppercase, 0.04em) | Invented title class, emoji in heading |
| 10 | Badge | CORE-24 | Production CSS Reference > `.badge` | `.badge` (neutral: `--neutral-bg`, `--neutral`) | `.badge-soon`, `.status-neutral`, Bootstrap badge |
| 11 | Disabled button | CORE-23 | Production CSS Reference > `.disabled` | `.btn .btn-secondary .disabled`, `opacity: .45`, `cursor: not-allowed` | Active-looking fake button, no disabled attribute |
| 12 | Notice | CORE-32 | Production CSS Reference > `.notice` | `.notice` (`--surface-muted`, `--line-soft`, padding:8px, radius:2px) | Blue `.alert-info`, Bootstrap `.alert`, decorative alert |
| 13 | Key-value block | CORE-31 | Production CSS Reference > `.kv`, `.kv dt`, `.kv dd` | `.kv` (grid 94px 1fr), `dt/dd` or `div > .k`, row `border-bottom` mandatory | Showcase paragraphs, missing dividers, accent colors |
| 14 | Disabled button (attribute) | CORE-23 | Core Kit > `.disabled` | `disabled` attribute on `<button>`, CSS `.disabled` class | Button without `disabled` attribute |
| 15 | Sidebar active state | CORE-02 | Production CSS Reference > `.nav-item.is-active` | `.nav-item.is-active` (`--nav-active-bg`, `--nav-text-act`, border-left `--nav-gold`) | Highlight-only active, no left border |

### 0.4. CSS COMPATIBILITY CHECK

| Parent | Child | Parent padding | Flush requirement | Padding belongs to | Border token | Required states |
|--------|-------|---------------|-------------------|--------------------| ------------|-----------------|
| `.panel` | `.panel-head` | **0** (mandatory) | **YES — head flush to top** | `.panel-body` (10px) | `--line-hair` (head border-bottom) | `h2` or `.panel-head-title` for heading |
| `.panel` | `.panel-body` | 0 | — | `.panel-body` (10px) | — | `.text-muted` for description, `.btn-secondary.disabled` for action |
| `.panel-head` | `.panel-head-title` | 8px 10px | — | `.panel-head` padding | — | `font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em` |
| `.kv` | `dt` + `dd` | — | — | `5px 0` per row | `--line-hair` (`border-bottom: 1px solid`) | `dt`: color `--text-faint`, weight 700. `dd`: color `--text-main`, weight 400 |
| `.nav-list` | `.nav-item` | — | — | — | `--nav-gold` (active border-left) | `:hover` mandatory: `background: var(--nav-hover); color: var(--nav-text-act)` |
| `.nav-item` | `.nav-dot` | — | — | — | `--nav-gold` (background) | Optional. Only if in handoff. |
| `.topbar` | `.environment-badge` | — | — | — | — | Optional. Handoff-approved. Font-size 11px, `color: var(--text-faint)` |
| `.content` | `.page-head` | 12px 14px 28px | — | `.content` padding | — | `h1`: 16px, 700. Subtitle: 13px, `--text-muted` |

**CRITICAL**: `.panel` + `.panel-head/.panel-body` pattern requires `.panel { padding: 0; overflow: hidden; }`. Adding padding to `.panel` when using `.panel-head` is a BLOCKER. This is the exact issue that caused the independent design audit failure.

---

## 1. Страница

- Route / view: `/superadmin` → `app/View/pages/superadmin_dashboard.php`
- Тип страницы: **admin settings** (центральная административная панель, НЕ dashboard)
- Пользователь (роль): SUPERADMIN (отдельный от локальной ERP, максимальные права)
- Главная задача пользователя: навигация к модулям управления системой, обзор состояния
- Что нельзя менять в бизнес-логике: на текущем этапе бизнес-логика отсутствует. Запрещено добавлять реальные данные, запросы к БД, авторизацию, CRUD, feature toggles.
- Page title: `SUPERADMIN`
- Page subtitle: `Центральная панель управления ERP PLANEX`
- Topbar context: `SUPERADMIN — Центральная панель управления`
- Sidebar active state: активен пункт `SUPERADMIN`; корневой пункт `Навигация` не активен на `/superadmin`.
- Production pattern: **admin/settings**, не showcase, не dashboard, не UI foundation.

## 2. Layout

- App shell: **topbar 38px + sidebar 224px + content** (существующий `app/View/layouts/main.php`)
- Content min-width: 1440px (desktop-first)
- Основная сетка: одноколоночная, плотная вертикальная композиция:
  ```
  page-head (компактный, без KPI-карточек)
  → admin sections (панели с текстовыми описаниями модулей)
  → системная информация
  ```
- Правый inspector: **нет**
- Нижняя форма/editor: **нет**
- Scroll areas: вертикальный скролл внутри `.content` при переполнении
- **Запрещено**: карточная сетка, SaaS-dashboard layout, большие пустоты, декоративные отступы

## 3. Page head

- Eyebrow: нет (или "Центральная панель управления")
- Title: **"SUPERADMIN"**
- Summary counters: **НЕ ИСПОЛЬЗОВАТЬ**. Никаких KPI-карточек, summary-cards, числовых плиток. Это admin-панель, не dashboard.
- Primary action (`.btn .btn-primary`): отсутствует (на текущем этапе)
- Secondary actions: отсутствуют

## 4. Filters / toolbar

- Filters-bar: **нет** (на текущем этапе)
- Поля фильтрации: не применимо
- Filter chips: нет
- Toolbar actions: нет

## 5. Main content / admin sections

Вместо карточной сетки используется список **admin sections** — текстовых панелей, описывающих будущие модули SUPERADMIN.

Каждая секция — `.panel`:
- заголовок секции (название модуля);
- краткое описание (muted);
- статус разработки (текст "В разработке" — скромный, не основной элемент);
- **без иконок, без эмодзи, без псевдоиконок**;
- **не кликабельная** (на текущем этапе).

### Admin sections (будущие модули)

| # | Название модуля | Описание | Статус |
|---|---|---|---|
| 1 | Управление компаниями | Создание, настройка и управление локальными ERP-системами | В разработке |
| 2 | Пользователи SUPERADMIN | Управление учётными записями администраторов системы | В разработке |
| 3 | Feature toggles | Управление доступностью модулей, страниц и отчётов по компаниям | В разработке |
| 4 | Системные настройки | Общие параметры системы, мониторинг, логи, аудит | В разработке |

### Системная информация

Под admin sections — компактный информационный блок:
- Версия системы
- Статус БД (если применимо)
- Информация о среде

Всё в muted стиле, без акцентных цветов, без иконок.

### Required sections order

1. Page head: title `SUPERADMIN`, subtitle `Центральная панель управления ERP PLANEX`.
2. Neutral alert: `Центральная панель управления ERP PLANEX. Функционал находится в разработке.`
3. Admin section: `Управление компаниями`.
4. Admin section: `Пользователи SUPERADMIN`.
5. Admin section: `Feature toggles`.
6. Admin section: `Системные настройки`.
7. Compact key-value section: `Системная информация`.

### STYLE ERP extracted target qualities

- Dense admin/settings page, not showcase composition.
- Sidebar 224px, topbar 38px, page-specific active nav.
- Compact page-head, no hero, no KPI dashboard.
- Admin sections as panels with title, muted description and neutral status.
- Key-value block for system facts.
- Surface hierarchy: page background, panels, alert and key-value block must not merge into beige mass.
- No empty unused workspace below the admin sections.

### Key-value structure

Системная информация должна быть key-value блоком, а не showcase paragraphs:

| Key | Value |
|---|---|
| Система | ERP PLANEX / app_name |
| Среда | app_env |
| Статус БД | не проверялся / будущий статус |

## 6. Inspector / detail panel

- Нужен: **нет**
- Width: не применимо

## 7. Forms

- Не применимо (на текущем этапе форм нет)

## 8. Modals

- Не применимо (на текущем этапе модалок нет)

## 9. Toasts / alerts

- Не применимо (на текущем этапе)

### Информационный alert (опционально)

Компактный информационный блок `.alert` (не `.alert-info` с синей рамкой — использовать нейтральный стиль):
> Центральная панель управления ERP PLANEX. Функционал находится в разработке.

Без восклицательных знаков, без иконок, без ярких цветов.

## 10. Empty / loading / error

- Empty state: "Модули SUPERADMIN находятся в разработке."
- Loading skeleton: не применимо (статическая страница)
- Error state: не применимо (на текущем этапе)

## 11. Statuses and badges

- Используемые badges: только `.status-neutral` для статуса "В разработке" (скромно, muted).
- **Запрещены**: `.badge-soon`, яркие бейджи, debug badges как основной визуальный элемент.
- Flight statuses: не применимо.

## 12. Charts

- Не применимо.

## 13. CSS/classes coder must use

### Существующие классы (из `app.css`)

- Layout: `.app-shell`, `.app-sidebar`, `.app-main`, `.content`, `.topbar`, `.brand`, `.brand-mark`
- Панели: `.panel`
- Текст: стандартные теги, `var(--color-muted)` через utility или компонент
- Статусы: `.status`, `.status-neutral`
- Sidebar nav: `.nav-list`, `.nav-item`, `.nav-section`, `.nav-dot`, `.is-disabled`, `.is-active`

### Запрещённые классы (из предыдущего варианта)

**Эти классы не должны использоваться в новом варианте:**
- `.summary-cards` — KPI-карточки запрещены
- `.summary-card` — карточный подход запрещён
- `.summary-card-icon` — запрещён
- `.summary-card-value` — запрещён
- `.summary-card-label` — запрещён
- `.placeholder-nav` — заменяется на admin sections
- `.placeholder-nav-item` — запрещён
- `.placeholder-nav-icon` — псевдоиконки запрещены
- `.badge-soon` — debug-бейдж запрещён
- `.cards-grid` — карточная сетка запрещена
- `.module-card` — карточка модуля запрещена
- `.module-card-icon` — псевдоиконки запрещены
- `.module-card-title` — карточный подход запрещён
- `.module-card-status` — заменяется на скромный текстовый статус
- `.module-card-desc` — карточный подход запрещён

### Новые классы (если нужны)

Только через дизайнера. Запрещено придумывать кодеру. Если нужен новый класс — указать его здесь до передачи кодеру.

## 14. Strict prohibitions for coder

- Не использовать **НИ ОДИН** класс из списка запрещённых выше.
- Не использовать текст `UI foundation` на `/superadmin`, в page title, topbar context, nav active context или основном контенте.
- Не использовать текст `Техническая демо-страница`.
- Не использовать абстрактный текст `Основное действие`.
- Не использовать demo/showcase/foundation wording.
- Не делать форму/таблицу как showcase компонентов.
- Не оставлять пустую неиспользуемую нижнюю рабочую область.
- Не использовать псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` и любые другие.
- Не использовать emoji как иконки.
- Не использовать карточную сетку (`.cards-grid`, `.summary-cards`).
- Не использовать `.badge-soon`.
- Не использовать KPI-карточки / summary counters.
- Не делать SaaS-dashboard layout.
- Не делать большие пустоты.
- Не использовать blue/white corporate цвета.
- Не использовать случайные CSS-классы.
- Не использовать `border-radius > 4px` для новых элементов.
- Не использовать `box-shadow blur > 8px` для новых элементов.
- Не использовать inline styles.
- Не использовать Bootstrap/Tailwind/Material классы.
- Не подключаться к БД.
- Не добавлять авторизацию, сессии, проверку прав.
- Не добавлять интерактивность (JS).
- Не менять layout/main.php.
- Не менять app.css без явного указания дизайнера.
- Не придумывать новые классы без дизайнера.

## 14.1. Coder implementation checklist

- [ ] Использовать только актуальный раздел `B. Current target production handoff`.
- [ ] Не использовать rejected previous implementation как основу.
- [ ] Убрать с `/superadmin` все признаки `UI foundation` / demo / showcase.
- [ ] Проверить page title, subtitle, topbar context и active sidebar state.
- [ ] Реализовать required sections order без перестановок.
- [ ] Реализовать `Системная информация` как compact key-value block.
- [ ] Не добавлять backend/auth/CRUD/migrations/runner.
- [ ] Не менять PHP/CSS/view вне разрешённого scope конкретной задачи.

## 14.2. QA formal checklist

- [ ] `UI foundation` отсутствует на `/superadmin` как page/admin context.
- [ ] `Техническая демо-страница` отсутствует.
- [ ] `Основное действие` отсутствует.
- [ ] demo/showcase/foundation wording отсутствует.
- [ ] Page title/subtitle/topbar/sidebar active state соответствуют handoff.
- [ ] Все required sections присутствуют и идут в нужном порядке.
- [ ] Forbidden classes отсутствуют.
- [ ] Pseudo-icons и emoji отсутствуют.
- [ ] SaaS-card/dashboard pattern отсутствует.
- [ ] Empty unused workspace отсутствует.
- [ ] Backend/auth/CRUD/migrations/runner не тронуты.
- [ ] Formal UI QA: PASS/FAIL.
- [ ] Architect pre-owner review required: YES.
- [ ] Manual owner visual review required: YES.
- [ ] Commit allowed before owner visual approval: NO.

## 14.3. Architect pre-owner review checklist

- [ ] Handoff был принят архитектором до передачи кодеру.
- [ ] Результат устраняет причины предыдущего visual rejection.
- [ ] Экран не выглядит как demo/foundation/showcase/web-page/SaaS-dashboard.
- [ ] Экран сообщает назначение production SUPERADMIN central admin panel.
- [ ] Нет пустой неиспользуемой рабочей области.
- [ ] Нет причин запускать ещё один designer/coder/QA cycle до owner review.

## 15. Owner visual check (ОБЯЗАТЕЛЬНО)

- **VISUAL CHECK URL**: `http://127.0.0.1:[port]/superadmin`
- **Что владелец должен проверить глазами**:
  - [ ] Страница выглядит как строгая админ-панель, а не SaaS-dashboard.
  - [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`, emoji.
  - [ ] Нет карточной сетки / KPI-плиток.
  - [ ] Нет больших пустот.
  - [ ] Sidebar 224px тёмный, nav текст светлый.
  - [ ] Topbar 38px.
  - [ ] Плотная рабочая композиция.
  - [ ] Цветовая схема Industrial Graphite + Warm Accent.
  - [ ] Нет случайных цветов.
  - [ ] Нет blue/white corporate стиля.
  - [ ] Нет debug badges как основного визуального элемента.
  - [ ] `border-radius` ≤ 4px (новые элементы).
  - [ ] `box-shadow` blur ≤ 8px (новые элементы).
- **Возможные визуальные блокеры**:
  - Остатки карточного подхода от предыдущего варианта.
  - Псевдоиконки или emoji.
  - Слишком разреженная композиция.
  - Несоответствие цветовой схеме.
- **Manual owner visual review required**: **YES**
- **Commit allowed before owner visual approval**: **NO**

## 16. Acceptance checklist

- [ ] Industrial Graphite + Warm Accent сохранён.
- [ ] Страница — строгая admin/settings панель, не SaaS-dashboard.
- [ ] Нет псевдоиконок `[=]`, `[#]`, `[~]`, `[v]`, emoji.
- [ ] Нет классов из запрещённого списка (`.summary-cards`, `.cards-grid`, `.module-card`, `.badge-soon`, etc.).
- [ ] Нет KPI-карточек / summary counters.
- [ ] Sidebar 224px тёмный, nav текст светлый.
- [ ] Topbar 38px.
- [ ] Плотная рабочая композиция без больших пустот.
- [ ] Admin sections вместо карточной сетки.
- [ ] Скромный текстовый статус "В разработке" (не основной визуальный элемент).
- [ ] Все элементы статичные, без JS-интерактивности.
- [ ] Нет обращений к БД.
- [ ] Нет случайных CSS-классов.
- [ ] Нет хардкода цветов.
- [ ] Нет inline styles.
- [ ] Нет Bootstrap/Tailwind/Material классов.
- [ ] `border-radius` новых элементов ≤ 4px.
- [ ] `box-shadow blur` новых элементов ≤ 8px.
- [ ] Кодер может реализовать страницу без поиска примеров.
- [ ] QA может проверить страницу по этому шаблону.
- [ ] `php -l` для изменённых PHP-файлов — без ошибок.
- [ ] Маршрут `/superadmin` работает.
- [ ] `docs/ai/AGENT_WORK_LOG.md` обновлён.
- [ ] **Formal UI QA**: PASS.
- [ ] **VISUAL CHECK URL** предоставлен.
- [ ] **Manual owner visual review required**: YES.
- [ ] **Commit allowed before owner visual approval**: NO.
