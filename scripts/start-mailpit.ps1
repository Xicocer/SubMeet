param()

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir
$toolsDir = Join-Path $rootDir '.tools\mailpit'
$runDir = Join-Path $rootDir '.run'
$mailpitExe = Join-Path $toolsDir 'mailpit.exe'

function Get-MailpitArchitecture {
    $architecture = $env:PROCESSOR_ARCHITECTURE

    if ([string]::IsNullOrWhiteSpace($architecture)) {
        $architecture = [System.Runtime.InteropServices.RuntimeInformation]::OSArchitecture.ToString()
    }

    switch -Regex ($architecture) {
        'ARM64' { return 'arm64' }
        'AMD64|X64' { return 'amd64' }
        'X86|386' { return '386' }
        default {
            throw "Unsupported Windows architecture for Mailpit: $architecture"
        }
    }
}

function Install-Mailpit {
    New-Item -Path $toolsDir -ItemType Directory -Force | Out-Null
    New-Item -Path $runDir -ItemType Directory -Force | Out-Null

    $arch = Get-MailpitArchitecture
    $archivePath = Join-Path $runDir "mailpit-windows-$arch.zip"
    $extractDir = Join-Path $runDir "mailpit-windows-$arch"
    $downloadUrl = "https://github.com/axllent/mailpit/releases/latest/download/mailpit-windows-$arch.zip"

    Write-Host "Mailpit was not found. Downloading $downloadUrl" -ForegroundColor Yellow

    if (Test-Path $archivePath) {
        Remove-Item $archivePath -Force
    }

    if (Test-Path $extractDir) {
        Remove-Item $extractDir -Recurse -Force
    }

    Invoke-WebRequest -Uri $downloadUrl -OutFile $archivePath
    Expand-Archive -Path $archivePath -DestinationPath $extractDir -Force

    $downloadedExe = Get-ChildItem -Path $extractDir -Recurse -Filter 'mailpit.exe' |
        Select-Object -First 1

    if ($null -eq $downloadedExe) {
        throw 'Downloaded Mailpit archive does not contain mailpit.exe.'
    }

    Copy-Item -Path $downloadedExe.FullName -Destination $mailpitExe -Force
    Remove-Item $archivePath -Force
    Remove-Item $extractDir -Recurse -Force
}

if (-not (Test-Path $mailpitExe)) {
    Install-Mailpit
}

Write-Host 'Mailpit SMTP: 127.0.0.1:1025' -ForegroundColor Cyan
Write-Host 'Mailpit inbox: http://127.0.0.1:8025' -ForegroundColor Cyan

& $mailpitExe
