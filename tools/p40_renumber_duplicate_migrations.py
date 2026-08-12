from pathlib import Path
import subprocess

RENAMES = {
    'database/migrations-local/029_create_crew_drivers.sql': 'database/migrations-local/057_create_crew_drivers.sql',
    'database/migrations-local/052_create_linear_route_points.sql': 'database/migrations-local/058_create_linear_route_points.sql',
}
REPLACEMENTS = {
    '029_create_crew_drivers.sql': '057_create_crew_drivers.sql',
    '052_create_linear_route_points.sql': '058_create_linear_route_points.sql',
}

for old, new in RENAMES.items():
    old_p, new_p = Path(old), Path(new)
    if old_p.exists() and not new_p.exists():
        subprocess.run(['git', 'mv', str(old_p), str(new_p)], check=True)
    elif old_p.exists() and new_p.exists():
        raise SystemExit(f'both old and new migrations exist: {old} / {new}')
    elif not new_p.exists():
        raise SystemExit(f'missing migration source and normalized target: {old} / {new}')

roots = [Path('app'), Path('database'), Path('scripts'), Path('tools'), Path('tests')]
for root in roots:
    if not root.exists():
        continue
    for p in root.rglob('*'):
        if not p.is_file() or p.suffix.lower() not in {'.php', '.py', '.md', '.txt', '.sql'}:
            continue
        try:
            s = p.read_text(encoding='utf-8')
        except UnicodeDecodeError:
            continue
        original = s
        for old, new in REPLACEMENTS.items():
            s = s.replace(old, new)
        if s != original:
            p.write_text(s, encoding='utf-8')

print('P40 migration numbering is normalized and idempotent')
