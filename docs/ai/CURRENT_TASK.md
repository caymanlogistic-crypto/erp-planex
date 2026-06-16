# ERP PLANEX — текущая задача

## ЗАКРЫТО: SUPERADMIN — разделение реквизитов руководителя и ERP-пользователя

STATUS: CLOSED

STABLE COMMIT: da1cc90 — fix(superadmin): separate company director requisites from ERP user

ARCHITECTURE DECISION (#16):
- Руководитель в карточке компании — это реквизитные данные компании для документов.
- Хранится в таблице companies: director_position, director_full_name.
- ERP-доступ руководителя создаётся отдельно через /superadmin/companies/{id}/create-owner.
- Автоматическое создание company_owner при создании/редактировании экспедитора запрещено.

ВАЖНЫЕ COMMITS:
- 488a88b — test(superadmin): verify post-design functionality
- 49e7218 — fix(superadmin): restore company create handler
- da1cc90 — fix(superadmin): separate company director requisites from ERP user

ИСПРАВЛЕННАЯ РЕГРЕССИЯ:
После дизайн/функциональных правок POST /superadmin/companies/create был ошибочно заменён логикой создания руководителя.
Симптом: Warning: Undefined variable $company в superadmin_company_owner_create.php
Причина: не выполнялся INSERT INTO companies, использовался неопределённый $id, рендерился неправильный view.
Исправлено: 49e7218 — fix(superadmin): restore company create handler

КОНТАКТЫ КОМПАНИИ:
contact_person, contact_phone, contact_email остаются в БД, но убраны из форм создания/редактирования экспедитора и сейчас не используются в UI.

## IN PROGRESS: Водители / Машины / Экипажи — Этап 2: CRUD + функциональный UX

STATUS: CRUD_UX_ACCEPTED

ARCHITECTURE:
- Подрядчик + (Водитель + ТС) = Экипаж
- driver_vehicle_blocks = Водитель + ТС (vehicle_set)
- crews = contractor_id + driver_vehicle_block_id

ВЫПОЛНЕНО (Этап 2):
- CRUD vehicle_sets: list, create, view, edit, archive (8 маршрутов)
- CRUD driver_vehicle_blocks: list, create, view, edit, archive (8 маршрутов)
- Crews переписаны: 7 маршрутов обновлены под driver_vehicle_block_id
- Vehicles обновлены: unit_type, pts_number скрыт, entity_type=vehicle_unit
- Добавлены grants: vehicle_set, driver_vehicle_block, access_level view/edit, revoke
- Обновлены documents: новые entity_type, 20MB, document_type optional, soft delete deleted_at
- Sidebar: vehicle-sets, driver-vehicle-blocks
- Document whitelist: все 7 entity_type
- 8 новых view-файлов, 9 изменённых

ОГРАНИЧЕНИЯ (следующий подэтап):
- Contractor contacts/driver phones inline CRUD не реализован
- Contractor tax history не реализован
- Role-based access (logist vs company_owner) требует доработки
- Каскадная видимость не реализована

СЛЕДУЮЩИЙ ШАГ:
- Runtime owner review / исправления / дизайн-полировка
