param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$runDir = Join-Path $rootDir '.run'
$metadataPath = Join-Path $runDir 'dev-processes.json'

function New-ServiceDefinition {
    param(
        [string]$Name,
        [string]$Workdir,
        [int]$Port = 0,
        [string]$Url = '',
        [string]$Command
    )

    return [pscustomobject]@{
        Name = $Name
        Workdir = $Workdir
        Port = $Port
        Url = $Url
        Command = $Command
    }
}

$services = @(
    (New-ServiceDefinition `
        -Name 'Auth Service' `
        -Workdir (Join-Path $rootDir 'services\auth-service') `
        -Port 8000 `
        -Url 'http://127.0.0.1:8000' `
        -Command 'php artisan serve --host=127.0.0.1 --port=8000'),
    (New-ServiceDefinition `
        -Name 'Event Service' `
        -Workdir (Join-Path $rootDir 'services\event-service') `
        -Port 8001 `
        -Url 'http://127.0.0.1:8001' `
        -Command 'php artisan serve --host=127.0.0.1 --port=8001'),
    (New-ServiceDefinition `
        -Name 'Event Scheduler' `
        -Workdir (Join-Path $rootDir 'services\event-service') `
        -Command 'php artisan schedule:work'),
    (New-ServiceDefinition `
        -Name 'Event Auth Projection Consumer' `
        -Workdir (Join-Path $rootDir 'services\event-service') `
        -Command 'php artisan rabbitmq:consume-auth-users --idle-timeout=0'),
    (New-ServiceDefinition `
        -Name 'Halls Service' `
        -Workdir (Join-Path $rootDir 'services\halls-service') `
        -Port 8002 `
        -Url 'http://127.0.0.1:8002' `
        -Command 'php artisan serve --host=127.0.0.1 --port=8002'),
    (New-ServiceDefinition `
        -Name 'Booking Service' `
        -Workdir (Join-Path $rootDir 'services\booking-service') `
        -Port 8003 `
        -Url 'http://127.0.0.1:8003' `
        -Command 'php artisan serve --host=127.0.0.1 --port=8003'),
    (New-ServiceDefinition `
        -Name 'Booking Scheduler' `
        -Workdir (Join-Path $rootDir 'services\booking-service') `
        -Command 'php artisan schedule:work'),
    (New-ServiceDefinition `
        -Name 'Booking Ticket Worker' `
        -Workdir (Join-Path $rootDir 'services\booking-service') `
        -Command 'php artisan rabbitmq:consume-ticket-jobs --idle-timeout=0'),
    (New-ServiceDefinition `
        -Name 'Recommendation Service' `
        -Workdir (Join-Path $rootDir 'services\recommendation-service') `
        -Port 8004 `
        -Url 'http://127.0.0.1:8004' `
        -Command 'powershell -ExecutionPolicy Bypass -File .\start-local.ps1 -ListenHost 127.0.0.1 -Port 8004'),
    (New-ServiceDefinition `
        -Name 'Admin Service' `
        -Workdir (Join-Path $rootDir 'services\admin-service') `
        -Port 8005 `
        -Url 'http://127.0.0.1:8005' `
        -Command 'php artisan serve --host=127.0.0.1 --port=8005'),
    (New-ServiceDefinition `
        -Name 'Frontend' `
        -Workdir (Join-Path $rootDir 'frontend\vue-project') `
        -Port 5173 `
        -Url 'http://127.0.0.1:5173' `
        -Command 'npm run dev -- --host 127.0.0.1 --port 5173')
)

function Get-ListeningProcessId {
    param(
        [int]$Port
    )

    try {
        $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction Stop |
            Select-Object -First 1

        return $connection.OwningProcess
    } catch {
        return $null
    }
}

function Test-PortListening {
    param(
        [int]$Port
    )

    return $null -ne (Get-ListeningProcessId -Port $Port)
}

if (Test-Path $metadataPath) {
    $existingEntries = @(Get-Content -Raw $metadataPath | ConvertFrom-Json)
    $runningEntries = @()

    foreach ($entry in $existingEntries) {
        if ($null -ne (Get-Process -Id $entry.Pid -ErrorAction SilentlyContinue)) {
            $runningEntries += $entry
        }
    }

    if ($runningEntries.Count -gt 0) {
        Write-Host 'Launcher metadata already exists and some processes are still running.' -ForegroundColor Yellow
        Write-Host 'Run npm run dev:stop first, then start again.' -ForegroundColor Yellow
        exit 1
    }

    Remove-Item $metadataPath -Force
}

$busyPorts = @()

foreach ($service in $services) {
    if ($service.Port -le 0) {
        continue
    }

    $processId = Get-ListeningProcessId -Port $service.Port

    if ($null -ne $processId) {
        $process = Get-Process -Id $processId -ErrorAction SilentlyContinue
        $processName = 'PID {0}' -f $processId

        if ($null -ne $process) {
            $processName = $process.ProcessName
        }

        $busyPorts += [pscustomobject]@{
            Name = $service.Name
            Port = $service.Port
            Process = $processName
        }
    }
}

if ($busyPorts.Count -gt 0) {
    Write-Host 'Cannot start the workspace because some required ports are already in use.' -ForegroundColor Red

    foreach ($item in $busyPorts) {
        Write-Host (' - {0}: port {1} is already used by {2}' -f $item.Name, $item.Port, $item.Process) -ForegroundColor Red
    }

    Write-Host 'Free the ports or run npm run dev:stop if these are old project windows.' -ForegroundColor Yellow
    exit 1
}

if ($DryRun) {
    Write-Host 'Dry run: these commands will be started.' -ForegroundColor Cyan

    foreach ($service in $services) {
        Write-Host (' - {0} -> {1}' -f $service.Name, $service.Command)
        Write-Host ('   cwd: {0}' -f $service.Workdir)
    }

    if (-not (Test-PortListening -Port 3306)) {
        Write-Host 'Warning: local MySQL on port 3306 was not detected.' -ForegroundColor Yellow
    }

    if (-not (Test-PortListening -Port 5672)) {
        Write-Host 'Warning: local RabbitMQ on port 5672 was not detected.' -ForegroundColor Yellow
    }

    exit 0
}

New-Item -Path $runDir -ItemType Directory -Force | Out-Null

$startedProcesses = @()

foreach ($service in $services) {
    $windowTitle = 'SubMeet - {0}' -f $service.Name
    $command = "Set-Location '$($service.Workdir)'; `$host.UI.RawUI.WindowTitle = '$windowTitle'; Write-Host 'Starting $($service.Name)...' -ForegroundColor Cyan; $($service.Command)"

    $process = Start-Process `
        -FilePath 'powershell' `
        -ArgumentList @('-NoExit', '-ExecutionPolicy', 'Bypass', '-Command', $command) `
        -PassThru

    $startedProcesses += [pscustomobject]@{
        Name = $service.Name
        Port = $service.Port
        Url = $service.Url
        Pid = $process.Id
        Workdir = $service.Workdir
    }

    Start-Sleep -Milliseconds 250
}

$startedProcesses | ConvertTo-Json -Depth 3 | Set-Content -Path $metadataPath -Encoding UTF8

Write-Host 'Workspace processes have been started in separate PowerShell windows.' -ForegroundColor Green
Write-Host ''

foreach ($service in $services) {
    if ([string]::IsNullOrWhiteSpace($service.Url)) {
        Write-Host (' - {0}' -f $service.Name) -ForegroundColor Green
    } else {
        Write-Host (' - {0}: {1}' -f $service.Name, $service.Url) -ForegroundColor Green
    }
}

Write-Host ''
Write-Host 'Stop them later with: npm run dev:stop' -ForegroundColor Cyan

if (-not (Test-PortListening -Port 3306)) {
    Write-Host 'Warning: local MySQL on port 3306 was not detected.' -ForegroundColor Yellow
}

if (-not (Test-PortListening -Port 5672)) {
    Write-Host 'Warning: local RabbitMQ on port 5672 was not detected.' -ForegroundColor Yellow
}
