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

### Что НЕ использовать

- **НЕ возвращать UI «Доступ логистов»** в карточку перевозчика — сценарий удалён.
- **НЕ развивать cascade sharing** — заменён на contractor assignment.
- **НЕ создавать новые сложные UI расшаривания** без прямого запроса владельца.

## Последние подтверждённые commits

```text
f993342 fix(access): enforce contractor assignment context visibility
c0cf919 feat(access): add contractor assignment management
d5a6ace feat(access): add contractor cascade sharing (заменён)
d3d3524 feat(access): add senior logist role visibility
888ba64 feat(master-flow): add contractor crew creation workflows
```

## Следующий блок

**BLOCK E** — стабилизация рабочих сценариев водитель + ТС + экипаж.

Они не являются текущей задачей, пока не закрыта форма редактирования водителя.
