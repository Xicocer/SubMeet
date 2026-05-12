<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'bookings:expire-reservations {--chunk=100 : Number of expired bookings to process per batch}';

    protected $description = 'Expire stale booking reservations and release their inventory';

    public function handle(BookingService $bookingService): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $expired = $bookingService->expirePendingBookings(chunk: $chunk);

        $this->info("Expired {$expired} booking reservation(s).");

        return self::SUCCESS;
    }
}
