# ERP PLANEX — текущая задача

Актуально на 2026-08-12.

## Статус

`REPOSITORY_STABILIZED_HANDOFF_READY`

Параллельные агенты остановлены. Каноническая/default ветка — `chatgpt/production-stabilization-20260802`. Репозиторий приведён к однопоточному безопасному режиму и готов к дальнейшей разработке от текущего HEAD.

## Что завершено

- Последняя функциональная линия P39/P40 сохранена в канонической ветке.
- Предыдущее состояние default branch сохранено в `backup/pre-stabilization-default-20260812` как recovery evidence.
- Временные/автоматические P24–P40 production-deploy/reconcile workflows удалены.
- В `.github/workflows` оставлены только `ci.yml` и единственный production-capable `erpv2_controlled_deploy.yml`.
- Production deploy разрешён только вручную через `workflow_dispatch`, exact SHA и `confirm_deploy=DEPLOY_ERPV2`.
- Local migration numbering нормализован: crew drivers = `057_create_crew_drivers.sql`, linear route points = `058_create_linear_route_points.sql`; старые journal names `029`/`052` обрабатываются отдельным reconciliation script.
- Управляющие MD приведены к каноническому состоянию; исторические отчёты классифицированы через `docs/ai/DOCUMENTATION_INDEX.md` и не считаются текущими инструкциями.
- Четыре исторических WARNING `architecture_guard.php` по Company dynamic table expressions проверены по data-flow. Это закрытые literal whitelist/static-map источники, а не request-derived identifiers.
- Дополнительно проверен пятый аналогичный Superadmin site `ManagementActions/entity_list.php`.
- Добавлен fail-closed `tools/dynamic_table_safety_audit.py`: он разрешает только пять проверенных dynamic-identifier sites, проверяет их закрытые maps и падает при появлении нового site или request-derived identifier.
- Canonical CI дополнен Python syntax + dynamic identifier safety audit.

## Последний подтверждённый gate

Финальный canonical CI run `31586894872` на SHA `99903d638b82b6cbb1c2706aae5630251f1df01e` — `SUCCESS`.

PASS:

- PHP syntax;
- Python syntax;
- migration numbering;
- migration normalization regression;
- dynamic identifier safety audit;
- architecture guard;
- JavaScript syntax;
- control-plane guard;
- canonical P21 control-plane policy.

На финальном gate central migrations = 11, local migrations = 57, duplicate numbers = 0. Control plane = 2 workflow files, из них deploy-capable = 1, automatic production deploy = 0.

## Production state

Последний подтверждённый production runtime до repository-normalization — линия P39 с migration-checksum reconciliation. Текущий canonical HEAD идёт впереди production в основном за счёт control-plane cleanup, документации, переименования migration files и reconciliation tooling. При сравнении с подтверждённым production baseline `844c15de2455d6f45f4d136a2fcbb80dad98e984` нет новых изменений пользовательского PHP/JS/CSS runtime; основная разница относится к GitHub Actions, документации, migration filenames и обслуживающим scripts/tools.

Новый `erpv2_controlled_deploy.yml` после нормализации ещё не запускался. Поэтому canonical HEAD нельзя объявлять deployed только по состоянию GitHub. Это намеренное fail-closed состояние: production не меняется ради формального совпадения SHA.

## Следующая задача

Стабилизационный проход закрыт. Следующая работа должна быть только новой явно поставленной функциональной/исправительной задачей. Перед изменениями агент читает `HANDOFF_FOR_NEW_AGENT.md`, проверяет текущий HEAD и сохраняет существующий single-deploy control plane.
