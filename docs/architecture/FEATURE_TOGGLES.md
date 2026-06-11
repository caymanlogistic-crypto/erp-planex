# ERP PLANEX — FEATURE_TOGGLES

## Назначение

Feature toggles нужны, чтобы в общем коде включать или скрывать функционал для конкретных компаний.

Пример:
- Компания А заказала нестандартный отчёт.
- Код отчёта есть в общем коде.
- SUPERADMIN включает отчёт только для Компании А.
- Остальные компании отчёт не видят.

## Источник истины

Источник истины — центральная БД SUPERADMIN.

Локальная ERP использует локальный кэш доступных features.

## Типы features

```text
module
page
report
action
integration
ui_block
```

## Правило

Любой новый модуль, страница, отчёт или интеграция должны иметь `feature_code`.

Запрещено добавлять новую страницу без feature_code.

## Предварительные таблицы SUPERADMIN

```text
features
  id
  code
  name
  type
  description
  is_active

companies
  id
  key
  name
  folder_path
  db_name
  is_active

company_features
  id
  company_id
  feature_code
  is_enabled
  enabled_from
  enabled_until
```

## Локальный кэш

В локальной ERP можно предусмотреть таблицу:

```text
local_feature_cache
  id
  feature_code
  is_enabled
  synced_at
```

## Цепочка проверки доступа

```text
1. feature доступна компании?
2. permission доступна роли?
3. разрешено конкретное действие?
4. показать страницу / выполнить действие
```
