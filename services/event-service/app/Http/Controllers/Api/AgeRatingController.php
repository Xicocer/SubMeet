<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgeRating;
use Illuminate\Http\JsonResponse;

class AgeRatingController extends Controller
{
    public function index(): JsonResponse
    {
        $ageRatings = AgeRating::query()
            ->select(['id', 'label', 'min_age'])
            ->orderBy('min_age')
            ->get();

        return response()->json($ageRatings);
    }
}
