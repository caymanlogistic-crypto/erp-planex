# SUPERADMIN Company Owner Management — Handoff v1.1

## Status
**HANDOFF_READY** (v1.1 — added D1/D2 action classification + D4 confirm scope).

---

## ACTION CLASSIFICATION (D2 — MANDATORY)

Owner management actions follow the SUPERADMIN action classification taxonomy:

| Label | Class | Visual class | Confirm | Notes |
|-------|-------|-------------|---------|-------|
| «Редактировать» | EDIT | `.btn-primary` | NO | Navigate to edit form |
| «← К карточке компании» | NAVIGATION | `.btn-ghost` | NO | Back to company card |
| «← К карточке Руководителя» | NAVIGATION | `.btn-ghost` | NO | Back to owner card |
| «Сбросить пароль» | SECURITY | `.btn-danger` | `confirm()` | Irreversible password change |
| «Сохранить» | EDIT | `.btn-primary` | NO | Save edited form |
| «Отмена» | NAVIGATION | `.btn-ghost` | NO | Cancel and go back |

### D4: confirm() scope for SECURITY actions
- Password reset: `confirm()` is ENOUGH — operates on a single user, reversible by setting a new password
- Confirm text: `«Сбросить пароль для [owner_name]? Текущий пароль будет заменён. Новый пароль будет показан только один раз.»`

---

## Назначение
Управление Руководителем компании из панели SUPERADMIN: просмотр карточки, редактирование, смена статуса, сброс пароля.

## Маршруты
- `GET /superadmin/companies/{id}/owner` — карточка Руководителя (view)
- `GET /superadmin/companies/{id}/owner/edit` — форма редактирования
- `POST /superadmin/companies/{id}/owner/edit` — сохранение изменений (redirect на view)
- `POST /superadmin/companies/{id}/owner/reset-password` — сброс пароля (render success)

## DB
- Таблица: `company_users` в центральной БД
- Поля, которые РЕДАКТИРУЮТСЯ: full_name, login, email, phone, status, comments
- Поля, которые НЕЛЬЗЯ менять: id, company_id, password_hash, role, created_at, updated_at
- password_hash меняется ТОЛЬКО через reset-password (bcrypt через password_hash())

## Страница 1: Карточка Руководителя (view)

### Layout
- page-head: «Руководитель: [full_name]», subtitle «Компания: [company.name] (ID: [company.id])»
- page-head-actions: «Редактировать» (btn-primary → /superadmin/companies/{id}/owner/edit), «← К карточке компании» (btn-ghost → /superadmin/companies/{id})

### Секции (внутри .panel > .panel-body)

**1. Основные данные (KV-list)**
| Ключ | Значение |
|------|----------|
| ФИО | full_name |
| Логин | login (в <code>) |
| Email | email или «—» |
| Телефон | phone или «—» |
| Роль | «Руководитель» |
| Статус | status badge |
| Комментарий | comments или «—» |
| Создан | created_at |
| Обновлён | updated_at |

**2. Действия**
- Кнопка «Сбросить пароль» (btn-danger — SECURITY класс, DESTRUCTIVE визуал)
- Форма сброса пароля: `POST /superadmin/companies/{id}/owner/reset-password`
  - Кнопка подтверждения: «Сбросить пароль» (`.btn-danger` — опасное действие)
  - Подтверждение: `«Сбросить пароль для [owner_name]? Текущий пароль будет заменён. Новый пароль будет показан только один раз.»`

### Состояния
- **company not found**: .notice.warn «Компания не найдена. ← К реестру»
- **owner not found**: .notice.warn «Руководитель не создан. <a href=".../create-owner">Создать Руководителя</a>»
- **db error**: .notice.danger

## Страница 2: Редактирование Руководителя (edit)

### Layout
- page-head: «Редактировать Руководителя», subtitle «[owner full_name] — Компания: [company.name]»
- page-head-actions: «← К карточке Руководителя» (btn-ghost → /superadmin/companies/{id}/owner)

### Форма: POST /superadmin/companies/{id}/owner/edit
Все поля внутри `.panel > .panel-body > form`

