# ERP PLANEX — утверждённые решения

## Актуализация 2026-06-26 — Исполнители рейса / Транспорт

Статус: подготовлен пакет исправлений `ERP_ROUTE_EXECUTORS_VEHICLE_SETS_FIXED_STRUCTURE_v4_SCHEMA_REAL.zip`; перед финальной фиксацией владелец должен применить файлы, проверить runtime и затем закоммитить результат.

Что обязательно учитывать дальше:

- `/company/route-executors` должен быть доступен `company_owner`, `senior_logist`, `logist`. Для `logist` пустой список — это не «Нет доступа», а нормальное пустое состояние с действием `Создать исполнителя рейса`.
- `Исполнитель рейса` — пользовательская сущность `Подрядчик + водитель + ТС`; технически создаются/используются `driver_vehicle_blocks` + `crews`.
- Реальная локальная схема БД: таблицы сущностей находятся в `erp_company_{id}`, а не в центральной `erp_planex`.
- `driver_vehicle_blocks` НЕ имеет поля `vehicle_id`. Запрещено писать `vehicle_id` в `driver_vehicle_blocks`.
- `driver_vehicle_blocks` хранит: `driver_id`, `vehicle_set_id`, `status`, `comments`, `created_by_user_id`, `created_by_role`, `updated_by_user_id`, `updated_by_role`.
- `crews` всё ещё имеет legacy-поля `vehicle_id` и `driver_id`; при создании исполнителя рейса `crews.vehicle_id` нужно заполнять значением `vehicle_sets.primary_vehicle_unit_id`, а `crews.driver_id` — выбранным водителем.
- Для `logist` выбор contractor/driver/vehicle_set и видимость списков должны фильтроваться по `created_by_user_id` + активным grants. Активный grant: `revoked_at IS NULL` и `access_level IN ('view','edit')`.
- На `/company/vehicle-sets` модалка создания транспорта должна быть в DOM всегда, включая пустой список, иначе кнопка `Добавить новый транспорт` визуально есть, но не работает.
- Если возникает ошибка схемы БД, сначала запускать `db_schema_route_executor.php` и сверять реальные `DESCRIBE/SHOW CREATE TABLE`, не угадывать поля.

## Назначение

Короткий список актуальных решений.
Не хранить споры, черновики и длинные объяснения.

## Решения

