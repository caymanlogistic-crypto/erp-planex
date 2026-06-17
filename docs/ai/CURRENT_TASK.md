# ERP PLANEX — текущая задача

## DONE: CRITICAL_UX_FIX_PACKAGE_2

STATUS: CRITICAL_UX_FIX_PACKAGE_2_DONE

ИСПРАВЛЕНО:
- TASK-003: Enum formatter — ui_set_type, ui_unit_type, ui_entity_type, ui_role, ui_document_status в view_formatters.php; применены во всех view-файлах.
- TASK-013: grants superadmin — entity_type и access_level переведены через ui_entity_type / ui_access_level.
- TASK-019: documents superadmin — entity_type переведён, роль через ui_role, статус «uploaded» добавлен в docStatusBadge.
- TASK-018: superadmin vehicles — заголовок «Транспорт компании» → «Транспортные единицы»; vehicle_type через ui_unit_type.
- TASK-007: Убраны «ID X» из cell-sub в company_drivers, company_vehicles, company_contractors, company_logists, company_driver_vehicle_blocks, company_vehicle_sets.
- TASK-004: «Пользователи» / «ДОСТУП ПОЛЬЗОВАТЕЛЕЙ» → «Логисты» / «Доступ логистов» во всех company_-страницах и сайдбаре.
- TASK-009: Убраны все disabled «Рейсы» и «Настройки» из меню для company_owner, logist, superadmin.

ИСПРАВЛЕНО В ПРОЦЕССЕ ВЕРИФИКАЦИИ:
- Logist nav-block в main.php был случайно удалён Python-скриптом вместе с disabled-элементами — восстановлен.
- docStatusBadge в superadmin_company_documents.php не содержала 'uploaded' — добавлена и расширена полная карта статусов.

ИЗМЕНЕНЫ:
- app/View/layouts/main.php
- app/View/pages/company_vehicles.php
- app/View/pages/company_contractors.php
- app/View/pages/company_logists.php
- app/View/pages/company_logist_view.php
- app/View/pages/company_drivers.php
- app/View/pages/company_driver_vehicle_blocks.php
- app/View/pages/company_driver_vehicle_blocks_create.php
- app/View/pages/company_driver_vehicle_block_edit.php
- app/View/pages/company_driver_vehicle_block_view.php
- app/View/pages/company_driver_view.php
- app/View/pages/company_vehicle_view.php
- app/View/pages/company_vehicles_create.php
- app/View/pages/company_vehicle_sets.php
- app/View/pages/company_vehicle_sets_create.php
- app/View/pages/company_vehicle_set_view.php
- app/View/pages/company_crew_view.php
- app/View/pages/company_crews_create.php
- app/View/pages/company_crew_edit.php
- app/View/pages/company_dashboard.php
- app/View/pages/superadmin_company_vehicles.php
- app/View/pages/superadmin_company_access_grants.php
- app/View/pages/superadmin_company_documents.php

ПРОВЕРКИ:
- php -l: все 23 файла — PASS
- git diff --check: PASS
- runtime browser: company_owner (dashboard, drivers, vehicles, logists, contractors), logist (menu, drivers), superadmin (grants, documents, vehicles)
- screenshots: docs/design-audit/fix-package-2/screenshots/

## DONE: CRITICAL_UX_FIX_PACKAGE_1

STATUS: CRITICAL_UX_FIX_PACKAGE_1_ACCEPTED

ИСПРАВЛЕНО:
- TASK-001: из списка `/company/drivers` убраны паспорт и СНИЛС; в колонке документов остаётся только ВУ или «ВУ: нет».
- TASK-002: `/company/documents` без параметров показывает нормальное empty-state, без backend entity_type.
- TASK-028: `/company/documents/upload` без параметров показывает инструкцию, без backend entity_type.
- TASK-016: edit экипажа использует текущий `driver_vehicle_block_id`, selected-значения не сбрасываются.
- TASK-029: тип транспортной единицы обязателен в UI и серверной валидации create/edit.
- TASK-005: на архивном экипаже кнопка «Архивировать» не показывается; отображается статус «В архиве».

ИЗМЕНЕНЫ:
- `public/index.php`
- `app/View/pages/company_drivers.php`
- `app/View/pages/company_documents.php`
- `app/View/pages/company_documents_upload.php`
- `app/View/pages/company_crew_edit.php`
- `app/View/pages/company_crew_view.php`
- `app/View/pages/company_vehicles_create.php`
- `app/View/pages/company_vehicle_edit.php`

ПРОВЕРКИ:
- `php -l` по изменённым PHP и `public/index.php`
- `git diff --check`
- UTF-8/null-byte/div-balance sanity check
- inline-style check
- runtime browser: owner, `logist_runtime_1`, `logist_runtime_2`
- screenshots: `docs/design-audit/fix-package-1/screenshots`

СЛЕДУЮЩИЙ ПАКЕТ:
- Package 2: enum translation, backend terms, grants, disabled menu, ID cleanup.

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
