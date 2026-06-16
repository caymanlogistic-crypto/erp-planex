# ERP PLANEX — текущая задача

TASK: SUPERADMIN + управление пользователями экспедитора — полное закрытие блока

STATUS: ARCHITECT_ACCEPTED (принято после REWORK, ожидает commit)

REWORK FIXES (2026-06-16):
- FIX 1 [CRITICAL]: Убран захардкоженный AND role_code = 'logist' из reset-password запроса
- FIX 2 [IMPORTANT]: Убран захардкоженный AND role_code = 'logist' из superadmin edit user запроса
- FIX 3 [MINOR]: Убран фильтр role_code = 'logist' из подсчёта пользователей (3 места в delete preview)
- FIX 4 [MISSING]: Добавлена проверка self-deactivation в POST /superadmin/companies/{id}/owner/edit
- FIX 5 [MISSING]: Добавлена проверка последнего активного Руководителя в том же обработчике
- FIX 6 [MINOR]: Добавлено авто-применение миграции 010_add_position_to_users.sql в /company/logists GET
- BONUS: Восстановлен пропавший try { в обработчике POST /superadmin/companies/create

IMPLEMENTED:
- DB: миграция 007 (position в company_users), обновлена local 001, добавлена 010
- SUPERADMIN: форма создания компании + поля руководителя, автосоздание company_owner (FR3-FR5)
- SUPERADMIN: форма редактирования компании + поля руководителя (FR6-FR7)
- SUPERADMIN: замена "логист" → "пользователь" в labels/заголовках, role dropdown (FR8-FR15)
- SUPERADMIN: создание/редактирование пользователя с выбором роли (FR9-FR12)
- COMPANY OWNER: замена "Логисты" → "Пользователи" в sidebar/views (FR16-FR17)
- COMPANY OWNER: создание/редактирование пользователя с выбором роли (FR18-FR21)
- COMPANY OWNER: отображение фактической роли вместо хардкода (FR22-FR23)
- Маршруты НЕ переименованы (FR24)
- Пароль показывается только один раз (FR25)
- Добавлена функция applyCentralMigrations() для авто-применения миграций
- Убраны ограничения role_code='logist' из CRUD-запросов для поддержки будущих ролей
- Добавлено поле position в create-owner, edit-owner, company edit

PENDING:
- Runtime-проверка всех маршрутов (см. список в CHECKLIST ниже)
- Применение миграции 007 к центральной БД (через SUPERADMIN dashboard или вручную)
- Применение миграции 010 к локальным БД существующих компаний

CHECKLIST:
[ ] /login
[ ] /superadmin/dashboard
[ ] /superadmin/companies
[ ] /superadmin/companies/create (GET + POST с руководителем и без)
[ ] /superadmin/companies/{id}
[ ] /superadmin/companies/{id}/edit (GET + POST с полями руководителя)
[ ] /superadmin/companies/{id}/users
[ ] /superadmin/companies/{id}/users/logists/create (GET + POST с role dropdown)
[ ] /superadmin/companies/{id}/create-owner (GET + POST с position)
[ ] /superadmin/companies/{id}/owner
[ ] /superadmin/companies/{id}/owner/edit (GET + POST с position)
[ ] /company/dashboard
[ ] /company/logists
[ ] /company/logists/create (GET + POST с role dropdown)
[ ] /company/logists/{id}/edit (GET + POST с role dropdown)
[ ] Сброс пароля (SUPERADMIN + company_owner)
[ ] Вход под SUPERADMIN, company_owner, logist
[ ] Проверка запрета доступа к чужой компании

NEXT:
1. Запустить локальный сервер
2. Пройти checklist
3. Применить миграции вручную если авто-применение не сработало
4. Commit после успешной проверки
