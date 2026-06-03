param(
    [string]$HostAddress = '127.0.0.1',
    [int]$Port = 7700,
    [string]$MasterKey = 'submeet-meili-key'
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$toolsDir = Join-Path $rootDir '.tools\meilisearch'
$runDir = Join-Path $rootDir '.run'
$dbPath = Join-Path $runDir 'meilisearch'
$exePath = Join-Path $toolsDir 'meilisearch.exe'

New-Item -Path $toolsDir -ItemType Directory -Force | Out-Null
New-Item -Path $dbPath -ItemType Directory -Force | Out-Null

if (-not (Test-Path $exePath)) {
    Write-Host 'Downloading Meilisearch open-source binary...' -ForegroundColor Cyan

    $release = Invoke-RestMethod `
        -Uri 'https://api.github.com/repos/meilisearch/meilisearch/releases/latest' `
        -Headers @{ 'User-Agent' = 'SubMeet-Meilisearch-Launcher' }

    $asset = $release.assets |
        Where-Object { $_.name -eq 'meilisearch-windows-amd64.exe' } |
        Select-Object -First 1

    if ($null -eq $asset) {
        throw 'Could not find meilisearch-windows-amd64.exe in the latest Meilisearch release.'
    }

    Invoke-WebRequest -Uri $asset.browser_download_url -OutFile $exePath
}

& $exePath `
    --master-key $MasterKey `
    --http-addr ("{0}:{1}" -f $HostAddress, $Port) `
    --db-path $dbPath
