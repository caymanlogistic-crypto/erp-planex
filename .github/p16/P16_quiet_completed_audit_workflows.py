from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
targets = [
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
    '.github/workflows/P16_quiet_completed_audits.yml',
]
pattern = re.compile(r'(?ms)^on:\n.*?(?=^(?:permissions:|concurrency:|env:|jobs:))')
changed = []
for rel in targets:
    path = root / rel
    if not path.exists():
        continue
    text = path.read_text(encoding='utf-8')
    replacement = 'on:\n  workflow_dispatch:\n\n'
    updated, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise RuntimeError(f'workflow trigger anchor mismatch: {rel}')
    if updated != text:
        path.write_text(updated, encoding='utf-8', newline='\n')
        changed.append(rel)
print(f'P16_QUIET_AUDITS=PASS changed={len(changed)}')
