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

## IN PROGRESS: Водители / Машины / Экипажи — Этап 1: фундамент БД

STATUS: DATABASE_FOUNDATION_ACCEPTED

ARCHITECTURE:
- Подрядчик + (Водитель + ТС) = Экипаж
- driver_vehicle_blocks = Водитель + ТС
- crews = contractor_id + driver_vehicle_block_id

ВЫПОЛНЕНО (Этап 1):
- Миграции 011-023 (database/migrations-local/)
- applyLocalMigrations() в index.php
- vehicles → vehicle_units (переименование таблицы + обновление всех ссылок в коде)
- SUPERADMIN-статистика обновлена
- documents: soft delete через deleted_at
- entity_access_grants расширены

СЛЕДУЮЩИЙ ШАГ (Этап 2):
- Функциональные CRUD-страницы для contractors, drivers, vehicle_units, crews
- Формы, дизайн, UX-сценарии
