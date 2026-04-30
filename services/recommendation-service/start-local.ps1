param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8004,
    [switch]$NoReload
)

$ErrorActionPreference = 'Stop'

$serviceDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent (Split-Path -Parent $serviceDir)
$venvDir = Join-Path $serviceDir '.venv'
$venvPython = Join-Path $venvDir 'Scripts\python.exe'
$requirementsPath = Join-Path $serviceDir 'requirements.txt'
$requirementsHashPath = Join-Path $venvDir '.requirements.sha256'
$workspacePath = Join-Path $rootDir 'ml_recommended'

if (-not (Test-Path $venvPython)) {
    Write-Host 'Creating Python virtual environment for recommendation-service...' -ForegroundColor Cyan
    python -m venv $venvDir
}

$requirementsHash = (Get-FileHash $requirementsPath -Algorithm SHA256).Hash
$installNeeded = $true

if (Test-Path $requirementsHashPath) {
    $savedHash = (Get-Content $requirementsHashPath -Raw).Trim()
    $installNeeded = $savedHash -ne $requirementsHash
}

if ($installNeeded) {
    Write-Host 'Installing recommendation-service dependencies...' -ForegroundColor Cyan
    & $venvPython -m pip install --upgrade pip
    & $venvPython -m pip install -r $requirementsPath
    Set-Content -Path $requirementsHashPath -Value $requirementsHash -Encoding UTF8
}

if (-not $env:ML_WORKSPACE) {
    $env:ML_WORKSPACE = $workspacePath
}

$arguments = @('-m', 'uvicorn', 'app.main:app', '--host', $ListenHost, '--port', "$Port")

if (-not $NoReload) {
    $arguments += '--reload'
}

Write-Host "Starting recommendation-service on http://${ListenHost}:${Port}" -ForegroundColor Green
& $venvPython @arguments
