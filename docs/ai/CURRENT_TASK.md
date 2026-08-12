# ERP PLANEX — текущая задача

Актуально на 2026-08-12.

## Статус

`FINANCE_MODERNIZATION_HANDOFF_READY`

Предыдущий цикл доработок линейных рейсов завершён и опубликован. Следующий рабочий цикл — модификация бухгалтерского/финансового блока ERP PLANEX новым агентом.

## Режим работы

Каноническая/default ветка: `chatgpt/production-stabilization-20260802`. Работа последовательная: один агент, текущий HEAD, один production path. Не создавать параллельные Pxx deploy-ветки/workflows.

Перед любым изменением новый агент обязан выполнить read-only ознакомление с системой и особенно finance. Основной специализированный документ: `docs/ai/FINANCE_HANDOFF.md`.

## Что сначала изучить

1. `README.md` и `AGENTS.md`.
2. `docs/ai/HANDOFF_FOR_NEW_AGENT.md`.
3. `docs/ai/PROJECT_STATE.md`.
4. `docs/ai/FINANCE_HANDOFF.md`.
5. `docs/ai/DECISIONS.md` и `DOCUMENTATION_INDEX.md`.
6. Фактический current code/schema finance: routes -> controllers/actions -> services -> views/assets -> migrations/tables -> tests.
7. Текущие production finance screens read-only.

Первый результат нового агента должен быть не кодом, а карта финансового блока `screen -> route -> controller/action -> service -> tables -> tests`, список найденных противоречий/рисков и подтверждение понимания deployment/database discipline.

## Finance invariants

- Finance доступен только `company_owner`; не расширять права без отдельного задания.
- Tenant isolation обязательна.
- Денежные значения хранятся как decimal money; форматирование не должно менять значение.
- Банковский XLSX import, matching/reconciliation, allocations/actions/history должны оставаться аудируемыми и fail-closed.
- Не менять production finance data ради тестов.
- Любое DDL — только migration; standard deploy migrations не запускает.
- При schema change после deploy нужен отдельный controlled migration/reconciliation + проверка tenant journals.
- При использовании нового/существующего service из web entrypoint проверять `app/Support/entrypoint_dependencies.php`; исторически отсутствие dependency уже давало live HTTP 500.

## Deployment

Нормальный путь:

`push canonical -> ERP PLANEX CI -> SUCCESS -> workflow_run -> ERPv2 Controlled Deploy`.

Deploy делает backup, собирает artifact строго из green SHA, запускает P07, проверяет marker, `/erpv2/login`, DB fingerprint, старый `/erp`, `.env` и runtime directories. Ручной exact-SHA `workflow_dispatch` остаётся fallback и требует `DEPLOY_ERPV2`.

Standard deploy НЕ запускает migrations.

Нельзя сообщать пользователю «готово» только после commit/CI. Для code/UI нужны green CI + successful deploy + focused runtime verification; для schema changes дополнительно controlled migration verification.

## Предыдущая функциональность, которую нельзя сломать

Линейные рейсы поддерживают multi-driver crews, единый блок `Загрузка / выгрузка`, комбинированные операции одной точки и табличный реестр. Legacy `/erp` запрещено менять. Persistent `storage/companies/{company_id}/...` нельзя очищать deploy-ом.

## Следующее действие

Новый агент начинает с read-only ознакомления с finance и ждёт конкретного задания пользователя на модификацию после отчёта о понимании системы.