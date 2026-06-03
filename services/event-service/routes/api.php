<?php

use App\Http\Controllers\Api\AgeRatingController;
use App\Http\Controllers\Api\AdminAgeRatingController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminEventModerationController;
use App\Http\Controllers\Api\AdminTagController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventAssistantChatController;
use App\Http\Controllers\Api\EventAssistantConversationController;
use App\Http\Controllers\Api\EventAssistantController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InternalHallUsageController;
use App\Http\Controllers\Api\InternalRecommendationController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerEventCopywriterController;
use App\Http\Controllers\Api\OrganizerEventController;
use App\Http\Controllers\Api\OrganizerEventTagSuggestionController;
use App\Http\Controllers\Api\OrganizerHallUsageController;
use App\Http\Controllers\Api\OrganizerSessionController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\WantToGoController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/age-ratings', [AgeRatingController::class, 'index']);
Route::get('/events', [EventController::class, 'index']);
Route::prefix('/events/assistant')->group(function () {
    Route::post('/', EventAssistantController::class);
    Route::post('/chat/stream', EventAssistantChatController::class);
});
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{id}/sessions', [EventController::class, 'sessions']);
Route::get('/sessions/{id}', [SessionController::class, 'show']);

Route::middleware('internal.api')->prefix('internal/recommendations')->group(function () {
    Route::get('/events', [InternalRecommendationController::class, 'events']);
    Route::get('/interactions', [InternalRecommendationController::class, 'interactions']);
});

Route::middleware('internal.api')->prefix('internal/halls')->group(function () {
    Route::get('/{id}/usage', InternalHallUsageController::class);
});

Route::middleware('api.auth')->group(function () {
    Route::get('/me/want-to-go', [WantToGoController::class, 'index']);
    Route::post('/events/{id}/want-to-go', [WantToGoController::class, 'store']);
    Route::delete('/events/{id}/want-to-go', [WantToGoController::class, 'destroy']);
    Route::get('/events/assistant/conversations', [EventAssistantConversationController::class, 'index']);
    Route::get('/events/assistant/conversations/{conversationId}', [EventAssistantConversationController::class, 'show']);
});

Route::middleware('organizer.auth')->prefix('organizer')->group(function () {
    Route::get('/dashboard', OrganizerDashboardController::class);
    Route::get('/halls/{id}/usage', OrganizerHallUsageController::class);
    Route::post('/events/copywriter/rewrite', [OrganizerEventCopywriterController::class, 'rewrite']);
    Route::post('/events/suggest-tags', [OrganizerEventTagSuggestionController::class, 'suggest']);
    Route::get('/events', [OrganizerEventController::class, 'myEvents']);
    Route::post('/events', [OrganizerEventController::class, 'store']);
    Route::put('/events/{id}', [OrganizerEventController::class, 'update']);
    Route::delete('/events/{id}', [OrganizerEventController::class, 'destroy']);
    Route::get('/events/{id}/sessions', [OrganizerSessionController::class, 'index']);
    Route::post('/events/{id}/sessions', [OrganizerSessionController::class, 'store']);
    Route::put('/sessions/{id}', [OrganizerSessionController::class, 'update']);
    Route::delete('/sessions/{id}', [OrganizerSessionController::class, 'destroy']);
});

Route::middleware('admin.auth')->prefix('admin')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class);
    Route::get('/events', [AdminEventModerationController::class, 'index']);
    Route::patch('/events/{id}/moderation', [AdminEventModerationController::class, 'update']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

    Route::get('/age-ratings', [AdminAgeRatingController::class, 'index']);
    Route::post('/age-ratings', [AdminAgeRatingController::class, 'store']);
    Route::put('/age-ratings/{id}', [AdminAgeRatingController::class, 'update']);
    Route::delete('/age-ratings/{id}', [AdminAgeRatingController::class, 'destroy']);

    Route::get('/tags', [AdminTagController::class, 'index']);
    Route::post('/tags', [AdminTagController::class, 'store']);
    Route::put('/tags/{id}', [AdminTagController::class, 'update']);
    Route::delete('/tags/{id}', [AdminTagController::class, 'destroy']);
});
