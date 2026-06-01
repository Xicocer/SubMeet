<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'min_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'max_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $halls = Hall::query()
            ->active()
            ->when(
                $validated['search'] ?? null,
                function (Builder $query, string $search): void {
                    $like = '%' . trim($search) . '%';

                    $query->where(function (Builder $builder) use ($like): void {
                        $builder
                            ->where('name', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
                }
            )
            ->when(
                $validated['address'] ?? null,
                fn (Builder $query, string $address) => $query->where('address', 'like', '%' . trim($address) . '%')
            )
            ->when(
                array_key_exists('min_hourly_rate', $validated),
                fn (Builder $query) => $query->where('hourly_rate', '>=', (float) $validated['min_hourly_rate'])
            )
            ->when(
                array_key_exists('max_hourly_rate', $validated),
                fn (Builder $query) => $query->where('hourly_rate', '<=', (float) $validated['max_hourly_rate'])
            )
            ->orderBy('hourly_rate')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 12)
            ->withQueryString()
            ->through(fn (Hall $hall) => $this->transformHall($hall, false));

        return response()->json($halls);
    }

    public function show(int $id): JsonResponse
    {
        $hall = Hall::query()
            ->active()
            ->findOrFail($id);

        return response()->json($this->transformHall($hall, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformHall(Hall $hall, bool $includeLayout): array
    {
        $payload = [
            'id' => $hall->id,
            'name' => $hall->name,
            'address' => $hall->address,
            'description' => $hall->description,
            'photo_urls' => $hall->photo_urls ?? [],
            'venue_owner_id' => $hall->venue_owner_id,
            'status' => $hall->status,
            'hourly_rate' => (float) $hall->hourly_rate,
            'capacities' => [
                'seat' => $hall->seat_capacity,
                'table' => $this->calculateTableCapacity($hall->layout['elements'] ?? []),
                'vip' => $hall->vip_capacity,
                'dancefloor' => $hall->dancefloor_capacity,
                'total' => $hall->total_capacity,
            ],
            'layout_meta' => $this->layoutMeta($hall->layout),
            'created_at' => $hall->created_at?->toISOString(),
            'updated_at' => $hall->updated_at?->toISOString(),
        ];

        if ($includeLayout) {
            $payload['layout'] = $hall->layout;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $layout
     * @return array<string, mixed>
     */
    private function layoutMeta(?array $layout): array
    {
        $levels = $layout['levels'] ?? [];
        $elements = $layout['elements'] ?? [];
        $elementsCollection = collect(is_array($elements) ? $elements : []);

        return [
            'levels_count' => is_array($levels) ? count($levels) : 0,
            'elements_count' => $elementsCollection->count(),
            'has_dancefloor' => $elementsCollection->contains(
                fn ($element) => is_array($element) && ($element['type'] ?? null) === 'dancefloor'
            ),
            'has_stage' => $elementsCollection->contains(
                fn ($element) => is_array($element) && ($element['type'] ?? null) === 'stage'
            ),
            'tables_count' => $elementsCollection->filter(
                fn ($element) => is_array($element) && ($element['type'] ?? null) === 'table'
            )->count(),
        ];
    }

    /**
     * @param  mixed  $elements
     */
    private function calculateTableCapacity(mixed $elements): int
    {
        if (!is_array($elements)) {
            return 0;
        }

        return (int) collect($elements)
            ->filter(fn ($element) => is_array($element) && ($element['type'] ?? null) === 'table')
            ->sum(fn ($element) => (int) ($element['capacity'] ?? 2));
    }
}
