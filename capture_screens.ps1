# Screenshot capture script for ERP PLANEX design audit
# Usage: powershell -ExecutionPolicy Bypass -File capture.ps1

param(
    [string]$BaseUrl = "http://127.0.0.1:8016",
    [string]$OutputDir = "docs\design-audit\full-ui-revision\screenshots",
    [string]$EdgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    [int]$Timeout = 15000
)

$ErrorActionPreference = "Continue"
$workdir = "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"
$outDir = Join-Path $workdir $OutputDir
$tmpDir = Join-Path $workdir "tmp_screenshots"
New-Item -ItemType Directory -Path $tmpDir -Force | Out-Null

# Helper: login and get session cookie
function Login-And-GetCookie {
    param($Login, $Password)
    try {
        $body = @{login=$Login; password=$Password}
        $r = Invoke-WebRequest -Uri "$BaseUrl/login" -Method POST -Body $body -UseBasicParsing -SessionVariable sv 2>$null
        $cookieHeader = $r.Headers['Set-Cookie'] 
        if ($cookieHeader) {
            $match = [regex]::Match($cookieHeader, 'PHPSESSID=([^;]+)')
            if ($match.Success) { return $match.Groups[1].Value }
        }
        # Try getting from the session variable
        $cookies = $sv.Cookies.GetCookies($BaseUrl)
        foreach ($c in $cookies) {
            if ($c.Name -eq 'PHPSESSID') { return $c.Value }
        }
        return $null
    } catch { return $null }
}

# Helper: take single screenshot
function Take-Screenshot {
    param($Cookie, $Role, $Menu, $Page, $State, $Url)
    
    $filename = "${Role}__${Menu}__${Page}__${State}.png" -replace '[\\/:*?"<>|]', '_'
    $outPath = Join-Path $outDir $filename
    $tmpHtml = Join-Path $tmpDir "capture_$([Guid]::NewGuid().ToString().Substring(0,8)).html"
    
    # Create temp HTML that sets cookie and redirects
    $html = @"
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head><body>
<script>
document.cookie = "PHPSESSID=$Cookie; path=/";
setTimeout(function(){ window.stop(); }, $Timeout);
location.href = "$BaseUrl$Url";
</script>
</body></html>
"@
    Set-Content -Path $tmpHtml -Value $html -Encoding UTF8
    
    # Launch msedge headless
    $args = @(
        "--headless=new",
        "--screenshot=$outPath",
        "--window-size=1440,900",
        "--disable-gpu",
        "--no-sandbox",
        "--disable-extensions",
        "--disable-dev-shm-usage",
        "--virtual-time-budget=10000",
        "file:///$($tmpHtml -replace '\\','/')"
    )
    
    try {
        $proc = Start-Process -FilePath $EdgePath -ArgumentList $args -Wait -NoNewWindow -PassThru
        Remove-Item $tmpHtml -Force -ErrorAction SilentlyContinue
        if (Test-Path $outPath) {
            $size = (Get-Item $outPath).Length
            Write-Host "OK: $filename ($size bytes)"
            return @{ file=$filename; size=$size; success=$true }
        } else {
            Write-Host "FAIL: $filename (no file created)"
            return @{ file=$filename; size=0; success=$false }
        }
    } catch {
        Remove-Item $tmpHtml -Force -ErrorAction SilentlyContinue
        Write-Host "ERROR: $filename - $_"
        return @{ file=$filename; size=0; success=$false }
    }
}

# ---- MAIN ----

$roles = @(
    @{name="superadmin"; login="admin@planex.local"; password="admin123"},
    @{name="owner"; login="owner_test_runtime"; password="pass1234"},
    @{name="logist_runtime_1"; login="logist_runtime_1"; password="pass1111"},
    @{name="logist_runtime_2"; login="logist_runtime_2"; password="pass2222"}
)

