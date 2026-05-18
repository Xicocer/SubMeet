<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgeRating;
use App\Models\Category;
use App\Services\EventCopywriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerEventCopywriterController extends Controller
{
    public function __construct(
        private readonly EventCopywriterService $copywriter,
    ) {
    }

    public function rewrite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'age_rating_id' => ['nullable', 'integer', 'exists:age_ratings,id'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'min:2', 'max:40'],
        ]);

        $categoryName = isset($validated['category_id'])
            ? Category::query()->whereKey($validated['category_id'])->value('name')
            : null;

        $ageRatingLabel = isset($validated['age_rating_id'])
            ? AgeRating::query()->whereKey($validated['age_rating_id'])->value('label')
            : null;

        $result = $this->copywriter->rewrite(
            title: $validated['title'],
            description: $validated['description'] ?? null,
            categoryName: $categoryName,
            ageRatingLabel: $ageRatingLabel,
            tags: $validated['tags'] ?? [],
        );

        return response()->json([
            'message' => $result['mode'] === 'ai'
                ? 'AI помог улучшить описание мероприятия.'
                : 'Описание улучшено локальным fallback-режимом.',
            'description' => $result['description'],
            'tips' => $result['tips'],
            'mode' => $result['mode'],
        ]);
    }
}
