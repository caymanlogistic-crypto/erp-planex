# CLIENTS_MODULE_E8_PLAN

## Статус: IN_PROGRESS

## Инвентаризация

| Файл | Строк | Тип | Назначение |
|------|-------|-----|-----------|
| `app/Http/Routes/company_clients.php` | 1372 | legacy route | 11 маршрутов с бизнес-логикой в closures |
| `app/View/pages/company_clients.php` | 152 | view | Список клиентов |
| `app/View/pages/company_clients_create.php` | 1320 | view | Форма создания |
| `app/View/pages/company_client_edit.php` | 180 | view | Форма редактирования |
| `app/View/pages/company_client_view.php` | 236 | view | Просмотр клиента |
| `app/View/components/client_contact_fields.php` | 17 | component | Обёртка над contact_fields |
| `app/View/components/contact_fields.php` | 154 | shared component | Универсальные контактные поля |
| `app/View/partials/company_client_modal_view.php` | 108 | partial | Modal view |
| `app/View/partials/company_client_modal_edit.php` | 82 | partial | Modal edit form |
| `app/Service/ClientContactService.php` | 214 | service | CRUD контактов клиента |
| `app/Support/legal_entity_document_upload.php` | 313 | shared support | Загрузка документов |
| `app/Support/http_runtime.php` | 333 | shared support | `clientFormDefaultContacts()` |
| `public/assets/js/legal-entity-modal.js` | 139 | JS | Modal logic |
| `public/assets/js/contact-fields.js` | 279 | JS | Dynamic contact fields |
| `public/assets/js/legal-entity-documents.js` | 362 | JS | Document upload |
| `public/assets/js/legal-entity-inn.js` | 171 | JS | INN auto-fill |

## Маршруты в company_clients.php

1. `GET /company/clients` — список клиентов
2. `GET /company/clients/create` — форма создания (page + modal)
3. `POST /company/clients/create` — создание клиента
4. `GET /company/clients/{id}` — просмотр клиента
5. `GET /company/clients/{id}/edit` — форма редактирования
6. `POST /company/clients/{id}/edit` — сохранение изменений
7. `POST /company/clients/{id}/archive` — архивация
8. `GET /company/clients/{id}/modal-view` — modal просмотра
9. `GET /company/clients/{id}/edit-modal` — modal редактирования (GET)
10. `POST /company/clients/{id}/edit-modal` — modal редактирования (POST)
11. `POST /company/clients/{id}/archive-modal` — modal архивации

## Целевая структура

```
app/Http/Routes/company_clients.php          (~60 строк, только route registration)
app/Http/Controllers/Company/ClientController.php  (thin controller)
app/Http/Controllers/Company/ClientActions/         (action include files)
    ├── index.php
    ├── create_form.php
    ├── create_submit.php
    ├── show.php
    ├── edit_form.php
    ├── edit_submit.php
    ├── archive.php
    ├── modal_view.php
    ├── modal_edit_form.php
    ├── modal_edit_submit.php
    └── modal_archive.php
app/Service/ClientService.php                 (shared business logic)
```

## Паттерн

Следуем установленному в проекте паттерну `RouteExecutorController`:
- Route файл — только `$router->get/post(...)` с вызовами `[$controller, 'method']`
- Controller — тонкий, делегирует в action includes
- Action includes — тело замыканий из legacy route файла
- Service — общая бизнес-логика (подключение к БД, валидация, миграции)

## Shared зависимости (НЕ трогаем)

- `ClientContactService.php` — остаётся как есть
- `legal_entity_document_upload.php` — общая, не менять
- `legal_entity_create_form.php` — общий partial, не менять
- `contact_fields.php` — общий компонент, не менять
- `clientFormDefaultContacts()` в `http_runtime.php` — не менять
- View файлы — не переписывать
- JS файлы — не менять
