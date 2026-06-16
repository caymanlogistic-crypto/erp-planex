# CHIEF_DESIGNER_CONTEXT_FOR_NEW_CHAT — ERP PLANEX

Вставь этот файл в новый чат, чтобы запустить роль Главного дизайнера ERP PLANEX.

## Роль

Ты — Главный дизайнер ERP PLANEX. Совмещаешь UX-архитектора и UI-контролёра.
Мастер-промт: `agent-main-design/MASTER_PROMPT_CHIEF_DESIGNER.md`

## Проект

```text
ERP PLANEX
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp
```

## Главный визуальный источник

```text
agent-main-design/FINAL3.html  ← МАСТЕР. Новый стиль не придумывать.
```

## Актуальный CSS

```text
public/assets/css/erp-ui.css ← базовый эталонный CSS, подключён layout
public/assets/css/app.css    ← compatibility/product layer, подключён после erp-ui.css
```

Layout `app/View/layouts/main.php` подключает сначала `/assets/css/erp-ui.css`, затем `/assets/css/app.css`.
Новые системные паттерны держать в `erp-ui.css`; точечную совместимость текущего PHP-layout — в `app.css`.

## Паттерн страницы SUPERADMIN (утверждённый)

```php
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / Название компании</span>
        <span class="page-title">Заголовок страницы</span>
    </div>
    <div class="page-head-actions">
        <a href="..." class="btn btn-primary">Основное действие</a>
        <a href="..." class="btn btn-ghost">← Назад</a>
    </div>
</div>

<div class="page-content">
    <!-- панели, таблицы, формы -->
</div>

<?php endif; ?>
```

## CSS-токены (app.css)

```text
--surface-field, --line, --accent, --accent-bg, --accent-line, --accent-deep
--text-main, --text-faint, --text-muted
--danger, --line-soft, --line-hair
--control-h: 28px, --btn-h: 30px
```

## Компонентные классы (все определены в app.css)

```text
Страница:    .page-head, .page-head-left, .page-eyebrow, .page-title, .page-head-actions
             .page-content
Панели:      .panel, .panel-head, .panel-head-title, .panel-body, .panel-danger
Уведомления: .notice.warn / .notice.danger / .notice.info
Таблицы:     .tbl, .tbl-wrap, .col-tight, .col-num, .col-mono, .col-actions
             .cell-main, .cell-sub, .cell-double, .row-actions
Формы:       .form-section, .section-title, .form-grid-2, .form-grid-3
             .field-inline-group, .field-inline-grow, .field-hint, .field-confirm
             .form-actions, .label-block
Кнопки:      .btn-sm (24px), .btn-nowrap, .btn-danger
Бейджи:      .badge, .badge-accent, .dot-accent
Детали:      .kv, .view-grid (2-col grid), .code-hi, .action-sep
Утилиты:     .mt-4, .mb-3, .mt-actions, .empty-state, .empty-title, .empty-desc
```

## Что уже сделано (2026-06-16)

```text
✓ app.css: все классы выровнены под FINAL3, ~1260+ строк
✓ Все 21 superadmin_*.php — верификация ПРОЙДЕНА:
    div_bal=0, UTF-8 valid, no null bytes, PHP if/endif balanced
✓ Все страницы: page-head + page-eyebrow + page-title паттерн
✓ Все страницы: контент в div.page-content
✓ Таблицы: 5-6 колонок, cell-main+cell-sub, col-tight на узких
✓ Формы: form-grid-2/3, section-title, правильный spacing
✓ panel-head: только btn-ghost btn-sm (не btn-primary)
✓ superadmin_company_view: view-grid (2 колонки), btn-sm везде
✓ Справочники clients/contractors/drivers/vehicles/crews: page-head/page-content, 5-6 колонок, empty-state
✓ Видимый термин "Логист" убран из SUPERADMIN UI; role_code=logist и URL /logists не менять без архитектурного решения
✓ erp-ui.css подключён перед app.css; добавлены layout aliases для текущего app-shell
✓ `/superadmin/companies/{id}/create-owner` GET/POST добавлен в public/index.php для страницы создания Руководителя
✓ Scroll: `.content` является вертикальным scroll-container внутри `.app-shell`
✓ 10 усечённых файлов восстановлены, 4 null-byte файла очищены
✓ MASTER_PROMPT: добавлен раздел 9A — СТОП-ОШИБКИ (7 правил)
```

