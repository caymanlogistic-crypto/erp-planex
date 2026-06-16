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
Водители / Машины / Экипажи — ЭТАП 2: CRUD + функциональный UX
Следующий шаг: runtime owner review → исправления → дизайн-полировка
```

## Последний принятый этап

```text
Принято erp-architect (CRUD_UX_ACCEPTED):
- Созданы CRUD-страницы для vehicle_sets (транспортные комплекты)
- Созданы CRUD-страницы для driver_vehicle_blocks (блоки «Водитель+ТС»)
- Экипажи переписаны под новую схему: contractor_id + driver_vehicle_block_id
- Транспортные единицы обновлены: unit_type, pts_number скрыт, entity_type=vehicle_unit
- Grants расширены: vehicle_set, driver_vehicle_block, access_level view/edit, revoke
- Документы расширены: новые entity_type, 20MB, document_type optional, soft delete (deleted_at)
- Sidebar обновлён: пункты «Комплекты» и «Водитель+ТС»
- Обновлены все view-файлы (9 изменено, 8 создано)
- Document whitelist включает все 7 entity_type

ИЗВЕСТНЫЕ ОГРАНИЧЕНИЯ (следующий подэтап):
- Contractor contacts inline CRUD не реализован
- Driver phones inline CRUD не реализован
- Contractor tax history не реализован
- Role-based access (logist vs company_owner) — базовый
- Каскадная видимость не реализована
- Списки contractors/drivers требуют расширения полей
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего блока.

Не добавлять длинные отчёты.
