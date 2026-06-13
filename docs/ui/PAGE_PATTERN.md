# ERP PLANEX — PAGE_PATTERN

## Назначение

Этот файл задаёт общий стандарт страницы ERP PLANEX.

Главный UI-регламент: `docs/ui/DESIGN_CODE_INTEGRATION.md`.

Формализованные правила из STYLE ERP: `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`.

Основной компактный UI-kit: `docs/ui/ERP_UI_KIT_CORE.html`.

Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html`.

Шаблоны конкретных страниц хранятся в:

```text
docs/ui/pages/
```

---

## Стандарт страницы модуля

Каждая страница должна иметь:

```text
Заголовок
Краткое описание / summary
Основное действие
Фильтры / поиск при необходимости
Таблицу, форму или рабочую область
Статусы
Действия
Пустое состояние
Loading state, если есть загрузка
Error state
```

---

## Production Page Patterns from STYLE ERP

Для важных экранов дизайнер обязан выбрать один рабочий pattern и описать его в page handoff:

- `list + inspector`: page-head → filters-bar → main table/work area + right inspector 320-380px → pagination.
- `table-only`: page-head → toolbar/filter → full-width ERP-grid → pagination.
- `master-detail`: entity head/status/actions → tabs → left sections/forms/tables + right summary/activity.
- `form/editor`: grouped fieldsets → validation panel/summary when needed → clear form actions.
- `admin/settings`: page-head → settings/admin sections → tables/forms/key-value blocks → neutral status.
- `report/charts`: page-head → filters → chart cards + table detail when needed.

Запрещено использовать showcase composition как production page.

## Page Header Rules

- Page title: 15-16px, 700, конкретное название business/admin объекта.
- Subtitle: коротко объясняет назначение страницы.
- Topbar context должен соответствовать текущей странице.
- Sidebar active item должен соответствовать route.
- Запрещены `UI foundation`, `Техническая демо-страница`, `Основное действие`, demo/showcase/foundation wording на business/admin pages.

---

## Обязательное правило

Если страница создаётся или меняется, сначала должен быть актуальный MD-шаблон страницы в `docs/ui/pages/`.

Кодер не должен сам проектировать структуру страницы.

MD-шаблон страницы обязан содержать `CORE modules used`, выбранный `COMPOSITE pattern` и `MODULE USAGE DECISIONS` по `docs/ui/ERP_UI_KIT_CORE.html`.

Private applied examples не являются названиями универсальных модулей. Названия, привязанные к конкретному source screen, запрещены в universal handoff.

Если для страницы нет подходящего формализованного модуля, дизайнер не проектирует его внутри handoff, а возвращает:

```text
BLOCKED: NEEDS_UI_MODULE_EXPANSION
```

---

## Пустое состояние

Если данных нет, пользователь должен понимать, что делать.

Пример:

```text
Клиенты пока не добавлены. Добавьте первого клиента, чтобы создать заявку и рейс.
```

---

## Ошибки

Ошибки должны быть понятными обычному пользователю.

Запрещено показывать пользователю сырые PHP/SQL/debug ошибки.

---

## Опасные действия

Удаление, отклонение, сброс, архивирование и изменение критичного статуса должны иметь подтверждение.

---

## SUPERADMIN UI pattern

SUPERADMIN — центральная административная панель ERP PLANEX. Использует строгий **admin/settings pattern**:

- sidebar 224px с текстовой навигацией;
- topbar 38px;
- page-head;
- settings/admin sections;
- reserved modules as system sections;
- tables/forms/panels когда появляются данные;
- **запрещены** KPI dashboard cards (если явно не approved владельцем);
- **запрещены** псевдоиконки `[=]`, `[#]`, `[~]`, `[v]` и emoji как иконки;
- **запрещены** debug badges как основной визуальный элемент;
- **запрещён** SaaS-dashboard/card-grid подход;
- **запрещены** большие пустоты;
- плотная рабочая композиция без декоративных элементов.
