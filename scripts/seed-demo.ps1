param()

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $scriptDir

$services = @(
    @{ Name = 'Auth Service'; Path = 'services\auth-service' },
    @{ Name = 'Halls Service'; Path = 'services\halls-service' },
    @{ Name = 'Event Service'; Path = 'services\event-service' },
    @{ Name = 'Booking Service'; Path = 'services\booking-service' }
)

foreach ($service in $services) {
    $workdir = Join-Path $rootDir $service.Path

    Write-Host ("Seeding {0}..." -f $service.Name) -ForegroundColor Cyan

    Push-Location $workdir
    try {
        php artisan db:seed --force
    } finally {
        Pop-Location
    }
}

Write-Host ''
Write-Host 'Demo data is ready.' -ForegroundColor Green
Write-Host 'Demo accounts:' -ForegroundColor Green
Write-Host ' - admin@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - anna@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - nikita@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - dkh@submeet.local / Password123! (approved organizer)' -ForegroundColor Yellow
Write-Host ' - milo@submeet.local / Password123! (approved organizer)' -ForegroundColor Yellow
Write-Host ' - citylight@submeet.local / Password123! (pending organizer)' -ForegroundColor Yellow
Write-Host ' - oldarena@submeet.local / Password123! (blocked organizer)' -ForegroundColor Yellow
