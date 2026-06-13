# ERP PLANEX — ENTITY_OWNERSHIP_AND_ACCESS

## Назначение

Архитектурная спецификация модели владения записями (ownership) и выдачи доступа (access grants) в локальной ERP компании.

## Статус

**Stage: реализовано (DECISION-0044, DECISION-0046).** Актуально для MVP.

---

## Модель Ownership

### Колонки

Каждая локальная таблица компании содержит:

| Колонка | Тип | Назначение |
|---------|-----|------------|
| `created_by_user_id` | INT UNSIGNED | ID пользователя (из локальной `users`), создавшего запись |
| `created_by_role` | VARCHAR(20) | Роль создателя: `company_owner` или `logist` |

Таблицы с ownership: `users`, `clients`, `contractors`, `drivers`, `vehicles`, `crews`, `documents`.

### Заполнение

При создании записи:
```php
'created_by_user_id' => (int)$_SESSION['user_id'],
'created_by_role'   => $_SESSION['role_code'],
```

---

## Модель Access Grants

### Таблица entity_access_grants

```sql
CREATE TABLE IF NOT EXISTS entity_access_grants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    granted_to_user_id INT UNSIGNED NOT NULL,
    granted_by_user_id INT UNSIGNED NOT NULL,
    access_level VARCHAR(20) NOT NULL DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grant (entity_type, entity_id, granted_to_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Разрешённые entity_type

- `client`
- `contractor`
- `driver`
- `vehicle`
- `crew`

### Уровни доступа (access_level)

- `view` — просмотр записи (единственный на первом этапе)

---

## Правила видимости

### Company Owner (Руководитель)

```sql
SELECT * FROM {table} ORDER BY created_at DESC
```

Видит **все** записи своей компании.

### Logist (Логист)

```sql
SELECT * FROM {table}
WHERE (
    created_by_user_id = ?
    OR id IN (
        SELECT entity_id FROM entity_access_grants
        WHERE entity_type = ? AND granted_to_user_id = ? AND access_level = 'view'
    )
)
ORDER BY created_at DESC
```

Видит:
- Записи, которые сам создал (`created_by_user_id = его id`)
- Записи, на которые ему выдан grant view

---

## Выдача доступа

### Маршрут

```
POST /company/access-grants/grant
```

Доступен только `company_owner`.

### Параметры

| Поле | Описание |
|------|----------|
| `entity_type` | Тип сущности (whitelist) |
| `entity_id` | ID сущности |
| `granted_to_user_id` | Кому выдать доступ (ID логиста) |
| `redirect` | Куда редиректить после выдачи |

### UI

На карточке каждой сущности (view-страница) — секция «Доступ логистов»:
- Таблица выданных доступов (логист, доступ, дата)
- Форма: select логиста → кнопка «Дать доступ»

---

## Что НЕ реализовано сейчас

- Отзыв доступа (revoke)
- Уровни доступа кроме `view` (edit, delete)
- Массовая выдача доступа
- Отдельная страница управления доступами (`/company/access-grants`)
- Access grants для документов

---

## Связанные документы

- `docs/ai/DECISIONS_LOG.md` — DECISION-0044, DECISION-0046
- `docs/architecture/PERMISSIONS_MODEL.md` — модель ролей
- `docs/architecture/AUTH_SESSION_MODEL.md` — авторизация и сессии

---

## Последнее обновление

2026-06-13 — создан при реализации Full Reference Functional Completion.
