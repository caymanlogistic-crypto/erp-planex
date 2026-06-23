# ERP PLANEX — текущее состояние проекта

## Назначение файла

Короткий файл текущего состояния проекта. Не хранит длинную историю, не заменяет Git и не дублирует отчёты агентов.

## Текущая агентская схема

```text
Владелец + ChatGPT → erp-architect → erp-coder → erp-architect acceptance → владелец + ChatGPT
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

QA встроен в работу кодера, архитектора и финальную приёмку владельцем + ChatGPT.

## Дизайн-база

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS:

```text
public/assets/css/erp-ui.css
public/assets/css/app.css
```

Рабочий стандарт:

```text
docs/ui/DESIGN_STANDARD.md
```

## Последний подтверждённый commit по текущей ветке работ

```text
c3b825c — fix(drivers): align create modal footer with FINAL3
```

## Важные commits из текущей цепочки

```text
c3b825c — fix(drivers): align create modal footer with FINAL3
7a796fb — feat(drivers): open create form in FINAL3 modal from drivers list
051155f — wip(ui): refine ERP grid toolbar and list pages
3ddade7 — wip(drivers): recompose drivers grid fields and documents
90ab717 — fix(drivers): remove unique phone constraint
365dbbe — wip(ui): unify ERP grid list tables
a2149a7 — feat(stepper): contractor/driver/vehicle create-full with FINAL3 stepper
67b5454 — feat(clients): align client create flow with legal entity standard
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
CLIENT_CREATE_LEGAL_ENTITY_STANDARD — ПРИНЯТ.
DRIVER_CREATE_MODAL_FROM_LIST — ПРИНЯТ И ЗАКОММИЧЕН (c3b825c).
DRIVERS_GRID_DOCUMENT_COLUMNS — частично сделано ранее, не текущий фокус.
DRIVER_EDIT_MODAL_VIEW_EDIT_FLOW — В РАБОТЕ, НЕ ПРИНЯТ.
STEPPER_CONTRACTOR_DRIVER_VEHICLE — есть предыдущая реализация/подготовка, но сейчас не текущий фокус.
VEHICLE_SET_PRODUCTION_CREATE — подготовлены MD, отложено до закрытия driver edit modal.
PROTECTED_ARCHITECTURE_PLAN — ПРИНЯТ (c19c67a).
FOUNDATION_STAGE_B — ВЫПОЛНЕН: AccessControlService + DocumentService (не подключены к ядру).
```

## Текущая активная задача

```text
DRIVER_EDIT_MODAL_GEOMETRY_NEEDS_REWORK
```

Нужно довести edit modal водителя до соответствия create-form 1 в 1 по геометрии.

Критерий: edit-form должна быть той же формой создания, только предзаполненной. Разница только в значениях, кнопке `Сохранить`, `Заменить` и `×` у существующих документов.

## Текущий визуальный blocker

По скринам владельца:

```text
CREATE: левая колонка ≈ 644 px, правая ≈ 304 px, общая рабочая ширина ≈ 948 px.
EDIT: левая колонка ≈ 575 px, правая ≈ 304 px, общая рабочая ширина ≈ 880 px.
```

Edit-form сжата примерно на 65–70 px по левой колонке. Нужно исправить modal/body/layout/wrapper/padding/margin, не трогая backend и интерактив.

## CRITICAL UI LOCK RULE

Запрещено менять без прямого подтверждения владельца:

1. Основную шапку ERP.
2. Шапку контентного блока / page-head.
3. Основное меню / sidebar.
4. Shell layout.

Разрешено только добавлять новые кнопки/действия без изменения существующей структуры и поведения.

## Правило обновления

Этот файл обновлять только при изменении текущего статуса проекта, активной задачи, агентской схемы, последнего принятого этапа или следующего блока.
