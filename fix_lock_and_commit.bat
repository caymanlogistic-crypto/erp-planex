@echo off
cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp

echo === Removing git lock file ===
del /f .git\index.lock 2>nul
echo Done (no error = lock was there; 'Could not find' = was already clean)

echo.
echo === Removing temp files ===
del /f public\tmp_check_users.php 2>nul
del /f get_logist_creds.bat 2>nul
del /f lint_package2.bat 2>nul
del /f check_package2.bat 2>nul
del /f start_server_p2.bat 2>nul

echo.
echo === Git add package-2 files ===
git add app/View/layouts/main.php
git add app/View/pages/company_vehicles.php
git add app/View/pages/company_logists.php
git add app/View/pages/company_logist_view.php
git add app/View/pages/company_drivers.php
git add app/View/pages/company_driver_vehicle_blocks.php
git add app/View/pages/company_driver_vehicle_blocks_create.php
git add app/View/pages/company_driver_vehicle_block_edit.php
git add app/View/pages/company_driver_vehicle_block_view.php
git add app/View/pages/company_driver_view.php
git add app/View/pages/company_vehicle_view.php
git add app/View/pages/company_vehicles_create.php
git add app/View/pages/company_vehicle_sets.php
git add app/View/pages/company_vehicle_sets_create.php
git add app/View/pages/company_vehicle_set_view.php
git add app/View/pages/company_crew_view.php
git add app/View/pages/company_crews_create.php
git add app/View/pages/company_crew_edit.php
git add app/View/pages/company_dashboard.php
git add app/View/pages/superadmin_company_vehicles.php
git add app/View/pages/superadmin_company_access_grants.php
git add app/View/pages/superadmin_company_documents.php
git add docs/ai/CURRENT_TASK.md
git add docs/ai/PROJECT_STATE.md
git add docs/ai/HANDOFF_FOR_NEW_CHAT.md
git add agent-main-design/DESIGN_WORK_LOG.md
git add docs/design-audit/fix-package-2/

echo.
echo === Staged files ===
git diff --cached --name-only

echo.
echo === Commit ===
git commit -m "fix(ui): close critical design audit issues package 2" -m "TASK-003: enum formatter applied (ui_set_type, ui_unit_type, ui_entity_type, ui_role, ui_document_status)" -m "TASK-007: removed ID X from all company_* table cell-sub rows" -m "TASK-004: Пользователи -> Логисты in nav, pages, dashboard" -m "TASK-009: removed all disabled Рейсы and Настройки from sidebar (all roles)" -m "TASK-013: superadmin grants page — entity_type and access_level translated" -m "TASK-018: superadmin vehicles — Транспортные единицы title, unit_type formatted" -m "TASK-019: superadmin documents — entity_type, role, status translated; docStatusBadge extended" -m "FIX: restored logist nav block accidentally removed during TASK-009 regex cleanup"

echo.
echo === Git log (last 3) ===
git log --oneline -3

echo.
pause
