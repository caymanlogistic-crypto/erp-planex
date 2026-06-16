# ERP PLANEX — текущее состояние проекта

## Назначение файла

Один короткий файл текущего состояния проекта.
Не хранит длинную историю.
Не заменяет Git.
Не дублирует отчёты агентов.

## Текущая агентская схема

```text
Владелец + ChatGPT → erp-architect → erp-coder → внешний ChatGPT "Главный дизайнер" → erp-coder
```

## Активные KILO-агенты

```text
erp-architect
erp-coder
```

## Исключены из постоянной цепочки

```text
erp-uiux-designer
erp-qa-tester
```

## Дизайн-база

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS для проекта:

```text
public/assets/css/erp-ui.css
```

Рабочий стандарт:

```text
docs/ui/DESIGN_STANDARD.md
```

## Текущее правило разработки

Кодер обязан:

```text
реализовать функционал → протестировать → применить erp-ui.css → повторно проверить
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
Последний стабильный commit: da1cc90
```

## Текущий блок

```text
Водители / Машины / Экипажи — ЭТАП 2: CRUD + функциональный UX ЗАВЕРШЁН
Следующий шаг: runtime owner review → при необходимости исправления → дизайн-полировка
```

## Последний принятый этап

```text
Принято erp-architect (CRUD_UX_ACCEPTED):

Частичный подэтап (commit 65eaf8e):
- vehicle_sets CRUD, driver_vehicle_blocks CRUD
- crews по новой схеме contractor + driver_vehicle_block
- vehicles → vehicle_units, pts_number скрыт
- grants/documents расширены, sidebar обновлён

Финальный подэтап (текущий commit):
- Contractor contacts inline CRUD (6 маршрутов: create/edit/delete/set-primary/set-document-email)
- Driver phones inline CRUD (4 маршрута: create/edit/delete/set-main)
- Contractor tax history append-only
- Role-based access: logist видит свои + grants, company_owner видит все
- Card-level access checks (view/edit/archive)
- Каскадная видимость в crew_view, driver_view, vehicle_view
- Documents access after grants (logist удаляет только свои, company_owner видит удалённые)
- Расширены views: contractors (тип, КПП, контакты, банк, налоги), drivers (паспорт, СНИЛС, телефоны, блоки)
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего блока.

Не добавлять длинные отчёты.
