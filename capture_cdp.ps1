# ERP PLANEX Screenshot Capture via Edge CDP (Chrome DevTools Protocol)
# This script starts ONE Edge instance and captures all screenshots in sequence.

param(
    [string]$BaseUrl = "http://127.0.0.1:8016",
    [string]$OutputDir = "docs\design-audit\full-ui-revision\screenshots",
    [string]$EdgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    [int]$DebugPort = 9223
)

$ErrorActionPreference = "Continue"
$workdir = "C:\Users\Vladimir\Desktop\PLANEX\SITE\erp"
$outDir = Join-Path $workdir $OutputDir
$userDataDir = Join-Path $workdir "tmp_edge_cdp_profile"
$tmpDir = Join-Path $workdir "tmp_cdp"

# Cleanup from previous runs
if (Test-Path $userDataDir) { Remove-Item $userDataDir -Recurse -Force -ErrorAction SilentlyContinue }
if (Test-Path $tmpDir) { Remove-Item $tmpDir -Recurse -Force -ErrorAction SilentlyContinue }
New-Item -ItemType Directory -Path $userDataDir -Force | Out-Null
New-Item -ItemType Directory -Path $tmpDir -Force | Out-Null
New-Item -ItemType Directory -Path $outDir -Force | Out-Null

# Start Edge with remote debugging
Write-Host "Starting Edge with remote debugging on port $DebugPort..."
$edgeArgs = @(
    "--headless=new",
    "--user-data-dir=$userDataDir",
    "--remote-debugging-port=$DebugPort",
    "--window-size=1440,900",
    "--disable-gpu",
    "--no-sandbox",
    "--disable-extensions",
    "about:blank"
)
$edgeProcess = Start-Process -FilePath $EdgePath -ArgumentList $edgeArgs -PassThru -NoNewWindow
Start-Sleep -Seconds 3

# Helper: Get CDP WebSocket URL
function Get-CDPEndpoint {
    try {
        $response = Invoke-RestMethod -Uri "http://localhost:$DebugPort/json" -Method GET -TimeoutSec 5
        if ($response -and $response.Count -gt 0) {
            return $response[0].webSocketDebuggerUrl
        }
    } catch {}
    return $null
}

# Helper: Send CDP command via WebSocket
function Send-CDPCommand {
    param($wsUrl, $method, $params = @{})
    
    $id = Get-Random -Minimum 1 -Maximum 100000
    $message = @{
        id = $id
        method = $method
        params = $params
    } | ConvertTo-Json -Depth 5 -Compress
    
    $tmpFile = Join-Path $tmpDir "cdp_cmd_$id.json"
    $tmpOut = Join-Path $tmpDir "cdp_out_$id.json"
    Set-Content -Path $tmpFile -Value $message -Encoding UTF8
    
    # Use a simple PowerShell WebSocket helper via .NET
    try {
        $ws = New-Object System.Net.WebSockets.ClientWebSocket
        $ct = New-Object System.Threading.CancellationToken
        $connectTask = $ws.ConnectAsync([Uri]$wsUrl, $ct)
        
        $timeout = [System.Threading.Tasks.Task]::WhenAny($connectTask, [System.Threading.Tasks.Task]::Delay(5000))
        if ($timeout.Result -ne $connectTask) { 
            $ws.Dispose()
            return $null 
        }
        if ($ws.State -ne 'Open') {
            $ws.Dispose()
            return $null
        }
        
        # Send
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($message)
        $segment = [ArraySegment[byte]]::new($bytes)
        $sendTask = $ws.SendAsync($segment, [System.Net.WebSockets.WebSocketMessageType]::Text, $true, $ct)
        [System.Threading.Tasks.Task]::WhenAny($sendTask, [System.Threading.Tasks.Task]::Delay(5000)).Wait()
        
        # Receive
        $buffer = [byte[]]::new(65536)
        $result = $null
        $fullResponse = ""
        do {
            $recvTask = $ws.ReceiveAsync([ArraySegment[byte]]::new($buffer), $ct)
            [System.Threading.Tasks.Task]::WhenAny($recvTask, [System.Threading.Tasks.Task]::Delay(5000)).Wait()
            if ($recvTask.IsCompleted) {
                $result = $recvTask.Result
                $fullResponse += [System.Text.Encoding]::UTF8.GetString($buffer, 0, $result.Count)
            } else {
                break
            }
        } while (-not $result.EndOfMessage)
        
        $ws.Dispose()
        return $fullResponse | ConvertFrom-Json -ErrorAction SilentlyContinue
    } catch {
        return $null
    }
}

# Get CDP endpoint
$wsUrl = Get-CDPEndpoint
if (-not $wsUrl) {
    Write-Host "ERROR: Could not get CDP WebSocket URL. Is Edge running?"
    Stop-Process -Id $edgeProcess.Id -Force -ErrorAction SilentlyContinue
    exit 1
}
Write-Host "CDP connected: $wsUrl"

# Enable Page domain and navigate
function Navigate-To($url) {
    $result = Send-CDPCommand -wsUrl $wsUrl -method "Page.navigate" -params @{url=$url}
    if ($result) { Start-Sleep -Seconds 2 } # Wait for page load
    return $result
}

function Take-Screenshot($filePath) {
    $result = Send-CDPCommand -wsUrl $wsUrl -method "Page.captureScreenshot" -params @{format="png"; clip=@{x=0; y=0; width=1440; height=900; scale=1}}
    if ($result -and $result.result -and $result.result.data) {
        $bytes = [Convert]::FromBase64String($result.result.data)
        [System.IO.File]::WriteAllBytes($filePath, $bytes)
        return $true
    }
    return $false
}

# Login as superadmin by loading login page, then POST via Evaluate
Write-Host "`n=== Logging in as superadmin ==="
Navigate-To("$BaseUrl/login")
Start-Sleep -Seconds 2

# Execute login form submission via JavaScript
$loginJs = @"
document.querySelector('input[name="login"]').value = 'admin@planex.local';
document.querySelector('input[name="password"]').value = 'admin123';
document.querySelector('form').submit();
"@
Send-CDPCommand -wsUrl $wsUrl -method "Runtime.evaluate" -params @{expression=$loginJs}
Start-Sleep -Seconds 3

# Verify login by checking current URL
$checkUrl = Send-CDPCommand -wsUrl $wsUrl -method "Runtime.evaluate" -params @{expression="document.location.href"; returnByValue=$true}
Write-Host "Current URL after login: $($checkUrl | ConvertTo-Json -Depth 3)"

# Take test screenshot
$testPath = Join-Path $outDir "cdp_test.png"
$screenshotOk = Take-Screenshot($testPath)
if ($screenshotOk -and (Test-Path $testPath)) {
    Write-Host "CDP Screenshot: $((Get-Item $testPath).Length) bytes"
} else {
    Write-Host "CDP Screenshot FAILED"
}

# Cleanup
Write-Host "`nStopping Edge..."
Stop-Process -Id $edgeProcess.Id -Force -ErrorAction SilentlyContinue
if (Test-Path $userDataDir) { Remove-Item $userDataDir -Recurse -Force -ErrorAction SilentlyContinue }
if (Test-Path $tmpDir) { Remove-Item $tmpDir -Recurse -Force -ErrorAction SilentlyContinue }

Write-Host "Done."