1. Постоянная KILO-схема сокращена до двух агентов: `erp-architect` и `erp-coder`.
2. Главный дизайнер — отдельный ChatGPT-чат, не KILO-агент.
3. Постоянный QA-агент исключён; проверки встроены в обязанности архитектора и кодера.
4. `FINAL3.html` — главный визуальный источник дизайн-системы ERP.
5. Главный дизайнер подготовил базу дизайн-системы: `public/assets/css/erp-ui.css`, `docs/ui/DESIGN_STANDARD.md`, `docs/ui/DESIGN_SYSTEM_PREP_REPORT.md`.
6. Кодер обязан работать циклом: функционал → тесты → применение `erp-ui.css` → повторная проверка.
7. Кодер обязан думать о пользовательской структуре: сценарий, понятные действия, ошибки, пустые состояния, подтверждения опасных действий.
8. После утверждения UI дизайнером кодер не имеет права переписывать его целиком при функциональных доработках.
9. Документация сокращается до минимального набора рабочих MD.
10. Новые управляющие MD создаются только по решению владельца + ChatGPT.
11. Поле `position` (должность руководителя) хранится в `company_users` центральной БД, не в `companies`.
12. Роль пользователя в локальной БД (`users.role_code`) выбирается из выпадающего списка, а не захардкожена.
13. Маршруты `/company/logists` и `/superadmin/.../logists/...` НЕ переименовываются; меняются только UI-лейблы (логист → пользователь).
14. Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев. Основной поток: erp-architect → erp-coder → erp-architect (приёмка).
15. `company_owner` продолжает храниться в центральной БД (`company_users`) отдельно от локальной; локальная `users` пока содержит только `logist`.
16. Руководитель в карточке компании — это реквизитные данные компании для документов, счетов, договоров и актов. Хранится в таблице `companies` (поля `director_position`, `director_full_name`). Это НЕ ERP-пользователь. Это НЕ company_owner. Автоматическое создание company_owner при создании/редактировании экспедитора запрещено. ERP-доступ руководителя создаётся отдельно через `/superadmin/companies/{id}/create-owner`.
17. Поля `contact_person`, `contact_phone`, `contact_email` остаются в таблице `companies`, но убраны из форм создания/редактирования экспедитора и не используются в UI.
18. Архитектура блока «Водители / Машины / Экипажи»: Подрядчик + (Водитель + ТС) = Экипаж. driver_vehicle_blocks = Водитель + vehicle_set (ТС). crews = contractor_id + driver_vehicle_block_id.
19. Таблица `vehicles` переименована в `vehicle_units`. URL `/company/vehicles` сохранён. UI-лейблы: «Транспортные единицы». entity_type: `vehicle_unit`.
20. `inn` в `contractors` — НЕ unique, обычный индекс `idx_inn`. `plate_number` в `vehicle_units` — НЕ unique, обычный индекс `idx_plate`.
21. Множественные контакты подрядчика вынесены в `contractor_contacts`. История налогообложения — в `contractor_tax_history`.
22. Телефоны водителей вынесены в `driver_phones`. Старый `drivers.phone` сохранён, data-миграция копирует в driver_phones.
23. `crews` перестроены: contractor_id + driver_vehicle_block_id. Автоматическая миграция при пустой таблице, блокировка при наличии старых записей.
24. Документы: soft delete через `deleted_at`. Активный документ: `deleted_at IS NULL`. `documents.status` оставлен как legacy.
25. `entity_access_grants` расширены: comment, revoked_at, idx_granted_user. Допустимые entity types: client, contractor, driver, vehicle_unit, vehicle_set, driver_vehicle_block, crew.
26. Миграции локальной БД (011-023) идемпотентны: проверяют INFORMATION_SCHEMA перед каждым изменением. Функция `applyLocalMigrations()` применяет их автоматически при доступе к локальной БД.
27. `applyLocalMigrations()` должен быть расширен до актуального диапазона локальных миграций. После client standard commit актуальный диапазон минимум 001-033. Миграции 001-010 (CREATE TABLE) используют `IF NOT EXISTS` или PREPARE/EXECUTE с проверкой INFORMATION_SCHEMA. Миграция 008 (ALTER TABLE) переписана на идемпотентный PREPARE/EXECUTE-паттерн.
28. PDO-подключения используют `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true` для предотвращения ошибки "Cannot execute queries while other unbuffered queries are active" при последовательных запросах после миграций с SELECT.
29. Создание локальной БД (`CREATE DATABASE IF NOT EXISTS`) выполняется автоматически перед первым подключением к `erp_company_{id}` во всех обработчиках `/company/logists/*` и SUPERADMIN-создании пользователя.
30. CRITICAL UI LOCK: архитектору, кодеру и любому агенту ERP PLANEX запрещено менять основную шапку ERP, шапку контентного блока (page-head/page-header) и основное меню без прямого подтверждения владельца. Разрешено только добавлять новые кнопки, пункты меню, действия без изменения существующей структуры.
31. Документы могут загружаться inline при создании сущностей (водитель, транспорт, перевозчик, клиент). Предопределённые типы документов сеются миграциями с category='predefined'. Пользовательские типы создаются через /company/document-types или inline через форму создания.
32. Справочник типов документов (document_types) — локальная таблица компании. UNIQUE constraint на (name, entity_type). Связь с документами через documents.document_type_id INT UNSIGNED.
33. Предопределённые документы водителя (Паспорт, ВУ, СНИЛС) поддерживают multiple upload при создании водителя. Каждый файл создаёт отдельную запись в documents.
34. Из формы создания водителя убраны поля license_category и license_expire_date. Поля в БД не удалены, в edit/view пока остаются без изменений.
35. Колонки created_by_user_id и created_by_role в таблице documents добавляются inline-ALTER при первом обращении, т.к. отсутствуют в миграциях 007 и 020.
36. WEBP добавлен в whitelist допустимых расширений для документов (во всех обработчиках). MIME-валидация не добавлялась — только проверка расширения.

