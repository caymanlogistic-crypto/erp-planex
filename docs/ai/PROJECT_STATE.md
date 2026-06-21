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

QA встроен в работу кодера, архитектора и финальную приёмку владельцем + ChatGPT.

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

Page-specific CSS допускается в:

```text
public/assets/css/app.css
```

## Текущее правило разработки

Кодер обязан:

```text
понять задачу → продумать пользовательский сценарий → реализовать функционал → выполнить runtime → применить дизайн-систему → повторно проверить → коротко отчитаться
```

## Статус блоков

```text
SUPERADMIN — ЗАКРЫТ на текущем этапе.
CONTRACTORS_MENU_REWORK — ЗАКРЫТ (с исправленной регрессией доступа логиста).
CREATE_FORMS_WITH_DOCUMENT_TYPES — ПРИНЯТ.
DRIVER_CREATE_DOCS_AND_PHONES — ПРИНЯТ.
CONTRACTOR_CREATE_LEGAL_ENTITY_STANDARD — ПРИНЯТ.
UPLOAD_LIMITS_20_80 — ПРИНЯТ.
CLIENT_CREATE_LEGAL_ENTITY_STANDARD — ПРИНЯТ (commit 67b5454).
STEPPER_CONTRACTOR_DRIVER_VEHICLE — ГОТОВ (ожидает commit после проверки владельцем).
```

## Последний стабильный commit

```text
67b5454 — feat(clients): align client create flow with legal entity standard
```

## Важные последние commits

```text
67b5454 — feat(clients): align client create flow with legal entity standard
0f37c35 — fix(upload): enforce document upload size limits
663ab6e — fix(forms): finalize create forms production checks
6c729a2 — checkpoint: save contractor create form before tech parity rework
03427f9 — chore: save cleaned ERP project after restore
```

## Contractor contacts

```text
contractor_contacts — основная модель контактов перевозчика/подрядчика.
У одного перевозчика может быть несколько контактов.
is_primary — один главный контакт, по умолчанию первый непустой.
is_document_email — необязательный флаг email для официальной рассылки, может быть у нескольких контактов.
Legacy-поля contractors.contact_person / contact_phone / contact_email удаляются локальной миграцией.
```

## Client contacts

```text
client_contacts — основная модель контактов клиента.
У клиента может быть несколько контактов.
is_primary — один главный контакт, по умолчанию первый непустой.
is_document_email — необязательный флаг email для документов, может быть у нескольких контактов.
Legacy-поля clients.contact_person / contact_phone / contact_email удалены локальной миграцией 033.
```

## Последний принятый этап

```text
ГОТОВО (не закоммичено): STEPPER_CONTRACTOR_DRIVER_VEHICLE
```

Сделано:

- Логист получил доступ к `/company/contractors/create-full` (GET/POST);
- `/company/driver-vehicle-blocks/create` переделана на 3-шаговый stepper FINAL3;
- `/company/contractors/create-full` переделана на 4-шаговый stepper FINAL3 с поддержкой выбора существующих ИЛИ создания новых сущностей;
- Backend create-full расширен: режимы existing/new, проверка дублей driver_vehicle_block и crew, created_by_user_id/created_by_role;
- JS-валидация без alert(), через inline-сообщения `.field-msg.is-error`;
- Предыдущий commit: 67b5454 — feat(clients): align client create flow with legal entity standard.

## Следующий блок

```text
Ожидает проверки владельцем и commit.
```

## CRITICAL UI LOCK RULE (активен)

Запрещено менять без прямого подтверждения владельца:

1. **Основную шапку ERP** — HTML, CSS, размеры, отступы, структуру, классы, поведение, внешний вид.
2. **Шапку контентного блока** — page-head / page-header, заголовочную зону страницы, высоту, отступы, кнопочную структуру, классы, визуальное поведение.
3. **Основное меню** — структуру, внешний вид, классы, поведение, отступы, активные состояния, раскрытие/сворачивание.

Разрешено только: добавлять новые кнопки, пункты меню, действия без изменения существующей структуры.

Если задача требует изменить заблокированные зоны — агент обязан остановиться и запросить подтверждение владельца.

## Семантика меню компании — новая модель подрядчиков

```text
Подрядчики — раскрываемая группа меню, а не отдельный справочник.
Перевозчики — пользовательское название сущности contractors; это бывшие Подрядчики.
Водители+ТС — пользовательское название driver_vehicle_blocks; неизменяемая по составу связка Водитель + Транспорт.
Водители — справочник drivers; здесь редактируются данные водителя.
Транспорт — пользовательское название vehicle_sets; бывшие Транспортные комплекты; здесь редактируются данные транспорта.
Транспортные единицы — vehicle_units; техническая внутренняя сущность, из меню убрать, из БД не удалять.
Экипажи / crews — техническая связь Перевозчик + Водители+ТС; не показывать как главный пользовательский раздел.
```

Правило неизменяемости связки `Водители+ТС`:

```text
Нельзя заменить водителя или транспорт внутри существующей связки.
Если нужен другой водитель или другой транспорт — создаётся новая связка.
В разделе Водители+ТС разрешены просмотр, документы и переход к редактированию исходных карточек водителя/транспорта.
```

## Правило обновления

Файл обновляется только при изменении:

- текущего статуса проекта;
- активной задачи;
- агентской схемы;
- последнего принятого этапа;
- следующего блока.

Не добавлять длинные отчёты.
