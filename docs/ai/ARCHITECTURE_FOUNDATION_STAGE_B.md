# ERP PLANEX — ARCHITECTURE FOUNDATION STAGE B

## STATUS: FOUNDATION_STAGE_B_COMPLETE

Дата: 2026-06-24
Безопасная точка Git: cdc0d12

---

## 1. Что создано

### 1.1 AccessControlService

Файл: `app/Service/AccessControlService.php`

Единая будущая точка проверки прав доступа внутри company ERP.

**Методы (готовы к использованию):**

| Метод | Назначение |
|-------|-----------|
| `isSuperadmin($user)` | Проверка роли superadmin |
| `isCompanyOwner($user)` | Проверка роли company_owner |
| `isLogist($user)` | Проверка роли logist |
| `isSeniorLogist($user)` | Проверка роли senior_logist |
| `isCompanyRole($user)` | Роль принадлежит компании |
| `canSeeAllCompanyData($user)` | Видит все записи компании |
| `canManageCompanyUsers($user)` | Может управлять пользователями |
| `canViewOwnedEntity($user, $entity)` | Право просмотра сущности |
| `canEditOwnedEntity($user, $entity)` | Право редактирования сущности |
| `canAccessEntity($user, $entity, $action)` | Универсальная проверка |
| `buildLogistFilter($user, $entityType)` | SQL WHERE для logist |
| `isAllowedEntityType($entityType)` | Допустимый entity_type для grants |
| `hasOwnershipColumn($entityType)` | Есть ли created_by_user_id |
| `roleLabel($roleCode)` | Человекочитаемое имя роли |
| `allowedRolesForCompanyUserCreation()` | Роли для создания |

**Константы ролей:**
- `ROLE_SUPERADMIN` = `'superadmin'`
- `ROLE_COMPANY_OWNER` = `'company_owner'`
- `ROLE_SENIOR_LOGIST` = `'senior_logist'` (отображаемое имя: «Логист+»)
- `ROLE_LOGIST` = `'logist'`

### 1.2 DocumentService

Файл: `app/Service/DocumentService.php`

Будущий единый сервис для работы с документами.

**Методы (готовы к использованию):**

| Метод | Назначение |
|-------|-----------|
| `isAllowedEntityType($entityType)` | Проверка допустимости entity_type |
| `normalizeEntityType($entityType)` | Нормализация entity_type |
| `getAllowedExtensions()` | Список допустимых расширений |
| `getMaxFileSizeBytes()` | Макс. размер файла (20 MB) |
| `isAllowedExtension($filename)` | Проверка расширения |
| `extractExtension($filename)` | Извлечение расширения |
| `isFileSizeValid($bytes)` | Проверка размера |
| `makeSafeFilename($originalName)` | Безопасное имя файла |
| `isSafeOriginalName($originalName)` | Проверка на path traversal |
| `buildRelativePath($companyId, $entityType, $entityId)` | Относительный путь |
| `buildAbsoluteDir($companyId, $entityType, $entityId)` | Абсолютный путь |
| `buildStoredFilePath(...)` | Полный путь к файлу |
| `detectDocumentBadge($filename, $mimeType)` | Бейдж для UI |
| `formatFileSize($bytes)` | Форматирование размера |
| `driverPredefinedDocs()` | Предопределённые док-ты водителя |
| `vehicleSetPredefinedDocs($role)` | Предопределённые док-ты транспорта |

**Константы:**
- `MAX_FILE_SIZE_BYTES` = 20 MB
- `ALLOWED_EXTENSIONS` — 21 расширение
- `ALLOWED_ENTITY_TYPES` — 7 типов сущностей

### 1.3 Документация

Файл: `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md` (этот документ)

---

## 2. Что пока НЕ подключено

Оба сервиса созданы как FOUNDATION-классы. Они:

- **НЕ подключены** к существующим routes в `public/index.php`
- **НЕ используются** в DO_NOT_TOUCH_WORKING_CORE:
  - `/company/drivers`
  - `/company/vehicle-sets`
  - `/company/clients`
  - `/company/contractors`
- **НЕ меняют** существующие проверки прав (requireRole, inline grants)
- **НЕ меняют** текущий upload/download документов
- **НЕ меняют** БД (нет новых миграций)
- **НЕ меняют** session/auth

---

## 3. Как использовать в будущих модулях

### 3.1 AccessControlService