37. Лимиты загрузки документов: максимум 20 МБ на один файл и 80 МБ на одну отправку формы. Frontend popup обязателен, backend validation обязателен. Рекомендуемые настройки локального сервера: `upload_max_filesize=25M`, `post_max_size=100M`, `max_file_uploads=50`, `memory_limit=256M`, `max_execution_time=120`, `max_input_time=120`.
38. Клиенты приведены к стандарту юридического лица по образцу перевозчиков. Контакты клиента вынесены в `client_contacts`; legacy-поля `clients.contact_person`, `clients.contact_phone`, `clients.contact_email` удалены локальной миграцией 033. Создание клиента использует legal entity форму с DaData, контактами, банковскими реквизитами и документами.
39. Client-specific CSS хранится в `public/assets/css/app.css`. `public/assets/css/erp-ui.css` остаётся базой дизайн-системы и не должен засоряться page-specific правилами.
40. Для custom documents backend-валидация названия документа должна выполняться до создания основной сущности. Нельзя создавать клиента/перевозчика/другую сущность, если пользователь выбрал custom file, но не указал тип/название документа.
41. Отчёт агента `DONE` принимается только после реального runtime, если задача требует runtime. Проверка `302 → login` не считается runtime-проверкой формы или CRUD-сценария.
42. Форма редактирования водителя в модальном окне должна быть той же формой, что и форма создания водителя: тот же partial/pattern, те же размеры, сетка, поля, отступы и документный блок. Разница только в предзаполнении данных, action update, кнопке `Сохранить`, состояниях `Заменить`/`×` для существующих документов и возврате в просмотр после сохранения.
43. AJAX/modals с формами не должны зависеть от inline-script и глобальных `document.getElementById`. Интерактив формы должен инициализироваться scoped-функцией вида `initDriverForm(form)`, работающей внутри конкретной формы. `cloneNode(form)` для интерактивной формы запрещён.
44. Для edit-mode документов водителя существующие предопределённые документы показываются внутри того же `document-file-row`, что и при создании. В edit-form не использовать отдельный список `Открыть`; кнопка существующего документа — `Заменить`, `×` должен быть рабочим soft-delete/clear через hidden input или не показываться.
45. Driver edit modal не принят, если геометрия отличается от create-form. Текущий ориентир по скринам: create рабочая ширина ≈ 948 px, левая колонка ≈ 644 px, правая ≈ 304 px; edit был ≈ 880 / 575 / 304 px и требует исправления.
46. Browser `alert()` запрещён в формах ERP. Ошибки frontend validation показывать внутри формы через системный alert/form-alert/field-msg pattern.
47. 4 страницы объявлены DO_NOT_TOUCH_WORKING_CORE: `/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`. Их routes, views, partials, JS, CSS, сервисы, таблицы и миграции запрещено менять без отдельной явной задачи от владельца. Полный список защищённых файлов и архитектурный план безопасного рефакторинга — в `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md`.
48. Созданы foundation-сервисы `AccessControlService` и `DocumentService` как архитектурный фундамент. Они пока НЕ подключены к защищённому ядру и НЕ меняют существующую бизнес-логику. Новые модули (driver_vehicle_blocks, crews) должны использовать их с момента создания. Документация: `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md`.
49. Главный пользовательский паттерн ERP PLANEX — master-flow: многошаговый мастер, создающий цепочку Перевозчик → Водитель → Машина → Связка → Экипаж за один проход. Обычные CRUD-страницы — второстепенный инструмент для просмотра/исправления/архивирования. Архитектурный план: `docs/ai/MASTER_FLOW_ARCHITECTURE.md`.

50. **Роль senior_logist / Логист+** — техническая роль с `role_code = 'senior_logist'`, UI-лейбл «Логист+». Видит все данные компании без фильтрации. НЕ управляет пользователями. НЕ управляет привязкой перевозчиков. НЕ является company_owner. Роль активирована в routes, sidebar и visibility-фильтрах (commit `d3d3524`).

51. **Отказ от UI «Доступ логистов» / cascade sharing.** Пользовательский сценарий «Доступ логистов» в карточке перевозчика признан непонятным и удалён. Cascade sharing routes (`/company/contractors/{id}/share`, `.../unshare`) удалены. Секция UI в `company_contractor_view.php` удалена. Общая таблица `entity_access_grants` сохранена как технический механизм для других мест. Основной сценарий для руководителя теперь — «Привязка перевозчиков», а не «расшаривание».

52. **Привязка перевозчиков (contractor assignment)** — основной механизм управления доступом между логистами. Страница: `/company/contractor-assignments`, меню: «Привязка перевозчиков», доступ: только `company_owner`. При перепривязке `created_by_user_id` и `created_by_role` меняются на нового логиста (`role = 'logist'`). Переносится весь рабочий контекст: contractor → crews → driver_vehicle_blocks → drivers → vehicle_sets. Все сущности контекста переносятся без проверки shared (полный перенос). История записывается в таблицу `contractor_assignment_history`. Активные grants на contractor и cascade grants отзываются.

