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
    if Path(old).exists() and not Path(new).exists():
        subprocess.run(['git', 'mv', old, new], check=True)
    elif not Path(new).exists():
        raise SystemExit(f'missing migration source: {old}')

roots = [Path('app'), Path('database'), Path('scripts'), Path('tools'), Path('tests'), Path('.github')]
for root in roots:
    if not root.exists():
        continue
    for p in root.rglob('*'):
        if not p.is_file() or p.suffix.lower() not in {'.php', '.py', '.yml', '.yaml', '.md', '.txt', '.sql'}:
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

print('P40 migration renumbering applied: 029 crew->057, 052 route points->058')
