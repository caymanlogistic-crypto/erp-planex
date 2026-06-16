# ERP PLANEX — минимальный пакет новой агентской схемы v2

## Назначение

Этот архив заменяет старую перегруженную агентскую документацию на минимальную рабочую схему и добавляет дизайн-базу из результата Главного дизайнера.

## Важно

Не удалять папку проекта `erp` целиком.
Архив нужно распаковать поверх существующего проекта:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\
```

Перед распаковкой сделать backup проекта.

## Новая схема

```text
Владелец + ChatGPT → KILO erp-architect → KILO erp-coder → внешний ChatGPT "Главный дизайнер" → KILO erp-coder точечно внедряет правки
```

## Что входит

```text
AGENTS.md
docs/ai/PROJECT_STATE.md
docs/ai/AGENT_RULES.md
docs/ai/CURRENT_TASK.md
docs/ai/DECISIONS.md
docs/ai/HANDOFF_FOR_NEW_CHAT.md
docs/ui/DESIGN_STANDARD.md
docs/ui/DESIGN_SYSTEM_PREP_REPORT.md
public/assets/css/erp-ui.css
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

## Что исключено

- постоянный KILO дизайнер;
- постоянный KILO QA;
- длинные рабочие логи;
- дублирующие MD;
- разрастание документации после каждой задачи.

## Новое правило кодера

Кодер не просто программирует. Он обязан:

```text
функционал → тесты → применение erp-ui.css → повторная проверка
```

При последующих доработках утверждённого дизайнером интерфейса кодер работает только точечно.
