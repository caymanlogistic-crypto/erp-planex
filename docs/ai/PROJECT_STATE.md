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
Водители / Машины / Экипажи — ЭТАП 1: фундамент БД
Следующий шаг: функциональные CRUD-страницы и UX-сценарии
```

## Последний принятый этап

```text
Принято erp-architect (DATABASE_FOUNDATION_ACCEPTED):
- Созданы миграции 011-023 для локальной БД компании (database/migrations-local/)
- Обновлена структура таблиц: contractors (тип, банк, индексы), contractor_contacts, contractor_tax_history
- Обновлена структура drivers: паспорт, СНИЛС, driver_phones
- vehicles → vehicle_units: переименование таблицы, новые поля (unit_type, диагностические карты), индекс idx_plate
- Созданы таблицы vehicle_sets, driver_vehicle_blocks
- Обновлена логика crews: contractor_id + driver_vehicle_block_id, безопасная миграция при пустой таблице
- Обновлены documents: document_* поля, soft delete (deleted_at), документная статистика через deleted_at IS NULL
- Расширены entity_access_grants: comment, revoked_at, idx_granted_user
- Обновлены все ссылки vehicles→vehicle_units в PHP-коде (SQL, entity_type, статистика)
- SUPERADMIN-статистика обновлена: vehicle_units, vehicle_sets, driver_vehicle_blocks
- Добавлена функция applyLocalMigrations() — авто-применение миграций при доступе к локальной БД
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего блока.

Не добавлять длинные отчёты.
