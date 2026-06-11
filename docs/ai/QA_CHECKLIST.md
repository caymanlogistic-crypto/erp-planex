# ERP PLANEX — QA_CHECKLIST

## Общий QA для агентных задач

Перед статусом DONE проверить:

- [ ] Агент прочитал `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- [ ] Агент прочитал актуальные MD-файлы.
- [ ] Если задача связана с KILO/промтами/ролями, прочитан `docs/ai/AGENT_NETWORK.md`.
- [ ] Если задача связана с UI/дизайн-кодом, прочитан `docs/ui/DESIGN_CODE_INTEGRATION.md`.
- [ ] Изменения соответствуют DECISIONS_LOG.
- [ ] Не добавлены неподтверждённые бизнес-правила.
- [ ] Обновлён AGENT_WORK_LOG.
- [ ] Обновлён PROJECT_STATUS.
- [ ] Обновлены профильные MD-файлы.
- [ ] Проверено, нужно ли обновить `docs/ai/ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.
- [ ] Если переносимый контекст изменился, `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` обновлён.
- [ ] Если `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md` не обновлялся, причина указана в FINAL REPORT.
- [ ] Если агент сомневался, причина решения записана в `AGENT_WORK_LOG.md` или файл обновлён.
- [ ] Указан следующий шаг.
- [ ] Выполнены доступные runtime/синтаксические проверки.
- [ ] Непроверенные части явно зафиксированы.
- [ ] Блокеры записаны как NEEDS_OWNER_DECISION или BLOCKED.

## Windows PowerShell HTTP Runtime Checks

**Обязательный пункт**: HTTP runtime checks on Windows must use PowerShell-safe commands.

Проект работает на Windows. PowerShell 5.1 отличается от Linux shell. `curl` в PowerShell — это alias для `Invoke-WebRequest`, Linux-флаги (`-s`, `-o NUL`, `-w`) ломаются.

Правильные команды для HTTP-проверок на Windows:

```powershell
# HTTP 200
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).StatusCode"

# HTTP 404 с try/catch (без try/catch прервёт проверку как ошибку)
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8015/nonexistent' -UseBasicParsing; $r.StatusCode } catch { $_.Exception.Response.StatusCode.value__ }"

# Проверка контента
powershell -Command "(Invoke-WebRequest -Uri 'http://127.0.0.1:8015/superadmin' -UseBasicParsing).Content -match 'SUPERADMIN'"
```

Если команда сломалась из-за оболочки — это не ACCEPTED, нужно повторить корректной командой.

Полные правила: `docs/ai/WINDOWS_POWERSHELL_COMMAND_RULES.md`.

---

## QA для кода

- [ ] PHP-файлы проходят `php -l`.
- [ ] SQL не ломает существующую схему.
- [ ] Нет захардкоженных company-specific правил без feature toggle.
- [ ] Нет прямого доступа к чужим компаниям из локальной ERP.
- [ ] SUPERADMIN и локальная ERP разделены.
- [ ] Права доступа проверяются до действия.
- [ ] Feature проверяется до показа страницы/отчёта.
- [ ] Загрузка файлов идёт в `/storage`, не в public.
- [ ] В БД хранятся пути и метаданные, не сами файлы.

## QA для UI

- [ ] Форма понятна пользователю.
- [ ] Labels и обязательные поля очевидны.
- [ ] Ошибки понятны.
- [ ] Нет технических сообщений для обычного пользователя.
- [ ] Таблицы читаемы.
- [ ] Действия не выглядят опасно без подтверждения.
- [ ] UI использует единый дизайн-фундамент, а не разрозненные стили.
- [ ] Дизайн-код не содержит бизнес-логики.


---

## Formal UI QA (обязательно для всех UI-задач)

QA обязан проверять UI формально. Запрещены субъективные оценки:

- «визуально красиво»;
- «визуально принято»;
- «дизайн выглядит хорошо»;
- «интерфейс выглядит нормально».

QA должен писать только:

```text
Formal UI QA: PASS
Formal UI QA: FAIL
```

### Обязательный Formal UI QA checklist

