# ERP PLANEX - Complete Screenshot Capture via Edge CDP
# Captures all design audit screenshots for full-ui-revision package
param([int]$DebugPort = 9223)

$ErrorActionPreference = "SilentlyContinue"
$workdir = "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"
$BaseUrl = "http://127.0.0.1:8016"
$outDir = Join-Path $workdir "docs\design-audit\full-ui-revision\screenshots"
$userDataDir = Join-Path $workdir "tmp_edge_profile"
$EdgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

# Cleanup
if (Test-Path $userDataDir) { Remove-Item $userDataDir -Recurse -Force }
New-Item -ItemType Directory -Path $userDataDir -Force | Out-Null
New-Item -ItemType Directory -Path $outDir -Force | Out-Null

# Start Edge
Write-Host "Starting Edge..."
$edgeArgs = @("--headless=new", "--user-data-dir=$userDataDir", "--remote-debugging-port=$DebugPort", "--window-size=1440,900", "--disable-gpu", "--no-sandbox", "about:blank")
$edgeProc = Start-Process -FilePath $EdgePath -ArgumentList $edgeArgs -PassThru -NoNewWindow
Start-Sleep -Seconds 4

# Get CDP WebSocket URL
function Get-WsUrl {
    try {
        $r = Invoke-RestMethod -Uri "http://localhost:$DebugPort/json" -TimeoutSec 5
        foreach ($page in $r) {
            if ($page.type -eq 'page') { return $page.webSocketDebuggerUrl }
        }
    } catch {}
    return $null
}

$wsUrl = Get-WsUrl
if (-not $wsUrl) { Write-Host "ERROR: Cannot connect to CDP"; Stop-Process -Id $edgeProc.Id -Force; exit 1 }
Write-Host "CDP: $wsUrl"

# CDP command sender using .NET WebSocket
Add-Type -AssemblyName System.Net.WebSockets
$global:cdpId = 0

function Cdp-Send {
    param($method, $params = @{}, [int]$waitMs = 3000)
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

function Cdp-Navigate {
    param($url)
    $r = Cdp-Send -method "Page.navigate" -params @{url=$url} -waitMs 10000
    Start-Sleep -Seconds 3
    return $r
}

function Cdp-Eval {
    param($js)
    return Cdp-Send -method "Runtime.evaluate" -params @{expression=$js; returnByValue=$true} -waitMs 5000
}

function Cdp-Screenshot {
    param($filePath)
    $r = Cdp-Send -method "Page.captureScreenshot" -params @{format="png"; fromSurface=$true} -waitMs 10000
    if ($r -and $r.result -and $r.result.data) {
        [IO.File]::WriteAllBytes($filePath, [Convert]::FromBase64String($r.result.data))
        if (Test-Path $filePath) { return (Get-Item $filePath).Length }
    }
    return 0
}

function Cdp-Login {
    param($login, $password, $role)
    Write-Host "  Login: $login"
    Cdp-Navigate("$BaseUrl/login")
    Start-Sleep -Seconds 1
    $js = "document.querySelector('input[name=`"login`"]').value='$login';document.querySelector('input[name=`"password`"]').value='$password';document.querySelector('form').submit();"
    Cdp-Eval($js)
    Start-Sleep -Seconds 4
    $urlCheck = Cdp-Eval("document.location.href")
    if ($urlCheck -and $urlCheck.result -and $urlCheck.result.result) {
        Write-Host "    -> $($urlCheck.result.result.value)"
    }
}

# === CAPTURE ===
$results = @()
$captureCount = 0

function Capture-Page {
    param($role, $menu, $page, $state, $url)
    $captureCount++
    $filename = "${role}__${menu}__${page}__${state}.png" -replace '[\\/:*?"<>|]', '_'
    $filePath = Join-Path $outDir $filename
    
    Write-Host "  [$captureCount] $filename"
    Cdp-Navigate("$BaseUrl$url")
    Start-Sleep -Seconds 2
    $size = Cdp-Screenshot($filePath)
    
    $result = @{file=$filename; size=$size; success=($size -gt 5000)}
    $results += $result
    if ($result.success) { Write-Host "    OK: $size bytes" } else { Write-Host "    FAIL: $size bytes" }
}

# --- AUTH ---
Write-Host "`n=== AUTH ==="
Capture-Page -role "auth" -menu "login" -page "login" -state "form" -url "/login"

# --- SUPERADMIN ---
Write-Host "`n=== SUPERADMIN ==="
Cdp-Login -login "admin@planex.local" -password "admin123" -role "superadmin"

