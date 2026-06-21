# ERP PLANEX — текущая задача

## STATUS: CODE_CHANGED_NEEDS_RUNTIME_AND_OWNER_CHECK

Форма создания полной связки и форма создания связки Водитель+Машина переведены на stepper FINAL3.
Логист получил доступ к `/company/contractors/create-full`.

## Последний commit (ещё не сделан)

```text
Рабочая директория изменена. Ожидает commit после полной проверки владельцем.
```

## Что сделано

### Этап 2 — Права доступа
- `GET/POST /company/contractors/create-full`: `requireRole(['company_owner'])` → `requireRole(['company_owner', 'logist'])`

### Этап 3 — Stepper для DVB create
- `company_driver_vehicle_blocks.php`: текст кнопки «Создать связку» → «Создать связку Водитель + Машина»
- `company_driver_vehicle_blocks_create.php`: полная переработка на 3-шаговый stepper (Водитель → Машина/Транспорт → Проверка)

### Этап 4 — Stepper для create-full
- `company_contractors_create_full.php`: полная переработка на 4-шаговый stepper (Перевозчик → Водитель → Машина/Транспорт → Проверка)
- `public/index.php` (POST create-full): расширен backend — поддержка выбора существующих ИЛИ создания новых сущностей на каждом шаге
- `public/index.php` (GET create-full): добавлена загрузка списков contractors/drivers/vehicleSets

### Этап 5 — JS/CSS cleanup
- Удалены все `alert()` из JS валидации в обоих stepper'ах
- Валидация через inline-сообщения `.field-msg.is-error`
- Очистка ошибок при выборе/вводе

## Файлы изменены

| Файл | Изменения |
|---|---|
| `public/index.php` | requireRole fix, GET entity lists, POST validation + creation modes |
| `app/View/pages/company_contractors_create_full.php` | 249 → ~800 строк, 4-шаговый stepper |
| `app/View/pages/company_driver_vehicle_blocks.php` | Текст кнопки (2 строки) |
| `app/View/pages/company_driver_vehicle_blocks_create.php` | 144 → ~350 строк, 3-шаговый stepper |

## Что проверено

- `php -l` для всех 4 файлов — PASS
- `git diff --check` — PASS (только CRLF-предупреждения Windows)
- Отсутствие `alert()` во view-файлах — PASS
- Отсутствие inline-style кроме `display:none` — PASS
- `.is-hidden` существует в erp-ui.css — PASS
- Backend: duplicate check для driver_vehicle_block — сохранён
- Backend: duplicate check для crew — добавлен
- Backend: created_by_user_id / created_by_role — проставляются

## Что не закрыто этим этапом

- Ownership-фильтрация dropdown-списков для логиста (логист видит все сущности в select'ах — существующий паттерн, требует отдельного решения)
- Inline-создание водителя/транспорта внутри `/company/driver-vehicle-blocks/create` (backend пока только select)
- Визуальное выделение активного режима для `.btn-ghost.is-active` (DESIGN_TODO для дизайнера)
