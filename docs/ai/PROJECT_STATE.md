# ERP PLANEX — текущее состояние проекта

## Назначение файла

Один короткий файл текущего состояния проекта.
Не хранит длинную историю.
Не заменяет Git.
Не дублирует отчёты агентов.

## Текущая агентская схема

```text
Владелец + ChatGPT → erp-architect → erp-coder → внешний ChatGPT "Главный дизайнер" → erp-coder
```

## Активные KILO-агенты

```text
erp-architect
erp-coder
```

## Исключены из постоянной цепочки

```text
erp-uiux-designer
erp-qa-tester
```

## Дизайн-база

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS для проекта:

```text
public/assets/css/erp-ui.css
```

Рабочий стандарт:

```text
docs/ui/DESIGN_STANDARD.md
```

## Текущее правило разработки

Кодер обязан:

```text
реализовать функционал → протестировать → применить erp-ui.css → повторно проверить
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
Последний стабильный commit: da1cc90
```

## Текущий блок

```text
FINAL_VISUAL_POLISH — ЗАВЕРШЁН
STATUS: FINAL_VISUAL_POLISH_DONE
Финальная визуальная полировка интерфейса выполнена.
Все 6 пакетов дизайн-аудита закрыты + финальная полировка.
Следующий шаг: владелец + ChatGPT финальная визуальная приёмка.
```

## Последнее принятое исправление

```text
DESIGN_AUDIT_PACKAGES_3_6 (2026-06-17):

Package 3 (fd927ae): logist grant states
- TASK-020: unified empty states for logist without access (6 lists)
- TASK-021: special message for logist with archived grants
- TASK-025: improved 403 Forbidden page (error_403.php + navigation)
- TASK-006+TASK-026: tech info hidden from all logists (6 view pages)

Package 4 (fea1730): cards, danger actions, forms
- TASK-010: danger zone separated from documents on all view cards
- TASK-015: SNILS moved to main data, service dates hidden from logist
- TASK-017: password masking with show/hide/copy on create user forms
- TASK-022: Block/Archive removed from SA users table
- TASK-024: passport fields added to driver edit form
- TASK-014: empty select fallback with links on create forms
- TASK-027: confirmation before owner password reset

Package 5 (d75dfe6): tables, headings, contractor card, login
- TASK-011+UX-019: logists list improved (@login format, removed role column)
- TASK-012: ID removed from superadmin companies cell-sub
- TASK-023: real FIO via ui_actor() in "Created by" columns
- UX-009: contractor card restructured (contacts above bank, bank collapsed)
- UX-014: vehicle set heading with plate numbers
- UX-016: DVB heading with driver name and plate
- UX-008: contractors table compacted (5 columns, INN in cell-sub)
- UX-001: login page shows "ERP PLANEX" brand
- UX-038: VIN removed from vehicle set card
- UX-040: quotes removed from DVB empty state

Package 6 (3aca7a1): operational dashboards
- TASK-008: company dashboard with role-based metrics (owner/logist)
- UX-002: superadmin dashboard with company stats + recent companies table
- Forbidden text removed: "Среда: local", "В разработке", "Статус БД"
```

## Последний принятый этап

```text
Принято erp-architect (COMPANY_USERS_AND_RUNTIME_ACCEPTED):

Исправление пользователей компании и полный runtime-сценарий:
- SUPERADMIN создание пользователя компании исправлено (CREATE DATABASE + поле пароля + детальная ошибка)
- /company/logists исправлен (автосоздание локальной БД во всех 7 обработчиках)
- PDO unbuffered query fix (MYSQL_ATTR_USE_BUFFERED_QUERY)
- applyLocalMigrations расширен до 001-030
- Проверки прав для archive/edit экипажей
- Grant update (view → edit без ошибки "уже выдан")
- HTML required убран с бизнес-полей (ИНН, телефон, тип документа)
- Полный runtime-сценарий на новом экспедиторе пройден:
  - Подрядчик + контакт + налоговая запись + документ
  - Водитель + телефон + документ
  - Тягач + полуприцеп + документы
  - Сцепка + документ
  - Блок Водитель+ТС + документ
  - Экипаж + документ
  - Role-based visibility: logist_runtime_2 не видит записи logist_runtime_1
  - view-grant + edit-grant проверены
  - Удаление чужих/своих документов проверено
  - /company/vehicles работает, pts_number скрыт
  - Бизнес-поля не обязательны (серверно + HTML)
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего блока.

Не добавлять длинные отчёты.
