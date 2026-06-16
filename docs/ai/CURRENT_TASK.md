# ERP PLANEX — текущая задача

TASK: SUPERADMIN — разделение реквизитов руководителя и ERP-пользователя

STATUS: ARCHITECT_ACCEPTED

ARCHITECTURE DECISION (2026-06-16):
- Руководитель в карточке компании — это реквизитные данные компании для документов.
- Хранится в таблице companies: director_position, director_full_name.
- ERP-доступ руководителя создаётся отдельно через /superadmin/companies/{id}/create-owner.
- Автоматическое создание company_owner при создании/редактировании экспедитора запрещено.
- Решение зафиксировано в docs/ai/DECISIONS.md (#16).

WHAT WAS CHANGED:
- database/migrations/008_add_director_requisites_to_companies.sql (NEW)
- public/index.php: CREATE POST handler (director fields in INSERT), EDIT POST handler (director fields in UPDATE, removed owner creation), migration auto-applier
- app/View/pages/superadmin_companies_create.php: removed contacts section, simplified director to position+full_name+hint, removed dead success block
- app/View/pages/superadmin_company_edit.php: removed contacts section, simplified director to position+full_name+hint from companies
- app/View/pages/superadmin_company_view.php: "Руководитель" panel shows requisites from companies + ERP access status
- docs/ai/DECISIONS.md: added decision #16

DB RESULT:
- companies table: +director_position VARCHAR(255), +director_full_name VARCHAR(255)
- company_users table: unchanged (used for future ERP-user creation)

CHECKS:
- PHP Syntax: 4 files — OK (0 errors)
- flash_owner_created / flash_owner_password: removed
- director_login / director_phone / director_email: removed from index.php handlers
- create-owner route: intact, unchanged

NEXT:
1. Commit
2. Переход к блоку водители / машины / экипажи