# Screenshot definitions: (role, menu, page, state, url)
$screens = @(
    # AUTH
    @{role="auth"; menu="login"; page="login"; state="form"; url="/login"; nologin=$true},
    
    # SUPERADMIN
    @{role="superadmin"; menu="dashboard"; page="dashboard"; state="default"; url="/superadmin"},
    @{role="superadmin"; menu="companies"; page="list"; state="filled"; url="/superadmin/companies"},
    @{role="superadmin"; menu="company"; page="create"; state="form"; url="/superadmin/companies/create"},
    @{role="superadmin"; menu="company"; page="view"; state="runtime_company"; url="/superadmin/companies/9"},
    @{role="superadmin"; menu="company"; page="edit"; state="form"; url="/superadmin/companies/9/edit"},
    @{role="superadmin"; menu="company"; page="create_owner"; state="form"; url="/superadmin/companies/9/create-owner"},
    @{role="superadmin"; menu="company"; page="owner"; state="view"; url="/superadmin/companies/9/owner"},
    @{role="superadmin"; menu="company"; page="owner_edit"; state="form"; url="/superadmin/companies/9/owner/edit"},
    @{role="superadmin"; menu="company"; page="users"; state="list"; url="/superadmin/companies/9/users"},
    @{role="superadmin"; menu="company"; page="logist_create"; state="form"; url="/superadmin/companies/9/users/logists/create"},
    @{role="superadmin"; menu="company"; page="logist_view"; state="view"; url="/superadmin/companies/9/users/logists/1"},
    @{role="superadmin"; menu="company"; page="logist_edit"; state="form"; url="/superadmin/companies/9/users/logists/1/edit"},
    @{role="superadmin"; menu="company"; page="directories"; state="overview"; url="/superadmin/companies/9/directories"},
    @{role="superadmin"; menu="company"; page="clients"; state="list"; url="/superadmin/companies/9/clients"},
    @{role="superadmin"; menu="company"; page="contractors"; state="list"; url="/superadmin/companies/9/contractors"},
    @{role="superadmin"; menu="company"; page="drivers"; state="list"; url="/superadmin/companies/9/drivers"},
    @{role="superadmin"; menu="company"; page="vehicles"; state="list"; url="/superadmin/companies/9/vehicles"},
    @{role="superadmin"; menu="company"; page="crews"; state="list"; url="/superadmin/companies/9/crews"},
    @{role="superadmin"; menu="company"; page="documents"; state="list"; url="/superadmin/companies/9/documents"},
    @{role="superadmin"; menu="company"; page="access_grants"; state="list"; url="/superadmin/companies/9/access-grants"},
    @{role="superadmin"; menu="company"; page="delete"; state="form"; url="/superadmin/companies/9/delete"},
    
    # COMPANY OWNER
    @{role="owner"; menu="dashboard"; page="dashboard"; state="default"; url="/company/dashboard"},
    @{role="owner"; menu="logists"; page="list"; state="filled"; url="/company/logists"},
    @{role="owner"; menu="logists"; page="create"; state="form"; url="/company/logists/create"},
    @{role="owner"; menu="logists"; page="view"; state="runtime_logist"; url="/company/logists/1"},
    @{role="owner"; menu="logists"; page="edit"; state="form"; url="/company/logists/1/edit"},
    @{role="owner"; menu="contractors"; page="list"; state="filled"; url="/company/contractors"},
    @{role="owner"; menu="contractors"; page="create"; state="form"; url="/company/contractors/create"},
    @{role="owner"; menu="contractors"; page="view"; state="runtime"; url="/company/contractors/1"},
    @{role="owner"; menu="contractors"; page="edit"; state="form"; url="/company/contractors/1/edit"},
    @{role="owner"; menu="drivers"; page="list"; state="filled"; url="/company/drivers"},
    @{role="owner"; menu="drivers"; page="create"; state="form"; url="/company/drivers/create"},
    @{role="owner"; menu="drivers"; page="view"; state="runtime"; url="/company/drivers/1"},
    @{role="owner"; menu="drivers"; page="edit"; state="form"; url="/company/drivers/1/edit"},
    @{role="owner"; menu="vehicles"; page="list"; state="filled"; url="/company/vehicles"},
    @{role="owner"; menu="vehicles"; page="create"; state="form"; url="/company/vehicles/create"},
    @{role="owner"; menu="vehicles"; page="view"; state="runtime_tractor"; url="/company/vehicles/1"},
    @{role="owner"; menu="vehicles"; page="edit"; state="form"; url="/company/vehicles/1/edit"},
    @{role="owner"; menu="vehicle_sets"; page="list"; state="filled"; url="/company/vehicle-sets"},
    @{role="owner"; menu="vehicle_sets"; page="create"; state="form"; url="/company/vehicle-sets/create"},
    @{role="owner"; menu="vehicle_sets"; page="view"; state="runtime_coupling"; url="/company/vehicle-sets/1"},
    @{role="owner"; menu="vehicle_sets"; page="edit"; state="form"; url="/company/vehicle-sets/1/edit"},
    @{role="owner"; menu="dvb"; page="list"; state="filled"; url="/company/driver-vehicle-blocks"},
    @{role="owner"; menu="dvb"; page="create"; state="form"; url="/company/driver-vehicle-blocks/create"},
    @{role="owner"; menu="dvb"; page="view"; state="runtime"; url="/company/driver-vehicle-blocks/1"},
    @{role="owner"; menu="dvb"; page="edit"; state="form"; url="/company/driver-vehicle-blocks/1/edit"},
    @{role="owner"; menu="crews"; page="list"; state="filled"; url="/company/crews"},
    @{role="owner"; menu="crews"; page="create"; state="form"; url="/company/crews/create"},
    @{role="owner"; menu="crews"; page="view"; state="runtime"; url="/company/crews/1"},
    @{role="owner"; menu="crews"; page="edit"; state="form"; url="/company/crews/1/edit"},
    @{role="owner"; menu="documents"; page="list"; state="filled"; url="/company/documents"},
    @{role="owner"; menu="documents"; page="upload"; state="form"; url="/company/documents/upload"},
    
    # LOGIST 1
    @{role="logist_runtime_1"; menu="contractors"; page="list"; state="own"; url="/company/contractors"},
    @{role="logist_runtime_1"; menu="contractors"; page="view"; state="own"; url="/company/contractors/1"},
    @{role="logist_runtime_1"; menu="drivers"; page="list"; state="own"; url="/company/drivers"},
    @{role="logist_runtime_1"; menu="drivers"; page="view"; state="own"; url="/company/drivers/1"},
    @{role="logist_runtime_1"; menu="vehicles"; page="list"; state="own"; url="/company/vehicles"},
    @{role="logist_runtime_1"; menu="vehicles"; page="view"; state="own_tractor"; url="/company/vehicles/1"},
    @{role="logist_runtime_1"; menu="vehicle_sets"; page="list"; state="own"; url="/company/vehicle-sets"},
    @{role="logist_runtime_1"; menu="vehicle_sets"; page="view"; state="own"; url="/company/vehicle-sets/1"},
    @{role="logist_runtime_1"; menu="dvb"; page="list"; state="own"; url="/company/driver-vehicle-blocks"},
    @{role="logist_runtime_1"; menu="dvb"; page="view"; state="own"; url="/company/driver-vehicle-blocks/1"},
    @{role="logist_runtime_1"; menu="crews"; page="list"; state="own"; url="/company/crews"},
    @{role="logist_runtime_1"; menu="crews"; page="view"; state="own"; url="/company/crews/1"},
    
    # LOGIST 2 (grant access)
    @{role="logist_runtime_2"; menu="contractors"; page="list"; state="grant"; url="/company/contractors"},
    @{role="logist_runtime_2"; menu="drivers"; page="list"; state="grant"; url="/company/drivers"},
    @{role="logist_runtime_2"; menu="vehicles"; page="list"; state="grant"; url="/company/vehicles"},
    @{role="logist_runtime_2"; menu="vehicle_sets"; page="list"; state="grant"; url="/company/vehicle-sets"},
    @{role="logist_runtime_2"; menu="dvb"; page="list"; state="grant"; url="/company/driver-vehicle-blocks"},
    @{role="logist_runtime_2"; menu="crews"; page="list"; state="grant"; url="/company/crews"},
    @{role="logist_runtime_2"; menu="crews"; page="view"; state="grant"; url="/company/crews/1"}
)

