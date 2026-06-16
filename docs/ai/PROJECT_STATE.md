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
SUPERADMIN — пост-дизайн функциональная приёмка: ARCHITECT_ACCEPTED.
Все 20 view-файлов + CSS + index.php проверены. Багов нет.
Файл: docs/ai/CURRENT_TASK.md
```

## Последний принятый этап

```text
Принято erp-architect (пост-дизайн):
- PHP-синтаксис: все 21 файл — OK
- Формы: 5 критичных форм — action/method/names/submit/CSRF целы
- Runtime-маршруты: 0 ошибок 500, все protected → 302, /login → 200
- Дизайн-безопасность: erp-ui.css подключён, layout цел, inline-style убраны
- Дизайнер попутно исправил баг роутинга (create до dynamic {user_id})
- Дизайнер добавил null-safe счётчики в logist_view
```

## Следующий шаг

```text
1. Commit (message: "style(superadmin): apply chief designer polish and verify post-design functionality")
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
