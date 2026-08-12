# ERP PLANEX — documentation index

Актуально на 2026-08-12. Этот индекс определяет статус Markdown-документов и предотвращает использование исторических планов/отчётов как текущих инструкций.

## Правило приоритета

`CURRENT` → текущий source of truth. `REFERENCE` → полезная спецификация/архитектурный материал, но при конфликте уступает CURRENT docs и текущему коду. `HISTORICAL` → evidence конкретного завершённого этапа; старые SHA, branch names, blockers и workflow из него не являются текущим состоянием.

## CURRENT — обязательны для нового агента

- `README.md` — входная точка, branch/control-plane/safety.
- `AGENTS.md` — операционный контракт агента.
- `docs/ai/AGENT_RULES.md` — технические правила работы.
- `docs/ai/HANDOFF_FOR_NEW_AGENT.md` — главный handoff.
- `docs/ai/HANDOFF_FOR_NEW_CHAT.md` — краткий handoff для нового чата/агента.
- `docs/ai/PROJECT_STATE.md` — текущая архитектура и состояние.
- `docs/ai/CURRENT_TASK.md` — текущий stabilization gate/следующая задача.
- `docs/ai/DECISIONS.md` — журнал принятых архитектурных решений; читать как историю решений, а текущие control-plane решения брать также из handoff.
- `docs/ai/DOCUMENTATION_INDEX.md` — этот индекс.
- `docs/ui/DESIGN_STANDARD.md` — текущий текстовый UI standard вместе с ERP PLANEX master-reference.

## REFERENCE — действующие архитектурные/предметные материалы

- `docs/ai/MASTER_FLOW_ARCHITECTURE.md`
- `docs/ai/MODULAR_DEVELOPMENT_RULES.md`
- `docs/ai/PROTECTED_ARCHITECTURE_PLAN.md`
- `docs/ai/ROUTE_EXECUTOR_ARCHITECTURE_PLAN.md`
- `docs/ai/erp-architect-updated.md`
- `docs/ai/vehicle_set_full_implementation_prompt_for_erp_coder.md`
- `docs/ai/vehicle_set_schema_for_codex.md`
- `docs/PROJECT_BRIEF_FOR_AUDIT.md`

Эти файлы содержат полезные ограничения и историю проектирования, но могут упоминать старый workflow разработки. Текущие branch/deploy/CI/migration facts брать из CURRENT docs.

## HISTORICAL — завершённые этапы разработки/аудита

- `CLEANUP_REPORT.md`
- `README_UNPACK.md`
- `docs/ai/ARCHITECTURE_FOUNDATION_STAGE_B.md`
- `docs/ai/ARCHITECTURE_STABILIZATION_E7_1_PLAN.md`
- `docs/ai/ARCHITECTURE_STABILIZED_AFTER_E7_1.md`
- `docs/ai/CLIENTS_MODULE_E8_PLAN.md`
- `docs/ai/CLIENTS_MODULE_E8_REPORT.md`
- `docs/ai/CONTRACTORS_MODULE_E9_PLAN.md`
- `docs/ai/CONTRACTORS_MODULE_E9_REPORT.md`
- `docs/ai/DEAD_CODE_CANDIDATES_E7.md`
- `docs/ai/DEAD_CODE_REMOVED_E7.md`
- `docs/ai/E10_E13_BATCH_REFACTOR_REPORT.md`
- `docs/ai/E10_E13_RUNTIME_VERIFICATION_REPORT.md`
- `docs/ai/E14_E15_FINAL_ARCHITECTURE_REPORT.md`
- `docs/ai/FINAL_HANDOFF_PACKAGE_REPORT.md`
- `docs/ai/REFACTOR_E7_REPORT.md`
- `docs/ai/ROUTE_MAP_AFTER_E7.md`
- `docs/ai/RUNTIME_SMOKE_CHECKLIST_E7_2.md`
- `docs/compose/plans/2026-06-28-soft-delete-stage1.md`
- `docs/design-audit/full-ui-revision/DESIGN_AUDIT_REPORT.md`
- `docs/design-audit/full-ui-revision/PAGE_INVENTORY.md`
- `docs/design-audit/full-ui-revision/README.md`
- `docs/design-audit/full-ui-revision/RUNTIME_ACCESS_NOTES.md`
- `docs/design-audit/full-ui-revision/SCREENSHOT_MANIFEST.md`
- `docs/design-audit/full-ui-revision/UI_ARCHITECTURE_MAP.md`
- `docs/ui/DESIGN_SYSTEM_PREP_REPORT.md`
- `docs/ui/PROMPT_CHIEF_DESIGNER_PREPARE_UI_SYSTEM.md`

## Как трактовать старые документы

Исторический документ не нужно «исправлять» задним числом: его старый `BLOCKED`, SHA, branch, migration number или workflow является частью evidence. Его актуализация выполняется этим индексом — он явно помечен HISTORICAL и не может переопределять текущий handoff.

Если агент обнаружит новый Markdown-файл, которого нет в этом списке, он обязан сначала определить его статус и обновить индекс. Нельзя оставлять новый управляющий MD без классификации.
