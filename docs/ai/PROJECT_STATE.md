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
Водители / Машины / Экипажи — CRITICAL UX FIX PACKAGE 1 ЗАВЕРШЁН
STATUS: CRITICAL_UX_FIX_PACKAGE_1_ACCEPTED
Следующий шаг: Package 2 из дизайн-аудита
```

## Последнее принятое исправление

```text
CRITICAL_UX_FIX_PACKAGE_1:
- список водителей больше не раскрывает паспорт и СНИЛС;
- /company/documents и /company/documents/upload без параметров показывают UX empty-state;
- edit экипажа сохраняет текущий driver_vehicle_block_id;
- тип транспортной единицы обязателен в UI и серверной валидации;
- архивный экипаж не показывает кнопку «Архивировать»;
- runtime проверен под owner, logist_runtime_1, logist_runtime_2;
- screenshots: docs/design-audit/fix-package-1/screenshots.
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
