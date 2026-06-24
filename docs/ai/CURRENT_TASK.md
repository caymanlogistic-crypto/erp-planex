# ERP PLANEX — текущая задача

## STATUS: BLOCK_E1_ROUTE_EXECUTOR_ARCHITECTURE

Текущая активная задача — архитектурный план упрощения модели «Водитель+ТС» + «Экипаж» → «Исполнитель рейса».

Это архитектурный этап: только MD, без кода, без SQL, без UI, без миграций.

## Что сделано

1. Изучены все таблицы: `driver_vehicle_blocks`, `crews`, `contractors`, `drivers`, `vehicle_sets`, `vehicle_units`, `entity_access_grants`, `contractor_assignment_history`, `documents`.
2. Изучены все routes для driver_vehicle_blocks, crews, contractor-assignments.
3. Изучены master-flow routes: create-full, add-crew.
4. Изучена структура меню в `app/View/layouts/main.php`.
5. Изучены миграции: 006, 009, 017, 018, 019, 021, 036, 037.
6. Создан `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`.
7. Выбран рекомендованный путь: **Вариант А (UI-facade)** — физические таблицы сохраняются, UI показывает «Исполнитель рейса».

## Новая целевая сущность

**Исполнитель рейса** = Подрядчик + Водитель + ТС.

- «Водитель+ТС» и «Экипаж» больше не являются целевыми пользовательскими пунктами.
- «Привязка перевозчиков» упраздняется как отдельный пункт.
- Будущая перепривязка ответственного — через списки: Исполнитель рейса / Подрядчик / Водитель / ТС.

## Файлы к обновлению

```text
docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md — СОЗДАН (новый)
docs/ai/DECISIONS.md                        — добавить решение #54
docs/ai/PROJECT_STATE.md                    — статус E1
docs/ai/CURRENT_TASK.md                     — этот файл
docs/ai/HANDOFF_FOR_NEW_CHAT.md             — новый контекст
```

## После выполнения

Commit: `docs(architecture): plan route executor simplification`

Следующая задача: **BLOCK_E2** — UI/menu facade «Исполнители рейса» (после утверждения плана владельцем).

## Запреты

- Не писать код.
- Не менять PHP/SQL/CSS/JS.
- Не создавать новые routes.
- Не менять меню сейчас.
- Не делать миграции.
- Не удалять таблицы.