**Проверка роли на входе в роут:**
```php
$user = ['role_code' => $_SESSION['role_code'], 'user_id' => $_SESSION['user_id']];

if (!AccessControlService::canSeeAllCompanyData($user)) {
    // logist — фильтруем
    $filter = AccessControlService::buildLogistFilter($user, 'driver_vehicle_block');
    $sql = "SELECT * FROM driver_vehicle_blocks WHERE " . $filter['where'];
}
```

**Проверка доступа к конкретной записи:**
```php
if (!AccessControlService::canEditOwnedEntity($user, $block)) {
    http_response_code(403);
    exit;
}
```

**Создание пользователя (добавление senior_logist):**
```php
$allowedRoles = AccessControlService::allowedRolesForCompanyUserCreation();
// ['logist' => 'Логист', 'senior_logist' => 'Логист+']
```

### 3.2 DocumentService

**Валидация файла перед upload:**
```php
if (!DocumentService::isAllowedExtension($originalName)) {
    $errors[] = 'Недопустимый формат файла';
}
if (!DocumentService::isFileSizeValid($fileSize)) {
    $errors[] = 'Файл превышает 20 МБ';
}
if (!DocumentService::isSafeOriginalName($originalName)) {
    $errors[] = 'Недопустимое имя файла';
}
```

**Генерация безопасного имени:**
```php
$storedName = DocumentService::makeSafeFilename($originalName);
```

**Путь хранения:**
```php
$dir = DocumentService::buildAbsoluteDir($companyId, 'driver_vehicle_block', $blockId);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$destPath = $dir . DIRECTORY_SEPARATOR . $storedName;
```

**Бейдж для UI (существующие документы):**
```php
$badge = DocumentService::detectDocumentBadge($doc['original_name'], $doc['mime_type']);
// ['badge_class' => 'is-pdf', 'badge_text' => 'PDF']
```

---

## 4. Как проектировать новые модули без разрастания index.php

**Целевой стандарт для новых модулей:**

1. **Controller** — отдельный файл в `app/Controller/Company/` (или inline в index.php НА ПЕРВОМ ЭТАПЕ, с последующим выносом)

2. **Service** — используют AccessControlService и DocumentService

3. **View** — используют `app/View/pages/` и `app/View/partials/` как сейчас

4. **Route** — регистрируется в index.php, но handler — тонкая прослойка:
   ```php
   $router->get('/company/driver-vehicle-blocks', function () use ($config, $db) {
       requireRole(AccessControlService::COMPANY_ROLES);
       // ... controller logic or delegation
   });
   ```

5. **Никакого копирования document upload logic** — использовать DocumentService

6. **Никакого копирования access checks** — использовать AccessControlService

---

## 5. Как защищать DO_NOT_TOUCH_WORKING_CORE

**Правило:** новые сервисы НЕ подключаются к защищённым страницам до отдельного acceptance-этапа.

**Процесс перевода существующего модуля:**
1. Создать controller/service рядом со старым кодом
2. Протестировать на отдельном dev-окружении
3. Точечно заменить вызовы в index.php
4. Runtime-проверка старой и новой логики
5. Коммит

**Не делать:** массовую замену всех inline-проверок на сервис за один коммит.

---

## 6. Следующие модули для нового стандарта

| Модуль | URL | Статус | Что нужно |
|--------|-----|--------|-----------|
| driver_vehicle_blocks | `/company/driver-vehicle-blocks` | Список есть, модалок нет | Добавить create/edit/view modal |
| crews | `/company/crews` | Базовый CRUD есть | Перестроить на новый стандарт |
| senior_logist | — | Роль спроектирована, не активирована | Добавить в routes/sidebar |

---

## 7. Что дальше

1. **Принять этот этап** — владелец подтверждает foundation-сервисы
2. **Следующий крупный блок** — доработка driver_vehicle_blocks:
   - create modal (выбор driver + vehicle_set)
   - edit modal
   - view modal
   - использование AccessControlService
   - использование DocumentService
3. **После** — доработка crews (экипаж = contractor + driver_vehicle_block)
4. **Параллельно** — активация senior_logist в routes

---

## 8. Связанные документы

- `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md` — полный архитектурный план
- `docs/ai/AGENT_RULES.md` — правила агентов (раздел 9 — PROTECTED WORKING CORE)
- `docs/ai/DECISIONS.md` — решение #47 (DO_NOT_TOUCH_WORKING_CORE)
- `app/Service/AccessControlService.php` — сервис прав
- `app/Service/DocumentService.php` — сервис документов
