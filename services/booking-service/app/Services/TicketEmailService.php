<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TicketEmailService
{
    public function sendTicket(Booking $booking): void
    {
        $booking->loadMissing(['snapshot', 'items']);

        if (
            $booking->customer_email === null ||
            $booking->ticket_pdf_path === null ||
            $booking->ticket_sent_at !== null
        ) {
            return;
        }

        $disk = (string) config('payments.tickets.disk', 'local');
        $absolutePath = Storage::disk($disk)->path($booking->ticket_pdf_path);
        $eventTitle = (string) ($booking->snapshot?->event_title ?? 'Submeet event');

        Mail::raw(
            "Здравствуйте!\n\nВо вложении находится PDF-билет на событие «{$eventTitle}».\n\nЕсли вы покупали несколько мест одним заказом, все билеты находятся в этом документе.\n\nSubmeet",
            function ($message) use ($booking, $absolutePath, $eventTitle): void {
                $message
                    ->to($booking->customer_email)
                    ->subject('Ваш билет Submeet: ' . $eventTitle)
                    ->attach($absolutePath, [
                        'as' => 'submeet-ticket-' . $booking->id . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
            },
        );

        $booking->forceFill([
            'ticket_sent_at' => now(),
        ])->save();
    }
}
