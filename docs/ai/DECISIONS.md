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

63. **Двухветочная модель после нормализации.** Начиная с 2026-07-02 рабочая схема веток фиксирована: `develop` — единственная ветка разработки и тестирования, `master` — стабильная deploy/server ветка. Все новые задачи, runtime и commits выполняются в `develop`.

64. **`master` трогать только по прямой команде владельца.** Без отдельного решения владельца запрещено менять, коммитить, merge/rebase/reset или иным образом синхронизировать `master`. Рабочий порядок: сначала завершить блок и commit в `develop`, затем владелец отдельно решает, когда выровнять `master`.

65. **Модуль `Рейсы → Линейные` принят.** Commit `fd511031 feat(trips): add accepted linear routes module` зафиксирован как accepted state. Подтверждённый пользовательский scope: меню `Рейсы → Линейные`, линейные и агентские рейсы, повторяемые блоки принципалов/оплат/документов, целочисленные суммы, browser-click flow create/view/edit/documents/delete.

66. **`public/index.php` должен оставаться тонким front controller.** После принятия модуля `Рейсы → Линейные` текущий ориентир дерева — около `92` строк в `public/index.php`, а маршруты остаются вынесенными в `app/Http/Routes`. Возврат к монолитной регистрации routes в index считается регрессией архитектуры.

67. **Codex Desktop поддерживает документацию и работает через OpenCode как supervisor.** После каждой значимой модификации проекта документация должна быть актуализирована точечно в нужных управляющих MD. Новые важные правила, решения, workflow и ограничения нельзя оставлять только в чате. Codex Desktop не пишет код сам, если задачу можно безопасно поручить OpenCode/DeepSeek; Codex отвечает за постановку задачи, проверку diff/checks, correction loop, документацию и финальную приёмку.

68. **Подтверждённые уроки runtime-тестирования связки Codex Desktop + OpenCode/DeepSeek.** OpenCode эффективен для аудита, точечных правок, runtime-проверок, DB fixtures и чистки артефактов. Codex не должен слепо принимать отчёты OpenCode — отчёты сначала показываются владельцу. Промты OpenCode должны содержать точный scope, запреты, правила команд, матрицу приёмки, требования чистки и формат отчёта. OpenCode запрещено использовать PowerShell, curl, Unix-only утилиты, npm install/package-lock/node_modules, foreground php -S и широкие правки без разрешения. При нарушении правил или фиктивном runtime — correction loop до приёмки. Codex проверяет: git diff, git status, php -l, architecture_guard, git diff --check, mojibake, артефакты, runtime-доказательства.

69. **Unified legal entity create partial.** Единый partial `app/View/partials/legal_entity_create_form.php` используется для client, contractor и superadmin company/expeditor create. Full-page routes сохранены. Client/contractor modal mode сохраняет `is_modal=1` по умолчанию через `$leIsModal`. Company/expeditor mode отключает секции contacts/bank/documents через флаги `$leShowContacts`, `$leShowBankDetails`, `$leShowDocuments`.

70. **Compact legal-entity full-page edit UX aligned with Driver edit pattern.** Full-page edit forms for client and contractor restructured to use form-grid layouts (form-grid-2, form-grid-3, form-grid-4) instead of full-width vertical fields. Sections: Основные данные (name+status), Реквизиты (INN/KPP/OGRN/type), Адреса (2-column textarea grid), Контакты, Банковские реквизиты (contractor), Комментарий (compact rows=2). This matches the compact Driver edit UX pattern. No CSS additions needed — all classes already exist in the design system.

71. **Production `/erp` deployment requires PHP 8.x at web runtime.** Deployment target is `/home/s/spugovxsim/planexp/public_html/erp` on the SpaceWeb account, corresponding to requested `/planexp/public_html/erp`. MySQL 5.7 and CLI PHP 8.3 are available. Initial Apache runtime was PHP 7.1.33 and was not acceptable; owner switched web runtime to Apache PHP 8.3.31 on 2026-07-05. ERP PLANEX must not be accepted in production on PHP 7.1. User-level `.htaccess` PHP handlers and CGI wrapper inside `/erp` were tested earlier and did not provide acceptable runtime.

72. **Base path support via `APP_BASE_PATH`.** Added `APP_BASE_PATH` env/config variable. The Router strips the base path prefix before matching routes. Helper functions: `app_base_path()`, `app_url()`, `redirect_to()`, `current_app_path()`, plus HTML output rewrite helpers for legacy/dynamic `href/action/src="/..."` attributes and Location header rewrite helpers for legacy `header('Location: /...')` redirects. Critical hardcoded redirects are being migrated to `redirect_to()`, but the header rewrite layer protects remaining legacy redirects when `APP_BASE_PATH` is non-empty. Simple form actions and navigation links in view files use `app_url()`; dynamic legacy URLs are covered by the HTML rewrite layer. Asset URLs in layouts use `app_url()`. Root-local deployment behaviour unchanged when `APP_BASE_PATH` is empty.

