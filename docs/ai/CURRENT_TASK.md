# ERP PLANEX — текущая задача

## STATUS: DRIVER_EDIT_MODAL_GEOMETRY_NEEDS_REWORK

Текущая активная задача — довести форму редактирования водителя в модальном окне до production-соответствия эталону создания.

Страница:

```text
/company/drivers
```

Сценарий:

```text
/company/drivers
→ двойной клик по строке водителя
→ модал просмотра
→ Редактировать
→ форма редактирования
```

## Главный критерий

Форма редактирования водителя должна быть **1 в 1 как форма создания водителя**.

Эталон:

```text
/company/drivers/create
/company/drivers → Создать водителя
```

Разница edit от create допускается только в данных и действиях:

```text
create: пустые поля / Выбрать / Создать водителя
edit: предзаполненные поля / Заменить / × / Сохранить
```

Геометрия должна совпадать.

## Что уже было исправлено по отчёту агента, но требует проверки

```text
- edit/view modal width 1040px → 1100px;
- data-doc-types перенесён внутрь form;
- интерактивные id префиксированы через DOM-prefix;
- browser alert заменён на .form-alert.alert-error;
- добавлен delete_predef_doc для ×;
- POST modal-edit сохраняет predef docs, custom docs, soft-delete;
- initDriverForm(form) scoped по form;
- cloneNode(form) убран.
```

## Почему задача ещё не принята

По визуальной проверке владельца edit-form всё ещё не совпадает с create-form.

По скринам:

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

Проблема: правая колонка почти совпадает, но левая колонка edit сжата примерно на 65–70 px. Нужно вернуть недостающую ширину в левую колонку за счёт modal/body/layout/wrapper/padding, не сжимая документы.

## Следующее действие архитектора

Исправить только геометрию edit-form:

```text
1. Сравнить /company/drivers/create и /company/drivers → Редактировать.
2. Найти wrapper/padding/margin/panel-body, который съедает ширину edit-form.
3. Сделать edit modal/layout той же рабочей ширины, что create.
4. Оставить правую колонку документов около 304 px.
5. Вернуть левую колонку к ширине около 644 px.
6. Не трогать backend/JS-интерактив/сохранение, если они уже исправлены.
```

## Запреты текущей задачи

Не трогать без отдельного решения:

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

## Проверка

```bat
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l app\View\partials\company_driver_create_form.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -l app\View\partials\company_driver_modal_edit.php"
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git diff --check"
```

Runtime запуск:

```bat
cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php -d upload_max_filesize=25M -d post_max_size=100M -d max_file_uploads=50 -d memory_limit=256M -d max_execution_time=120 -d max_input_time=120 -S 127.0.0.1:8016 -t public public/index.php"
```

Ручная проверка:

```text
1. /company/drivers/create — эталон не сломан.
2. /company/drivers → Создать водителя — create modal не сломан.
3. /company/drivers → двойной клик → Редактировать.
4. edit-form совпадает с create-form по геометрии.
5. + Доп. телефон работает.
6. + Добавить документ работает.
7. Заменить работает.
8. × работает.
9. Сохранить возвращает в просмотр.
10. Консоль без JS errors.
```

## Коммит

Не коммитить до owner visual/runtime acceptance.

Если владелец подтвердит, коммитить только кодовые файлы, не docs/ai и не .kilo.
