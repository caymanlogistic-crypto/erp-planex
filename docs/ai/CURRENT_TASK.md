# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — compact legal-entity full-page edit UX

**Статус**: LEGAL_ENTITY_EDIT_COMPACT_UX_IMPLEMENTED

Выполнено:
- `app/View/pages/company_client_edit.php` — restructured to compact Driver-like layout using form-grid-2/form-grid-3.
- `app/View/pages/company_contractor_edit.php` — restructured to compact Driver-like layout using form-grid-2/form-grid-4.
- Sections: Основные данные (name + status in grid), Реквизиты (INN/KPP/OGRN/type in grid), Адреса (2-column textarea grid), Контакты, Банковские реквизиты (contractor), Комментарий (compact rows=2).
- No inline styles, no CSS changes needed (all classes already exist).
- All input names, form actions, methods, and contact mapping preserved.

Ограничения:
- `master` не менять, не коммитить и не синхронизировать без отдельной команды владельца.

### Предыдущий контекст: unified legal-entity create form refactor (принят)

**Статус**: LEGAL_ENTITY_CREATE_UNIFIED_PARTIAL_IMPLEMENTED (принят ранее)

### Предыдущий контекст: Исполнители рейса (принят)

Блоки E3 (исполнитель рейса CRUD) и E4 (переназначение ответственных логистов) приняты.
Ключевые правила:
- `driver_vehicle_blocks` не имеет `vehicle_id`.
- `crews.vehicle_id` заполняется из `vehicle_sets.primary_vehicle_unit_id`.
- Для logist пустой список — пустое состояние, не отказ доступа.
- Модалки действий должны быть в DOM всегда, включая пустой список.

## Что дальше

- Продолжать любую новую разработку только в `develop`.
- После завершения следующего блока сначала делать commit в `develop`.
- Отдельное решение о синхронизации `master` принимает только владелец.
