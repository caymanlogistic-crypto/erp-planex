# Phase 3 commit script — run once from PowerShell in the erp folder
# cd "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"

Set-Location "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"

# Remove stale git lock (left by sandbox git add)
if (Test-Path ".git\index.lock") {
    Remove-Item ".git\index.lock" -Force
    Write-Host "Removed stale index.lock"
}

git add -A

git commit -m "style(superadmin): rework management center visual structure

- Registry: simplify row-actions to single 'Otkryt' button
- Company card: restructure into 4 info panels + management center
- Users/owner/logist: formalize action hierarchy (ghost|sep|secondary|danger)
- Add .panel-danger CSS class (replaces inline style trio)
- Add .code-hi, .btn-full, .field-hint, .action-sep and 20+ CSS utilities
- Login: field-hint class, btn-full, neutral hint text
- Zero inline styles in superadmin_*.php (except JS-controlled display:none)
- Update PROJECT_STATUS, AGENT_WORK_LOG, ERP_PLANEX_CONTEXT_FOR_NEW_CHAT"

Write-Host ""
Write-Host "Done. Run: git log --oneline -3"
