# ERP PLANEX — HANDOFF_FOR_NEW_CHAT

## Главное для нового ChatGPT-чата

Прочитай этот файл первым. Он является главным переносимым контекстом текущей работы ERP PLANEX.

Рабочая папка проекта:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp
```

Разработка ведётся через KILO-агентов:

```text
Владелец + ChatGPT → erp-architect → erp-coder → erp-architect acceptance → владелец + ChatGPT
```

Постоянные KILO-агенты только:

```text
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

Главный дизайнер / CODEX-дизайнер — внешние чаты/инструменты, не постоянные KILO-агенты.

## PROTECTED WORKING CORE

4 страницы объявлены защищённым рабочим ядром. Их нельзя менять без отдельной явной задачи:

```text
/company/drivers
/company/vehicle-sets
/company/clients
/company/contractors
```

Полный список защищённых файлов (routes, views, partials, JS, CSS, сервисы, таблицы) и архитектурный план безопасного рефакторинга:

```text
docs/ai/PROTECTED_ARCHITECTURE_PLAN.md
```

Любой будущий рефакторинг должен начинаться с MD/карт/правил, а не с переноса кода.

## Стиль взаимодействия с владельцем

- Отвечать коротко и по делу.
- Не писать промты агентам без прямой просьбы владельца: «пиши промт», «напиши промт», «дай промт».
- Если не хватает данных — запросить реальные файлы/архив, не фантазировать.
- Если нужны файлы — сразу дать CMD/PowerShell-команду в одну строку для архива.
- Не принимать отчёт агента `DONE`, если нет фактической проверки.
- UI оценивать только как соответствует / не соответствует / частично соответствует утверждённому стандарту, без вкусовщины.

## Текущий активный фокус

Сейчас активная работа — **форма просмотра/редактирования водителя в модальном окне** на странице:

```text
/company/drivers
```

Сценарий:

```text
/company/drivers
→ двойной клик по строке водителя
→ модал просмотра
→ кнопка Редактировать
→ форма редактирования
→ Сохранить
→ возврат в просмотр
```

## Последние подтверждённые commits из текущей цепочки

По выводу владельца в этом чате:

```text
c3b825c fix(drivers): align create modal footer with FINAL3
7a796fb feat(drivers): open create form in FINAL3 modal from drivers list
051155f wip(ui): refine ERP grid toolbar and list pages
3ddade7 wip(drivers): recompose drivers grid fields and documents
90ab717 fix(drivers): remove unique phone constraint
365dbbe wip(ui): unify ERP grid list tables
a2149a7 feat(stepper): contractor/driver/vehicle create-full with FINAL3 stepper
67b5454 feat(clients): align client create flow with legal entity standard
```

`c3b825c` — последний подтверждённый commit по созданию водителя в модалке.

После `c3b825c` были незакоммиченные доработки edit-модала водителя. Они пока **не приняты как production**.

## Текущий статус edit-модала водителя

Текущий статус:

```text
DRIVER_EDIT_MODAL_GEOMETRY_NEEDS_REWORK
```

Не коммитить как финал, пока владелец не подтвердит визуально и runtime.

Что уже было сделано агентами по отчёту, но требует проверки:

```text
- edit-form подключает общий company_driver_create_form.php;
- создана/обновлена window.initDriverForm(form);
- убран cloneNode(form);
- data-doc-types перемещался внутрь form;
- добавлены уникальные DOM-prefix id;
- убран browser alert в пользу form-alert;
- добавлена логика delete_predef_doc для ×;
- добавлена backend-обработка predef/custom docs в POST modal-edit;
- ширина edit/view modal менялась 1040px → 1100px.
```

Но по визуальной проверке владельца форма редактирования всё ещё отличается от эталона создания.

## Главный текущий визуальный дефект

Эталон — форма создания водителя:

```text
/company/drivers/create
/company/drivers → Создать водителя
```

Редактирование должно быть **той же формой создания 1 в 1**, только:

```text
- поля предзаполнены;
- action ведёт на update route;
- кнопка в footer = Сохранить;
- для существующих документов кнопка Заменить вместо Выбрать;
- есть рабочий × для документа;
- после сохранения возврат в просмотр.
```

По скринам владельца было видно:

```text
CREATE:
общая рабочая ширина формы ≈ 948 px
левая колонка данных ≈ 644 px
правая колонка документов ≈ 304 px

EDIT:
общая рабочая ширина формы ≈ 880 px
левая колонка данных ≈ 575 px
правая колонка документов ≈ 304 px
```

Проблема: правая колонка документов почти нормальная, а левая колонка edit сжата примерно на 65–70 px. Поэтому поля, отступы, доп. телефон и общая геометрия не совпадают с формой создания.

## Текущая задача для erp-architect

