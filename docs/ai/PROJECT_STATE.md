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

## Активная задача

```text
SUPERADMIN — пост-дизайн функциональная приёмка: ARCHITECT_ACCEPTED с исправлением регрессии.
Исправлен сломанный POST-обработчик создания экспедитора, заменённый в коммите 3d4ee24.
Файл: docs/ai/CURRENT_TASK.md
```

## Последний принятый этап

```text
Принято erp-architect:
- Восстановлен оригинальный POST-обработчик /superadmin/companies/create
  (был ошибочно заменён на код создания руководителя в коммите 3d4ee24)
- Устранён warning Undefined variable $company
- POST handler теперь: читает поля компании, INSERT INTO companies,
  создаёт БД + storage, редиректит на /superadmin/companies
- Рендерит superadmin_companies_create.php (не owner_create)
- Оставшиеся 5 ссылок на owner_create — в легитимных маршрутах create-owner
```

## Следующий шаг

```text
1. Commit (message: "fix(superadmin): restore company create handler")
2. Переход к блоку водители / машины / экипажи
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего шага.

Не добавлять длинные отчёты.
