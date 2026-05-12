<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

class RecommendationServiceClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @param  array<int, string>  $preferredCategories
     * @param  array<int, string>  $preferredTags
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function preview(
        ?int $userId,
        ?string $token,
        array $preferredCategories = [],
        array $preferredTags = [],
        ?string $referenceDate = null,
        int $limit = 12,
    ): array {
        $request = $this->http->acceptJson();

        if ($token) {
            $request = $request->withToken($token);
        }

        $payload = [
            'limit' => $limit,
            'user_context' => [
                'preferred_categories' => array_values($preferredCategories),
                'preferred_tags' => array_values($preferredTags),
            ],
            'reference_date' => $referenceDate,
        ];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        $response = $request->post(
            rtrim(config('services.recommendations.base_url'), '/') . '/recommendations/preview',
            $payload,
        );

        if ($response->failed()) {
            $response->throw();
        }

        $items = $response->json('items');

        return is_array($items) ? $items : [];
    }
}
