# ERP PLANEX — текущая задача

Актуально на 2026-08-12.

## Статус

`STABILIZATION_AND_HANDOFF_FINALIZATION`

Параллельные агенты остановлены. Работа ведётся только от канонической/default ветки `chatgpt/production-stabilization-20260802`.

## Что уже нормализовано

- Последняя функциональная линия P39/P40 перенесена в каноническую ветку.
- Предыдущее состояние default branch сохранено в `backup/pre-stabilization-default-20260812`.
- Временные/автоматические P24–P40 production-deploy workflows удалены.
- Production control plane сокращён до одного ручного workflow: `.github/workflows/erpv2_controlled_deploy.yml`.
- Добавлен read-only CI `.github/workflows/ci.yml`.
- Локальные migration numbers нормализованы: crew drivers = `057`, linear route points = `058`.
- Добавлен безопасный migration journal reconciliation для старых имён `029`/`052`.
- README, AGENTS и основной handoff переведены на каноническое состояние.
- Последний подтверждённый CI до текущего documentation pass: run `31583989951`, все шаги PASS на SHA `0321efa32dfcd8908670f3f37e78dbd15f887628`.

## Что требуется завершить в текущем проходе

1. Актуализировать текущие управляющие MD и классифицировать все остальные MD как CURRENT или HISTORICAL в едином `DOCUMENTATION_INDEX.md`.
2. Не переписывать исторические evidence-отчёты задним числом; они должны оставаться доказательствами своих этапов.
3. Проверить четыре warning-кандидата `architecture_guard.php`; исправлять production code только если это реальный риск, а не безопасный whitelist/dynamic-table false positive.
4. После последнего documentation/code commit дождаться нового зелёного canonical CI.
5. Не выполнять production deploy только ради синхронизации SHA. Deployment допускается лишь через controlled workflow после явной необходимости; repository stabilization и production runtime — разные состояния.

## Gate завершения

Репозиторий можно передавать следующему агенту, когда текущие управляющие документы согласованы между собой, migration numbering уникальна, control-plane audit показывает ровно один deploy-capable workflow, canonical CI зелёный, а handoff содержит точный финальный HEAD и известные ограничения.
