# ERP PLANEX — CONTRACTORS_MODULE_E9_REPORT

## До/После

| Параметр | До | После |
|----------|----|-------|
| `company_contractors.php` | 3212 строк, монолитные closures | 51 строка, только регистрация маршрутов |
| Controller | — | `ContractorController.php` (135 строк) |
| Service | — | `ContractorService.php` (256 строк) |
| Action files | — | 22 файла в `ContractorActions/` |
| Архитектура | Monolithic route closures | Route → Controller → Service → View |

## Routes (полный список)

1. `GET /company/contractors` → `index`
2. `GET /company/contractors/create` → `createForm`
3. `POST /company/contractors/create` → `createSubmit`
4. `GET /company/requisites/lookup-by-inn` → `lookupInn`
5. `POST /company/requisites/lookup-by-inn` → `lookupInn`
6. `GET /company/contractors/create-full` → `createFullForm`
7. `POST /company/contractors/create-full` → `createFullSubmit`
8. `GET /company/contractors/{id}/add-crew` → `addCrewForm`
9. `POST /company/contractors/{id}/add-crew` → `addCrewSubmit`
10. `GET /company/contractors/{id}` → `show`
11. `GET /company/contractors/{id}/edit` → `editForm`
12. `POST /company/contractors/{id}/edit` → `editSubmit`
13. `POST /company/contractors/{id}/archive` → `archive`
14. `GET /company/contractors/{id}/modal-view` → `modalView`
15. `GET /company/contractors/{id}/modal-edit` → `modalEditForm`
16. `POST /company/contractors/{id}/modal-edit` → `modalEditSubmit`
17. `POST /company/contractors/{id}/modal-archive` → `modalArchive`
18. `POST /company/contractors/{contractor_id}/contacts/create` → `contactCreate`
19. `POST /company/contractors/{contractor_id}/contacts/{contact_id}/edit` → `contactEdit`
20. `POST /company/contractors/{contractor_id}/contacts/{contact_id}/delete` → `contactDelete`
21. `POST /company/contractors/{contractor_id}/contacts/{contact_id}/set-primary` → `contactSetPrimary`
22. `POST /company/contractors/{contractor_id}/contacts/{contact_id}/set-document-email` → `contactSetDocumentEmail`
23. `POST /company/contractors/{contractor_id}/tax-history/create` → `taxHistoryCreate`

## Новые файлы

- `app/Http/Controllers/Company/ContractorController.php`
- `app/Service/ContractorService.php`
- `app/Http/Controllers/Company/ContractorActions/index.php`
- `app/Http/Controllers/Company/ContractorActions/create_form.php`
- `app/Http/Controllers/Company/ContractorActions/create_submit.php`
- `app/Http/Controllers/Company/ContractorActions/lookup_inn.php`
- `app/Http/Controllers/Company/ContractorActions/create_full_form.php`
- `app/Http/Controllers/Company/ContractorActions/create_full_submit.php`
- `app/Http/Controllers/Company/ContractorActions/add_crew_form.php`
- `app/Http/Controllers/Company/ContractorActions/add_crew_submit.php`
- `app/Http/Controllers/Company/ContractorActions/show.php`
- `app/Http/Controllers/Company/ContractorActions/edit_form.php`
- `app/Http/Controllers/Company/ContractorActions/edit_submit.php`
- `app/Http/Controllers/Company/ContractorActions/archive.php`
- `app/Http/Controllers/Company/ContractorActions/modal_view.php`
- `app/Http/Controllers/Company/ContractorActions/modal_edit_form.php`
- `app/Http/Controllers/Company/ContractorActions/modal_edit_submit.php`
- `app/Http/Controllers/Company/ContractorActions/modal_archive.php`
- `app/Http/Controllers/Company/ContractorActions/contact_create.php`
- `app/Http/Controllers/Company/ContractorActions/contact_edit.php`
- `app/Http/Controllers/Company/ContractorActions/contact_delete.php`
- `app/Http/Controllers/Company/ContractorActions/contact_set_primary.php`
- `app/Http/Controllers/Company/ContractorActions/contact_set_document_email.php`
- `app/Http/Controllers/Company/ContractorActions/tax_history_create.php`
- `docs/ai/CONTRACTORS_MODULE_E9_PLAN.md`

## Изменённые файлы

- `app/Http/Routes/company_contractors.php` (3212 → 51 строка)

## Проверки

| Проверка | Статус |
|----------|--------|
| `php -l` все новые файлы | PASS (0 ошибок) |
| `php -l` роут-файл | PASS |
| `architecture_guard.php` | PASS (0 errors, 0 warnings) |
| `git diff --check` | PASS (только LF→CRLF warning) |

## Сохранённый функционал

- **Список подрядчиков**: полный, с контактами, фильтрацией по ролям
- **Create (full page + modal)**: валидация, legal entity fields, contacts, document upload
- **Edit (full page + modal)**: валидация, contacts replace, status update
- **View**: contacts, crew blocks, tax history, grants
- **Archive**: full page + modal (JSON), access checks, crew validation
- **INN/autofill**: через `CompanyInnLookupService`
- **Contractor contacts**: полный CRUD (create, edit, delete, set-primary, set-document-email)
- **Tax history**: создание записей
- **Create-full + Add-crew**: комплексные формы создания подрядчика+водителя+ТС
- **Ролевой доступ**: company_owner, senior_logist, logist с grants
- **Контакты**: через `ContractorContactService`
- **Документы**: predef + custom document upload при создании

## Остаточные риски

- Runtime требует сессии аутентификации; полный E2E smoke возможен только через браузер
- `ContractorService::checkLogistCanEdit` — новая вспомогательная функция (заменяет inline grant check)
- `ContractorService::validateContractor` объединяет валидацию для create и edit (ранее была частично дублирована)
