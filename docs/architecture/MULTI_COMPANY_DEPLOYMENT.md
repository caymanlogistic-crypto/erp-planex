# ERP PLANEX — MULTI_COMPANY_DEPLOYMENT

## Главный принцип

Код общий, но каждая компания разворачивается в отдельной папке и использует отдельную БД.

```text
erp/
  superadmin/
  planex/
  company_a/
  company_b/
```

## Одна локальная ERP = одно юридическое лицо

Внутри одной локальной ERP не храним несколько рабочих юридических лиц.

## Пример

```text
erp/planex/
  app/
  public/
  storage/
  docs/
  .env

DB: erp_planex
```

```text
erp/company_a/
  app/
  public/
  storage/
  docs/
  .env

DB: erp_company_a
```

## SUPERADMIN

Путь:

```text
erp/superadmin/
```

SUPERADMIN имеет отдельную центральную БД.

## Feature-доступы

Источник истины по доступности функций — центральная БД SUPERADMIN.

Локальная ERP должна иметь локальный кэш доступных features, чтобы продолжать работать при временной недоступности SUPERADMIN.

## Правило безопасности

Локальная ERP не должна иметь прямого доступа к данным другой локальной ERP.

SUPERADMIN может иметь максимальный доступ, но его доступ должен быть явно отделён от локальных ролей.
