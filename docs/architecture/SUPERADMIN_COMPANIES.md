# UPDATE — 2026-06-13 — OWNER-APPROVED DETAILS FOR FIRST IMPLEMENTATION

## Approved input fields for creating an expeditor

For the first implementation, `SUPERADMIN` enters the same baseline business fields as the current contractor/client standard, but the expeditor/company registry must remain a separate SUPERADMIN entity/table/model and must not reuse local contractor tables.

Baseline fields:
- `name` — наименование, required;
- `inn` — ИНН, required;
- `kpp` — КПП;
- `ogrn` — ОГРН;
- `legal_address` — юридический адрес;
- `physical_address` — фактический адрес;
- `contact_person` — контактное лицо;
- `contact_phone` — телефон;
- `contact_email` — email;
- `status` — статус;
- `comments` — комментарий.

Do not add extra fields without owner approval.

## Approved local DB naming rule

Local DB name is generated automatically from company ID.

`SUPERADMIN` does not manually enter DB name.

`slug/key` must not be the source of truth for local DB name generation.

## Approved storage naming rule

Storage folder is generated/named by company ID.

`slug/key` must not be the source of truth for storage folder generation.

## Superseded older rule

Any older statement that `key/slug` is used for DB names or storage paths is superseded for the first Companies Registry implementation. `key/slug` may remain only as a separate machine/display/URL code if required by the existing schema, but the first implementation must generate DB and storage from company ID.

---

# ERP PLANEX — SUPERADMIN_COMPANIES

## Назначение

Документ описывает архитектурную модель создания экспедитора из панели `SUPERADMIN`.

## Утвержденная модель

Один экспедитор в системе — это отдельная локальная ERP / компания.

При создании экспедитора система автоматически создает:
- запись в центральной БД SUPERADMIN;
- отдельную локальную БД экспедитора;
- отдельную папку для загруженных документов экспедитора.

Кодовая база остается общей:
- PHP-скрипты не копируются;
- CSS/JS не копируются;
- общие миграционные и сервисные скрипты не копируются;
- папка экспедитора используется для uploaded documents, а не для runtime-кода.

## Центральная БД

Центральная БД SUPERADMIN хранит:
- реестр экспедиторов / компаний;
- статусы provisioning;
- настройки и технические параметры компании;
- главных пользователей экспедиторов;
- привязку главного пользователя к экспедитору.

Текущая спецификация центральной БД описана в:

```text
docs/architecture/SUPERADMIN_DATABASE.md
```

**Утверждённая схема главного пользователя (DECISION-0033, 2026-06-13):**

Таблица `company_users` в центральной БД SUPERADMIN:
- `company_id` → `companies.id` (FK, ON DELETE CASCADE)
- роль `company_owner` (Руководитель)
- один активный Руководитель на компанию
- создание — отдельное действие SUPERADMIN после создания экспедитора
- полная спецификация: `docs/architecture/PERMISSIONS_MODEL.md`, DECISION-0033

## Локальная БД

Локальная БД экспедитора хранит данные конкретной компании:
- локальных пользователей;
- клиентов;
- подрядчиков;
- транспорт;
- водителей;
- связки / экипажи;
- будущие рейсы и документы локальной ERP.

Первый локальный пользовательский сценарий:

```text
Руководитель создает Логиста
```

`Руководитель` хранится в центральной БД. `Логист` хранится в локальной БД экспедитора.

## Storage

Для каждого экспедитора создается отдельная папка документов.

В storage-папке экспедитора хранятся только загруженные файлы и производные файловые материалы, если они будут нужны позже.

Не хранить в storage-папке экспедитора:
- PHP-код;
- JS/CSS;
- миграции;
- конфиги с секретами;
- системные скрипты.

## Provisioning flow

Создание экспедитора должно выполняться одной автоматической операцией из SUPERADMIN:

1. SUPERADMIN отправляет форму создания экспедитора.
2. Система валидирует входные данные.
3. Система создает запись компании в центральной БД со статусом provisioning.
4. Система создает локальную БД.
5. Система применяет локальную схему / миграции.
6. Система создает storage-папку экспедитора.
7. Система обновляет центральную запись компании до рабочего статуса.
8. При ошибке система фиксирует понятный статус ошибки и не скрывает частично созданное состояние.

Техническая реализация пункта 5 требует отдельного решения: как именно применять локальные миграции к новой БД экспедитора.

## Статусы

Для реестра компаний использовать статусы, согласованные со спецификацией центральной БД.

Статусы локальных справочников клиентов, подрядчиков, транспорта и водителей:

```text
active
blocked
archive
```

Не смешивать статусы provisioning центральной компании со статусами локальных справочников.

## Следующий технический шаг

Перед задачей кодеру нужно подготовить отдельную архитектурную спецификацию:
- какие поля SUPERADMIN вводит при создании экспедитора;
- как генерируется имя локальной БД;
- как генерируется имя storage-папки;
- как хранятся параметры подключения к локальной БД без попадания секретов в git;
- какие миграции применяются к новой локальной БД;
- как откатывается или маркируется частично неудачный provisioning.