Capture-Page -role "superadmin" -menu "dashboard" -page "dashboard" -state "default" -url "/superadmin"
Capture-Page -role "superadmin" -menu "companies" -page "list" -state "filled" -url "/superadmin/companies"
Capture-Page -role "superadmin" -menu "company" -page "create" -state "form" -url "/superadmin/companies/create"
Capture-Page -role "superadmin" -menu "company" -page "view" -state "company9" -url "/superadmin/companies/9"
Capture-Page -role "superadmin" -menu "company" -page "edit" -state "form" -url "/superadmin/companies/9/edit"
Capture-Page -role "superadmin" -menu "company" -page "create_owner" -state "form" -url "/superadmin/companies/9/create-owner"
Capture-Page -role "superadmin" -menu "company" -page "owner" -state "view" -url "/superadmin/companies/9/owner"
Capture-Page -role "superadmin" -menu "company" -page "owner_edit" -state "form" -url "/superadmin/companies/9/owner/edit"
Capture-Page -role "superadmin" -menu "company" -page "users" -state "list" -url "/superadmin/companies/9/users"
Capture-Page -role "superadmin" -menu "company" -page "logist_create" -state "form" -url "/superadmin/companies/9/users/logists/create"
Capture-Page -role "superadmin" -menu "company" -page "logist_view" -state "view" -url "/superadmin/companies/9/users/logists/1"
Capture-Page -role "superadmin" -menu "company" -page "logist_edit" -state "form" -url "/superadmin/companies/9/users/logists/1/edit"
Capture-Page -role "superadmin" -menu "company" -page "directories" -state "overview" -url "/superadmin/companies/9/directories"
Capture-Page -role "superadmin" -menu "company" -page "clients" -state "list" -url "/superadmin/companies/9/clients"
Capture-Page -role "superadmin" -menu "company" -page "contractors" -state "list" -url "/superadmin/companies/9/contractors"
Capture-Page -role "superadmin" -menu "company" -page "drivers" -state "list" -url "/superadmin/companies/9/drivers"
Capture-Page -role "superadmin" -menu "company" -page "vehicles" -state "list" -url "/superadmin/companies/9/vehicles"
Capture-Page -role "superadmin" -menu "company" -page "crews" -state "list" -url "/superadmin/companies/9/crews"
Capture-Page -role "superadmin" -menu "company" -page "documents" -state "list" -url "/superadmin/companies/9/documents"
Capture-Page -role "superadmin" -menu "company" -page "access_grants" -state "list" -url "/superadmin/companies/9/access-grants"
Capture-Page -role "superadmin" -menu "company" -page "delete" -state "form" -url "/superadmin/companies/9/delete"

# --- COMPANY OWNER ---
Write-Host "`n=== COMPANY OWNER ==="
Cdp-Login -login "owner_test_runtime" -password "pass1234" -role "owner"

Capture-Page -role "owner" -menu "dashboard" -page "dashboard" -state "default" -url "/company/dashboard"
Capture-Page -role "owner" -menu "logists" -page "list" -state "filled" -url "/company/logists"
Capture-Page -role "owner" -menu "logists" -page "create" -state "form" -url "/company/logists/create"
Capture-Page -role "owner" -menu "logists" -page "view" -state "logist1" -url "/company/logists/1"
Capture-Page -role "owner" -menu "logists" -page "edit" -state "form" -url "/company/logists/1/edit"
Capture-Page -role "owner" -menu "contractors" -page "list" -state "filled" -url "/company/contractors"
Capture-Page -role "owner" -menu "contractors" -page "create" -state "form" -url "/company/contractors/create"
Capture-Page -role "owner" -menu "contractors" -page "view" -state "contractor1" -url "/company/contractors/1"
Capture-Page -role "owner" -menu "contractors" -page "edit" -state "form" -url "/company/contractors/1/edit"
Capture-Page -role "owner" -menu "drivers" -page "list" -state "filled" -url "/company/drivers"
Capture-Page -role "owner" -menu "drivers" -page "create" -state "form" -url "/company/drivers/create"
Capture-Page -role "owner" -menu "drivers" -page "view" -state "driver1" -url "/company/drivers/1"
Capture-Page -role "owner" -menu "drivers" -page "edit" -state "form" -url "/company/drivers/1/edit"
Capture-Page -role "owner" -menu "vehicles" -page "list" -state "filled" -url "/company/vehicles"
Capture-Page -role "owner" -menu "vehicles" -page "create" -state "form" -url "/company/vehicles/create"
Capture-Page -role "owner" -menu "vehicles" -page "view" -state "tractor1" -url "/company/vehicles/1"
Capture-Page -role "owner" -menu "vehicles" -page "edit" -state "form" -url "/company/vehicles/1/edit"
Capture-Page -role "owner" -menu "vehicle_sets" -page "list" -state "filled" -url "/company/vehicle-sets"
Capture-Page -role "owner" -menu "vehicle_sets" -page "create" -state "form" -url "/company/vehicle-sets/create"
Capture-Page -role "owner" -menu "vehicle_sets" -page "view" -state "coupling1" -url "/company/vehicle-sets/1"
Capture-Page -role "owner" -menu "vehicle_sets" -page "edit" -state "form" -url "/company/vehicle-sets/1/edit"
Capture-Page -role "owner" -menu "dvb" -page "list" -state "filled" -url "/company/driver-vehicle-blocks"
Capture-Page -role "owner" -menu "dvb" -page "create" -state "form" -url "/company/driver-vehicle-blocks/create"
Capture-Page -role "owner" -menu "dvb" -page "view" -state "block1" -url "/company/driver-vehicle-blocks/1"
Capture-Page -role "owner" -menu "dvb" -page "edit" -state "form" -url "/company/driver-vehicle-blocks/1/edit"
Capture-Page -role "owner" -menu "crews" -page "list" -state "filled" -url "/company/crews"
Capture-Page -role "owner" -menu "crews" -page "create" -state "form" -url "/company/crews/create"
Capture-Page -role "owner" -menu "crews" -page "view" -state "crew1" -url "/company/crews/1"
Capture-Page -role "owner" -menu "crews" -page "edit" -state "form" -url "/company/crews/1/edit"
Capture-Page -role "owner" -menu "documents" -page "list" -state "filled" -url "/company/documents"
Capture-Page -role "owner" -menu "documents" -page "upload" -state "form" -url "/company/documents/upload"

