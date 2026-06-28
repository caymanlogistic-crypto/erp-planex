# MODULAR_DEVELOPMENT_RULES

Статус: **ACTIVE** с E14-E15 (2026-06-28)

## Жёсткие правила дальнейшей разработки

### 1. Route -> Controller -> Service -> View

```
Route  →  Controller  →  Service  →  View
         (HTTP/request)   (бизнес-   (только
          сессия/view)    логика,     отображение)
                          SQL)
```

- **Route-файл** — только регистрирует маршруты. Никакой бизнес-логики, SQL, валидации.
- **Controller** — управляет HTTP-запросом, сессией, вызывает Service, выбирает View.
- **Service** — содержит бизнес-логику, SQL-запросы, валидацию данных.
- **Support** — helpers, runtime, общие утилиты.
- **View** — только отображение данных. Без логики, без SQL.

### 2. Запрет на SQL в route-файлах

**Новый SQL-запрос в route-файле запрещён.** Весь SQL — только в Service или Controller.

### 3. Лимиты размеров

| Файл | Макс. строк | Примечание |
|------|-------------|------------|
| `auth.php` | 80 | Только route registration |
| `company_route_executors.php` | 80 | Только route registration |
| `company_responsible_assignments.php` | 80 | Только route registration |
| `superadmin.php` | 120 | Только route registration |
| `legacy_redirects.php` | 120 | Только route registration |
| Новый route-файл | 150 | Абсолютный лимит |
| Controller | 300 | Требует service/action split |
| `public/index.php` | 150 | Только bootstrap + route loading |

### 4. Action include-файлы

Action include-файлы (`app/Http/Controllers/*/Actions/*.php`) допустимы **как принятый transitional pattern** (E14-E15 verdict). Все контроллеры используют единый паттерн делегирования action-файлам.

При рефакторинге (только по отдельной задаче):
1. Перенести логику в Service.
2. Перенести HTTP-логику в Controller method.
3. Удалить action include.

### 4a. Inline closure route-файлы

3 route-файла используют inline closures с полной бизнес-логикой:
- `company_dashboard.php` — dashboard + access grants
- `company_logists.php` — logist CRUD (960 строк)
- `superadmin_company_delete.php` — company delete flow (445 строк)

Эти файлы — refactoring-кандидаты. Изменения в них допустимы только точечные (баг-фикс). Полный рефакторинг — по отдельной задаче.

### 5. Legacy route-файлы

Старые route-файлы (`company_contractors.php`, `company_clients.php`, `company_drivers.php`, `company_vehicle_sets.php`, `company_documents.php`, `superadmin_management.php`) **содержат legacy-процедурную логику**.

При необходимости изменения:
1. Не расширять legacy-логику — выносить в Service/Controller.
2. Не добавлять новый SQL — выносить в Service.
3. Фиксировать баги точечно, не проводить рефакторинг заодно.

### 6. Новые сущности

Все новые сущности реализуются по полной схеме:
- Route (регистрация маршрутов)
- Controller (HTTP-handling)
- Service (бизнес-логика)
- View (отображение)

### 7. Запреты

- Запрещён новый route-файл > 150 строк.
- Запрещён controller > 300 строк без service/action split.
- Запрещён SQL в route-файлах.
- Запрещено менять `public/index.php` сверх загрузки bootstrap + route registration.
- Запрещено менять визуал/menu/CSS/	topbar без задачи дизайнера.
