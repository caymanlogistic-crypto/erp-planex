# SUPERADMIN Company Management — Handoff

## Назначение
Управление компанией (экспедитором) из панели SUPERADMIN: просмотр карточки, редактирование полей, смена статуса.

## Маршруты
- `GET /superadmin/companies/{id}` — карточка компании (view)
- `GET /superadmin/companies/{id}/edit` — форма редактирования
- `POST /superadmin/companies/{id}/edit` — сохранение изменений (redirect на view)

## DB
- Таблица: `companies` в центральной БД
- Поля, которые РЕДАКТИРУЮТСЯ: name, inn, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, status, comments
- Поля, которые НЕЛЬЗЯ менять: id, key, db_identifier, storage_path, folder_path, settings_json, error_message, entity_type, short_name, created_at, updated_at
- Для owner info: SELECT из `company_users` WHERE company_id=? AND role='company_owner' (любой статус, не только active)

## Страница 1: Карточка компании (view)

### Layout
- page-head: «Компания: [name]», subtitle «ID: [id] · Статус: [status badge]»
- page-head-actions: «Редактировать» (btn-primary → /superadmin/companies/{id}/edit), «← К реестру» (btn-ghost → /superadmin/companies)

### Секции (внутри .panel > .panel-body)

**1. Основные данные (KV-list)**
| Ключ | Поле |
|------|------|
| Название | name |
| ИНН | inn |
| КПП | kpp или «—» |
| ОГРН | ogrn или «—» |
| Статус | status badge |
| Комментарий | comments или «—» |

**2. Адреса (KV-list)**
| Ключ | Поле |
|------|------|
| Юридический адрес | legal_address или «—» |
| Фактический адрес | physical_address или «—» |

**3. Контакты (KV-list)**
| Ключ | Поле |
|------|------|
| Контактное лицо | contact_person или «—» |
| Телефон | contact_phone или «—» |
| Email | contact_email или «—» |

**4. Техническая информация (KV-list)**
| Ключ | Поле |
|------|------|
| Локальная БД | db_identifier или «—» |
| Storage | storage_path или «—» |
| Provisioning | status, если error — error_message |
| Создана | created_at |
| Обновлена | updated_at |

**5. Руководитель (если company_users запись существует)**
| Ключ | Поле |
|------|------|
| ФИО | full_name |
| Логин | login |
| Email | email или «—» |
| Телефон | phone или «—» |
| Статус | badge |
| Управление | ссылка «Управлять Руководителем» → /superadmin/companies/{id}/owner |

Если руководителя нет: «Руководитель не создан. <a href="/superadmin/companies/{id}/create-owner">Создать</a>»

### Состояния
- **company not found**: .notice.warn «Компания не найдена. ← К реестру»
- **db error**: .notice.danger с сообщением ошибки

## Страница 2: Редактирование компании (edit)

### Layout
- page-head: «Редактировать компанию», subtitle «[company name]»
- page-head-actions: «← К карточке» (btn-ghost → /superadmin/companies/{id})

### Форма: POST /superadmin/companies/{id}/edit
Все поля внутри `.panel > .panel-body > form`

**Секция 1: Основные данные**
| Поле | Тип | Обязательное | Валидация |
|------|-----|-------------|-----------|
| name | field-input | Да (*) | Не пустое |
| inn | field-input | Да (*) | Не пустое; unique среди companies с другим id |
| kpp | field-input | Нет | — |
| ogrn | field-input | Нет | — |

**Секция 2: Адреса**
| Поле | Тип | Обязательное |
|------|-----|-------------|
| legal_address | field-textarea | Нет |
| physical_address | field-textarea | Нет |

**Секция 3: Контакты**
| Поле | Тип | Обязательное |
|------|-----|-------------|
| contact_person | field-input | Нет |
| contact_phone | field-input | Нет |
| contact_email | field-input (type=email) | Нет |

**Секция 4: Статус и комментарий**
| Поле | Тип | Обязательное | Опции |
|------|-----|-------------|-------|
| status | field-select | Да | active / inactive / blocked / archived |
| comments | field-textarea | Нет | — |

**Кнопки:** «Сохранить» (btn-primary, type=submit), «Отмена» (btn-ghost → /superadmin/companies/{id})

### Обработка POST
1. Загрузить company по id → если нет: formError «Компания не найдена»
2. Валидировать name (required), inn (required)
3. Проверить inn на дубликат: SELECT COUNT(*) FROM companies WHERE inn=? AND id!=? → если >0: ошибка поля inn «ИНН уже используется»
4. Если ошибки: перерендерить форму с .is-error и $errors
5. UPDATE companies SET name, inn, kpp, ogrn, legal_address, physical_address, contact_person, contact_phone, contact_email, status, comments WHERE id=?
6. db_identifier, storage_path, key — НЕ обновлять
7. Redirect 302 → /superadmin/companies/{id}

### Состояния
- **company not found**: .notice.warn
- **validation errors**: .is-error на полях, .field-msg под полями
- **success**: redirect → company view

## UI-компоненты (Core Kit)
- CORE-05: .page-head, .page-head-actions
- CORE-08: .panel, .panel-body
- CORE-13: .kv (key-value list: dl.kv > dt + dd)
- CORE-17: .badge, .badge-ok, .badge-warn, .badge-danger
- CORE-19: .btn, .btn-primary, .btn-ghost
- CORE-24: .notice.warn, .notice.danger
- CORE-26: .field, .field-label, .req, .field-input, .field-select, .field-textarea, .field-msg, .is-error
- CORE-27: .form-section
- CORE-28: .form-actions

## COMPOSITE pattern: PATTERN-02 (Admin detail + edit form)

## Соответствие существующему коду
- Используй те же CSS-классы, что в superadmin_companies.php и superadmin_company_owner_create.php
- statusBadge() функция уже существует в superadmin_companies.php — можно вынести в helpers или продублировать во view
- e() для экранирования
- $db->connection() для PDO

## Файлы для создания/изменения
1. `app/View/pages/superadmin_company_view.php` — новый
2. `app/View/pages/superadmin_company_edit.php` — новый
3. `public/index.php` — добавить 3 маршрута
4. `app/View/pages/superadmin_companies.php` — обновить колонку «Руководитель»: ссылка на карточку компании

## Что кодеру запрещено
- Менять db_identifier, storage_path, key при редактировании
- Добавлять hard delete
- Менять main.php, app.css, Database.php, Router.php
- Добавлять новые CSS-классы без отдельного разрешения
- Менять существующие SUPERADMIN маршруты (кроме обновления ссылок в companies list)
- Ломать существующие модули (companies registry, owner creation, logists, clients, contractors, drivers, vehicles, crews)

## Проверки после реализации
- `php -l` для всех новых/изменённых PHP-файлов
- GET /superadmin/companies/{id} → 200 (существующая компания)
- GET /superadmin/companies/99999 → 200 (не 500, сообщение)
- POST /superadmin/companies/{id}/edit → 302 → view с обновлёнными данными
- Пустой name → ошибка валидации
- Пустой inn → ошибка валидации
- Дубликат inn → ошибка валидации
- db_identifier не изменился после edit
- storage_path не изменился после edit
- Статус изменился (например, active → blocked)
- Все предыдущие маршруты работают (companies list, create-owner, logists, clients, contractors, drivers, vehicles, crews)
