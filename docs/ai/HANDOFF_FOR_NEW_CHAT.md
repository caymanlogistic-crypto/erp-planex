# ERP PLANEX — контекст для нового ChatGPT-чата

## Что это за проект

ERP PLANEX — PHP/MySQL ERP для транспортной логистики.
Рабочая папка: `C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\`
Разработка ведётся ИИ-агентами KILO.

## Рабочая модель

```text
Владелец + ChatGPT → KILO erp-architect → KILO erp-coder
```

Главные решения принимает владелец вместе с ChatGPT (этим чатом).
erp-architect переводит решения в технические задачи для erp-coder.
erp-coder реализует, тестирует, применяет дизайн-базу.

Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев.
Основной поток: владелец/ChatGPT → erp-architect → erp-coder → erp-architect acceptance → commit.

## Агенты

Постоянные KILO-агенты (только два):

```text
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

Не используются постоянно: erp-uiux-designer, erp-qa-tester.

**Главный дизайнер** — отдельный ChatGPT-чат, НЕ KILO-агент. Подключается для дизайн-аудита и UI-полировки.

**CODEX-дизайнер** — внешний инструмент/чат, НЕ KILO-агент. Выполняет точечные дизайн-правки.
CODEX-дизайнер обязан сам проверять свои изменения: `php -l`, `git diff --check`, отсутствие inline-style (кроме `display:none`), сохранность `input name`/`form action`/`method`/`routes`, подключение CSS.
CODEX-дизайнер не меняет функциональную логику. Если найден функциональный баг — фиксирует в отчёте, исправление идёт через архитектора и кодера.

## Текущий статус проекта

```text
SUPERADMIN блок — ЗАКРЫТ на текущем этапе.
```

### Стабильный commit

```text
da1cc90 — fix(superadmin): separate company director requisites from ERP user
```

### Важные последние commits

```text
488a88b — test(superadmin): verify post-design functionality
49e7218 — fix(superadmin): restore company create handler
da1cc90 — fix(superadmin): separate company director requisites from ERP user
```

## Ключевое архитектурное решение: руководитель компании

Руководитель в карточке компании — это **реквизитные данные компании** для документов, счетов, договоров и актов.

Хранится в таблице `companies`:
- `director_position` VARCHAR(255)
- `director_full_name` VARCHAR(255)

Это **НЕ** ERP-пользователь.
Это **НЕ** `company_owner`.
Автоматическое создание `company_owner` при создании/редактировании экспедитора **запрещено**.

ERP-доступ руководителя создаётся отдельно через:
`/superadmin/companies/{id}/create-owner`

## Контакты компании

`contact_person`, `contact_phone`, `contact_email` остаются в БД (таблица `companies`), но убраны из форм создания/редактирования экспедитора и сейчас **не используются в UI**.

## Исправленная регрессия

После дизайн/функциональных правок была найдена регрессия:
`POST /superadmin/companies/create` был ошибочно заменён логикой создания руководителя.

Симптом: `Warning: Undefined variable $company` в `superadmin_company_owner_create.php`

Причина: не выполнялся `INSERT INTO companies`, использовался неопределённый `$id`, рендерился неправильный view.

Исправлено commit: `49e7218 — fix(superadmin): restore company create handler`

## Дизайн-система

Главный исторический дизайн-источник:
```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS:
```text
public/assets/css/erp-ui.css
```

Текстовый стандарт:
```text
docs/ui/DESIGN_STANDARD.md
```

Кодер обязан использовать `erp-ui.css`, не писать inline-style, не создавать второй UI-kit.

## Документация

Рабочие MD (не раздувать):

```text
docs/ai/PROJECT_STATE.md    — текущее состояние проекта
docs/ai/AGENT_RULES.md      — правила всех агентов
docs/ai/CURRENT_TASK.md     — текущая задача и статус
docs/ai/DECISIONS.md        — утверждённые архитектурные решения
docs/ai/HANDOFF_FOR_NEW_CHAT.md — этот файл, главный контекст для нового чата
AGENTS.md                   — агентская схема (в корне проекта)
docs/ui/DESIGN_STANDARD.md  — стандарт дизайн-системы
```

Новые управляющие MD создаются только по решению владельца + ChatGPT.

**Файл `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` не существует и не используется.**
Главный переносимый контекст — `docs/ai/HANDOFF_FOR_NEW_CHAT.md` (этот файл).

## Следующий блок в работе

```text
Водители / Машины / Экипажи
```

Пока не начинать кодинг нового блока.
Сначала передать задачу через erp-architect.

## Что нельзя нарушать

- Руководитель — это реквизиты компании, а не ERP-пользователь.
- `company_owner` не создаётся автоматически при создании/редактировании экспедитора.
- Контакты не добавлять в формы создания/редактирования экспедитора.
- Кодер не получает задачи напрямую, только через erp-architect.
- Inline-style запрещены (кроме `display:none` для JS).
- Новый UI-kit не создавать — использовать `erp-ui.css`.
- Не переписывать утверждённый дизайнером UI целиком.
- Не создавать новые MD без решения владельца + ChatGPT.

## Ключевые пути

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\                    — корень проекта
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\public\index.php     — роутер
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\app\View\pages\      — view-файлы
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\database\migrations\  — миграции
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp\public\assets\css\erp-ui.css — главный CSS
```
