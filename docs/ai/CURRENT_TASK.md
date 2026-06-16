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

ЧАСТИЧНО ПРИНЯТ (commit 65eaf8e):
- vehicle_sets CRUD, driver_vehicle_blocks CRUD
- crews по новой схеме contractor + driver_vehicle_block
- vehicles → vehicle_units, pts_number скрыт
- grants/documents расширены, sidebar обновлён

ДОДЕЛАНО (текущая задача):
- Contractor contacts inline CRUD: 6 маршрутов (create/edit/delete/set-primary/set-document-email)
- Driver phones inline CRUD: 4 маршрута (create/edit/delete/set-main)
- Contractor tax history append-only
- Role-based access: card-level checks (view/edit/archive) для всех сущностей
- Grants: реально применяются в списках и карточках
- Каскадная видимость: crew→dvb→driver/set/units, связанные блоки/комплекты
- Documents access: logist удаляет только свои, company_owner видит удалённые
- Расширены views: contractors (+тип, КПП, контакты, банк, налоги), drivers (+паспорт, СНИЛС, телефоны, блоки)

АРХИТЕКТУРА (не изменилась):
- Подрядчик + (Водитель + ТС) = Экипаж
- driver_vehicle_blocks = Водитель + ТС (vehicle_set)
- crews = contractor_id + driver_vehicle_block_id

Всего маршрутов (включая Этап 1+2): 53

СЛЕДУЮЩИЙ ШАГ:
- Runtime owner review / исправления / дизайн-полировка

## IN PROGRESS: Исправление пользователей компании и runtime-проверка

STATUS: COMPANY_USERS_AND_RUNTIME_ACCEPTED

КОРНЕВАЯ ПРИЧИНА БЛОКЕРА:
- Локальная БД `erp_company_{id}` не создавалась при создании компании
- `applyLocalMigrations()` не включала миграции 001-010
- PDO unbuffered query в миграциях ломал все последующие запросы

ИСПРАВЛЕНО:
- CREATE DATABASE IF NOT EXISTS в 8 обработчиках (SUPERADMIN + /company/logists)
- applyLocalMigrations расширен до 001-030
- PDO::MYSQL_ATTR_USE_BUFFERED_QUERY в Database.php
- Поле пароля в SUPERADMIN-форме создания пользователя
- Детальная ошибка создания пользователя ($e->getMessage())
- Проверки прав для archive/edit экипажей
- Grant update (view → edit)
- HTML required убран с бизнес-полей
- Миграция 008 сделана идемпотентной
- Миграция 023 (SELECT 1) исправлена

RUNTIME-ПРОВЕРКА:
- Создан экспедитор: ООО "Тест Этап 2 Runtime" (ID=9, БД erp_company_9)
- Руководитель: owner_test_runtime / pass1234
- Логист через SUPERADMIN: logist_sa_test / pass5678
- Логист через руководителя: logist_runtime_1 / pass1111, logist_runtime_2 / pass2222
- Заполнены: подрядчик, водитель, тягач, полуприцеп, сцепка, блок Водитель+ТС, экипаж
- Загружены документы ко всем сущностям
- Права проверены: role-based visibility, view-grant, edit-grant, удаление документов
- /company/vehicles работает, pts_number скрыт
- Бизнес-поля не обязательны

FILES CHANGED (9): public/index.php, app/Core/Database.php, 4 view-файла, 2 миграции
