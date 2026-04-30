<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketDocumentService
{
    public function issueForBooking(Booking $booking): Booking
    {
        $booking->loadMissing(['snapshot', 'items']);

        if ($booking->ticket_pdf_path !== null && $booking->ticket_issued_at !== null) {
            return $booking;
        }

        $ticketCode = $booking->ticket_code ?: $this->generateTicketCode();
        $qrPayload = $this->buildQrPayload($booking, $ticketCode);
        $qrDataUri = $this->buildQrDataUri($qrPayload);
        $pdfBinary = Pdf::loadView('tickets.booking-ticket', [
            'booking' => $booking,
            'snapshot' => $booking->snapshot,
            'items' => $booking->items,
            'ticketCode' => $ticketCode,
            'qrDataUri' => $qrDataUri,
        ])->output();

        $relativePath = trim((string) config('payments.tickets.directory', 'tickets'), '/')
            . '/booking-' . $booking->id . '-' . Str::lower($ticketCode) . '.pdf';

        Storage::disk((string) config('payments.tickets.disk', 'local'))->put($relativePath, $pdfBinary);

        $booking->update([
            'ticket_code' => $ticketCode,
            'ticket_pdf_path' => $relativePath,
            'ticket_issued_at' => now(),
        ]);

        return $booking->fresh(['snapshot', 'items', 'payment']);
    }

    private function generateTicketCode(): string
    {
        return strtoupper(Str::random(12));
    }

    private function buildQrPayload(Booking $booking, string $ticketCode): string
    {
        return json_encode([
            'ticket_code' => $ticketCode,
            'booking_id' => $booking->id,
            'event_session_id' => $booking->session_snapshot_id,
            'issued_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $ticketCode;
    }

    private function buildQrDataUri(string $payload): string
    {
        $result = (new Builder(writer: new SvgWriter()))->build(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 280,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $result->getDataUri();
    }
}
