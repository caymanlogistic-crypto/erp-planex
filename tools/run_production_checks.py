#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'tmp' / 'production-checks-summary.json'
TEXT_SUFFIXES = {'.php', '.js', '.mjs', '.css', '.sql', '.py', '.json', '.xml', '.yml', '.yaml'}
SKIP_PARTS = {'.git', 'vendor', 'node_modules', 'storage', 'logs', 'cache', 'tmp', 'docs', '.kilo'}
MOJIBAKE_MARKERS = ('Рџ', 'РЎ', 'РµР', 'Р°Р', 'Ð', 'Ñ', '\ufffd')
SECRET_PATTERNS = {
    'private_key': re.compile(r'BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY'),
    'github_token': re.compile(r'gh[pousr]_[A-Za-z0-9]{30,}'),
    'openai_key': re.compile(r'sk-[A-Za-z0-9_-]{20,}'),
    'credential_url': re.compile(r'(?i)\b(?:mysql|postgres(?:ql)?|https?|imap|smtp)://[^\s/:]+:[^\s/@]+@'),
}


def run(cmd: list[str], timeout: int = 180) -> dict:
    started = time.time()
    try:
        p = subprocess.run(cmd, cwd=ROOT, text=True, capture_output=True, timeout=timeout)
        return {'command': cmd, 'exit': p.returncode, 'seconds': round(time.time() - started, 3), 'stdout': p.stdout[-12000:], 'stderr': p.stderr[-12000:]}
    except subprocess.TimeoutExpired as exc:
        return {'command': cmd, 'exit': 124, 'seconds': round(time.time() - started, 3), 'stdout': (exc.stdout or '')[-12000:], 'stderr': (exc.stderr or '')[-12000:] + '\nTIMEOUT'}


def source_files() -> list[Path]:
    result = []
    for p in ROOT.rglob('*'):
        if not p.is_file() or any(part in SKIP_PARTS for part in p.relative_to(ROOT).parts):
            continue
        if p.name == 'architecture_guard.php':
            continue
        if p.suffix.lower() in TEXT_SUFFIXES:
            result.append(p)
    return sorted(result)


summary: dict = {
    'status': 'PASS',
    'root': str(ROOT),
    'php_version': None,
    'php_extensions': {},
    'php_lint': {},
    'tests': {},
    'architecture_guard': {},
    'scenario_audit': {},
    'javascript_syntax': {},
    'encoding': {},
    'security_scan': {},
    'migrations': {},
    'runtime_limitations': [],
}

php_v = run(['php', '-r', 'echo PHP_VERSION;'])
summary['php_version'] = php_v['stdout'] if php_v['exit'] == 0 else None
for ext in ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'zip', 'simplexml', 'fileinfo', 'imap']:
    r = run(['php', '-r', f"exit(extension_loaded('{ext}') ? 0 : 1);"])
    summary['php_extensions'][ext] = r['exit'] == 0

php_files = sorted(p for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'vendor' not in p.parts)
lint_failures = []
for p in php_files:
    r = run(['php', '-l', str(p)], timeout=30)
    if r['exit'] != 0:
        lint_failures.append({'file': str(p.relative_to(ROOT)), 'output': r['stdout'] + r['stderr']})
summary['php_lint'] = {'files': len(php_files), 'failures': lint_failures, 'pass': not lint_failures}

results = []
for test in sorted((ROOT / 'tests').glob('*_test.php')):
    r = run(['php', str(test)], timeout=180)
    classification = 'pass' if r['exit'] == 0 else ('deferred_runtime' if r['exit'] == 2 else 'fail')
    results.append({'file': str(test.relative_to(ROOT)), 'classification': classification, **r})
summary['tests'] = {
    'total': len(results),
    'passed': sum(x['classification'] == 'pass' for x in results),
    'deferred_runtime': sum(x['classification'] == 'deferred_runtime' for x in results),
    'failed': sum(x['classification'] == 'fail' for x in results),
    'results': results,
}

summary['architecture_guard'] = run(['php', 'tools/architecture_guard.php'])
summary['architecture_guard']['pass'] = summary['architecture_guard']['exit'] == 0
summary['scenario_audit'] = run([sys.executable, 'tools/production_scenario_audit.py'])
summary['scenario_audit']['pass'] = summary['scenario_audit']['exit'] == 0

