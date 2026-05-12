<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventDiscoveryAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventAssistantController extends Controller
{
    public function __construct(
        private readonly EventDiscoveryAssistant $assistant,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        $payload = $this->assistant->reply(
            query: $validated['query'],
            token: $request->bearerToken(),
            resultLimit: (int) ($validated['limit'] ?? 4),
        );

        return response()->json($payload);
    }
}
