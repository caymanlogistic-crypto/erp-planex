# ERP PLANEX — HANDOFF_FOR_NEW_CHAT

## Главное для нового ChatGPT-чата

Прочитай этот файл первым. Он является главным переносимым контекстом текущей работы ERP PLANEX.

Рабочая папка проекта:

```text
C:\Users\Vladimir\Desktop\PLANEX\SITE\erp
```

Разработка ведётся через KILO-агентов:

```text
Владелец + ChatGPT → erp-architect → erp-coder → erp-architect acceptance → владелец + ChatGPT
```

Постоянные KILO-агенты только:

```text
.kilo/agents/erp-architect.md
.kilo/agents/erp-coder.md
```

Главный дизайнер / CODEX-дизайнер — внешние чаты/инструменты, не постоянные KILO-агенты.

## PROTECTED WORKING CORE

4 страницы объявлены защищённым рабочим ядром. Их нельзя менять без отдельной явной задачи:

```text
/company/drivers
/company/vehicle-sets
/company/clients
/company/contractors
```

Полный список защищённых файлов (routes, views, partials, JS, CSS, сервисы, таблицы) и архитектурный план безопасного рефакторинга:

```text
docs/ai/PROTECTED_ARCHITECTURE_PLAN.md
```

Любой будущий рефакторинг должен начинаться с MD/карт/правил, а не с переноса кода.

## FOUNDATION SERVICES (не подключены к ядру)

Созданы архитектурные сервисы для будущих модулей:

```text
app/Service/AccessControlService.php  — единая проверка прав (роли, ownership, grants)
app/Service/DocumentService.php       — единый сервис документов (upload, replace, пути, бейджи)
```

Оба сервиса НЕ подключены к защищённому ядру.
Новые модули (driver_vehicle_blocks, crews) должны использовать эти сервисы с момента создания.

Подробнее: `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md`

## MASTER-FLOW (РЕАЛИЗОВАН)

Реализованы два сценария master-flow:

```text
docs/ai/MASTER_FLOW_ARCHITECTURE.md
```

### Сценарий B: «Создать перевозчика с экипажем» (create-full)
- Маршрут: GET/POST `/company/contractors/create-full`
- 4-шаговый stepper: Перевозчик → Водитель → Машина → Проверка
- Транзакционное создание: contractor + driver + vehicle_set + driver_vehicle_block + crew
- Проверка дублей перед INSERT
- Success-экран показывает все 5 сущностей со ссылками

### Сценарий A: «Добавить экипаж перевозчику» (из карточки)
- Маршрут: GET/POST `/company/contractors/{id}/add-crew`
- 3-шаговый stepper: Водитель → Машина → Проверка
- Кнопка «+ Водитель + Машина» в карточке перевозчика
- Перевозчик уже выбран (из URL), пользователь НЕ выбирает его заново
- Транзакционное создание: driver + vehicle_set + driver_vehicle_block + crew
- Проверка дублей перед INSERT
- Success-экран показывает все созданные сущности со ссылками

## Стиль взаимодействия с владельцем

- Отвечать коротко и по делу.
- Не писать промты агентам без прямой просьбы владельца: «пиши промт», «напиши промт», «дай промт».
- Если не хватает данных — запросить реальные файлы/архив, не фантазировать.
- Если нужны файлы — сразу дать CMD/PowerShell-команду в одну строку для архива.
- Не принимать отчёт агента `DONE`, если нет фактической проверки.
- UI оценивать только как соответствует / не соответствует / частично соответствует утверждённому стандарту, без вкусовщины.

## Текущий активный фокус

Актуальная модель доступа ERP PLANEX (после блоков D/D2/D5/D6):

### Модель ролей

| Роль | role_code | Видит | Управляет пользователями | Управляет привязкой |
|------|-----------|-------|------------------------|-------------------|
| superadmin | `superadmin` | Все компании | Да | Нет (системный уровень) |
| company_owner | `company_owner` | Все данные компании | Да | Да |
| senior_logist | `senior_logist` | Все данные компании | Нет | Нет |
| logist | `logist` | Своё + доступное | Нет | Нет |

### Привязка перевозчиков (основной сценарий)

Страница: `/company/contractor-assignments`
Меню: «Привязка перевозчиков» (только `company_owner`)
Назначение: сменить логиста-владельца перевозчика.

При перепривязке:
- `created_by_user_id` / `created_by_role` меняются на нового логиста;
- переносится ВЕСЬ контекст: contractor → crews → driver_vehicle_blocks → drivers → vehicle_sets;
- история записывается в `contractor_assignment_history`;
- активные grants на contractor и cascade grants отзываются.