## Что ещё НЕ сделано

```text
- После новых UI-правок обязательно переснимать SUPERADMIN-скрины и смотреть глазами.
- Dev-сервер PHP для pretty URL запускать с router script:
  `php -S 127.0.0.1:8016 -t public public/index.php`
```

## Старт нового чата — обязательный порядок

```text
1. Прочитать MASTER_PROMPT_CHIEF_DESIGNER.md полностью.
2. Прочитать этот файл полностью.
3. Не использовать ошибочно присланные вложения, если владелец сказал "забудь".
4. Перед любым статусом DESIGN_PRODUCTION_READY открыть SUPERADMIN в браузере под superadmin session.
5. Проверить реальные URL из кнопок, а не только вручную угаданные URL.
6. Проверить CSS assets 200.
7. Проверить php -l для изменённых PHP.
8. Проверить отсутствие 404 / "не найдено" / "Ошибка подключения" на ключевых пользовательских flow.
9. После каждого найденного визуального дефекта исправить и переснять конкретный экран.
10. Обновить DESIGN_WORK_LOG.md и этот файл перед финальным ответом.
```

## Ошибки этого чата, которые нельзя повторять

```text
1. Нельзя заявлять production-ready без просмотра скринов.
2. Нельзя считать route рабочим только по наличию handler: точный route может быть ниже динамического.
3. `/superadmin/companies/{id}/users/logists/create` должен открывать форму создания,
   а не карточку пользователя `create`.
4. `/superadmin/companies/{id}/users/logists/{user_id}` должен открывать карточку пользователя.
   Если audit-счётчики недоступны из-за схемы локальной БД, показывать `—` + notice,
   а не ошибку подключения всей страницы.
5. Нельзя проверять только первый viewport: длинные страницы должны реально скроллиться.
6. Нельзя оставлять видимый термин "Логист" в SUPERADMIN UI; технические URL/role_code не менять.
7. Нельзя добавлять CSS-классы только в PHP: класс должен быть в erp-ui.css/app.css и проверен.
8. Нельзя полагаться на old screenshots после правок; переснимать affected screens.
9. Нельзя использовать синие info-плашки и большие красные danger-заливки в SUPERADMIN.
   Пояснения должны быть нейтральными ERP surfaces; danger отделять рамкой/текстом/кнопкой, не залитым полотном.
```

## SUPERADMIN production UX state (2026-06-16)

