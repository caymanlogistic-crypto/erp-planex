from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TARGETS = [
    '.github/workflows/P14_linear_trip_edit_runtime.yml',
    '.github/workflows/P15_startup_gates.yml',
    '.github/workflows/P15_tenant_inventory_gate.yml',
    '.github/workflows/P15_tenant_inventory_gate_php83.yml',
    '.github/workflows/P15_tenant_inventory_live.yml',
    '.github/workflows/P15_remote_layout_discovery.yml',
    '.github/workflows/P15_apply_trip_fix.yml',
    '.github/workflows/P15_technical_verification.yml',
    '.github/workflows/P15_release_gate.yml',
    '.github/workflows/P15_complete_test_suite.yml',
    '.github/workflows/P15_source_inventory.yml',
    '.github/workflows/P15_cleanup_restore.yml',
    '.github/workflows/P15_trip_create_runtime.yml',
    '.github/workflows/P15_restore_verify.yml',
    '.github/workflows/P16_apply_fullhd_foundation.yml',
    '.github/workflows/P16_apply_representative_fixtures.yml',
    '.github/workflows/P16_generate_data_plan.yml',
    '.github/workflows/P16_populated_fullhd_runtime.yml',
    '.github/workflows/P16_quiet_completed_audits.yml',
    '.github/workflows/P16_release_gate.yml',
    '.github/workflows/P16_tenant_schema_inventory.yml',
    '.github/workflows/P17_complete_acceptance.yml',
    '.github/workflows/P17_crud_trip_finance_runtime.yml',
    '.github/workflows/P17_route_role_fullhd_runtime.yml',
    '.github/workflows/P17R_capability_discovery.yml',
    '.github/workflows/P17R_complete_acceptance.yml',
    '.github/workflows/P17R_v4_complete_factual_acceptance.yml',
    '.github/workflows/P17R_v5_complete_acceptance.yml',
    '.github/workflows/P17R_source_contract_inventory.yml',
    '.github/workflows/P17R_apply_zero_byte_document_fix.yml',
    '.github/workflows/P17R_quiet_legacy_workflows.yml',
]


def quiet(path: Path) -> bool:
    text = path.read_text(encoding='utf-8')
    lines = text.splitlines(keepends=True)
    start = next((i for i, line in enumerate(lines) if line.rstrip('\r\n') == 'on:'), None)
    if start is None:
        raise RuntimeError(f'on block missing: {path}')
    end = None
    for i in range(start + 1, len(lines)):
        stripped = lines[i].strip()
        if stripped and not lines[i].startswith((' ', '\t')) and not stripped.startswith('#'):
            end = i
            break
    if end is None:
        raise RuntimeError(f'next top-level YAML key missing: {path}')
    replacement = ['on:\n', '  workflow_dispatch:\n', '\n']
    updated = ''.join(lines[:start] + replacement + lines[end:])
    if updated == text:
        return False
    path.write_text(updated, encoding='utf-8', newline='\n')
    return True

changed = []
for rel in TARGETS:
    path = ROOT / rel
    if not path.is_file():
        continue
    if quiet(path):
        changed.append(rel)

Path('P17R_QUIETED_WORKFLOWS.json').write_text(
    __import__('json').dumps({'status': 'PASS', 'changed': changed, 'count': len(changed)}, indent=2),
    encoding='utf-8',
    newline='\n',
)
print(f'P17R_QUIET_LEGACY_WORKFLOWS=PASS changed={len(changed)}')
