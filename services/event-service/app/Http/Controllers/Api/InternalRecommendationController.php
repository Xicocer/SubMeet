<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventFavorite;
use App\Services\EventRecommendationCatalogBuilder;
use Illuminate\Http\JsonResponse;

class InternalRecommendationController extends Controller
{
    public function __construct(
        private readonly EventRecommendationCatalogBuilder $catalogBuilder,
    ) {
    }

    public function events(): JsonResponse
    {
        return response()->json(
            $this->catalogBuilder->buildPublishedRecommendationCatalog()
        );
    }

    public function interactions(): JsonResponse
    {
        $interactionId = 1;

        $payload = EventFavorite::query()
            ->orderBy('id')
            ->get()
            ->map(function (EventFavorite $favorite) use (&$interactionId): array {
                return [
                    'id' => $interactionId++,
                    'user_id' => (int) $favorite->user_id,
                    'event_id' => (int) $favorite->event_id,
                    'action' => 'favorite',
                    'rating' => '',
                    'created_at' => $favorite->created_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->values()
            ->all();

        return response()->json($payload);
    }
}
