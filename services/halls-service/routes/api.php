<?php

use App\Http\Controllers\Api\HallController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerHallRentalRequestController;
use App\Http\Controllers\Api\OrganizerHallController;
use App\Http\Controllers\Api\VenueHallRentalRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/halls', [HallController::class, 'index']);
Route::get('/halls/{id}', [HallController::class, 'show']);

Route::middleware('organizer.auth')->prefix('organizer')->group(function () {
    Route::get('/hall-rental-requests', [OrganizerHallRentalRequestController::class, 'index']);
    Route::post('/hall-rental-requests', [OrganizerHallRentalRequestController::class, 'store']);
    Route::get('/hall-rental-requests/{id}', [OrganizerHallRentalRequestController::class, 'show']);
});

Route::middleware('venue.auth')->prefix('venue')->group(function () {
    Route::get('/dashboard', OrganizerDashboardController::class);
    Route::get('/halls', [OrganizerHallController::class, 'index']);
    Route::post('/halls', [OrganizerHallController::class, 'store']);
    Route::get('/halls/{id}', [OrganizerHallController::class, 'show']);
    Route::put('/halls/{id}', [OrganizerHallController::class, 'update']);
    Route::delete('/halls/{id}', [OrganizerHallController::class, 'destroy']);
    Route::get('/hall-rental-requests', [VenueHallRentalRequestController::class, 'index']);
    Route::patch('/hall-rental-requests/{id}', [VenueHallRentalRequestController::class, 'update']);
});
