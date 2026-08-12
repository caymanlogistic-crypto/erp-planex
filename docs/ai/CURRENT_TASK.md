# ERP PLANEX — текущая задача

Актуально на 2026-08-12.

## Статус

`REPOSITORY_STABILIZED_AUTO_DEPLOY_ACTIVE`

Параллельные агенты остановлены. Каноническая/default ветка — `chatgpt/production-stabilization-20260802`. Репозиторий работает в однопоточном режиме: один агент вносит изменения в каноническую ветку, после каждого push сначала проходит canonical CI, и только его успешное завершение запускает единственный production deploy workflow.

## Что завершено

- Последняя функциональная линия P39/P40 сохранена в канонической ветке.
- Предыдущее состояние default branch сохранено в `backup/pre-stabilization-default-20260812` как recovery evidence.
- Временные P24–P40 production-deploy/reconcile workflows удалены.
- В `.github/workflows` оставлены только `ci.yml` и единственный production-capable `erpv2_controlled_deploy.yml`.
- Автодеплой восстановлен безопасно: `push` в canonical branch -> `ERP PLANEX CI` -> только при `SUCCESS` и только если исходный event был `push` запускается `ERPv2 Controlled Deploy` через `workflow_run`.
- Ручной exact-SHA deploy через `workflow_dispatch` сохранён как аварийный/операционный fallback и по-прежнему требует `confirm_deploy=DEPLOY_ERPV2`.
- Automatic deploy использует точный `head_sha` зелёного CI, detached checkout, artifact SHA256, server backup, P07 deploy engine, marker equality, HTTP `/erpv2/login`=200, DB fingerprint guard, old `/erp` fingerprint guard, `.env` fingerprint guard и сохранение runtime directories.
- Standard deploy не запускает миграции автоматически. Изменения схемы требуют отдельной явной миграционной операции.
- Local migration numbering нормализован: crew drivers = `057_create_crew_drivers.sql`, linear route points = `058_create_linear_route_points.sql`; старые journal names `029`/`052` обрабатываются отдельным reconciliation script.
- Управляющие MD приведены к каноническому состоянию; исторические отчёты классифицированы через `docs/ai/DOCUMENTATION_INDEX.md` и не считаются текущими инструкциями.
- Четыре исторических WARNING `architecture_guard.php` по Company dynamic table expressions проверены по data-flow. Это закрытые literal whitelist/static-map источники, а не request-derived identifiers.
- Дополнительно проверен пятый аналогичный Superadmin site `ManagementActions/entity_list.php`.
- Добавлен fail-closed `tools/dynamic_table_safety_audit.py`: он разрешает только пять проверенных dynamic-identifier sites, проверяет их закрытые maps и падает при появлении нового site или request-derived identifier.
- Canonical CI дополнен Python syntax + dynamic identifier safety audit.

## Последний подтверждённый gate и deploy

Canonical CI run `31595645852` на SHA `e387b2d206c84295392ec13c6630a11acf9502e5` — `SUCCESS`.

После него автоматически запустился `ERPv2 Controlled Deploy` run `31595690419` / job `94110512307` — `SUCCESS`.

Подтверждено:

- exact source SHA = `e387b2d206c84295392ec13c6630a11acf9502e5`;
- deploy mode = `automatic_after_green_ci`;
- backup создан до deploy;
- artifact собран строго из source SHA;
- P07 deploy engine завершился успешно;
- deployed marker равен source SHA;
- `/erpv2/login` = HTTP 200;
- DB fingerprint до/после одинаковый;
- старый `/erp` до/после одинаковый;
- `.env` до/после одинаковый;
- runtime directories сохранены;
- migrations_run = false.

## Production state

Production теперь синхронизируется с каждым успешным push в canonical branch автоматически, но только после зелёного canonical CI. Если CI падает, deploy не запускается. Если deploy/post-deploy guard падает, run становится красным и нельзя считать соответствующий SHA production-ready.

Последняя пользовательская доработка перед включением автодеплоя: в таблице `Банк -> Банковские счета` добавлен отдельный столбец `ИНН` сразу после `Контрагент`; значение берётся из существующего `counterparty_inn`, без изменения БД/импорта.

## Следующая задача

Продолжать разработку только в `chatgpt/production-stabilization-20260802`. Не создавать дополнительные deploy workflows и параллельные deployment branches. После каждого изменения агент обязан дождаться сначала зелёного CI, затем успешного auto-deploy и post-deploy verification, прежде чем писать пользователю, что изменение готово к тестированию.
