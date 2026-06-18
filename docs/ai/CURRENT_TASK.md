# ERP PLANEX — текущая задача

## STATUS: DRIVER_CREATE_DOCS_AND_PHONES_ACCEPTED

Задача реализована: исправление ошибки сохранения документов, мультизагрузка, доп. телефоны, WEBP, чистка формы создания водителя.

Реализованные commits:
- de190ab — fix: add created_by_user_id and created_by_role column check in driver create document handler
- b457557 — feat: multiple file upload support for predefined driver documents
- b1d665c — feat: add extra phones block to driver create form
- b105424 — feat: add WEBP support; remove license_category and license_expire_date from driver create form

## Что сделано

1. Исправлена ошибка сохранения документов: добавлен inline-ALTER для колонок created_by_user_id и created_by_role в таблице documents при создании водителя.
2. Предопределённые документы (Паспорт, ВУ, СНИЛС) поддерживают multiple upload.
3. В форму создания водителя добавлен блок дополнительных телефонов (driver_phones).
4. WEBP добавлен в whitelist и UI-подсказки.
5. Из формы создания водителя убраны поля «ВУ: категория» и «ВУ: дата окончания».
6. Улучшена диагностика ошибок: catch-блоки выводят реальное сообщение исключения.
7. Добавлена проверка возврата mkdir.

## UI LOCK: ACTIVE

CRITICAL UI LOCK RULE активен. Основная шапка, page-head и меню НЕ изменялись.

## NEXT: TBD (ожидает владельца)
