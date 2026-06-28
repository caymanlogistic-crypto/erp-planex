# RUNTIME_SMOKE_CHECKLIST_E7_2

Статус: **PASS** (2026-06-28)

## Auth

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /test` | 200 | 200 |
| `POST /login` (superadmin) | 302 -> /superadmin/companies | OK |
| `POST /login` (owner) | 302 -> /company/dashboard | OK |
| `POST /login` (senior_logist) | 302 -> /company/dashboard | OK |
| `POST /login` (logist) | 302 -> /company/dashboard | OK |

## Superadmin

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /superadmin/companies` | 200 | 200 |

## Company Owner

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /company/dashboard` | 200 | 200 |
| `GET /company/route-executors` | 200 | 200 |
| `GET /company/responsible-assignments` | 200 | 200 |
| `GET /company/contractors` | 200 (BLOCKER 1 fix) | 200 |
| `GET /company/contractors/create` | 200 | 200 |
| `GET /company/clients` | 200 (BLOCKER 2 fix) | 200 |

## Senior Logist

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /company/route-executors` | 200 | 200 |
| `GET /company/responsible-assignments` | 403 | 403 |

## Logist

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /company/route-executors` | 200 | 200 |
| `GET /company/responsible-assignments` | 403 | 403 |

## Legacy Redirects

| Endpoint | Ожидание | Результат |
|----------|----------|-----------|
| `GET /company/crews` | 302 -> /company/route-executors | 302 |
| `GET /company/crews/create` | 302 -> /company/route-executors/create | 302 |
| `GET /company/driver-vehicle-blocks` | 302 -> /company/route-executors | 302 |
| `GET /company/driver-vehicle-blocks/create` | 302 -> /company/route-executors/create | 302 |
| `GET /company/contractor-assignments` | 302 -> /company/responsible-assignments | 302 |

## BLOCKER Checks

| Проверка | Результат |
|----------|-----------|
| `/company/contractors` — Fatal error `ContractorContactService not found` | **FIXED** |
| `/company/contractors/create` — Fatal error | **FIXED** |
| Popup "Создать клиента" — кириллица | **FIXED** (legal_entity_create_form.php перезаписан в UTF-8) |
| `GET /company/clients` — 200 | OK |

## php -l

| Check | Результат |
|-------|-----------|
| `public/index.php` | PASS |
| `bootstrap/app.php` | PASS |
| `app/Http/Routes/core.php` | PASS |
| `for /r app *.php` | PASS (0 errors) |

## Architecture Guard

| Check | Результат |
|-------|-----------|
| `php tools/architecture_guard.php` | PASS (0 errors) |

## Browser Console

| Check | Результат |
|-------|-----------|
| Красные ошибки в console | Не проверено (нет browser automation) |

## Итог

**Все runtime-проверки пройдены.** Fatal error на `/company/contractors` исправлен. Кодировка клиентского popup исправлена.
