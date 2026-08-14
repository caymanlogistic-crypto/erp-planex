from pathlib import Path

p = Path('app/Support/entrypoint_dependencies.php')
s = p.read_text(encoding='utf-8')
needle = "require_once base_path('app/Service/FinanceStructureService.php');\n"
insert = needle + "require_once base_path('app/Service/FinanceStructureDeletionService.php');\n"
if "FinanceStructureDeletionService.php" not in s:
    if needle not in s: raise SystemExit('dependency insertion point missing')
    s = s.replace(needle, insert, 1)
p.write_text(s, encoding='utf-8')

p = Path('app/View/pages/company_finance_dds_categories.php')
s = p.read_text(encoding='utf-8')
old = '.fs-settings-danger{margin-right:auto}.fs-delete-action{color:var(--danger)!important;margin-right:auto}'
new = '.fs-settings-danger{margin-right:0}.fs-delete-action{color:var(--danger)!important;margin-right:auto}'
if old in s:
    s = s.replace(old, new, 1)
elif new not in s:
    raise SystemExit('financial structure action alignment pattern missing')
p.write_text(s, encoding='utf-8')

print('P61_FINALIZE_OK')
