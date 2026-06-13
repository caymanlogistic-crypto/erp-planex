# ERP PLANEX — FORMS_STANDARD

## Назначение

Этот файл задаёт стандарт форм ERP PLANEX.

Главный UI-регламент: `docs/ui/DESIGN_CODE_INTEGRATION.md`.

Формализованные правила из STYLE ERP: `docs/ui/STYLE_ERP_EXTRACTED_RULES.md`.

Основной компактный UI-kit: `docs/ui/ERP_UI_KIT_CORE.html`.

Legacy extraction draft: `docs/ui/ERP_UI_MODULE_CATALOG.html`.

---

## Стандарт форм

Каждая форма должна иметь:

- понятный заголовок;
- краткое пояснение при необходимости;
- label у каждого поля;
- отметку обязательных полей;
- понятные ошибки под конкретным полем;
- кнопку основного действия;
- кнопку отмены/возврата, если нужно;
- сообщение об успешном сохранении;
- disabled/readonly/error/success states, если применимо.

Формы должны использовать единые компоненты и токены из дизайн-фундамента.

Из STYLE ERP извлечены обязательные form rules:

- control height: 28px;
- button height: 30px;
- toolbar controls: 26px;
- radius: 2px, максимум 4px;
- `field`, `field-label`, `req`, `field-input`, `field-select`, `field-textarea`, `field-msg`;
- required marker рядом с label;
- error/success/disabled/readonly states описываются в handoff;
- поля группируются в sections/fieldsets по смыслу;
- 2/3-column form grids допустимы только для плотных editor screens;
- browser-default input/select запрещены.

Advanced form patterns можно использовать только если они описаны в page handoff:

- custom select with search;
- multiselect tags;
- entity lookup dropdown;
- file dropzone / inline file list / upload progress;
- input with prefix/suffix/action/clear button;
- numeric stepper;
- password strength;
- checkbox/radio groups;
- inline edit state.

Каждый form-модуль в handoff должен ссылаться на CORE module из `ERP_UI_KIT_CORE.html`, например `CORE-26 Form field`, `CORE-27 Form section`, `CORE-28 Validation/error state`, `CORE-29 Upload/document input`, `CORE-45 Bottom entity editor`. Если нужного form-модуля нет, сначала расширяется Core Kit / профильные MD:

```text
BLOCKED: NEEDS_UI_MODULE_EXPANSION
```

Запрещено создавать отдельный визуальный стиль формы внутри конкретной страницы без обновления UI-документации.

---

## Правило для дизайнера

Если страница содержит форму, дизайнер обязан описать её в MD-шаблоне страницы:

```text
docs/ui/pages/[page-name].md
```

Нужно указать поля, обязательность, валидацию, ошибки, состояния и кнопки.

---

## Правило для кодера

Кодер реализует форму строго по MD-шаблону страницы.

Кодер не должен сам менять состав полей, порядок блоков, тексты ошибок и расположение кнопок без дизайнера/архитектора.

---

## Загрузка файлов

При загрузке документов:

- показывать допустимые форматы;
- показывать ограничение размера;
- показывать статус загрузки;
- не хранить файлы в public;
- сохранять файл в `/storage`;
- сохранять путь и метаданные в БД;
- проверять права доступа на скачивание через контроллер.
