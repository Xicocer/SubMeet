<?php

namespace App\Services;

use App\Jobs\IssueTicketDocumentJob;
use Throwable;

class TicketDispatchService
{
    public function __construct(
        private readonly TicketJobPublisher $publisher,
    ) {
    }

    public function shouldIssueSynchronously(): bool
    {
        return app()->runningUnitTests()
            || config('booking.ticket_dispatch_driver', 'rabbitmq') === 'sync';
    }

    public function dispatch(int $bookingId): void
    {
        if ($this->shouldIssueSynchronously()) {
            IssueTicketDocumentJob::dispatchSync($bookingId);

            return;
        }

        try {
            $this->publisher->publishIssueTicket($bookingId);
        } catch (Throwable $exception) {
            report($exception);
            IssueTicketDocumentJob::dispatchSync($bookingId);
        }
    }
}
