param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$metadataPath = Join-Path (Join-Path (Join-Path $rootDir '.run') 'observability-local') 'processes.json'

function Read-MetadataEntries {
    param(
        [string]$Path
    )

    $parsed = Get-Content -Raw $Path | ConvertFrom-Json

    if ($parsed -is [System.Array]) {
        return $parsed
    }

    return @($parsed)
}

function Stop-TrackedProcess {
    param(
        [string]$Name,
        [object]$ProcessId,
        [switch]$DryRun
    )

    $resolvedPid = if ($ProcessId -is [System.Array]) { [int]$ProcessId[0] } else { [int]$ProcessId }
    $process = Get-Process -Id $resolvedPid -ErrorAction SilentlyContinue

    if ($null -eq $process) {
        Write-Host ('Skipping {0}: PID {1} is already gone.' -f $Name, $resolvedPid) -ForegroundColor Yellow
        return
    }

    if ($DryRun) {
        Write-Host ('Dry run: would stop {0} (PID {1})' -f $Name, $resolvedPid)
        return
    }

    Stop-Process -Id $resolvedPid -Force
    Write-Host ('Stopped {0} (PID {1})' -f $Name, $resolvedPid) -ForegroundColor Green
}

if (-not (Test-Path $metadataPath)) {
    Write-Host 'No local observability metadata file was found. The stack is probably not running from this launcher.' -ForegroundColor Yellow
    exit 0
}

$entries = Read-MetadataEntries -Path $metadataPath

if ($entries.Count -eq 0) {
    if (-not $DryRun) {
        Remove-Item $metadataPath -Force
    }

    Write-Host 'There are no tracked local observability processes to stop.' -ForegroundColor Yellow
    exit 0
}

foreach ($entry in $entries) {
    Stop-TrackedProcess -Name $entry.Name -ProcessId $entry.Pid -DryRun:$DryRun
}

$portFallbacks = @(3000, 3100, 9080)

foreach ($port in $portFallbacks) {
    $connection = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1

    if ($null -eq $connection) {
        continue
    }

    $processInfo = Get-CimInstance Win32_Process -Filter "ProcessId=$($connection.OwningProcess)" -ErrorAction SilentlyContinue

    if ($null -eq $processInfo) {
        continue
    }

    $commandLine = [string]$processInfo.CommandLine

    if ($commandLine -notlike '*observability-local*') {
        continue
    }

    Stop-TrackedProcess -Name ("Port $port fallback") -ProcessId $connection.OwningProcess -DryRun:$DryRun
}

if (-not $DryRun) {
    Remove-Item $metadataPath -Force
}
