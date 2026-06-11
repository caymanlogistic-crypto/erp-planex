# ERP PLANEX — MODULE_PATTERN

## Назначение

Этот файл задаёт единый паттерн добавления модулей.

ERP PLANEX развивается постранично, поэтому каждый новый модуль должен добавляться одинаково.

## Каждый модуль обязан иметь

```text
feature_code
permission_code
route
controller
service
repository/model
view
menu item
logs
migrations
checks
documentation
```

## Пример структуры модуля

```text
app/
  Modules/
    Clients/
      Controllers/
      Services/
      Repositories/
      Views/
      routes.php
      permissions.php
      module.md
```

Фактическая структура может быть уточнена техническим архитектором, но принцип должен сохраниться.

## Запрещено

- Делать отдельные страницы без feature_code.
- Делать действия без permission_code.
- Писать SQL прямо во view.
- Смешивать HTML и бизнес-логику.
- Добавлять модуль без обновления MD.