73. **Production shared-hosting DB workaround.** SpaceWeb MySQL user for production has no `CREATE DATABASE` privilege, so the deployed test company cannot use a separate `erp_company_{id}` schema. For the production smoke/runtime test company (`prod_test_expeditor`, company_id=1), `companies.db_identifier` points to the provided database `spugovxsim_plan`, and local company migrations are applied into that database. This is an infrastructure workaround for the current hosting account, not a change to the intended multi-schema architecture.

74. **Static CSS assets must use relative font URLs for `/erp`.** PHP-generated layout asset URLs can use `app_url()`, but static CSS files are served directly by Apache and do not pass through PHP base-path helpers. Font URLs inside `public/assets/css/app.css` must therefore remain relative (`../fonts/...`) so they resolve correctly both at root and under `/erp`.

75. **Superadmin expeditor creation popup + Dadata wiring (2026-07-05).** На `/superadmin/companies` кнопки "Создать экспедитора" открывают модальное окно. INN autofill (DaData) доступен в форме создания экспедитора. `data-inn-lookup-url` — кастомный атрибут формы, позволяющий задавать lookup-URL для разных контекстов (company и superadmin). Full-page `/superadmin/companies/create` сохранён как fallback. Добавлен endpoint `/superadmin/requisites/lookup-by-inn` — superadmin-safe, не требует company session.

76. **DaData runtime requires environment API key.** Кодовые endpoints Дадаты используют `CompanyInnLookupService` и переменную окружения `DADATA_API_KEY`. Если ключ пустой, UI и endpoint остаются доступными, но lookup возвращает безопасный `ok:false` и предлагает заполнить реквизиты вручную. API-ключ не хранить в документации и репозитории.

77. **DaData key configured outside repository (2026-07-05).** `DADATA_API_KEY` настроен в локальном `.env` и production `.env`. Ключ не хранится в git/docs. Production runtime check через `/erp/superadmin/requisites/lookup-by-inn` вернул `ok:true`.

78. **Superadmin companies: inline style `display:none` blocks modal `.is-open` (2026-07-05).** Модальное окно `#sa-company-create-modal` имело `style="display:none"`, которое не переопределялось CSS-классом `.is-open` (CSS меняет только opacity/pointer-events). Удалён inline style — CSS `.modal-overlay` по умолчанию имеет `display:flex; opacity:0; pointer-events:none`, а `.is-open` включает видимость через opacity.

79. **Superadmin companies double-click view/edit modal follows ModalShell pattern (2026-07-05).** Для `/superadmin/companies` реализован двойной клик по строке с открытием view/edit popup через `ModalShell.create`, по аналогии с driver/client/contractor модалками. Каждая строка имеет `data-company-id`. Колонка "Открыть" удалена. Full-page view/edit routes сохранены как fallback. Новые эндпоинты: `GET /superadmin/companies/{id}/modal-view`, `GET /superadmin/companies/{id}/modal-edit`, `POST /superadmin/companies/{id}/modal-edit`. Новый CSS/UI не добавлялся.

80. **Superadmin expeditor create: safe DB error recovery (2026-07-05).** При неудаче `CREATE DATABASE` в `create_submit.php` запись компании удаляется (`DELETE FROM companies`), а не оставляется в статусе `error` и не переводится в shared-режим. Пользователь получает понятное сообщение о необходимости прав на CREATE DATABASE. Многосхемная архитектура сохранена.

81. **Superadmin expeditor create modal aligned with Driver create modal (2026-07-05).** Модалка `/superadmin/companies` использует `driver-create-modal` класс, footer с `modal-foot is-spaced`, `modal-required-note`, `modal-foot-actions`. Добавлен флаг `$leShowInlineActions` в `legal_entity_create_form.php` — inline submit button скрывается для modal mode, submit через footer с `form="le-sa-company-create-form"`. Для full-page режима поведение не изменено. CSS: `.driver-layout--no-docs` для одноколоночной раскладки при отсутствии документов.

82. **Bank requisites returned to expeditor legal entity form (2026-07-05).** Банковские реквизиты (расчётный счёт, БИК, банк, корр. счёт) возвращены в форму создания экспедитора (как modal, так и full-page). Условие в `legal_entity_create_form.php` изменено с `$leShowBankDetails && !$isCompany` на `$leShowBankDetails`. Флаг `$leShowBankDetails` установлен в `true` во всех superadmin create/edit контекстах. Банковские поля сохранены в центральной таблице `companies`. Для центральной таблицы `companies` добавлены nullable колонки `bank_account`, `bank_name`, `bank_bik`, `bank_corr_account` через idempotent guard (`app/Helpers/company_bank_guard.php`), безопасный для MySQL 5.7.

