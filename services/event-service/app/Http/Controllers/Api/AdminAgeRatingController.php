<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgeRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAgeRatingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            AgeRating::query()->orderBy('min_age')->get(['id', 'label', 'min_age'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:20'],
            'min_age' => ['required', 'integer', 'min:0', 'max:21'],
        ]);

        $ageRating = AgeRating::query()->create($validated);

        return response()->json($ageRating, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ageRating = AgeRating::query()->findOrFail($id);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:20'],
            'min_age' => ['required', 'integer', 'min:0', 'max:21'],
        ]);

        $ageRating->update($validated);

        return response()->json($ageRating);
    }

    public function destroy(int $id): JsonResponse
    {
        $ageRating = AgeRating::query()->findOrFail($id);
        $ageRating->delete();

        return response()->json([
            'message' => 'Age rating deleted.',
        ]);
    }
}
