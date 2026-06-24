# ERP PLANEX — текущая задача

## STATUS: BLOCK_E3_COMPLETE

Блоки E1 (архитектурный план), E2 (facade list + menu), E3 (полный CRUD workflow) выполнены.

## Что реализовано в E3

1. **Список Исполнителей рейса** (`/company/route-executors`) — с кнопкой создания.
2. **Создание** (`GET/POST /company/route-executors/create`) — три выпадающих списка: Подрядчик + Водитель + ТС. Backend сам находит/создаёт driver_vehicle_block и crew в одной транзакции.
3. **Просмотр** (`GET /company/route-executors/{id}`) — карточка с блоками: Подрядчик, Водитель, ТС, Ответственный логист, Статус, Действия.
4. **Редактирование** (`GET/POST /company/route-executors/{id}/edit`) — изменение подрядчика/водителя/ТС/статуса.
5. **Архивирование** (`POST /company/route-executors/{id}/archive`) — архивирует crew, не трогает driver_vehicle_block.
6. **Backend-защита от чужих ID** — для logist проверяется доступ к contractor_id, driver_id, vehicle_set_id через created_by_user_id + entity_access_grants.
7. **Проверка дублей** — UNIQUE constraint на crews (contractor_id, driver_vehicle_block_id) защищает от дублей; backend проверяет перед INSERT.
8. **Dropdown filtering** — для logist выпадающие списки фильтруются по доступности.
9. **Меню** — скрыты «Водители+ТС», «Экипажи», «Привязка перевозчиков» (выполнено в E2).
10. **Старые routes** — сохранены и работают.

## Commit

`1488d55 — feat(route-executors): complete route executor workflow`

## Следующая задача

**BLOCK_E4** — Переназначение ответственных логистов (страница с вкладками: Исполнители рейса / Подрядчики / Водители / ТС).

## Запреты (всё ещё актуальны)

- Не удалять таблицы `driver_vehicle_blocks`, `crews`.
- Не менять protected core (`/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`).
- Не менять topbar/sidebar без подтверждения владельца.
- Не добавлять документы в форму Исполнителя рейса без отдельного этапа.
