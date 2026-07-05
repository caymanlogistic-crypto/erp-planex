# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — superadmin expeditor creation popup + Dadata wiring

**Статус**: SUPADMIN_EXPEDITOR_MODAL_IMPLEMENTED

Выполнено:
- Добавлен endpoint `/superadmin/requisites/lookup-by-inn` (GET+POST) для superadmin-safe DaData/INN autofill.
- Создан `CompanyActions/lookup_inn.php` для superadmin контроллера — использует `CompanyInnLookupService`, не требует company session.
- Маршрут добавлен в `app/Http/Routes/superadmin.php`.
- Метод `lookupInn()` добавлен в `CompanyController`.

- На `/superadmin/companies` кнопки "Создать экспедитора" (page-head и empty-state) открывают модальное окно вместо перехода на full-page.
- Добавлен modal overlay `#sa-company-create-modal` в DOM всегда (включая пустой список).
- Модалка загружает форму через AJAX (GET `/superadmin/companies/create` с `X-Requested-With: XMLHttpRequest`).
- При успешном создании модалка закрывается, страница перезагружается.
- При ошибках форма возвращается внутри модалки с сообщением об ошибке.
- Full-page `/superadmin/companies/create` сохранён как fallback.

- `legal_entity_create_form.php`: INN autofill message и кнопка "Заполнить по ИНН" теперь показаны для company/expeditor (ранее были скрыты).
- В форму добавлен `data-inn-lookup-url`, по умолчанию `app_url('/company/requisites/lookup-by-inn')`.
- `superadmin_companies_create.php` устанавливает `$leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn')`.

- `legal-entity-inn.js`: читает `form.dataset.innLookupUrl` перед fallback к `/company/requisites/lookup-by-inn`.
- `legal-entity-modal.js`: fallback-код также использует `form.dataset.innLookupUrl`.

- `CompanyActions/create_form.php`: для XHR-запросов возвращает только form partial (без layout); для обычных — полную страницу.
- `CompanyActions/create_submit.php`: поддерживает `is_modal=1` — возвращает `<div data-le-create-success="1"></div>` при успехе, form partial при ошибках.

Production deploy/check:
- Изменённые файлы загружены в `/home/s/spugovxsim/planexp/public_html/erp`.
- Remote `php -l` по изменённым PHP-файлам прошёл без ошибок.
- HTTP smoke: superadmin login -> `/erp/superadmin/companies` 200; modal markup присутствует; XHR `/erp/superadmin/companies/create` 200 и содержит форму `le-sa-company-create-form`, кнопку INN autofill и `data-inn-lookup-url`.
- `/erp/superadmin/requisites/lookup-by-inn` отвечает JSON 200.
- Остаточная настройка окружения: на сервере `DADATA_API_KEY` пустой, поэтому endpoint подключён, но реальные данные Дадаты не будут возвращаться до установки API-ключа.

Ограничения:
- `master` не менять, не коммитить и не синхронизировать без отдельной команды владельца.
- Не выполнять backport проекта под PHP 7.1 без отдельного решения владельца.

### Предыдущий контекст: production deploy / base path support (принят)

- Production `APP_BASE_PATH=/erp`, PHP 8.3.31, smoke QA passed.
- CRUD/file QA passed для owner (клиент, документы).
- Созданы runtime accounts: superadmin, owner, senior_logist, logist.
