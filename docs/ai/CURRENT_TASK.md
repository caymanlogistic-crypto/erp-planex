# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — superadmin companies: create popup fix + double-click view/edit modal

**Статус**: SUPERADMIN_COMPANIES_MODAL_VIEW_EDIT_IMPLEMENTED

Выполнено:

### A. Fix create popup
- Удалён `style="display:none"` из модалки `#sa-company-create-modal`, который блокировал открытие (CSS `.is-open` не переопределяет inline display).
- Кнопки "Создать экспедитора" (page-head и empty-state) теперь надёжно открывают модалку.

### B. Company view/edit popup by double-click
- На `/superadmin/companies` каждая строка таблицы теперь имеет `data-company-id`.
- Двойной клик по строке открывает view-popup через ModalShell.
- Удалена колонка действий с кнопкой "Открыть".
- View-popup показывает: ИНН, статус, КПП, ОГРН, руководитель, должность, адреса, комментарий.
- Footer view-popup: "Закрыть", "Редактировать".
- Edit-popup позволяет редактировать: name, inn, kpp, ogrn, legal_address, physical_address, director_position, director_full_name, status, comments.
- Валидация: name (обязательное), inn (обязательное, уникальное).
- При успешном сохранении возвращается view-partial; при ошибках — edit-partial с ошибками.
- Full-page маршруты сохранены как fallback.

Новые файлы:
- `app/Http/Controllers/Superadmin/CompanyActions/modal_view.php`
- `app/Http/Controllers/Superadmin/CompanyActions/modal_edit_form.php`
- `app/Http/Controllers/Superadmin/CompanyActions/modal_edit_submit.php`
- `app/View/partials/superadmin_company_modal_view.php`
- `app/View/partials/superadmin_company_modal_edit.php`

Изменённые файлы:
- `app/View/pages/superadmin_companies.php` — data-company-id, удалена колонка Открыть, удалён style=display:none, добавлен ERP_BASE_PATH
- `app/Http/Controllers/Superadmin/CompanyController.php` — методы modalView, modalEditForm, modalEditSubmit
- `app/Http/Routes/superadmin.php` — маршруты modal-view, modal-edit, modal-edit POST
- `public/assets/js/app.js` — ModalShell.create для superadmin company

### C. Docs
- Текущий файл обновлён.
- DECISIONS.md и HANDOFF_FOR_NEW_CHAT.md обновлены.

Production deploy/check:
- Изменённые файлы загружены в `/home/s/spugovxsim/planexp/public_html/erp`.
- Remote `php -l` по изменённым PHP-файлам прошёл без ошибок.
- HTTP smoke: `/erp/superadmin/companies` 200; `#sa-company-create-modal` без `style="display:none"`; строки имеют `data-company-id`; кнопка "Открыть" в строках отсутствует; modal-view/modal-edit endpoints 200.
- Browser smoke: superadmin login OK; click "Создать экспедитора" открывает create popup и загружает форму; double-click по строке открывает company view popup; "Редактировать" открывает edit popup со status select.
