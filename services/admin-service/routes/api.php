<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminDictionaryController;
use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AdminIncidentController;
use App\Http\Controllers\Api\AdminOrganizerController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin.auth')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class);
    Route::get('/incidents', [AdminIncidentController::class, 'index']);

    Route::get('/organizers', [AdminOrganizerController::class, 'index']);
    Route::patch('/organizers/{id}/moderation', [AdminOrganizerController::class, 'update']);

    Route::get('/events', [AdminEventController::class, 'index']);
    Route::patch('/events/{id}/moderation', [AdminEventController::class, 'update']);

    Route::get('/dictionaries/categories', [AdminDictionaryController::class, 'categories']);
    Route::post('/dictionaries/categories', [AdminDictionaryController::class, 'storeCategory']);
    Route::put('/dictionaries/categories/{id}', [AdminDictionaryController::class, 'updateCategory']);
    Route::delete('/dictionaries/categories/{id}', [AdminDictionaryController::class, 'destroyCategory']);

    Route::get('/dictionaries/age-ratings', [AdminDictionaryController::class, 'ageRatings']);
    Route::post('/dictionaries/age-ratings', [AdminDictionaryController::class, 'storeAgeRating']);
    Route::put('/dictionaries/age-ratings/{id}', [AdminDictionaryController::class, 'updateAgeRating']);
    Route::delete('/dictionaries/age-ratings/{id}', [AdminDictionaryController::class, 'destroyAgeRating']);

    Route::get('/dictionaries/tags', [AdminDictionaryController::class, 'tags']);
    Route::post('/dictionaries/tags', [AdminDictionaryController::class, 'storeTag']);
    Route::put('/dictionaries/tags/{id}', [AdminDictionaryController::class, 'updateTag']);
    Route::delete('/dictionaries/tags/{id}', [AdminDictionaryController::class, 'destroyTag']);
});