- [ ] Соответствие MD-шаблону страницы (`docs/ui/pages/[page].md`).
- [ ] Соответствие `DESIGN_CODE_INTEGRATION.md`.
- [ ] Соответствие `PAGE_PATTERN.md`.
- [ ] Нет псевдоиконок: `[=]`, `[#]`, `[~]`, `[v]` и подобных.
- [ ] Нет emoji как иконок.
- [ ] Нет demo-placeholder UI (карточный SaaS-dashboard для admin/settings, большие пустоты, blue/white corporate UI).
- [ ] Нет случайных CSS-классов.
- [ ] Нет inline styles.
- [ ] Нет Bootstrap/Tailwind/Material классов.
- [ ] `border-radius` новых элементов ≤ 4px.
- [ ] `box-shadow blur` новых элементов ≤ 8px.
- [ ] Нет debug badges как основного визуального элемента.
- [ ] VISUAL CHECK URL предоставлен.
- [ ] `Manual owner visual review required: YES` (для новых/изменённых экранов).
- [ ] `Commit allowed before owner visual approval: NO` (для новых/изменённых экранов).
- [ ] Все empty/loading/error states описаны и реализованы.
- [ ] Все опасные действия имеют подтверждение.

---

## Правило фактической проверки файлов

Если QA должен проверить настройки агентов, дизайн-код, UI, код, документацию или соответствие реализации правилам, проверка не делается по памяти или пересказу. Нужно запросить реальные файлы/архив.

---

## Правило DeepSeek/KILO не vision-модель

DeepSeek/KILO агенты не являются vision-моделями. QA не может финально оценивать внешний вид «глазами». QA проверяет только формальное соответствие правилам и MD-документации. Финальную визуальную приёмку UI делает владелец по скриншоту или в браузере.

---

## Правило ручной визуальной приёмки UI

Новые или существенно изменённые UI-экраны нельзя считать финально принятыми и нельзя коммитить как UI-approved, пока владелец не выполнит ручную визуальную проверку.

Для каждого нового или существенно изменённого UI-экрана handoff обязан содержать:
- VISUAL CHECK URL;
- список того, что владелец должен проверить глазами;
- список возможных визуальных блокеров;
- `Manual owner visual review required: YES`;
- `Commit allowed before owner visual approval: NO`.

Если владелец визуально отклоняет экран, статус задачи: `NEEDS_UI_REWORK`, даже если runtime/QA формально PASS.

---

## UI-задачи

Для UI-задач QA обязан проверить:

- [ ] есть актуальный MD-шаблон страницы в `docs/ui/pages/`;
- [ ] реализация соответствует MD-шаблону;
- [ ] кодер не придумал новые layout/classes/states без дизайнера;
- [ ] empty/loading/error states реализованы или явно не требуются;
- [ ] формы, таблицы, кнопки, статусы и inspector соответствуют дизайн-коду;
- [ ] опасные действия имеют подтверждение;
- [ ] **Formal UI QA checklist** пройден (см. выше);
- [ ] `AGENT_WORK_LOG.md` обновлён;
- [ ] проверена необходимость обновить `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`.

## Blocking acceptance rules

Статус `ACCEPTED` запрещён, если:

- UI-задача выполнена без актуального MD-шаблона страницы в `docs/ui/pages/`;
- кодер реализовал UI не по шаблону дизайнера;
- изменённые PHP-файлы не проверены через `php -l`;
- не выполнен `git status`;
- не обновлён `AGENT_WORK_LOG.md`;
- не обновлён `PROJECT_STATUS.md`, если изменился статус;
- не проверено, нужно ли обновить `ERP_PLANEX_CONTEXT_FOR_NEW_CHAT.md`;
- в код или MD попали `.env`, пароли, токены или секреты;
- нарушен `MODULE_PATTERN.md`;
- SQL написан прямо во view;
- бизнес-логика придумана без решения в `DECISIONS_LOG.md`.

## QA statuses

- `ACCEPTED` — всё соответствует ТЗ, архитектуре, UI-шаблону, проверкам и документации.
- `NEEDS_REWORK` — есть исправимые замечания, нужен второй круг.
- `REJECTED` — результат существенно не соответствует задаче или нарушает архитектуру.
- `BLOCKED` — не хватает решения, файла, UI-шаблона или невозможно выполнить проверку.