Нужно исправить **геометрию edit-form под create-form**, не трогая backend и интерактив, если они уже исправлены.

Архитектор должен сравнить:

```text
/company/drivers/create
/company/drivers → двойной клик по строке → Редактировать
```

Цель:

```text
edit-form должна совпасть с create-form по:
- общей рабочей ширине;
- ширине левой колонки;
- ширине правой колонки;
- левому краю формы;
- правому краю формы;
- отступу между колонками;
- ширине ФИО;
- ширине телефона;
- ширине паспортных полей;
- ширине комментария;
- положению блока документов;
- ширине document-file-row;
- footer.
```

Допустимая разница только в данных:

```text
create: пустые поля / Выбрать
edit: предзаполненные поля / Заменить / ×
```

## Что архитектору нельзя менять в текущей геометрической правке

Не менять без отдельной команды:

```text
backend
routes
сохранение
driver_phones
документы
замену файлов
soft delete
initDriverForm
sidebar
topbar
page-head
menu
```

Сейчас задача — только geometry/layout edit-form.

## Где искать текущий дефект

Файлы:

```text
app/View/partials/company_driver_create_form.php
app/View/partials/company_driver_modal_edit.php
app/View/pages/company_drivers.php
public/assets/css/app.css
public/assets/js/app.js
```

Проверить CSS/DOM:

```text
.driver-create-modal
.driver-view-overlay .modal.driver-view-modal-inner
.modal-body
.driver-modal-body
.entity-form-layout
.entity-form-main
.entity-form-docs
.panel
.panel-body
```

Если create modal имеет:

```css
.driver-create-modal {
  width: min(1100px, calc(100vw - 48px));
}
```

а edit/view modal имеет меньшую ширину — edit должен быть приведён к create.

Также нужно убрать всё, что съедает рабочую ширину edit-form:

```text
лишний wrapper
лишний padding
лишний margin
panel/panel-body spacing
page-form spacing внутри modal
```

Внутри edit modal должно быть:

```text
modal-body
  form#driver-edit-form
    entity-form-layout
      entity-form-main
      entity-form-docs
```

Без дополнительной внутренней рамки и без лишнего внешнего padding вокруг `entity-form-layout`.

## Проверки после геометрической правки

Команды через CMD:

```bat
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l app\View\partials\company_driver_create_form.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l app\View\partials\company_driver_modal_edit.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git diff --check"
```

Runtime:

```bat
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -d upload_max_filesize=25M -d post_max_size=100M -d max_file_uploads=50 -d memory_limit=256M -d max_execution_time=120 -d max_input_time=120 -S 127.0.0.1:8016 -t public public/index.php"
```

Вручную:

```text
1. /company/drivers/create — эталон не сломан.
2. /company/drivers → Создать водителя — create modal не сломан.
3. /company/drivers → двойной клик → Редактировать.
4. edit-form визуально совпадает с create-form.
5. + Доп. телефон работает.
6. + Добавить документ работает.
7. Заменить работает.
8. × работает.
9. Сохранить возвращает в просмотр.
10. Консоль без JS errors.
```

## После успешной проверки

Если владелец подтвердит, коммитить только кодовые файлы, без docs и `.kilo`:

```text
public/index.php
app/View/pages/company_drivers.php
app/View/partials/company_driver_create_form.php
app/View/partials/company_driver_modal_view.php
app/View/partials/company_driver_modal_edit.php
public/assets/js/app.js
public/assets/css/app.css
```

Не добавлять:

```text
.kilo/
docs/ai/
README.md
```

## Дизайн-система и UI lock

Главный исторический дизайн-источник:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\STYLE ERP\ФИНАЛЬНЫЙ РАБОЧИЙ ВАРИАНТ\FINAL3.html
```

Рабочий CSS:

```text
public/assets/css/erp-ui.css
public/assets/css/app.css
```

Запрещено менять без прямого подтверждения владельца:

```text
- основную шапку ERP;
- шапку контентного блока / page-head;
- основное меню / sidebar;
- shell layout.
```

## Правила документации

Разрешённый минимум MD:

```text
docs/ai/PROJECT_STATE.md
docs/ai/AGENT_RULES.md
docs/ai/CURRENT_TASK.md
docs/ai/DECISIONS.md
docs/ai/HANDOFF_FOR_NEW_CHAT.md
docs/ui/DESIGN_STANDARD.md
```

Новые управляющие MD не создавать без решения владельца + ChatGPT.

## Следующий отдельный блок, не текущий

Есть подготовленные документы по будущей форме транспортного комплекта:

```text
docs/ai/vehicle_set_schema_for_codex.md
docs/ai/vehicle_set_full_implementation_prompt_for_erp_coder.md
```

Они не являются текущей задачей, пока не закрыта форма редактирования водителя.
