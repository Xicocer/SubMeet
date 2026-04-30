<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function download(Request $request, int $id): BinaryFileResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $booking = Booking::query()
            ->whereKey($id)
            ->where('user_id', (int) $authUser['id'])
            ->firstOrFail();

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            abort(409, 'Ticket is available only for confirmed bookings.');
        }

        $booking = $this->bookingService->ensureTicketIssued($booking);

        if ($booking->ticket_pdf_path === null) {
            abort(404, 'Ticket PDF has not been generated yet.');
        }

        $disk = (string) config('payments.tickets.disk', 'local');
        $path = Storage::disk($disk)->path($booking->ticket_pdf_path);

        return response()->download(
            $path,
            'submeet-ticket-' . $booking->id . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
