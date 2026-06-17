# ERP PLANEX — текущая задача

## NEXT: TBD (ожидает владельца)

STATUS: CONTRACTORS_MENU_REWORK — CLOSED

Задача переработки меню и UX блока подрядчиков/перевозчиков выполнена в 6 блоках.

Новая структура меню компании:

```text
Подрядчики
├── Перевозчики
├── Водители+ТС
├── Водители
└── Транспорт
```

Главные решения:
- `contractors` в UI называются `Перевозчики`;
- `vehicle_sets` в UI называются `Транспорт`;
- `driver_vehicle_blocks` в UI называются `Водители+ТС`;
- `vehicle_units` убрать из меню, но не удалять из БД;
- `crews` не показывать как главный раздел, использовать как техническую связь `Перевозчик + Водители+ТС`;
- связка `Водители+ТС` immutable по составу: нельзя менять водителя или транспорт внутри связки;
- если нужен другой водитель или другой транспорт — создаётся новая связка;
- в `Водители+ТС` можно смотреть данные/документы и запускать редактирование исходных карточек водителя/транспорта, но не менять состав связки.

Ожидаемый пользовательский сценарий:
- менеджер может создать только перевозчика;
- менеджер может одной формой создать `Перевозчик + Водитель + Транспорт`;
- в карточке перевозчика можно добавить `Водителя+ТС`: выбрать существующую связку, собрать новую из существующего водителя/транспорта или создать нового водителя и транспорт;
- список `Водители+ТС` — это реестр уже собранных связок и документов.

БД физически не переименовывать и не ломать без отдельного решения.

---

## ЗАКРЫТО: SUPERADMIN — разделение реквизитов руководителя и ERP-пользователя

STATUS: CLOSED

STABLE COMMIT: da1cc90 — fix(superadmin): separate company director requisites from ERP user

ARCHITECTURE DECISION (#16):
- Руководитель в карточке компании — это реквизитные данные компании для документов.
- Хранится в таблице companies: director_position, director_full_name.
- ERP-доступ руководителя создаётся отдельно через /superadmin/companies/{id}/create-owner.
- Автоматическое создание company_owner при создании/редактировании экспедитора запрещено.

ВАЖНЫЕ COMMITS:
- 488a88b — test(superadmin): verify post-design functionality
- 49e7218 — fix(superadmin): restore company create handler
- da1cc90 — fix(superadmin): separate company director requisites from ERP user

ИСПРАВЛЕННАЯ РЕГРЕССИЯ:
После дизайн/функциональных правок POST /superadmin/companies/create был ошибочно заменён логикой создания руководителя.
Симптом: Warning: Undefined variable $company в superadmin_company_owner_create.php
Причина: не выполнялся INSERT INTO companies, использовался неопределённый $id, рендерился неправильный view.
Исправлено: 49e7218 — fix(superadmin): restore company create handler

КОНТАКТЫ КОМПАНИИ:
contact_person, contact_phone, contact_email остаются в БД, но убраны из форм создания/редактирования экспедитора и сейчас не используются в UI.

## ТОЧЕЧНАЯ ПРАВКА: shell/layout alignment with FINAL3

STATUS: DONE

COMMIT: 51f548d — fix: align shell-layout with FINAL3 reference

Исправлено по результатам ручной визуальной приёмки владельца:
- page-head height: 60px → 56px (эталон FINAL3)
- content padding: 12px 14px 28px → 10px (компактный системный отступ)
- filters-bar margin-bottom: 8px → 0 (устранён двойной вертикальный зазор с панелью)
- main.php: очищен BOM и NULL-byte после </html>

COMMIT: 8161b5a — fix: make page-head flush to edges with full border matching FINAL3

Дополнительно исправлено:
- page-head border: none+border-bottom → border: 1px solid var(--line) (полная рамка как в FINAL3)
- page-head margin: -10px -10px 0 -10px (отрицательные margin'ы компенсируют content padding, шапка flush к краям)
- Устранён визуальный эффект «скругления» — шапка получила острый индустриальный контур

COMMIT: e2a1da9 — fix: remove negative margins from page-head, restore content padding spacing

Откат отрицательных margin'ов:
- page-head margin: -10px -10px 0 -10px → 0 (шапка больше не вылезает за padding .content)
- border: 1px solid var(--line) сохранён (полная рамка из FINAL3)
- height: 56px сохранён
- .content padding: 10px сохранён

Аудит CSS: проблемных negative margin / large border-radius / box-shadow на shell/page/table/panel блоках не найдено.

COMMIT: fe1f7ff — fix: global border-radius 0 + content padding 0, hard edge normalization

Глобальная зачистка скруглений и внешних зазоров:
- Добавлен блок `ERP PLANEX HARD EDGE NORMALIZATION`: `* { border-radius: 0 !important }` — убраны все скругления (кнопки, инпуты, бейджи, таблицы, панели, dropdown, modal, toast)
- `.content { padding: 10px → 0 }` — убран внешний зазор по периметру рабочей области
- Media query `.content { padding: var(--space-4) → 0 }` — синхронизировано

---

## NEXT TASK

Водители / Машины / Экипажи

Пока не начинать кодинг нового блока.
Сначала передать задачу через erp-architect.
