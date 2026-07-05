# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — production deploy attempt

**Статус**: PRODUCTION_DEPLOY_BLOCKED_BY_APACHE_PHP_7_1

Выполнено:
- `develop` и `master` синхронизированы на commit `9d1fcc2 feat(ui): compact legal-entity full-page edit UX and route executor fixes`.
- Код развернут на сервер в разрешённую папку `/home/s/spugovxsim/planexp/public_html/erp` (соответствует рабочей папке `/planexp/public_html/erp` внутри аккаунта).
- Для production создан `.env` с DB-настройками, `.htaccess` routing в `public/`, `public/.htaccess`.
- MySQL 5.7 подключение проверено; центральные миграции применены.
- Созданы центральные таблицы: `companies`, `company_features`, `company_users`, `deleted_entities`, `features`, `superadmin_users`.
- Проверено: CLI `/usr/bin/php8.3` и PDO MySQL работают.

Блокер:
- Web Apache для домена `plan-ex.ru` исполняет PHP `7.1.33` через `apache2handler`.
- ERP-код требует PHP 8.x (`str_starts_with`, `match`, typed/modern syntax и другие PHP 8+ конструкции).
- User-level `.htaccess` handlers для PHP 8.x и CGI wrapper внутри `/erp` не дали рабочий production runtime.
- До переключения домена/папки `/erp` на PHP 8.x нельзя выполнить честную 100% runtime QA, создать production accounts и проверить upload/change/delete документов.

Что уже было принято до deploy-попытки:
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

## Актуализация 2026-07-05 — base path support

**Статус**: PRODUCTION_RUNTIME_QA_SMOKE_PASSED

Реализована поддержка развёртывания под поддиректорией (`/erp`):

- `APP_BASE_PATH` — переменная окружения (пусто = корень, `/erp` = production).
- Новые хелперы в `app/Support/helpers.php`:
  - `app_base_path(): string`
  - `app_url(string $path = ''): string`
  - `redirect_to(string $path, int $status = 302): never`
  - `current_app_path(): string`
- Router (`app/Http/Router.php`) — dispatch удаляет base path из URI перед сопоставлением маршрутов.
- `public/index.php` включает HTML output rewrite для `href/action/src="/..."`, чтобы старые динамические ссылки с PHP-вставками тоже работали под `/erp`.
- `public/index.php` включает Location header rewrite, чтобы legacy `header('Location: /...')` автоматически возвращал `/erp/...` в production.
- Критические редиректы заменены на `redirect_to()`:
  - AuthController (login/logout/redirects)
  - requireRole()
  - core.php (корневой редирект)
  - legacy_redirects.php
  - company_dashboard.php
  - Superadmin action files
- Простые формы и ссылки во view-файлах используют `app_url()`; оставшиеся динамические absolute URLs покрываются HTML output rewrite, legacy redirects покрываются Location header rewrite.
- Asset-ссылки (CSS, JS, fonts) в layouts используют `app_url()`.
- `.env.example` обновлён: добавлен `APP_BASE_PATH=`.

Что остаётся:
- Web PHP для `plan-ex.ru/erp` уже переключён владельцем на PHP 8.3.31 (`apache2handler`, `pdo_mysql=true`).
- Base path fix задеплоен; production `.env` содержит `APP_BASE_PATH=/erp`.
- Создана тестовая активная компания-экспедитор `prod_test_expeditor`, `company_id=1`.
- Из-за ограничения shared-hosting MySQL (`CREATE DATABASE` запрещён) `companies.db_identifier` для тестовой компании указывает на выданную БД `spugovxsim_plan`; локальные таблицы компании применены в этой БД.
- Созданы runtime accounts: superadmin, owner, senior_logist, logist. Пароли не хранить в документации; см. финальный отчёт владельцу.
- HTTP smoke QA passed: 4 роли логинятся, ключевые страницы superadmin/company возвращают 200, UTF-8 валиден.
- CRUD/file QA passed: owner создал клиента, загрузил TXT-документ, заменил файл, удалил документ; redirects возвращают `/erp/...`.
- Fixed static CSS font URLs: `public/assets/css/app.css` now uses relative `../fonts/...` paths so fonts load under `/erp/assets/css/app.css`.
- Не выполнять backport проекта под PHP 7.1 без отдельного решения владельца: это широкий рискованный рефакторинг.
