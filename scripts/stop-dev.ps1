param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$metadataPath = Join-Path (Join-Path $rootDir '.run') 'dev-processes.json'

if (-not (Test-Path $metadataPath)) {
    Write-Host 'No launcher metadata file was found. The workspace is probably not running from this launcher.' -ForegroundColor Yellow
    exit 0
}

$entries = @(Get-Content -Raw $metadataPath | ConvertFrom-Json)

if ($entries.Count -eq 0) {
    if (-not $DryRun) {
        Remove-Item $metadataPath -Force
    }

    Write-Host 'There are no tracked processes to stop.' -ForegroundColor Yellow
    exit 0
}

foreach ($entry in $entries) {
    $process = Get-Process -Id $entry.Pid -ErrorAction SilentlyContinue

    if ($null -eq $process) {
        Write-Host ('Skipping {0}: PID {1} is already gone.' -f $entry.Name, $entry.Pid) -ForegroundColor Yellow
        continue
    }

    if ($DryRun) {
        Write-Host ('Dry run: would stop {0} (PID {1})' -f $entry.Name, $entry.Pid)
        continue
    }

    Stop-Process -Id $entry.Pid -Force
    Write-Host ('Stopped {0} (PID {1})' -f $entry.Name, $entry.Pid) -ForegroundColor Green
}

if (-not $DryRun) {
    Remove-Item $metadataPath -Force
}
