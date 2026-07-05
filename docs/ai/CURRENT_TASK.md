# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — expeditor edit document uploads FINAL

**Статус**: EXPEDITOR_EDIT_DOCUMENTS_ENABLED

Выполнено:

### A. Full-page edit documents enabled
- `app/View/pages/superadmin_company_edit.php`: `$leShowDocuments` изменён с `false` на `true`.
- `app/Http/Controllers/Superadmin/CompanyActions/edit_submit.php`: после успешного UPDATE добавлена обработка документов через `processLegalEntityCreateDocuments()` с проверкой `db_identifier === 'erp_company_{id}'`.
- Если `db_identifier` не совпадает — документы не обрабатываются, в `$_SESSION['company_edit_doc_warning']` записывается предупреждение.

### B. Modal edit documents enabled
- `app/Http/Controllers/Superadmin/CompanyActions/modal_edit_submit.php`: после успешного UPDATE добавлена обработка документов.
- `app/View/partials/superadmin_company_modal_view.php`: добавлен вывод `$docWarning` в модальном окне при ошибках документов.

### C. Document helper loaded explicitly
- `require_once base_path('app/Support/legal_entity_document_upload.php')` вызывается в обоих edit-обработчиках.

### D. Edit documents enabled only when company runtime DB configured
- Решение #90 заменено: edit-документы включены, но backend проверяет `db_identifier === 'erp_company_{id}'` перед обработкой.

### E. Explicit helper load in create_submit.php
- `app/Http/Controllers/Superadmin/CompanyActions/create_submit.php`: добавлен `require_once base_path('app/Support/legal_entity_document_upload.php')` перед вызовом `processLegalEntityCreateDocuments()`.

### F. Flash warning display in superadmin_company_view.php
- `app/View/pages/superadmin_company_view.php`: добавлен вывод и очистка `$_SESSION['company_edit_doc_warning']` в верхней части контента.

### G. Fixed file detection for associative predef_doc keys
- `edit_submit.php` и `modal_edit_submit.php`: исправлена проверка `$hasFiles` для ассоциативных ключей `predef_doc` (company_card и т.д.).
- Добавлена локальная функция `hasAnyUploadedFiles()` — безопасно проверяет любую структуру `$_FILES`.
