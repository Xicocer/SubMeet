<?php

use App\Http\Controllers\Api\HallController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerHallController;
use Illuminate\Support\Facades\Route;

Route::get('/halls/{id}', [HallController::class, 'show']);

Route::middleware('organizer.auth')->prefix('organizer')->group(function () {
    Route::get('/dashboard', OrganizerDashboardController::class);
    Route::get('/halls', [OrganizerHallController::class, 'index']);
    Route::post('/halls', [OrganizerHallController::class, 'store']);
    Route::get('/halls/{id}', [OrganizerHallController::class, 'show']);
    Route::put('/halls/{id}', [OrganizerHallController::class, 'update']);
    Route::delete('/halls/{id}', [OrganizerHallController::class, 'destroy']);
});
