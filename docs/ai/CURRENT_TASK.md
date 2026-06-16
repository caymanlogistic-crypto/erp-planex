# ERP PLANEX — текущая задача

TASK: SUPERADMIN — пост-дизайн функциональная приёмка

STATUS: ARCHITECT_ACCEPTED (regression found and fixed)

DESIGNER CHANGES (2026-06-16):
- Главный дизайнер завершил дизайн-полировку блока SUPERADMIN
- Изменено: 20 view-файлов superadmin_*.php + main.php + erp-ui.css + app.css + index.php
- Убраны все inline-style, заменены на utility-классы erp-ui.css
- Добавлен <link> erp-ui.css в layout (был пропущен)
- Исправлен баг роутинга: create-route перенесён ПЕРЕД динамическим {user_id}
- Добавлен полноценный маршрут /superadmin/companies/{id}/create-owner
- Добавлены null-safe счётчики ($countsIncomplete, $hasColumn) в logist_view

POST-DESIGN VERIFICATION (2026-06-16):
- PHP Syntax: все 21 файл — OK (0 ошибок)
- Git diff --check: OK (только LF→CRLF warnings)
- Form Integrity: 5 критичных форм — action/method/names/submit/CSRF целы
- Runtime Routes: 0 ошибок 500; все protected → 302; /login → 200
- Design Safety: erp-ui.css подключён, layout цел, inline-style чисты

REGRESSION FOUND (2026-06-16):
- POST /superadmin/companies/create был сломан: обработчик читал поля руководителя,
  INSERT INTO company_users с неопределённым $id, рендерил owner_create template.
- Причина: в коммите 3d4ee24 обработчик был ошибочно заменён на код create-owner.
- Warning: Undefined variable $company в superadmin_company_owner_create.php:1.

REGRESSION FIXED (2026-06-16):
- Восстановлен оригинальный POST-обработчик создания экспедитора из коммита 627c100.
- Обработчик: читает поля компании, INSERT INTO companies, создаёт БД+storage,
  редиректит на /superadmin/companies.
- Рендерит superadmin_companies_create.php (не owner_create).
- PHP Syntax: public/index.php — OK.
- Git diff --check: OK.

NEXT:
1. Commit
2. Переход к блоку водители / машины / экипажи
