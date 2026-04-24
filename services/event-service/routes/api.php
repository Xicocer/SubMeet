<?php

use App\Http\Controllers\Api\AgeRatingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrganizerEventController;
use App\Http\Controllers\Api\OrganizerSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/age-ratings', [AgeRatingController::class, 'index']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{id}/sessions', [EventController::class, 'sessions']);

Route::middleware('organizer.auth')->prefix('organizer')->group(function () {
    Route::get('/events', [OrganizerEventController::class, 'myEvents']);
    Route::post('/events', [OrganizerEventController::class, 'store']);
    Route::put('/events/{id}', [OrganizerEventController::class, 'update']);
    Route::delete('/events/{id}', [OrganizerEventController::class, 'destroy']);
    Route::get('/events/{id}/sessions', [OrganizerSessionController::class, 'index']);
    Route::post('/events/{id}/sessions', [OrganizerSessionController::class, 'store']);
    Route::put('/sessions/{id}', [OrganizerSessionController::class, 'update']);
    Route::delete('/sessions/{id}', [OrganizerSessionController::class, 'destroy']);
});
