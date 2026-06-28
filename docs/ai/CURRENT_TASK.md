# ERP PLANEX — текущая задача

## Актуализация 2026-06-28 — E14-E15 Final Architecture Review

**Статус**: E14_E15_FINAL_ARCHITECTURE_ACCEPTED

Выполнено:
- **E14 Action Bridge Assessment**: все контроллеры используют единый паттерн делегирования action-файлам через require. Признан accepted transitional pattern — рефакторинг потребует перемещения тысяч строк кода и небезопасен на текущем этапе.
- **E15 Architecture Review**: расширен architecture_guard.php (mojibake, dynamic table whitelist, controller wiring, busines model rules). Проверены sidebar/layout (нет старых меню). Проверены dynamic table names (все через whitelist). Проверена UTF-8 валидность.
- **Runtime smoke**: все страницы для owner/senior/logist/superadmin — 200 OK.
- **3 route-файла** (company_dashboard, company_logists, superadmin_company_delete) используют inline closures — refactoring-кандидаты.

## Что дальше

Определяется владельцем.