83. **Central companies bank columns guarded (2026-07-05).** Колонки банковских реквизитов центральной таблицы `companies` добавляются идемпотентно через `SHOW COLUMNS` + `ALTER TABLE ADD COLUMN`, а не через `ADD COLUMN IF NOT EXISTS` (недоступно в MySQL 5.7). Guard-функция `ensureCompanyBankColumns()` вызывается перед каждым INSERT/UPDATE, который затрагивает банковские поля. Это защищает от ошибок схемы при развёртывании на старых версиях MySQL.

84. **Row action button restored as «Полная информация» (2026-07-05).** В таблицу `/superadmin/companies` возвращена колонка действий с кнопкой `Полная информация`, ссылающейся на full-page view `/superadmin/companies/{id}`. Двойной клик по строке сохранён для открытия popup view/edit. Кнопка не триггерит двойной клик, так как является нормальной ссылкой.

85. **Documents column restored in create expeditor (2026-07-05).** Модальное окно и full-page страница создания экспедитора на `/superadmin/companies` теперь показывают правую колонку загрузки документов, аналогично форме создания водителя. Для компании используются предопределённые типы документов: Карточка предприятия, Свидетельство ИНН, Свидетельство ОГРН, Договор. Работает через `legal_entity_create_form.php` и `processLegalEntityCreateDocuments()`.

86. **Edit expeditor unified with create form (2026-07-05).** Модальное и full-page редактирование экспедитора переведены на единый partial `legal_entity_create_form.php`. Поле статуса добавлено через флаг `$leShowStatus`. Для modal edit документы видны (как при создании), для full-page edit документы скрыты.

87. **Delete DB identifier check relaxed (2026-07-05).** Проверка `db_identifier === 'erp_company_{id}'` в `superadmin_company_delete.php` заменена на детекцию режима: отдельная БД (`erp_company_*`) → DROP DATABASE; shared-режим (центральная БД) → пропуск. Компания удаляется в обоих режимах.

88. **Shared DB fallback запрещён — Option B (safe block) (2026-07-05).** Решение #88 отменено. Fake shared DB fallback (запись в центральную БД с `db_identifier=central_db`) удалён как опасный — он может смешивать данные разных компаний в одних unprefixed таблицах. При неудаче `CREATE DATABASE` компания удаляется, пользователь получает сообщение о необходимости права CREATE DATABASE. Prefix mode (Option A) отложен до реализации безопасного table-prefix resolver/migration layer.

89. **Флаг `$leShowStatus` в `legal_entity_create_form.php` (2026-07-05).** В единую форму юридического лица добавлен флаг `$leShowStatus`. Включает выпадающий список статусов (active/inactive/blocked/archived/provisioning/error). Используется только в edit-контексте компании, не влияет на create-формы клиентов и перевозчиков.

90. **Edit expeditor documents enabled (2026-07-05).** Полная обработка документов при редактировании экспедитора включена как для full-page (`superadmin_company_edit.php`), так и для modal edit (`superadmin_company_modal_edit.php`). Backend-обработчики `edit_submit.php` и `modal_edit_submit.php` вызывают `processLegalEntityCreateDocuments()` после успешного UPDATE центральной компании. Обработка выполняется только при совпадении `db_identifier === 'erp_company_{id}'` (отдельная рабочая БД компании). При несовпадении или ошибке подключения к локальной БД — документы не обрабатываются, выводится предупреждение. `$leShowDocuments=true` во всех edit-контекстах. Ранее: документы в full-page edit были скрыты из-за отсутствия backend-обработки.

91. **DB pool support for superadmin expeditor creation (2026-07-05).** Добавлена поддержка пула предсозданных баз данных для новых экспедиторов. Если `CREATE DATABASE` не работает (нет прав), система использует первый свободный entry из `COMPANY_DB_POOL_JSON`. Per-company credentials (`db_host`, `db_port`, `db_username`, `db_password`) хранятся в центральной таблице `companies`. Хелпер `companyDatabaseConfig()` строит конфиг подключения с учётом этих полей (fallback на глобальный `config/database.php`). Для pool DB: `DROP DATABASE` при удалении компании пропускается. Новая миграция: `010_add_db_pool_columns_to_companies.sql`.

92. **DB pool one-way consumption usage journal (2026-07-05).** Pool DBs are consumed one-way — a previously used pool DB must never be reused, even if the company is deleted. Central table `company_db_pool_usage` tracks all assigned pool DBs. `findFreePoolDb()` checks both `companies.db_identifier` and `company_db_pool_usage.db_identifier`. New helper `markPoolDbUsed()` records assignment idempotently. If journal insert fails after pool assignment, the company row is rolled back. When pool runs out, owner must create new DBs and append to `COMPANY_DB_POOL_JSON`.