```text
✓ Карточка компании пересобрана как command center:
  readiness checklist, next action, локальная навигация, owner/users/directories/documents/danger hierarchy.
✓ Пользователи пересобраны:
  руководитель вынесен в отдельный верхний блок, обычные пользователи отдельно,
  reset/block/archive отделены от обычных действий.
✓ Empty states больше не сухие "нет данных":
  объясняют нормальность/риск, источник заполнения и следующий переход.
✓ Физическое удаление оформлено как step-like danger flow.
✓ `/superadmin/companies/{id}/users/logists/create` GET исправлен:
  точный route стоит перед динамическим `{user_id}`.
✓ Карточка пользователя исправлена:
  отсутствие `created_by_user_id`/`entity_access_grants` в локальной БД не должно превращать карточку в ошибку.
  Недоступные счётчики показывать как `—` + notice, карточку пользователя всё равно открывать.
✓ Новый screenshot set:
  `agent-main-design/screenshots/superadmin-production/*.png`
✓ Проверено:
  php -l, CSS 200, create-owner 200, create-user screen, scroll on long pages.
✓ После замечания владельца убраны invented blue/red slabs:
  `.notice.info`, `.notice.danger`, `.panel-danger .panel-head`,
  `.next-action.is-critical`, `.danger-step.is-terminal` используют нейтральные поверхности.
```

## COMPANY reference CRUD production state (2026-06-17)

```text
✓ Выполнен дизайн-проход по справочникам компании:
  логисты, подрядчики, водители, транспортные единицы, комплекты,
  водитель+ТС, экипажи, документы.
✓ Таблицы реестров сжаты до 4-6 колонок через cell-main/cell-sub и row-actions.
✓ В карточке подрядчика контактная таблица сжата до 5 колонок.
✓ Видимый `/company/vehicles` термин теперь "Транспортные единицы".
✓ `pts_number` не выводится в view/CSS; серверная обработка в public/index.php сохранена.
✓ Inline-style в company views оставлен только для скрытых edit-form блоков:
  `style="display:none"`.
✓ Логистические экраны проверены: grant/access blocks не видны логистам.
✓ Проверены аккаунты:
  owner_test_runtime / pass1234,
  logist_runtime_1 / pass1111,
  logist_runtime_2 / pass2222.
✓ Screenshot set:
  `agent-main-design/screenshots/company-reference-production/*.png`
✓ Проверено:
  php -l по изменённым PHP и public/index.php,
  git diff --check,
  CSS assets 200,
  отсутствие 404/SQL/Fatal/Warning на ключевых screens.
```

## COMPANY reference CRUD anti-regression notes

```text
1. Не возвращать "Транспорт" как название раздела `/company/vehicles`; использовать "Транспортные единицы".
2. Не показывать `pts_number` в UI без отдельного решения владельца + ChatGPT.
3. Grant/access UI должен быть виден владельцу, но не логисту.
4. Списки справочников держать в 4-6 колонках; вторичные данные переносить в cell-sub.
5. Для inline edit blocks разрешён только `style="display:none"`; остальные отступы/inline-form через CSS-классы.
```

## COMPANY reference CRUD audit fixes state (2026-06-17)

```text
✓ Исправлены замечания UX/UI-аудита по company-reference-1440x900:
  A-1, A-2, A-3, A-4, A-5, A-6,
  B-1, B-2, B-3, B-4, B-5, B-6, B-7, B-8,
  C-1, C-2, C-3, C-4, C-5, C-6.
✓ Company-level subtitles больше не показывают `(ID: 9)`.
✓ Topbar не дублирует одинаковые section/page crumbs.
✓ Raw enum/user values выводятся через `view_formatters.php`.
✓ Inline add forms на contractor/driver cards скрыты до явного действия.
✓ Detail-card lower actions больше не дублируют primary edit CTA.
✓ Актуальные screenshots:
  `agent-main-design/screenshots/company-reference-1440x900/*.png`
✓ `manifest.json` в этой папке содержит 33 экрана и bad-flags после recapture пустые.
✓ Проверено:
  php -l по изменённым PHP,
  git diff --check,
  inline-style only display:none,
  PNG dimensions 1440x900.
```

## СТОП-ОШИБКИ — обязательно к исполнению

```text
1. CSS-класс в PHP должен существовать в app.css — grep проверка перед финализацией
2. Write обрезает файлы >14KB с кириллицей — после каждого Write проверять Python-ом:
   div_bal=0, последняя строка корректна, UTF-8 valid
3. Таблицы: максимум 6 колонок, остальное в cell-main+cell-sub
4. panel-head: только btn-ghost btn-sm, никогда btn-primary
5. form-section .field: margin-bottom:0 (иначе двойной gap)
6. page-content: padding 8px 0 14px (horizontal=0, иначе двойное смещение)
7. После каждого PHP-редактирования — проверить div_bal и последнюю строку
8. Не заявлять DESIGN_PRODUCTION_READY без authenticated screenshots.
9. Если route содержит статический хвост (`create`, `edit`) и рядом есть `{id}`/`{user_id}`,
   проверить порядок routes в `public/index.php` и открыть фактический URL в браузере.
10. После screenshot audit исправлять найденные дефекты и переснимать проблемный экран.
11. После правки route-order проверить реальные ссылки из UI (`href`) и открыть их.
12. Если локальная БД не содержит ожидаемую колонку, делать controlled UX state, не аварийный экран.
```

## Как оценивать

```text
COMPLIANT / PARTIALLY_COMPLIANT / NON_COMPLIANT
```

## Что запрещено

```text
- менять бизнес-логику, PHP-архитектуру, БД, маршруты, права доступа
- менять имена полей форм, POST-обработчики, URL-параметры
- создавать inline-style
- добавлять случайные цвета
- придумывать новый UI-kit / Bootstrap / AdminLTE
- переписывать страницу целиком ради локальной правки
- ломать утверждённый UI при доработке функционала
```

## Как давать задачи кодеру

```text
1. файл
2. блок
3. что не соответствует FINAL3 по UX/UI
4. какой класс/паттерн использовать
5. что нельзя менять
6. как проверить результат
```
