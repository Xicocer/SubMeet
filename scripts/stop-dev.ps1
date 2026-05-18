param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$metadataPath = Join-Path (Join-Path $rootDir '.run') 'dev-processes.json'

function Read-LauncherMetadata {
    param(
        [string]$Path
    )

    $parsed = Get-Content -Raw $Path | ConvertFrom-Json

    if ($null -eq $parsed) {
        return @()
    }

    return @($parsed | ForEach-Object { $_ })
}

function Stop-TrackedProcess {
    param(
        [string]$Name,
        [int]$Pid
    )

    $process = Get-Process -Id $Pid -ErrorAction SilentlyContinue

    if ($null -eq $process) {
        Write-Host ('Skipping {0}: PID {1} is already gone.' -f $Name, $Pid) -ForegroundColor Yellow
        return
    }

    if ($DryRun) {
        Write-Host ('Dry run: would stop {0} (PID {1})' -f $Name, $Pid)
        return
    }

    try {
        Stop-Process -Id $Pid -Force -ErrorAction Stop
        Write-Host ('Stopped {0} (PID {1})' -f $Name, $Pid) -ForegroundColor Green
    } catch {
        if ($_.FullyQualifiedErrorId -like 'NoProcessFoundForGivenId,*') {
            Write-Host ('Skipping {0}: PID {1} exited while stopping.' -f $Name, $Pid) -ForegroundColor Yellow
            return
        }

        $process = Get-Process -Id $Pid -ErrorAction SilentlyContinue

        if ($null -eq $process) {
            Write-Host ('Skipping {0}: PID {1} exited while stopping.' -f $Name, $Pid) -ForegroundColor Yellow
            return
        }

        throw
    }
}

if (-not (Test-Path $metadataPath)) {
    $launcherProcesses = @(Get-Process -ErrorAction SilentlyContinue |
        Where-Object { $_.MainWindowTitle -like 'SubMeet - *' })

    if ($launcherProcesses.Count -eq 0) {
        Write-Host 'No launcher metadata file was found. The workspace is probably not running from this launcher.' -ForegroundColor Yellow
        exit 0
    }

    Write-Host 'No launcher metadata file was found, but SubMeet windows are still open. Stopping them by window title.' -ForegroundColor Yellow

    foreach ($process in $launcherProcesses) {
        Stop-TrackedProcess -Name $process.MainWindowTitle -Pid $process.Id
    }

    exit 0
}

$entries = @(Read-LauncherMetadata -Path $metadataPath)

if ($entries.Count -eq 0) {
    if (-not $DryRun) {
        Remove-Item $metadataPath -Force
    }

    Write-Host 'There are no tracked processes to stop.' -ForegroundColor Yellow
    exit 0
}

foreach ($entry in $entries) {
    Stop-TrackedProcess -Name $entry.Name -Pid $entry.Pid
}

if (-not $DryRun) {
    Remove-Item $metadataPath -Force
}
