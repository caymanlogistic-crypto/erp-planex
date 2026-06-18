# FULL UI REVISION — ERP PLANEX

## Цель пакета

Полный материал для дизайн-аудитора: карта всех страниц, меню, функции, роли доступа, валидные FULL HD full-page screenshots всех страниц.

## Дата подготовки

2026-06-17

## Commit / Branch

- Commit: 1e85dc7 (style(reference): address company reference UX audit)
- Branch: master

## Путь к screenshots

```
docs/design-audit/full-ui-revision/screenshots/
```

79 PNG screenshots, все 1920x1080+, fullPage, CSS загружен.

## Какие роли покрыты

| Роль | Логин | Пароль | Компания |
|---|---|---|---|
| superadmin | admin@planex.local | admin123 | все |
| company_owner | owner_test_runtime | pass1234 | ООО "Тест Этап 2 Runtime" (ID=9) |
| logist (владелец записей) | logist_runtime_1 | pass1111 | 9 |
| logist (grant-доступ) | logist_runtime_2 | pass2222 | 9 |

## Какие документы читать проверяющему

1. **PAGE_INVENTORY.md** — карта всех страниц с URL, меню, ролью, view-файлом, функцией, действиями, состояниями и screenshot-файлами.
2. **UI_ARCHITECTURE_MAP.md** — архитектура интерфейса: меню, зоны, связи сущностей, role-based visibility, каскадная видимость, документы, доступы.
3. **SCREENSHOT_MANIFEST.md** — связь каждого screenshot со страницей, размер, URL, роль, состояние, валидность.
4. **RUNTIME_ACCESS_NOTES.md** — тестовые доступы, runtime-сущности, ID, гранты, проверенные сценарии.

## Как запускать ERP

```bat
cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp
php -S 127.0.0.1:8016 -t public public/index.php
```

Базовый URL: http://127.0.0.1:8016

## Как логиниться

1. Открыть http://127.0.0.1:8016/login
2. Использовать учётные данные из таблицы выше
3. Каждая роль показывает разный интерфейс и разный набор страниц

## Основные файлы пакета

```
docs/design-audit/full-ui-revision/
├── README.md                    ← этот файл
├── PAGE_INVENTORY.md            ← карта всех страниц
├── UI_ARCHITECTURE_MAP.md       ← архитектура интерфейса
├── SCREENSHOT_MANIFEST.md       ← манифест screenshots
├── RUNTIME_ACCESS_NOTES.md      ← заметки о доступах и runtime
└── screenshots/                 ← 79 FULL HD full-page screenshots
```

## Что этот пакет НЕ делает

- НЕ оценивает дизайн
- НЕ исправляет UI
- НЕ меняет функционал
- НЕ меняет бизнес-логику
- НЕ меняет маршруты
- НЕ меняет права доступа

## Статус

DESIGN_AUDIT_PACKAGE_READY

Все screenshots валидны: FULL HD (1920x1080+), fullPage, CSS загружен, контент виден.
