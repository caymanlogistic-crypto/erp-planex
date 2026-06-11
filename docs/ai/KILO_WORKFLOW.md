# ERP PLANEX — KILO_WORKFLOW

## Назначение

KILO + DeepSeek используется как основной исполнитель кода и технических задач ERP PLANEX.

KILO должен работать строго по MD-документации проекта.

В проекте настроены 4 проектных KILO-режима (агента):
- `erp-architect` — главный координатор (primary, default_agent);
- `erp-uiux-designer` — UI/UX-дизайнер (subagent);
- `erp-coder` — исполнитель разработки (subagent);
- `erp-qa-tester` — тестировщик (subagent).

Определения агентов находятся в `.kilo/agent/`. Главный режим — `erp-architect`.

Пользователь в основном общается с `erp-architect`. Архитектор делегирует задачи дизайнеру, кодеру и тестировщику через агентскую сеть.

---

## Перед началом любой задачи KILO обязан

1. Прочитать:
   - `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
   - `docs/ai/PROJECT_STATUS.md`;
   - `docs/ai/DECISIONS_LOG.md`;
   - `docs/ai/AGENT_WORK_LOG.md`;
   - `docs/ai/AGENT_NETWORK.md`, если задача связана с ролями, промтом или агентным циклом;
   - профильные документы из `docs/architecture`, `docs/business`, `docs/ui`.

2. Понять текущий этап проекта.

3. Не начинать писать код, если задача архитектурно неясна.

4. Если данных недостаточно — поставить статус `NEEDS_OWNER_DECISION`.

---

## Запрещено

- Придумывать бизнес-логику.
- Менять архитектурные решения без записи в `DECISIONS_LOG.md`.
- Создавать страницу без feature_code и permission_code.
- Добавлять модуль без обновления MD.
- Завершать задачу без логов.
- Игнорировать проверки.
- Смешивать SUPERADMIN и локальную ERP без явного архитектурного решения.

---

## После выполнения задачи KILO обязан

1. Обновить `AGENT_WORK_LOG.md`.
2. Обновить `PROJECT_STATUS.md`.
3. Обновить профильные MD-файлы.
4. Выполнить доступные проверки.
5. Записать результаты проверок.
6. Указать следующий шаг.
7. Проверить и при необходимости обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

---

## Агентская сеть

Роли, промты и порядок работы KILO описаны в `docs/ai/AGENT_NETWORK.md`.

Запрещено запускать KILO с размытыми задачами. Любая задача должна иметь цель, список файлов для чтения, конкретные действия, запреты, проверки, список MD для обновления, указание по commit и формат FINAL REPORT.

---

## Модель работы

ERP PLANEX развивается постранично и модульно.

Каждый новый модуль должен иметь:

```text
feature_code
permission_code
routes
controller
service
repository/model
views
menu item
logs
migrations
checks
MD documentation
```
