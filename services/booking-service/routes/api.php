<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\InternalRecommendationController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerGuardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\SessionAvailabilityController;
use App\Http\Controllers\Api\TicketVerificationController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/sessions/{id}/availability', [SessionAvailabilityController::class, 'show']);
Route::post('/payments/webhooks/yookassa', [PaymentWebhookController::class, 'yookassa']);

Route::middleware('internal.api')->prefix('internal/recommendations')->group(function () {
    Route::get('/interactions', [InternalRecommendationController::class, 'interactions']);
});

Route::middleware('api.auth')->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/purchase', [BookingController::class, 'purchase']);
    Route::get('/my/bookings', [BookingController::class, 'index']);
    Route::get('/my/bookings/{id}', [BookingController::class, 'show']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/pay', [PaymentController::class, 'store']);
    Route::post('/bookings/{id}/refresh-payment', [PaymentController::class, 'refresh']);
    Route::get('/my/bookings/{id}/ticket', [TicketController::class, 'download']);
});

Route::middleware(['api.auth', 'api.organizer'])->prefix('organizer')->group(function () {
    Route::get('/guards/events/{id}/booking-impact', [OrganizerGuardController::class, 'eventBookingImpact']);
    Route::get('/guards/sessions/{id}/booking-impact', [OrganizerGuardController::class, 'sessionBookingImpact']);
    Route::get('/dashboard', OrganizerDashboardController::class);
    Route::post('/tickets/verify', [TicketVerificationController::class, 'store']);
});
