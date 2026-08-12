# ERP PLANEX — documentation index

Актуально на 2026-08-12. Индекс отделяет current source of truth от reference и historical evidence.

## Приоритет

`CURRENT` -> текущая инструкция. `REFERENCE` -> полезная архитектура/спецификация, но уступает current code/schema и CURRENT docs. `HISTORICAL` -> evidence завершённого этапа; старые SHA/branches/blockers/workflows не являются текущим состоянием.

## CURRENT — обязательны

- `README.md` — входная точка и текущий control plane.
- `AGENTS.md` — операционный контракт.
- `docs/ai/AGENT_RULES.md` — дополнительные технические правила; при конфликте уступает `AGENTS.md` и current handoff.
- `docs/ai/HANDOFF_FOR_NEW_AGENT.md` — главный handoff.
- `docs/ai/HANDOFF_FOR_NEW_CHAT.md` — краткий handoff; при расхождении использовать более свежий `HANDOFF_FOR_NEW_AGENT.md`.
- `docs/ai/PROJECT_STATE.md` — текущее состояние/архитектура.
- `docs/ai/CURRENT_TASK.md` — текущий рабочий цикл.
- `docs/ai/FINANCE_HANDOFF.md` — обязательный focused handoff для текущей модификации бухгалтерского/финансового блока.
- `docs/ai/DECISIONS.md` — журнал решений.
- `docs/ai/DOCUMENTATION_INDEX.md` — этот индекс.
- `docs/ui/DESIGN_STANDARD.md` — текущий UI standard.

## REFERENCE

- `docs/ai/MASTER_FLOW_ARCHITECTURE.md`
- `docs/ai/MODULAR_DEVELOPMENT_RULES.md`
- `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md`
- `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`
- `docs/ai/erp-architect-updated.md`
- `docs/ai/vehicle_set_full_implementation_prompt_for_erp_coder.md`
- `docs/ai/vehicle_set_schema_for_codex.md`
- `docs/PROJECT_BRIEF_FOR_AUDIT.md`

## HISTORICAL

`CLEANUP_REPORT.md`, `README_UNPACK.md`, завершённые E7–P40 планы/отчёты, старые design-audit reports и compose plans являются historical evidence. Их не нужно переписывать задним числом: старые статусы/SHA/branches/migration names сохраняют доказательную ценность, но не переопределяют current docs.

К явно historical относятся существующие `ARCHITECTURE_*E7*`, `CLIENTS_MODULE_E8_*`, `CONTRACTORS_MODULE_E9_*`, `DEAD_CODE_*`, `E10_E13_*`, `E14_E15_*`, `FINAL_HANDOFF_PACKAGE_REPORT.md`, `REFACTOR_E7_REPORT.md`, `ROUTE_MAP_AFTER_E7.md`, `RUNTIME_SMOKE_CHECKLIST_E7_2.md`, `docs/compose/plans/*`, `docs/design-audit/full-ui-revision/*`, старые UI preparation reports/prompts.

## Правило для нового файла

Любой новый управляющий Markdown должен быть классифицирован здесь. При конфликте фактов приоритет: current code/schema -> README/AGENTS -> HANDOFF/PROJECT_STATE/CURRENT_TASK/FINANCE_HANDOFF -> reference -> historical.