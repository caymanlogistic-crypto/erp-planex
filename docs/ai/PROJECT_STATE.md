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
CRITICAL_UX_FIX_PACKAGE_2 ЗАВЕРШЁН
STATUS: CRITICAL_UX_FIX_PACKAGE_2_DONE
Следующий шаг: владелец принимает → Package 3 или иная задача
```

## Последнее принятое исправление

```text
CRITICAL_UX_FIX_PACKAGE_2 (2026-06-17):
- Enum formatter: ui_set_type, ui_unit_type, ui_entity_type, ui_role, ui_document_status — применены везде
- superadmin grants: entity_type и access_level переведены
- superadmin documents: entity_type, роль, статус «uploaded» переведены
- superadmin vehicles: заголовок «Транспортные единицы», vehicle_type переведены
- ID X убраны из всех cell-sub таблиц company_*
- «Пользователи» → «Логисты» везде во фронтенде
- Disabled «Рейсы» и «Настройки» убраны из меню для всех ролей
- Logist nav-block восстановлен (был случайно удалён в процессе)
- docStatusBadge расширена (added: uploaded, pending, approved, rejected)
- runtime проверен: company_owner, logist (новый logist_test_1), superadmin
- screenshots: docs/design-audit/fix-package-2/screenshots/
- commit: fix(ui): close critical design audit issues package 2
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