$results = @()
$cookies = @{}

# Login each role
foreach ($role in $roles) {
    Write-Host "`n=== Logging in as $($role.name) ===" 
    $cookie = Login-And-GetCookie -Login $role.login -Password $role.password
    if ($cookie) {
        $cookies[$role.name] = $cookie
        Write-Host "  Got cookie: ${cookie}..."
    } else {
        Write-Host "  FAILED to get cookie for $($role.name)"
    }
}

# Take auth screenshot (no login needed)
Write-Host "`n=== AUTH screenshots ==="
foreach ($scr in $screens | Where-Object { $_.nologin }) {
    $r = Take-Screenshot -Cookie "" -Role $scr.role -Menu $scr.menu -Page $scr.page -State $scr.state -Url $scr.url
    $results += $r
}

# Take screenshots for each role
foreach ($role in $roles) {
    $cookie = $cookies[$role.name]
    if (-not $cookie) { continue }
    
    Write-Host "`n=== Screenshots for $($role.name) ==="
    $roleScreens = $screens | Where-Object { $_.role -eq $role.name }
    foreach ($scr in $roleScreens) {
        $r = Take-Screenshot -Cookie $cookie -Role $scr.role -Menu $scr.menu -Page $scr.page -State $scr.state -Url $scr.url
        $results += $r
    }
}

# Summary
Write-Host "`n=== SUMMARY ===" 
$success = ($results | Where-Object { $_.success }).Count
$failed = ($results | Where-Object { -not $_.success }).Count
Write-Host "Total: $($results.Count), OK: $success, FAILED: $failed"

# Cleanup
Remove-Item $tmpDir -Recurse -Force -ErrorAction SilentlyContinue
