# ERP PLANEX — контекст для нового ChatGPT-чата

## Проект

ERP PLANEX — PHP/MySQL ERP для транспортной логистики.

## Новая рабочая модель

```text
Владелец + ChatGPT → KILO erp-architect → KILO erp-coder → внешний ChatGPT "Главный дизайнер" → KILO erp-coder
```

Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев. Основной поток: erp-architect ставит задачу erp-coder и принимает результат. Если владелец случайно передал задачу кодеру напрямую, результат всё равно обязательно возвращается erp-architect на приёмку.

## Главное изменение

Старая схема с большим количеством агентов и MD признана неэффективной.
Новая схема минимальная.

## Активные KILO-агенты

```text
erp-architect
erp-coder
```

## Не использовать постоянно

```text
erp-uiux-designer
erp-qa-tester
```

## Главный дизайнер

Главный дизайнер — это отдельный ChatGPT-чат.
Он не находится в KILO и не должен быть описан как KILO-агент.

## Дизайн

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочая дизайн-база проекта:

```text
public/assets/css/erp-ui.css
docs/ui/DESIGN_STANDARD.md
docs/ui/DESIGN_SYSTEM_PREP_REPORT.md
```

## Правило для кодера

Кодер не придумывает UI сам.
Он работает циклом:

```text
функционал → тесты → применение erp-ui.css → повторная проверка
```

Кодер обязан думать о пользовательской структуре: понятный сценарий, действия, ошибки, пустые состояния, подтверждения опасных действий.

После утверждения UI Главным дизайнером кодер вносит только точечные функциональные изменения.

## Документация

Рабочие MD:

```text
docs/ai/PROJECT_STATE.md
docs/ai/AGENT_RULES.md
docs/ai/CURRENT_TASK.md
docs/ai/DECISIONS.md
docs/ai/HANDOFF_FOR_NEW_CHAT.md
docs/ui/DESIGN_STANDARD.md
```

Документация должна быть короткой.
