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

    Write-Host ("Migrating {0}..." -f $service.Name) -ForegroundColor Cyan

    Push-Location $workdir
    try {
        php artisan migrate --force
        if ($LASTEXITCODE -ne 0) {
            throw ("Migration failed for {0}." -f $service.Name)
        }

        Write-Host ("Seeding {0}..." -f $service.Name) -ForegroundColor Cyan

        php artisan db:seed --force
        if ($LASTEXITCODE -ne 0) {
            throw ("Seeding failed for {0}." -f $service.Name)
        }
    } finally {
        Pop-Location
    }
}

$eventServiceDir = Join-Path $rootDir 'services\event-service'

Write-Host 'Rebuilding event search index...' -ForegroundColor Cyan

Push-Location $eventServiceDir
try {
    php artisan scout:sync-index-settings
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Search index settings sync failed. Make sure Meilisearch is running on http://127.0.0.1:7700.' -ForegroundColor Yellow
    }

    php artisan scout:import "App\Models\Event"
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Search index import failed. Make sure Meilisearch is running on http://127.0.0.1:7700.' -ForegroundColor Yellow
    }
} finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Demo data is ready.' -ForegroundColor Green
Write-Host 'Demo accounts:' -ForegroundColor Green
Write-Host ' - admin@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - anna@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - nikita@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host ' - olga@submeet.local / Password123!' -ForegroundColor Yellow
Write-Host '   Loyalty demo: Anna has 2110 points after seeded purchases, Nikita has 2285, Olga has 350.' -ForegroundColor DarkYellow
Write-Host ' - dkh@submeet.local / Password123! (approved organizer)' -ForegroundColor Yellow
Write-Host ' - milo@submeet.local / Password123! (approved organizer)' -ForegroundColor Yellow
Write-Host ' - citylight@submeet.local / Password123! (pending organizer)' -ForegroundColor Yellow
Write-Host ' - oldarena@submeet.local / Password123! (blocked organizer)' -ForegroundColor Yellow
Write-Host ' - arena@submeet.local / Password123! (approved venue owner)' -ForegroundColor Yellow
Write-Host ' - roof@submeet.local / Password123! (approved venue owner)' -ForegroundColor Yellow
Write-Host ' - loft@submeet.local / Password123! (pending venue owner)' -ForegroundColor Yellow
Write-Host ' - closedhall@submeet.local / Password123! (blocked venue owner)' -ForegroundColor Yellow
