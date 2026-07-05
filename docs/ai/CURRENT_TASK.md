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

## Что дальше

- Переключить web PHP для `plan-ex.ru/erp` на PHP 8.1+ / 8.3 через панель хостинга или поддержку.
- После переключения PHP повторить серверную runtime QA: роли, CRUD, документы upload/change/delete, доступы.
- Не выполнять backport проекта под PHP 7.1 без отдельного решения владельца: это широкий рискованный рефакторинг.
