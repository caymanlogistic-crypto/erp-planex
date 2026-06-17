# ERP PLANEX — текущая задача

## NEXT: NEW_CHAT_VISUAL_ACCEPTANCE

STATUS: WAITING_FOR_OWNER_CHATGPT_VISUAL_ACCEPTANCE

Финальная визуальная полировка выполнена и закоммичена.

```text
COMMIT: a7cc70f — fix(ui): apply final visual polish after design audit
SCREENSHOTS: docs/design-audit/final-visual-polish/screenshots/ (12 files)
```

Следующее действие: перейти в новый ChatGPT-чат, загрузить актуальные MD и screenshots, выполнить финальную визуальную приёмку.

Проверять:
- соответствие дизайн-системе `FINAL3.html` / `DESIGN_STANDARD.md` / `erp-ui.css`;
- отсутствие backend/dev-слов в UI;
- читаемость меню, таблиц, карточек, dashboard;
- логичность действий и опасных зон;
- owner/logist/superadmin сценарии;
- отсутствие 404, technical error, пустых или обрезанных screenshots.

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

---

## NEXT TASK

Водители / Машины / Экипажи

Пока не начинать кодинг нового блока.
Сначала передать задачу через erp-architect.
