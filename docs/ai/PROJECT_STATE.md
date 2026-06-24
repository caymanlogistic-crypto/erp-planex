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
f993342 — fix(access): enforce contractor assignment context visibility
```

## Важные commits из текущей цепочки

```text
f993342 — fix(access): enforce contractor assignment context visibility
c0cf919 — feat(access): add contractor assignment management
d5a6ace — feat(access): add contractor cascade sharing (заменён, см. DECISIONS #51)
d3d3524 — feat(access): add senior logist role visibility
888ba64 — feat(master-flow): add contractor crew creation workflows
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
CLIENT_CREATE_LEGAL_ENTITY_STANDARD — ПРИНЯТ.
DRIVER_CREATE_MODAL_FROM_LIST — ПРИНЯТ И ЗАКОММИЧЕН (c3b825c).
DRIVER_EDIT_MODAL_VIEW_EDIT_FLOW — В РАБОТЕ, НЕ ПРИНЯТ.
MASTER_FLOW_IMPLEMENTATION — ВЫПОЛНЕН (888ba64): contractor crew creation workflows.
BLOCK_D — ВЫПОЛНЕН (d3d3524): senior_logist role + visibility rules.
BLOCK_D2/D5 — ВЫПОЛНЕН: contractor cascade sharing заменён на contractor assignment.
CONTRACTOR_ASSIGNMENT — УСЛОВНО ПРИНЯТ (c0cf919 + f993342): привязка перевозчиков + visibility enforcement.
BLOCK_D6 — ВЫПОЛНЕН: документация новой модели доступа (3e5405c).
BLOCK_E1 — АРХИТЕКТУРНЫЙ ПЛАН: упрощение «Водитель+ТС» + «Экипаж» → «Исполнитель рейса».
```

## Текущая активная задача

```text
BLOCK_E1 — архитектурный план перехода к «Исполнителю рейса» (только MD, без кода).
```

Следующая задача: **BLOCK_E2** — UI/menu facade «Исполнители рейса» (после утверждения плана владельцем).

## CRITICAL UI LOCK RULE

Запрещено менять без прямого подтверждения владельца:

1. Основную шапку ERP.
2. Шапку контентного блока / page-head.
3. Основное меню / sidebar.
4. Shell layout.

Разрешено только добавлять новые кнопки/действия без изменения существующей структуры и поведения.

## Правило обновления

Этот файл обновлять только при изменении текущего статуса проекта, активной задачи, агентской схемы, последнего принятого этапа или следующего блока.
