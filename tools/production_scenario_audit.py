#!/usr/bin/env python3
from __future__ import annotations
import json, re, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
issues=[]; routes=[]

def read(p:Path)->str:
    return p.read_text(encoding='utf-8',errors='replace')

def add(code,path,detail): issues.append({'code':code,'path':str(path.relative_to(ROOT)),'detail':detail})

def method_body(source:str,name:str):
    m=re.search(r'public function\s+'+re.escape(name)+r'\s*\([^)]*\)\s*:\s*void\s*\{',source)
    if not m: return None
    start=m.end(); depth=1; i=start; quote=None; esc=False
    while i<len(source):
        ch=source[i]
        if quote:
            if esc: esc=False
            elif ch=='\\': esc=True
            elif ch==quote: quote=None
        else:
            if ch in "'\"": quote=ch
            elif ch=='{': depth+=1
            elif ch=='}':
                depth-=1
                if depth==0: return source[start:i]
        i+=1
    return None

index=read(ROOT/'public/index.php')
if 'startCsrfFormInjection();' not in index or 'verifyCsrfRequest();' not in index:
    add('GLOBAL_CSRF_MISSING',ROOT/'public/index.php','HTML form injection and global request verification are required')

for route_file in sorted((ROOT/'app/Http/Routes').glob('*.php')):
    text=read(route_file)
    class_files={}
    for rel in re.findall(r"require_once base_path\('([^']+Controller\.php)'\)",text):
        p=ROOT/rel
        if not p.exists(): add('MISSING_CONTROLLER',route_file,rel); continue
        c=read(p); ns=re.search(r'namespace\s+([^;]+);',c); cl=re.search(r'(?:final\s+)?class\s+(\w+)',c)
        if ns and cl: class_files[ns.group(1)+'\\'+cl.group(1)]=p
    vars={}
    for m in re.finditer(r'\$(\w+)\s*=\s*new\s+\\?([A-Za-z0-9_\\]+)\s*\(',text): vars[m.group(1)]=class_files.get(m.group(2))
    # closures: capture until next route declaration as conservative handler region
    starts=list(re.finditer(r"\$router->(get|post)\('([^']+)'\s*,",text,re.I))
    for i,m in enumerate(starts):
        method,path=m.group(1).upper(),m.group(2); end=starts[i+1].start() if i+1<len(starts) else len(text); region=text[m.start():end]
        rec={'method':method,'path':path,'route_file':str(route_file.relative_to(ROOT)),'target':None,'role_guard':False,'roles':[],'csrf':'global' if method=='POST' else None}
        arr=re.search(r"\[\$(\w+),\s*'([^']+)'\]",region[:500])
        if arr:
            var,handler=arr.groups(); cp=vars.get(var); rec['target']=f'{cp}::{handler}' if cp else f'unresolved ${var}::{handler}'
            if not cp:
                add('UNRESOLVED_HANDLER',route_file,rec['target'])
            else:
                c=read(cp); body=method_body(c,handler)
                if body is None: add('MISSING_METHOD',cp,handler)
                else:
                    controller_guard='requireRole(' in body
                    target_text=body
                    am=re.search(r"require base_path\('([^']+\.php)'\)",body)
                    if am:
                        ap=ROOT/am.group(1); rec['target']=str(ap.relative_to(ROOT))
                        if not ap.exists(): add('MISSING_ACTION',cp,am.group(1))
                        else: target_text=read(ap)
                    rec['role_guard']=controller_guard or ('requireRole(' in target_text)
                    role_source = body + "\n" + target_text
                    role_values=[]
                    for role_call in re.findall(r'requireRole\((.*?)\)', role_source, re.S):
                        role_values.extend(re.findall(r"['\"]([a-z_]+)['\"]", role_call))
                    rec['roles']=sorted(set(role_values))
                    if path.startswith('/company/finance/') and "company_owner" not in target_text:
                        add('FINANCE_NOT_OWNER_ONLY',Path(rec['target'].split('::')[0]),path)
        elif 'function' in region[:500]:
            rec['target']=str(route_file.relative_to(ROOT))+':closure'
            rec['role_guard']='requireRole(' in region
            role_values=[]
            for role_call in re.findall(r'requireRole\((.*?)\)', region, re.S):
                role_values.extend(re.findall(r"['\"]([a-z_]+)['\"]", role_call))
            rec['roles']=sorted(set(role_values))
        routes.append(rec)
        if (path.startswith('/company/') or path.startswith('/superadmin/')) and not rec['role_guard']:
            # legacy redirects are allowed only when they immediately redirect and do not mutate/read private data
            if 'redirect_to(' not in region and "header('Location:" not in region:
                add('PROTECTED_ROUTE_WITHOUT_ROLE_GUARD',route_file,f'{method} {path}')

