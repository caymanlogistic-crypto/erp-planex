# ERP PLANEX — текущая задача

## Актуализация 2026-07-02 — small docs/UX fix before deployment prep

**Статус**: LINEAR_TRIPS_UNLOADING_DATE_ALIGNMENT_IN_PROGRESS

Выполнено:
- Принятый модуль `Рейсы → Линейные` зафиксирован в commit `fd511031 feat(trips): add accepted linear routes module`.
- Workflow-документация после нормализации веток зафиксирована в commit `3b96b77 docs(ai): record develop workflow and linear routes acceptance`.
- Текущий corrective accepted HEAD в `develop`: `231ccb5 fix(trips): close linear route corrective findings`.
- Рабочая модель веток нормализована: `develop` — единственная ветка разработки и тестирования, `master` — стабильная deploy/server ветка.
- `master` намеренно остаётся на принятом runtime commit `fd511031`, а `develop` намеренно идёт впереди на `231ccb5`.
- `public/index.php` остаётся тонким front controller (`92` строки), маршруты вынесены в `app/Http/Routes`.
- Модуль `Рейсы → Линейные` принят после browser-click runtime: меню `Рейсы → Линейные`, линейные и агентские рейсы, повторяемые блоки принципалов/оплат/документов, целочисленные суммы, flow create/view/edit/documents/delete.
- Текущий маленький corrective scope: в edit-форме `Плановая дата выгрузки` должна быть опциональной, чтобы edit-flow совпадал с уже принятым create-flow.

Ограничения текущей короткой проверки:
- `master` в этой задаче не менять, не коммитить и не синхронизировать без отдельной прямой команды владельца.

## Что дальше

- Продолжать любую новую разработку только в `develop`.
- После завершения следующего блока сначала делать commit в `develop`.
- Отдельное решение о синхронизации `master` принимает только владелец.
