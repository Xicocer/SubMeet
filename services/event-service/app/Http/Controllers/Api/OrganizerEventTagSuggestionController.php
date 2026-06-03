<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgeRating;
use App\Models\Category;
use App\Services\EventTagSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizerEventTagSuggestionController extends Controller
{
    public function __construct(
        private readonly EventTagSuggestionService $tagSuggestionService,
    ) {
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'age_rating_id' => ['nullable', 'integer', 'exists:age_ratings,id'],
            'age_rating_label' => ['nullable', 'string', 'max:50'],
            'already_selected_tags' => ['nullable', 'array', 'max:12'],
            'already_selected_tags.*' => ['string', 'min:2', 'max:40'],
        ]);

        $title = trim((string) ($validated['title'] ?? ''));
        $description = trim((string) ($validated['description'] ?? ''));

        if ($title === '' && $description === '') {
            throw ValidationException::withMessages([
                'title' => ['Укажите название или описание, чтобы подобрать теги.'],
            ]);
        }

        $categoryName = $validated['category_name'] ?? null;

        if (isset($validated['category_id'])) {
            $categoryName = Category::query()->whereKey($validated['category_id'])->value('name') ?? $categoryName;
        }

        $ageRatingLabel = $validated['age_rating_label'] ?? null;

        if (isset($validated['age_rating_id'])) {
            $ageRatingLabel = AgeRating::query()->whereKey($validated['age_rating_id'])->value('label') ?? $ageRatingLabel;
        }

        $result = $this->tagSuggestionService->suggest(
            title: $title !== '' ? $title : null,
            description: $description !== '' ? $description : null,
            categoryName: $categoryName,
            ageRatingLabel: $ageRatingLabel,
            alreadySelectedTags: $validated['already_selected_tags'] ?? [],
        );

        return response()->json([
            'message' => $result['mode'] === 'ai'
                ? 'Митя подобрал теги для события.'
                : 'Теги подобраны локальным fallback-режимом.',
            'tags' => $result['tags'],
            'mode' => $result['mode'],
        ]);
    }
}
