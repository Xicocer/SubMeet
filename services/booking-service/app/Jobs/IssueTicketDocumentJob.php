<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\TicketEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IssueTicketDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly int $bookingId,
    ) {
        $this->onQueue((string) config('booking.ticket_queue_name', 'tickets'));
    }

    public function handle(BookingService $bookingService, TicketEmailService $ticketEmailService): void
    {
        $booking = Booking::query()
            ->with(['snapshot', 'items.seat', 'items.standingArea', 'payment'])
            ->find($this->bookingId);

        if ($booking === null || $booking->status !== Booking::STATUS_CONFIRMED) {
            return;
        }

        $issuedBooking = $bookingService->ensureTicketIssued($booking);
        $ticketEmailService->sendTicket($issuedBooking);
    }
}