### Ограничения для обычного logist

Обычный logist видит только свои доступные данные. При создании driver_vehicle_block и crew:
- выпадающие списки фильтруются по `created_by_user_id` + grants;
- backend-валидация запрещает чужие `driver_id` / `vehicle_set_id` / `contractor_id` / `driver_vehicle_block_id`.

### Будущая модель: Исполнитель рейса (план)

Целевая пользовательская сущность: **Исполнитель рейса** = Подрядчик + Водитель + ТС.

Вместо двух пунктов меню («Водители+ТС», «Экипажи») пользователь работает с одной сущностью. Технические таблицы `driver_vehicle_blocks` и `crews` остаются как внутренний слой.

При создании исполнителя рейса:
- Выпадающие списки contractor/driver/vehicle_set фильтруются по доступности (как сейчас для crews).
- Backend создаёт driver_vehicle_block + crew в одной транзакции.
- Обычный logist не может выбрать чужие contractor/driver/vehicle_set.

Переназначение ответственного логиста:
- Отдельная страница с вкладками: Исполнители рейса / Подрядчики / Водители / ТС.
- Для contractor — каскадный перенос (как сейчас).
- Для driver/vehicle_set/crew — точечный перенос с опциональным каскадом.

Подробнее: `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`.

### Что НЕ использовать

- **НЕ возвращать UI «Доступ логистов»** в карточку перевозчика — сценарий удалён.
- **НЕ развивать cascade sharing** — заменён на contractor assignment.
- **НЕ создавать новые сложные UI расшаривания** без прямого запроса владельца.
- **НЕ вводить «Водители+ТС» и «Экипажи»** как основные пользовательские пункты — заменены на «Исполнители рейса».

## Последние подтверждённые commits

```text
3e5405c docs(access): document contractor assignment model
f993342 fix(access): enforce contractor assignment context visibility
c0cf919 feat(access): add contractor assignment management
d5a6ace feat(access): add contractor cascade sharing (заменён)
d3d3524 feat(access): add senior logist role visibility
888ba64 feat(master-flow): add contractor crew creation workflows
```

## BLOCK E3 — Исполнитель рейса: полный CRUD (ТЕКУЩИЙ ЭТАП — ПРИНЯТ)

**Статус**: E3 выполнен и принят. Commit `1488d55`.

Реализован полный пользовательский модуль «Исполнитель рейса»:

### Новые routes

```
GET  /company/route-executors
GET  /company/route-executors/create
POST /company/route-executors/create
GET  /company/route-executors/{id}
GET  /company/route-executors/{id}/edit
POST /company/route-executors/{id}/edit
POST /company/route-executors/{id}/archive
```

### Новые views

```
app/View/pages/company_route_executors.php         (список)
app/View/pages/company_route_executors_create.php  (создание)
app/View/pages/company_route_executor_view.php     (просмотр)
app/View/pages/company_route_executor_edit.php     (редактирование)
```

### Логика создания

- Пользователь выбирает: Подрядчик + Водитель + ТС (3 отдельных выпадающих списка).
- Backend находит или создаёт `driver_vehicle_block` для driver_id + vehicle_set_id.
- Backend создаёт `crew` для contractor_id + driver_vehicle_block_id.
- Всё в одной транзакции.
- Проверка дублей через UNIQUE constraint на crews.

### Логика редактирования

- При изменении водителя/ТС backend находит/создаёт новый driver_vehicle_block и обновляет crew.
- При изменении подрядчика обновляется crew.contractor_id.
- Проверка дублей исключая текущий crew.

### Защита доступа (logist)

- Dropdown фильтруются: только свои + grants (contractor/driver/vehicle_set).
- POST backend проверяет created_by_user_id + entity_access_grants для каждого ID.
- Чужие ID отклоняются с понятной ошибкой.

### Что НЕ изменилось

- Таблицы `driver_vehicle_blocks` и `crews` не менялись.
- Старые routes `/company/crews/*`, `/company/driver-vehicle-blocks/*`, `/company/contractor-assignments/*` сохранены и работают.
- Protected core (`/company/drivers`, `/company/vehicle-sets`, `/company/clients`, `/company/contractors`) не тронут.
- Документы в форму Исполнителя рейса не добавлены.

**Следующий блок**: **E4** — Переназначение ответственных логистов (вкладки: Исполнители рейса / Подрядчики / Водители / ТС).
