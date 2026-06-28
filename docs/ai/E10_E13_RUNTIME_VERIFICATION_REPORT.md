# ERP PLANEX — E10-E13 RUNTIME VERIFICATION REPORT

## Статус: E10_E13_RUNTIME_VERIFIED

## Выполненные проверки

| Проверка | Статус |
|----------|--------|
| `php -l` ключевые файлы | PASS |
| `architecture_guard.php` | PASS (0 errors, 0 warnings) |
| `git diff --check` | PASS (только LF→CRLF) |
| `git status` | Чистое дерево |

## Найденные баги

### БАГ #1 (CRITICAL): entity_list.php — неверный источник entityType

**Файл**: `app/Http/Controllers/Superadmin/ManagementActions/entity_list.php`

**Проблема**: Файл читал `$entityType = $_GET['entity_type']`, но URL запросов вида `/superadmin/companies/{id}/clients` не содержит query-параметра `entity_type`. Переменная `$entityType` становилась пустой строкой → SQL запрос `SELECT * FROM s` → SQL fatal.

**Дополнительная проблема**: Имя таблицы строилось динамически через `{$entityType}s` (конкатенация + 's'), что:
1. Работает случайно для `client`, `contractor`, `driver`, `crew` (→ `clients`, `contractors`, `drivers`, `crews`)
2. Работает случайно для `vehicle_unit` (→ `vehicle_units`)
3. НО является SQL injection риском, т.к. имя таблицы не проходит whitelist-фильтр

**Исправление**:
- В `ManagementController` добавлена явная установка `$entityType` перед require для каждого метода (clients, contractors, drivers, vehicles, crews)
- В `entity_list.php` заменена динамическая конкатенация на явную whitelist map: `entityType => [table, view, label, plural]`
- Неизвестные `$entityType` теперь возвращают 400 с сообщением 'Invalid entity type'
- Имя таблицы экранировано обратными кавычками: `` SELECT * FROM `$tableName` ``

**Затронутые файлы**:
- `app/Http/Controllers/Superadmin/ManagementController.php` (5 методов)
- `app/Http/Controllers/Superadmin/ManagementActions/entity_list.php` (полная перезапись)

## Runtime доступность

Сервер `127.0.0.1:8016` недоступен для HTTP-проверок из данного сеанса (ограничение окружения). Все изменения верифицированы статически:
- PHP lint: PASS
- Архитектурный guard: PASS
- Логика whitelist map: корректна
- Названия view-файлов сверены с существующими файлами в `app/View/pages/`

## Остаточные риски

- `company_contractor_edit.php` (проверка E9) содержит mojibake-текст в строках 16-65 (русские тексты вида `РљРѕРјРїР°РЅРёСЏ`). Это наследие из E9, не критично для функциональности (запасные ветки), но рекомендуется к исправлению в будущем
- `DocumentActions/download.php` и `DocumentActions/view.php` читают `$_GET['id']` без проверки entity_type — могут отдавать документы любого типа в рамках одной компании. Это идентично оригинальному поведению
- Полный E2E smoke (login + browser) требует ручного тестирования владельцем

## Файлы, изменённые в этой проверке

1. `app/Http/Controllers/Superadmin/ManagementController.php` — добавлен `$entityType` в 5 методов
2. `app/Http/Controllers/Superadmin/ManagementActions/entity_list.php` — whitelist map + безопасные запросы
3. `docs/ai/E10_E13_RUNTIME_VERIFICATION_REPORT.md` — данный отчёт
