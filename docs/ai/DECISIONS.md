# ERP PLANEX — утверждённые решения

## Назначение

Короткий список актуальных решений.
Не хранить споры, черновики и длинные объяснения.

## Решения

1. Постоянная KILO-схема сокращена до двух агентов: `erp-architect` и `erp-coder`.
2. Главный дизайнер — отдельный ChatGPT-чат, не KILO-агент.
3. Постоянный QA-агент исключён; проверки встроены в обязанности архитектора и кодера.
4. `FINAL3.html` — главный визуальный источник дизайн-системы ERP.
5. Главный дизайнер подготовил базу дизайн-системы: `public/assets/css/erp-ui.css`, `docs/ui/DESIGN_STANDARD.md`, `docs/ui/DESIGN_SYSTEM_PREP_REPORT.md`.
6. Кодер обязан работать циклом: функционал → тесты → применение `erp-ui.css` → повторная проверка.
7. Кодер обязан думать о пользовательской структуре: сценарий, понятные действия, ошибки, пустые состояния, подтверждения опасных действий.
8. После утверждения UI дизайнером кодер не имеет права переписывать его целиком при функциональных доработках.
9. Документация сокращается до минимального набора рабочих MD.
10. Новые управляющие MD создаются только по решению владельца + ChatGPT.
11. Поле `position` (должность руководителя) хранится в `company_users` центральной БД, не в `companies`.
12. Роль пользователя в локальной БД (`users.role_code`) выбирается из выпадающего списка, а не захардкожена.
13. Маршруты `/company/logists` и `/superadmin/.../logists/...` НЕ переименовываются; меняются только UI-лейблы (логист → пользователь).
14. Кодер не получает задачи напрямую от владельца/ChatGPT, кроме аварийных случаев. Основной поток: erp-architect → erp-coder → erp-architect (приёмка).
15. `company_owner` продолжает храниться в центральной БД (`company_users`) отдельно от локальной; локальная `users` пока содержит только `logist`.
16. Руководитель в карточке компании — это реквизитные данные компании для документов, счетов, договоров и актов. Хранится в таблице `companies` (поля `director_position`, `director_full_name`). Это НЕ ERP-пользователь. Это НЕ company_owner. Автоматическое создание company_owner при создании/редактировании экспедитора запрещено. ERP-доступ руководителя создаётся отдельно через `/superadmin/companies/{id}/create-owner`.
17. Поля `contact_person`, `contact_phone`, `contact_email` остаются в таблице `companies`, но убраны из форм создания/редактирования экспедитора и не используются в UI.
18. Архитектура блока «Водители / Машины / Экипажи»: Подрядчик + (Водитель + ТС) = Экипаж. driver_vehicle_blocks = Водитель + vehicle_set (ТС). crews = contractor_id + driver_vehicle_block_id.
19. Таблица `vehicles` переименована в `vehicle_units`. URL `/company/vehicles` сохранён. UI-лейблы: «Транспортные единицы». entity_type: `vehicle_unit`.
20. `inn` в `contractors` — НЕ unique, обычный индекс `idx_inn`. `plate_number` в `vehicle_units` — НЕ unique, обычный индекс `idx_plate`.
21. Множественные контакты подрядчика вынесены в `contractor_contacts`. История налогообложения — в `contractor_tax_history`.
22. Телефоны водителей вынесены в `driver_phones`. Старый `drivers.phone` сохранён, data-миграция копирует в driver_phones.
23. `crews` перестроены: contractor_id + driver_vehicle_block_id. Автоматическая миграция при пустой таблице, блокировка при наличии старых записей.
24. Документы: soft delete через `deleted_at`. Активный документ: `deleted_at IS NULL`. `documents.status` оставлен как legacy.
25. `entity_access_grants` расширены: comment, revoked_at, idx_granted_user. Допустимые entity types: client, contractor, driver, vehicle_unit, vehicle_set, driver_vehicle_block, crew.
26. Миграции локальной БД (011-023) идемпотентны: проверяют INFORMATION_SCHEMA перед каждым изменением. Функция `applyLocalMigrations()` применяет их автоматически при доступе к локальной БД.
27. `applyLocalMigrations()` расширен до диапазона 001-030. Миграции 001-010 (CREATE TABLE) используют `IF NOT EXISTS` или PREPARE/EXECUTE с проверкой INFORMATION_SCHEMA. Миграция 008 (ALTER TABLE) переписана на идемпотентный PREPARE/EXECUTE-паттерн.
28. PDO-подключения используют `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true` для предотвращения ошибки "Cannot execute queries while other unbuffered queries are active" при последовательных запросах после миграций с SELECT.
29. Создание локальной БД (`CREATE DATABASE IF NOT EXISTS`) выполняется автоматически перед первым подключением к `erp_company_{id}` во всех обработчиках `/company/logists/*` и SUPERADMIN-создании пользователя.