# --- LOGIST 1 ---
Write-Host "`n=== LOGIST RUNTIME 1 ==="
Cdp-Login -login "logist_runtime_1" -password "pass1111" -role "logist1"

Capture-Page -role "logist1" -menu "contractors" -page "list" -state "own" -url "/company/contractors"
Capture-Page -role "logist1" -menu "contractors" -page "view" -state "own_contractor1" -url "/company/contractors/1"
Capture-Page -role "logist1" -menu "drivers" -page "list" -state "own" -url "/company/drivers"
Capture-Page -role "logist1" -menu "drivers" -page "view" -state "own_driver1" -url "/company/drivers/1"
Capture-Page -role "logist1" -menu "vehicles" -page "list" -state "own" -url "/company/vehicles"
Capture-Page -role "logist1" -menu "vehicles" -page "view" -state "own_tractor1" -url "/company/vehicles/1"
Capture-Page -role "logist1" -menu "vehicle_sets" -page "list" -state "own" -url "/company/vehicle-sets"
Capture-Page -role "logist1" -menu "vehicle_sets" -page "view" -state "own_coupling1" -url "/company/vehicle-sets/1"
Capture-Page -role "logist1" -menu "dvb" -page "list" -state "own" -url "/company/driver-vehicle-blocks"
Capture-Page -role "logist1" -menu "dvb" -page "view" -state "own_block1" -url "/company/driver-vehicle-blocks/1"
Capture-Page -role "logist1" -menu "crews" -page "list" -state "own" -url "/company/crews"
Capture-Page -role "logist1" -menu "crews" -page "view" -state "own_crew1" -url "/company/crews/1"

# --- LOGIST 2 (grant access) ---
Write-Host "`n=== LOGIST RUNTIME 2 ==="
Cdp-Login -login "logist_runtime_2" -password "pass2222" -role "logist2"

Capture-Page -role "logist2" -menu "contractors" -page "list" -state "grant" -url "/company/contractors"
Capture-Page -role "logist2" -menu "drivers" -page "list" -state "grant" -url "/company/drivers"
Capture-Page -role "logist2" -menu "vehicles" -page "list" -state "grant" -url "/company/vehicles"
Capture-Page -role "logist2" -menu "vehicle_sets" -page "list" -state "grant" -url "/company/vehicle-sets"
Capture-Page -role "logist2" -menu "dvb" -page "list" -state "grant" -url "/company/driver-vehicle-blocks"
Capture-Page -role "logist2" -menu "crews" -page "list" -state "grant" -url "/company/crews"
Capture-Page -role "logist2" -menu "crews" -page "view" -state "grant_crew1" -url "/company/crews/1"

# === SUMMARY ===
Write-Host "`n========================================"
Write-Host "CAPTURE SUMMARY"
Write-Host "========================================"
$ok = ($results | Where-Object { $_.success }).Count
$fail = ($results | Where-Object { -not $_.success }).Count
Write-Host "Total: $($results.Count) | OK: $ok | FAILED: $fail"
Write-Host "Output: $outDir"

# Cleanup
Write-Host "`nStopping Edge..."
Stop-Process -Id $edgeProc.Id -Force -ErrorAction SilentlyContinue
if (Test-Path $userDataDir) { Remove-Item $userDataDir -Recurse -Force -ErrorAction SilentlyContinue }
Write-Host "Done."
