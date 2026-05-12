param(
    [switch]$DryRun,
    [switch]$NoDownload,
    [string]$GrafanaVersion = '11.2.2',
    [string]$LokiVersion = '3.2.1',
    [string]$PromtailVersion = '3.2.1',
    [int]$GrafanaPort = 3000,
    [int]$LokiPort = 3100,
    [int]$PromtailPort = 9080
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$runDir = Join-Path $rootDir '.run'
$stackDir = Join-Path $runDir 'observability-local'
$downloadsDir = Join-Path $stackDir 'downloads'
$toolsDir = Join-Path $stackDir 'tools'
$configDir = Join-Path $stackDir 'config'
$logsDir = Join-Path $stackDir 'logs'
$dataDir = Join-Path $stackDir 'data'
$metadataPath = Join-Path $stackDir 'processes.json'

function Convert-ToPosixPath {
    param(
        [string]$Path
    )

    return ($Path -replace '\\', '/')
}

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

function Get-PortOwnerName {
    param(
        [int]$Port
    )

    $processId = Get-ListeningProcessId -Port $Port

    if ($null -eq $processId) {
        return $null
    }

    $process = Get-Process -Id $processId -ErrorAction SilentlyContinue

    if ($null -eq $process) {
        return "PID $processId"
    }

    return $process.ProcessName
}

function Wait-ForListeningProcessId {
    param(
        [int]$Port,
        [int]$TimeoutSeconds = 45
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    do {
        $processId = Get-ListeningProcessId -Port $Port

        if ($null -ne $processId) {
            return $processId
        }

        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)

    return $null
}

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

function Ensure-Directory {
    param(
        [string]$Path
    )

    New-Item -ItemType Directory -Path $Path -Force | Out-Null
}

function Find-Executable {
    param(
        [string]$SearchRoot,
        [string]$Filter
    )

    if (-not (Test-Path $SearchRoot)) {
        return $null
    }

    $match = Get-ChildItem -Path $SearchRoot -Filter $Filter -Recurse -File -ErrorAction SilentlyContinue |
        Select-Object -First 1

    if ($null -eq $match) {
        return $null
    }

    return $match.FullName
}

function Ensure-DownloadedArchive {
    param(
        [string]$Name,
        [string]$Url,
        [string]$ArchivePath,
        [string]$ExtractPath
    )

    if ($DryRun) {
        Write-Host ("Dry run: would ensure {0} from {1}" -f $Name, $Url) -ForegroundColor Cyan
        return
    }

    Ensure-Directory -Path (Split-Path -Parent $ArchivePath)
    Ensure-Directory -Path $ExtractPath

    if (-not (Test-Path $ArchivePath)) {
        Write-Host ("Downloading {0}..." -f $Name) -ForegroundColor Cyan
        Invoke-WebRequest -Uri $Url -OutFile $ArchivePath
    }

    if ((Get-ChildItem -Path $ExtractPath -Force | Measure-Object).Count -eq 0) {
        Write-Host ("Extracting {0}..." -f $Name) -ForegroundColor Cyan
        Expand-Archive -LiteralPath $ArchivePath -DestinationPath $ExtractPath -Force
    }
}

function Write-LokiConfig {
    param(
        [string]$Path
    )

    $lokiDataRoot = Convert-ToPosixPath (Join-Path $dataDir 'loki')
    $lokiChunksDir = Convert-ToPosixPath (Join-Path $dataDir 'loki\chunks')
    $lokiRulesDir = Convert-ToPosixPath (Join-Path $dataDir 'loki\rules')
    $lokiCompactorDir = Convert-ToPosixPath (Join-Path $dataDir 'loki\compactor')

    $content = @"
auth_enabled: false

server:
  http_listen_port: $LokiPort
  grpc_listen_port: 9096

common:
  instance_addr: 127.0.0.1
  path_prefix: '$lokiDataRoot'
  storage:
    filesystem:
      chunks_directory: '$lokiChunksDir'
      rules_directory: '$lokiRulesDir'
  replication_factor: 1
  ring:
    kvstore:
      store: inmemory

schema_config:
  configs:
    - from: 2024-01-01
      store: tsdb
      object_store: filesystem
      schema: v13
      index:
        prefix: index_
        period: 24h

storage_config:
  filesystem:
    directory: '$lokiChunksDir'

limits_config:
  reject_old_samples: true
  reject_old_samples_max_age: 168h
  allow_structured_metadata: true

compactor:
  working_directory: '$lokiCompactorDir'

analytics:
  reporting_enabled: false
"@

    Set-Content -Path $Path -Value $content -Encoding UTF8
}

function Write-PromtailConfig {
    param(
        [string]$Path
    )

    $positionsPath = Convert-ToPosixPath (Join-Path $dataDir 'promtail\positions.yaml')
    $authLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\auth-service\storage\logs\*structured*.log*')
    $eventLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\event-service\storage\logs\*structured*.log*')
    $hallsLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\halls-service\storage\logs\*structured*.log*')
    $bookingLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\booking-service\storage\logs\*structured*.log*')
    $adminLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\admin-service\storage\logs\*structured*.log*')
    $recommendationLogs = Convert-ToPosixPath (Join-Path $rootDir 'services\recommendation-service\logs\*structured*.log*')

    $content = @"
server:
  http_listen_port: $PromtailPort
  grpc_listen_port: 0

positions:
  filename: '$positionsPath'

clients:
  - url: http://127.0.0.1:$LokiPort/loki/api/v1/push

scrape_configs:
  - job_name: auth-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: auth-service
          __path__: '$authLogs'

  - job_name: event-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: event-service
          __path__: '$eventLogs'

  - job_name: halls-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: halls-service
          __path__: '$hallsLogs'

  - job_name: booking-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: booking-service
          __path__: '$bookingLogs'

  - job_name: admin-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: admin-service
          __path__: '$adminLogs'

  - job_name: recommendation-service
    static_configs:
      - targets: [localhost]
        labels:
          stack: submeet
          service: recommendation-service
          __path__: '$recommendationLogs'
"@

    Set-Content -Path $Path -Value $content -Encoding UTF8
}

function Write-GrafanaDatasourceConfig {
    param(
        [string]$Path
    )

    $content = @"
apiVersion: 1

datasources:
  - name: Loki
    uid: submeet-loki
    type: loki
    access: proxy
    url: http://127.0.0.1:$LokiPort
    isDefault: true
    editable: false
"@

    Set-Content -Path $Path -Value $content -Encoding UTF8
}

function Write-GrafanaIni {
    param(
        [string]$Path,
        [string]$ProvisioningDir,
        [string]$GrafanaDataDir,
        [string]$GrafanaLogsDir,
        [string]$GrafanaPluginsDir
    )

    $content = @"
[server]
http_addr = 127.0.0.1
http_port = $GrafanaPort

[security]
admin_user = admin
admin_password = admin

[users]
allow_sign_up = false

[analytics]
reporting_enabled = false

[paths]
data = $GrafanaDataDir
logs = $GrafanaLogsDir
plugins = $GrafanaPluginsDir
provisioning = $ProvisioningDir
"@

    Set-Content -Path $Path -Value $content -Encoding UTF8
}

if (Test-Path $metadataPath) {
    $existingEntries = Read-MetadataEntries -Path $metadataPath
    $runningEntries = @()

    foreach ($entry in $existingEntries) {
        if ($null -ne (Get-Process -Id $entry.Pid -ErrorAction SilentlyContinue)) {
            $runningEntries += $entry
        }
    }

    if ($runningEntries.Count -gt 0) {
        Write-Host 'Observability local metadata already exists and some processes are still running.' -ForegroundColor Yellow
        Write-Host 'Run npm run observability:local:down first, then start again.' -ForegroundColor Yellow
        exit 1
    }

    Remove-Item $metadataPath -Force
}

$requiredPorts = @(
    [pscustomobject]@{ Name = 'Grafana'; Port = $GrafanaPort },
    [pscustomobject]@{ Name = 'Loki'; Port = $LokiPort },
    [pscustomobject]@{ Name = 'Promtail'; Port = $PromtailPort }
)

$busyPorts = @()

foreach ($item in $requiredPorts) {
    $owner = Get-PortOwnerName -Port $item.Port

    if ($null -ne $owner) {
        $busyPorts += [pscustomobject]@{
            Name = $item.Name
            Port = $item.Port
            Process = $owner
        }
    }
}

if ($busyPorts.Count -gt 0) {
    Write-Host 'Cannot start local observability because some required ports are already in use.' -ForegroundColor Red

    foreach ($item in $busyPorts) {
        Write-Host (' - {0}: port {1} is already used by {2}' -f $item.Name, $item.Port, $item.Process) -ForegroundColor Red
    }

    Write-Host 'Free the ports or stop the old local observability stack first.' -ForegroundColor Yellow
    exit 1
}

$grafanaUrl = "https://dl.grafana.com/oss/release/grafana-$GrafanaVersion.windows-amd64.zip"
$lokiUrl = "https://github.com/grafana/loki/releases/download/v$LokiVersion/loki-windows-amd64.exe.zip"
$promtailUrl = "https://github.com/grafana/loki/releases/download/v$PromtailVersion/promtail-windows-amd64.exe.zip"

$grafanaArchive = Join-Path $downloadsDir "grafana-$GrafanaVersion.windows-amd64.zip"
$lokiArchive = Join-Path $downloadsDir "loki-$LokiVersion.windows-amd64.zip"
$promtailArchive = Join-Path $downloadsDir "promtail-$PromtailVersion.windows-amd64.zip"

$grafanaExtractDir = Join-Path $toolsDir "grafana-$GrafanaVersion"
$lokiExtractDir = Join-Path $toolsDir "loki-$LokiVersion"
$promtailExtractDir = Join-Path $toolsDir "promtail-$PromtailVersion"

if (-not $NoDownload) {
    Ensure-DownloadedArchive -Name 'Grafana' -Url $grafanaUrl -ArchivePath $grafanaArchive -ExtractPath $grafanaExtractDir
    Ensure-DownloadedArchive -Name 'Loki' -Url $lokiUrl -ArchivePath $lokiArchive -ExtractPath $lokiExtractDir
    Ensure-DownloadedArchive -Name 'Promtail' -Url $promtailUrl -ArchivePath $promtailArchive -ExtractPath $promtailExtractDir
}

$grafanaExe = Find-Executable -SearchRoot $grafanaExtractDir -Filter 'grafana.exe'
$grafanaServerExe = Find-Executable -SearchRoot $grafanaExtractDir -Filter 'grafana-server.exe'
$lokiExe = Find-Executable -SearchRoot $lokiExtractDir -Filter 'loki-windows-amd64.exe'
$promtailExe = Find-Executable -SearchRoot $promtailExtractDir -Filter 'promtail-windows-amd64.exe'

if ($DryRun) {
    Write-Host 'Dry run: local observability stack will use the following artifacts.' -ForegroundColor Cyan
    Write-Host (" - Grafana URL: {0}" -f $grafanaUrl)
    Write-Host (" - Loki URL: {0}" -f $lokiUrl)
    Write-Host (" - Promtail URL: {0}" -f $promtailUrl)
    Write-Host (" - Metadata: {0}" -f $metadataPath)
    exit 0
}

if ($NoDownload) {
    if (($null -eq $grafanaExe -and $null -eq $grafanaServerExe) -or $null -eq $lokiExe -or $null -eq $promtailExe) {
        Write-Host 'Local observability binaries were not found, and -NoDownload was specified.' -ForegroundColor Red
        exit 1
    }
}

if (($null -eq $grafanaExe -and $null -eq $grafanaServerExe) -or $null -eq $lokiExe -or $null -eq $promtailExe) {
    Write-Host 'Some local observability binaries are still missing after download/extraction.' -ForegroundColor Red
    Write-Host ("Grafana: {0}" -f $(if ($grafanaExe) { $grafanaExe } elseif ($grafanaServerExe) { $grafanaServerExe } else { 'missing' })) -ForegroundColor Red
    Write-Host ("Loki: {0}" -f $(if ($lokiExe) { $lokiExe } else { 'missing' })) -ForegroundColor Red
    Write-Host ("Promtail: {0}" -f $(if ($promtailExe) { $promtailExe } else { 'missing' })) -ForegroundColor Red
    exit 1
}

Ensure-Directory -Path $runDir
Ensure-Directory -Path $stackDir
Ensure-Directory -Path $configDir
Ensure-Directory -Path $logsDir
Ensure-Directory -Path (Join-Path $dataDir 'loki\chunks')
Ensure-Directory -Path (Join-Path $dataDir 'loki\rules')
Ensure-Directory -Path (Join-Path $dataDir 'loki\compactor')
Ensure-Directory -Path (Join-Path $dataDir 'promtail')
Ensure-Directory -Path (Join-Path $dataDir 'grafana')
Ensure-Directory -Path (Join-Path $dataDir 'grafana-logs')
Ensure-Directory -Path (Join-Path $dataDir 'grafana-plugins')
Ensure-Directory -Path (Join-Path $configDir 'grafana\provisioning\datasources')

$lokiConfigPath = Join-Path $configDir 'loki-config.local.yml'
$promtailConfigPath = Join-Path $configDir 'promtail-config.local.yml'
$grafanaDatasourcePath = Join-Path $configDir 'grafana\provisioning\datasources\loki.yml'
$grafanaIniPath = Join-Path $configDir 'grafana\grafana.ini'

Write-LokiConfig -Path $lokiConfigPath
Write-PromtailConfig -Path $promtailConfigPath
Write-GrafanaDatasourceConfig -Path $grafanaDatasourcePath
Write-GrafanaIni `
    -Path $grafanaIniPath `
    -ProvisioningDir (Join-Path $configDir 'grafana\provisioning') `
    -GrafanaDataDir (Join-Path $dataDir 'grafana') `
    -GrafanaLogsDir (Join-Path $dataDir 'grafana-logs') `
    -GrafanaPluginsDir (Join-Path $dataDir 'grafana-plugins')

$grafanaHomeSource = if ($null -ne $grafanaExe) { $grafanaExe } else { $grafanaServerExe }
$grafanaHome = Split-Path -Parent (Split-Path -Parent $grafanaHomeSource)

$processDefinitions = @(
    [pscustomobject]@{
        Name = 'Loki'
        FilePath = $lokiExe
        Arguments = @("-config.file=$lokiConfigPath")
        Workdir = Split-Path -Parent $lokiExe
        Port = $LokiPort
        Url = "http://127.0.0.1:$LokiPort"
        StdOut = Join-Path $logsDir 'loki.stdout.log'
        StdErr = Join-Path $logsDir 'loki.stderr.log'
    },
    [pscustomobject]@{
        Name = 'Promtail'
        FilePath = $promtailExe
        Arguments = @("-config.file=$promtailConfigPath")
        Workdir = Split-Path -Parent $promtailExe
        Port = $PromtailPort
        Url = "http://127.0.0.1:$PromtailPort"
        StdOut = Join-Path $logsDir 'promtail.stdout.log'
        StdErr = Join-Path $logsDir 'promtail.stderr.log'
    },
    [pscustomobject]@{
        Name = 'Grafana'
        FilePath = $(if ($null -ne $grafanaExe) { $grafanaExe } else { $grafanaServerExe })
        Arguments = $(if ($null -ne $grafanaExe) { @('server', '--config', $grafanaIniPath, '--homepath', $grafanaHome) } else { @('--config', $grafanaIniPath, '--homepath', $grafanaHome) })
        Workdir = Split-Path -Parent $grafanaHomeSource
        Port = $GrafanaPort
        Url = "http://127.0.0.1:$GrafanaPort"
        StdOut = Join-Path $logsDir 'grafana.stdout.log'
        StdErr = Join-Path $logsDir 'grafana.stderr.log'
    }
)

$startedProcesses = @()

foreach ($item in $processDefinitions) {
    Write-Host ("Starting {0}..." -f $item.Name) -ForegroundColor Cyan

    $process = Start-Process `
        -FilePath $item.FilePath `
        -ArgumentList $item.Arguments `
        -WorkingDirectory $item.Workdir `
        -RedirectStandardOutput $item.StdOut `
        -RedirectStandardError $item.StdErr `
        -WindowStyle Hidden `
        -PassThru

    $startedProcesses += [pscustomobject]@{
        Name = $item.Name
        Port = $item.Port
        Url = $item.Url
        Pid = $process.Id
        Workdir = $item.Workdir
    }
}

$resolvedProcesses = @()

foreach ($entry in $startedProcesses) {
    $listeningPid = Wait-ForListeningProcessId -Port $entry.Port -TimeoutSeconds 45

    if ($null -eq $listeningPid) {
        Write-Host ("Warning: {0} did not open port {1} within the expected time." -f $entry.Name, $entry.Port) -ForegroundColor Yellow
        $resolvedProcesses += $entry
        continue
    }

    $resolvedProcesses += [pscustomobject]@{
        Name = $entry.Name
        Port = $entry.Port
        Url = $entry.Url
        Pid = $listeningPid
        Workdir = $entry.Workdir
    }
}

$resolvedProcesses | ConvertTo-Json -Depth 3 | Set-Content -Path $metadataPath -Encoding UTF8

Write-Host 'Local observability stack is starting in the background.' -ForegroundColor Green
Write-Host (' - Grafana: http://127.0.0.1:{0} (admin/admin)' -f $GrafanaPort) -ForegroundColor Green
Write-Host (' - Loki: http://127.0.0.1:{0}' -f $LokiPort) -ForegroundColor Green
Write-Host (' - Promtail: http://127.0.0.1:{0}' -f $PromtailPort) -ForegroundColor Green
Write-Host ''
Write-Host 'Stop it later with: npm run observability:local:down' -ForegroundColor Cyan