# All base_path source references must exist.
for p in list((ROOT/'app').rglob('*.php'))+[ROOT/'public/index.php']:
    text=read(p)
    for rel in re.findall(r"base_path\('([^']+)'\)",text):
        if '*' not in rel and not (ROOT/rel).exists(): add('MISSING_BASE_PATH_TARGET',p,rel)

# Migration numbering must be unique and SQL files non-empty.
for d in [ROOT/'database/migrations',ROOT/'database/migrations-local']:
    nums={}
    for p in sorted(d.glob('*.sql')):
        m=re.match(r'(\d+)_',p.name)
        if not m: add('MIGRATION_NAME_INVALID',p,p.name); continue
        n=int(m.group(1));
        if n in nums: add('MIGRATION_NUMBER_DUPLICATE',p,f'{n} also {nums[n].name}')
        nums[n]=p
        if p.stat().st_size<20: add('MIGRATION_EMPTY',p,'too small')

# No production credentials/hard-coded test DB password in source/tests.
secret_patterns=[
 ('PRIVATE_KEY',r'BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY'),
 ('HARDCODED_DB_PASSWORD',r"\$dbPass\s*=\s*['\"][^'\"]{3,}['\"]"),
 ('GITHUB_TOKEN',r'gh[pousr]_[A-Za-z0-9]{30,}'),
]
for base in [ROOT/'app',ROOT/'bootstrap',ROOT/'config',ROOT/'public',ROOT/'scripts',ROOT/'tests']:
  for p in base.rglob('*'):
    if not p.is_file() or p.suffix.lower() not in {'.php','.js','.json','.md','.txt'}: continue
    t=read(p)
    for code,pat in secret_patterns:
        if re.search(pat,t): add(code,p,'high-confidence pattern')

# Password-reset flows must use role guard, CSRF (global or local), password_hash and parameterized UPDATE.
for rel in [
 'app/Http/Controllers/Company/LogistActions/reset_password.php',
 'app/Http/Controllers/Superadmin/CompanyActions/owner_reset_password.php',
 'app/Http/Controllers/Superadmin/ManagementActions/logist_reset_password.php']:
 p=ROOT/rel;t=read(p)
 for token in ['requireRole(']:
    if token not in t: add('PASSWORD_RESET_GUARD_MISSING',p,token)
 if 'password_hash' not in t and 'UserSyncService' not in t:
    add('PASSWORD_RESET_HASH_PATH_MISSING',p,'neither local password_hash nor UserSyncService')
 if 'prepare(' not in t and 'UserSyncService' not in t: add('PASSWORD_RESET_UNPARAMETERIZED',p,'no prepared service path')

summary={
 'status':'PASS' if not issues else 'FAIL',
 'route_count':len(routes),
 'company_routes':sum(r['path'].startswith('/company/') for r in routes),
 'superadmin_routes':sum(r['path'].startswith('/superadmin/') for r in routes),
 'post_routes':sum(r['method']=='POST' for r in routes),
 'routes_with_role_guards':sum(bool(r['role_guard']) for r in routes),
 'global_csrf_post_routes':sum(r['method']=='POST' and r['csrf']=='global' for r in routes),
 'role_route_counts':{role:sum(role in r['roles'] for r in routes) for role in ['superadmin','company_owner','senior_logist','logist']},
 'issues':issues,
 'routes':routes,
}
out=ROOT/'tmp/production-scenario-audit.json';out.parent.mkdir(exist_ok=True);out.write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
(ROOT/'tmp/production-route-matrix.json').write_text(json.dumps(routes,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({k:v for k,v in summary.items() if k not in {'issues','routes'}},ensure_ascii=False))
for i in issues: print(f"{i['code']} {i['path']}: {i['detail']}")
sys.exit(0 if not issues else 1)