53. **Ограничения выбора для обычного logist.** Обычный logist видит только свои доступные данные. При создании связки (`driver_vehicle_block`) и экипажа (`crew`):
  - frontend-выпадающие списки фильтруются по `created_by_user_id = ?` + grants;
  - backend-валидация проверяет `created_by_user_id` и `entity_access_grants`;
  - чужие `driver_id` / `vehicle_set_id` / `contractor_id` / `driver_vehicle_block_id` через POST отклоняются с ошибкой.
  Маршруты master-flow (`/company/contractors/{id}/add-crew`) также защищены backend-валидацией.
  Commit: `f993342`.

54. **Упрощение модели: «Исполнитель рейса» вместо «Водитель+ТС» + «Экипаж».** Пользователь должен работать с одной сущностью «Исполнитель рейса» = Подрядчик + Водитель + ТС. Технические таблицы `driver_vehicle_blocks` и `crews` остаются как внутренний слой. Рекомендованный путь: **Вариант А (UI-facade)** — физические таблицы не меняются, UI показывает «Исполнитель рейса», старые routes сохраняются как технические.
  - Пункты меню «Водители+ТС», «Экипажи», «Привязка перевозчиков» убираются из основного пользовательского меню.
  - Добавляется пункт «Исполнители рейса» и (для company_owner) «Переназначение логистов».
  - «Привязка перевозчиков» упраздняется; переназначение ответственного логиста делается через вкладки: Исполнители рейса / Подрядчики / Водители / ТС.
  - Переназначение contractor переносит весь контекст каскадно (как сейчас). Переназначение driver/vehicle_set/crew — точечное, с опциональным переносом crews.
  - Архитектурный план: `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`.
  - Этап E1: архитектура (MD). E2–E7: реализация.


55. **Исполнитель рейса: реальная схема создания после проверки БД.** Таблица `driver_vehicle_blocks` не содержит `vehicle_id`; она связывает только `driver_id` и `vehicle_set_id`. Таблица `crews` сохраняет legacy-поля `vehicle_id` и `driver_id`; при создании исполнителя рейса `crews.vehicle_id` заполняется из `vehicle_sets.primary_vehicle_unit_id`, `crews.driver_id` — выбранным водителем, `crews.driver_vehicle_block_id` — id связки. Запрещено добавлять/писать `vehicle_id` в `driver_vehicle_blocks` без миграции и отдельного решения владельца.

56. **Пустой список не равен отсутствию доступа.** Для `logist` на `/company/route-executors` пустой набор данных должен отображаться как empty-state с понятным действием, а не как сообщение «Нет доступа».

57. **Модалки действий должны существовать в DOM во всех состояниях списка.** Для `/company/vehicle-sets` модалка создания транспорта должна подключаться независимо от того, есть ли строки в таблице. Нельзя размещать modal только внутри ветки непустого списка, если кнопка доступна и в empty-state.

58. **Legal entity modal stack закреплён как рабочий pattern.** Для client/contractor допускается расширение через `ModalShell` + `LegalEntityCreateModal` при условии сохранения существующих full-page CRUD и без изменения базовой ERP table/shell-структуры страниц списка.

59. **Contact fields и INN lookup должны жить в общих переиспользуемых helper-компонентах.** Для client/contractor принят единый partial `contact_fields.php` и единый frontend helper для INN lookup/fallback. Новые формы не должны дублировать эту логику отдельными реализациями.

60. **Document upload для legal entities принят только как отдельный runtime-блок.** Для client/contractor document helper `app/Support/legal_entity_document_upload.php` считается принятым только при отдельной проверке create/edit/remove/save/reopen. Для driver/vehicle-set document runtime остаётся обязательным при любых изменениях document UI/handler.

61. **Обязательный runtime-браузер — правило приёмки, а не опция.** Если задача затрагивает формы, CRUD, модалки, upload, lookup или frontend state, отчёт `DONE`/`PASS` невозможен без реального browser runtime с проверкой console/network. Простая проверка `302 -> login` или только backend lint не считается приёмкой.

62. **Superadmin modal-create отложен до owner decision.** Текущий provisioning flow superadmin/company/owner нельзя переводить в modal-only режим без отдельного решения владельца. До этого разрешены только безопасные E2E-проверки существующего full-page flow.