| Поле | Тип | Обязательное | Валидация |
|------|-----|-------------|-----------|
| full_name | field-input | Да (*) | Не пустое |
| login | field-input | Да (*) | Не пустое; латиница/цифры/подчёркивание; unique среди company_users с другим id |
| email | field-input (type=email) | Нет | Валидный email если заполнен |
| phone | field-input | Нет | — |
| status | field-select | Да | active / blocked |
| comments | field-textarea | Нет | — |

**Кнопки:** «Сохранить» (btn-primary, type=submit), «Отмена» (btn-ghost → /superadmin/companies/{id}/owner)

### Обработка POST
1. Загрузить company по id → если нет: formError
2. Найти owner: company_users WHERE company_id=? AND role='company_owner' → если нет: formError
3. Валидировать full_name (required), login (required, regex /^[a-zA-Z0-9_]+$/)
4. Проверить login на дубликат: SELECT COUNT(*) FROM company_users WHERE login=? AND id!=? → если >0: ошибка
5. Если ошибки: перерендерить форму
6. UPDATE company_users SET full_name, login, email, phone, status, comments WHERE id=?
7. Redirect 302 → /superadmin/companies/{id}/owner

### Состояния
- **company not found**: .notice.warn
- **owner not found**: .notice.warn
- **validation errors**: .is-error на полях
- **success**: redirect → owner view

## Страница 3: Сброс пароля (POST)

### Обработка POST /superadmin/companies/{id}/owner/reset-password
1. Загрузить company → если нет: ошибка
2. Найти owner → если нет: ошибка
3. Сгенерировать новый пароль: `generatePassword(10)` (функция уже есть в index.php)
4. password_hash($newPassword, PASSWORD_BCRYPT)
5. UPDATE company_users SET password_hash=? WHERE id=?
6. Показать success page:
   - .notice.success «Пароль успешно сброшен.»
   - KV-list: ФИО, Логин, Новый временный пароль (в <code style="background:var(--warning-bg)">)
   - .notice.warn «Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.»
   - Кнопка «← К карточке Руководителя»

### Состояния
- **company not found**: .notice.warn
- **owner not found**: .notice.warn
- **success**: пароль показан один раз, предупреждение
- **db error**: .notice.danger

## UI-компоненты (Core Kit)
- CORE-05: .page-head, .page-head-actions
- CORE-08: .panel, .panel-body
- CORE-13: .kv (key-value list: dl.kv > dt + dd)
- CORE-17: .badge, .badge-ok, .badge-warn
- CORE-19: .btn, .btn-primary, .btn-ghost, .btn-danger
- CORE-24: .notice.warn, .notice.danger, .notice.success
- CORE-26: .field, .field-label, .req, .field-input, .field-select, .field-textarea, .field-msg, .is-error
- CORE-28: .form-actions

## COMPOSITE pattern: PATTERN-02 (Admin detail + edit form)

## Соответствие существующему коду
- Используй те же классы, что в superadmin_company_owner_create.php
- e() для экранирования
- generatePassword() уже определена в index.php
- statusBadge() продублируй во view (как в superadmin_companies.php)

## Файлы для создания/изменения
1. `app/View/pages/superadmin_company_owner_view.php` — новый
2. `app/View/pages/superadmin_company_owner_edit.php` — новый
3. `public/index.php` — добавить 4 маршрута (owner view, edit GET, edit POST, reset-password POST)

## Что кодеру запрещено
- Сохранять plaintext пароль в БД, логах, MD или git
- Менять роль Руководителя (role всегда 'company_owner')
- Менять company_id владельца
- Менять main.php, app.css, Database.php, Router.php
- Добавлять новые CSS-классы без отдельного разрешения
- Ломать существующие модули

## Проверки после реализации
- `php -l` для всех новых/изменённых PHP-файлов
- GET /superadmin/companies/{id}/owner → 200 (существующий owner)
- GET /superadmin/companies/99999/owner → 200 (не 500, сообщение)
- GET /superadmin/companies/{id}/owner/edit → 200 (форма с данными)
- POST owner edit → 302 → owner view с обновлёнными данными
- Пустой full_name → ошибка валидации
- Пустой login → ошибка валидации
- Дубликат login → ошибка валидации
- POST reset-password → 200, показан новый пароль
- password_hash в БД — bcrypt (начинается с $2y$)
- Plaintext пароль НЕ в БД
- Статус owner изменился
- Все предыдущие маршруты работают