node = shutil.which('node')
js_files = sorted(list((ROOT / 'public' / 'assets' / 'js').glob('*.js')) + list((ROOT / 'tools').rglob('*.mjs')))
js_failures = []
if node:
    for p in js_files:
        r = run([node, '--check', str(p)], timeout=30)
        if r['exit'] != 0:
            js_failures.append({'file': str(p.relative_to(ROOT)), 'output': r['stdout'] + r['stderr']})
summary['javascript_syntax'] = {'node_available': bool(node), 'files': len(js_files), 'failures': js_failures, 'pass': bool(node) and not js_failures}

utf8_failures = []
bom_files = []
mojibake = []
secret_findings = []
texts = source_files()
for p in texts:
    raw = p.read_bytes()
    rel = str(p.relative_to(ROOT))
    if raw.startswith(b'\xef\xbb\xbf'):
        bom_files.append(rel)
    try:
        text = raw.decode('utf-8')
    except UnicodeDecodeError as exc:
        utf8_failures.append({'file': rel, 'error': str(exc)})
        continue
    for line_number, line in enumerate(text.splitlines(), 1):
        if 'MOJIBAKE' in line.upper() or 'mojibake' in line.lower():
            continue
        marker = next((candidate for candidate in MOJIBAKE_MARKERS if candidate in line), None)
        if marker is not None:
            mojibake.append({'file': rel, 'line': line_number, 'marker': marker})
            break
    for category, pattern in SECRET_PATTERNS.items():
        if pattern.search(text):
            secret_findings.append({'file': rel, 'category': category})
summary['encoding'] = {
    'text_files': len(texts),
    'utf8_failures': utf8_failures,
    'bom_files': bom_files,
    'mojibake_findings': mojibake,
    'pass': not utf8_failures and not bom_files and not mojibake,
}
summary['security_scan'] = {'high_confidence_findings': secret_findings, 'pass': not secret_findings}

migration_dirs = [ROOT / 'database' / 'migrations', ROOT / 'database' / 'migrations-local']
migrations = []
duplicates = []
for d in migration_dirs:
    seen: dict[int, str] = {}
    for p in sorted(d.glob('*.sql')):
        m = re.match(r'(\d+)_', p.name)
        number = int(m.group(1)) if m else None
        rel = str(p.relative_to(ROOT))
        migrations.append({'file': rel, 'number': number, 'bytes': p.stat().st_size, 'sha256': hashlib.sha256(p.read_bytes()).hexdigest()})
        if number is not None and number in seen:
            duplicates.append({'directory': str(d.relative_to(ROOT)), 'number': number, 'files': [seen[number], rel]})
        elif number is not None:
            seen[number] = rel
summary['migrations'] = {'count': len(migrations), 'duplicates': duplicates, 'files': migrations, 'pass': not duplicates and all(x['bytes'] > 20 for x in migrations)}

if not summary['php_extensions'].get('pdo_mysql'):
    summary['runtime_limitations'].append('Real MySQL migration, transaction, locking and tenant-database checks require pdo_mysql and a synthetic MySQL instance.')
if not summary['php_extensions'].get('mbstring'):
    summary['runtime_limitations'].append('Production preflight requires mbstring; matching has a deterministic fallback only for isolated text matching.')
if summary['tests']['deferred_runtime']:
    summary['runtime_limitations'].append('Stage 8 report/UI runtime remains deferred until a synthetic acceptance database is supplied.')

hard_fail = (
    not summary['php_lint']['pass']
    or summary['tests']['failed'] > 0
    or not summary['architecture_guard']['pass']
    or not summary['scenario_audit']['pass']
    or not summary['javascript_syntax']['pass']
    or not summary['encoding']['pass']
    or not summary['security_scan']['pass']
    or not summary['migrations']['pass']
)
summary['status'] = 'FAIL' if hard_fail else ('PASS_WITH_RUNTIME_DEFERRED' if summary['tests']['deferred_runtime'] else 'PASS')
OUT.parent.mkdir(exist_ok=True)
OUT.write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({
    'status': summary['status'],
    'php_files': summary['php_lint']['files'],
    'tests': {k: summary['tests'][k] for k in ['total', 'passed', 'deferred_runtime', 'failed']},
    'routes': json.loads((ROOT/'tmp/production-scenario-audit.json').read_text(encoding='utf-8')).get('route_count'),
    'migrations': summary['migrations']['count'],
    'runtime_limitations': summary['runtime_limitations'],
}, ensure_ascii=False))
sys.exit(1 if hard_fail else 0)
