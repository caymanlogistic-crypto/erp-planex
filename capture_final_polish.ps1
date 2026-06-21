# ERP PLANEX - Final Visual Polish Screenshot Capture
# Based on proven capture_all.ps1 CDP pattern
param([int]$DebugPort = 9225)

$ErrorActionPreference = "SilentlyContinue"
$workdir = "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"
$BaseUrl = "http://127.0.0.1:8016"
$outDir = Join-Path $workdir "docs\design-audit\final-visual-polish\screenshots"
$userDataDir = Join-Path $workdir "tmp_edge_cdp_profile"
$EdgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

New-Item -ItemType Directory -Path $outDir -Force | Out-Null
if (Test-Path $userDataDir) { Remove-Item $userDataDir -Recurse -Force }
New-Item -ItemType Directory -Path $userDataDir -Force | Out-Null

# Start Edge
Write-Host "Starting Edge headless on port $DebugPort..."
$edgeArgs = @("--headless=new", "--user-data-dir=$userDataDir", "--remote-debugging-port=$DebugPort", "--window-size=1920,1080", "--disable-gpu", "--no-sandbox", "about:blank")
$edgeProc = Start-Process -FilePath $EdgePath -ArgumentList $edgeArgs -PassThru -NoNewWindow
Start-Sleep -Seconds 4

# Get WebSocket URL
function Get-WsUrl {
    try {
        $r = Invoke-RestMethod -Uri "http://localhost:$DebugPort/json" -TimeoutSec 10
        foreach ($page in $r) { if ($page.type -eq 'page') { return $page.webSocketDebuggerUrl } }
    } catch {}
    return $null
}

$wsUrl = Get-WsUrl
if (-not $wsUrl) { Write-Host "ERROR: Cannot connect to CDP"; Stop-Process -Id $edgeProc.Id -Force; exit 1 }
Write-Host "CDP: $wsUrl"

# CDP command sender (proven pattern)
Add-Type -AssemblyName System.Net.WebSockets
$global:cdpId = 0

function Cdp-Send {
    param($method, $params = @{}, [int]$waitMs = 8000)
    $global:cdpId++
    $id = $global:cdpId
    $msg = (@{id=$id; method=$method; params=$params} | ConvertTo-Json -Depth 5 -Compress)
    
    try {
        $ws = [System.Net.WebSockets.ClientWebSocket]::new()
        $ct = [System.Threading.CancellationToken]::None
        $task = $ws.ConnectAsync([Uri]$wsUrl, $ct)
        if (-not $task.Wait(5000)) { $ws.Dispose(); return $null }
        if ($ws.State -ne 'Open') { $ws.Dispose(); return $null }
        
        $bytes = [Text.Encoding]::UTF8.GetBytes($msg)
        $ws.SendAsync([ArraySegment[byte]]::new($bytes), [System.Net.WebSockets.WebSocketMessageType]::Text, $true, $ct).Wait(5000)
        
        $buffer = [byte[]]::new(262144)
        $full = ""
        $timeout = [DateTime]::Now.AddMilliseconds($waitMs)
        do {
            $seg = [ArraySegment[byte]]::new($buffer)
            $task = $ws.ReceiveAsync($seg, $ct)
            if ($task.Wait(2000)) {
                $result = $task.Result
                $full += [Text.Encoding]::UTF8.GetString($buffer, 0, $result.Count)
                if ($result.EndOfMessage) { break }
            }
        } while ([DateTime]::Now -lt $timeout)
        $ws.Dispose()
        
        if ($full.Length -gt 10) {
            return $full | ConvertFrom-Json -ErrorAction SilentlyContinue
        }
    } catch {}
    return $null
}

# Enable domains
Cdp-Send -method "Page.enable" -params @{} -waitMs 3000 | Out-Null
Cdp-Send -method "Network.enable" -params @{} -waitMs 2000 | Out-Null

function Cdp-Navigate {
    param($url)
    Cdp-Send -method "Page.navigate" -params @{url=$url} -waitMs 12000 | Out-Null
    Start-Sleep -Seconds 2
}

function Cdp-Eval {
    param($js)
    return Cdp-Send -method "Runtime.evaluate" -params @{expression=$js; returnByValue=$true} -waitMs 5000
}

function Cdp-Screenshot {
    param($filePath)
    $r = Cdp-Send -method "Page.captureScreenshot" -params @{format="png"; fullPage=$true} -waitMs 12000
    if ($r -and $r.result -and $r.result.data) {
        [IO.File]::WriteAllBytes($filePath, [Convert]::FromBase64String($r.result.data))
        if (Test-Path $filePath) { return (Get-Item $filePath).Length }
    }
    return 0
}

function Cdp-Login {
    param($login, $password)
    Write-Host "  Logging in: $login"
    Cdp-Navigate("$BaseUrl/login")
    Start-Sleep -Seconds 1
    $js = "document.querySelector('input[name=`"login`"]').value='$login';document.querySelector('input[name=`"password`"]').value='$password';document.querySelector('form').submit();"
    Cdp-Eval($js) | Out-Null
    Start-Sleep -Seconds 3
}

# === CAPTURE LIST ===
$captureCount = 0

function Capture-Page {
    param($filename, $url)
    $captureCount++
    $filePath = Join-Path $outDir $filename
    Write-Host "  [$captureCount] $filename"
    Cdp-Navigate("$BaseUrl$url")
    $size = Cdp-Screenshot($filePath)
    if ($size -gt 5000) { Write-Host "    OK: $size bytes" } else { Write-Host "    FAIL: $size bytes" }
}

# 001 - Login form (no auth)
Write-Host "`n=== LOGIN PAGE ==="
Cdp-Navigate("$BaseUrl/login")
Capture-Page -filename "001_login_form.png" -url "/login"

# 002-006 - Company Owner
Write-Host "`n=== COMPANY OWNER ==="
Cdp-Login -login "owner_test_runtime" -password "test123"
Capture-Page -filename "002_owner_dashboard.png" -url "/company/dashboard"
Capture-Page -filename "003_owner_contractors_list.png" -url "/company/contractors"
Capture-Page -filename "004_owner_drivers_list.png" -url "/company/drivers"
Capture-Page -filename "005_owner_vehicles_list.png" -url "/company/vehicles"
Capture-Page -filename "006_owner_crews_list.png" -url "/company/crews"

# 007 - Logist 1 dashboard
Write-Host "`n=== LOGIST 1 ==="
Cdp-Login -login "logist_runtime_1" -password "pass1111"
Capture-Page -filename "007_logist1_dashboard.png" -url "/company/dashboard"

# 008-009 - Logist 2 (empty state + 403)
Write-Host "`n=== LOGIST 2 ==="
Cdp-Login -login "logist_runtime_2" -password "pass2222"
Capture-Page -filename "008_logist2_empty_state.png" -url "/company/contractors"
Capture-Page -filename "009_logist2_403.png" -url "/company/logists"

# 010-012 - Superadmin
Write-Host "`n=== SUPERADMIN ==="
Cdp-Login -login "admin@planex.local" -password "test123"
Capture-Page -filename "010_superadmin_dashboard.png" -url "/superadmin/companies"
Capture-Page -filename "011_superadmin_companies.png" -url "/superadmin/companies"
Capture-Page -filename "012_superadmin_company_view.png" -url "/superadmin/companies/9"

# Cleanup
Write-Host "`nCleaning up..."
if ($edgeProc) { Stop-Process -Id $edgeProc.Id -Force }
Write-Host "Done! $captureCount screenshots in: $outDir"
