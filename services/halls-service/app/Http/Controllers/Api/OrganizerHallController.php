<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Services\EventServiceClient;
use App\Services\HallLayoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class OrganizerHallController extends Controller
{
    public function __construct(
        private readonly HallLayoutService $hallLayoutService,
        private readonly EventServiceClient $eventServiceClient,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:draft,active,archived'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $halls = Hall::query()
            ->where('organizer_id', $organizer['id'])
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $validated['search'] ?? null,
                fn (Builder $query, string $search) => $query->where('name', 'like', '%' . $search . '%')
            )
            ->latest('updated_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (Hall $hall) => $this->transformHallSummary($hall));

        return response()->json($halls);
    }

    public function store(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        [$validated, $summary] = $this->validateHallPayload($request);

        $hall = Hall::query()->create([
            'organizer_id' => $organizer['id'],
            'name' => $validated['name'],
            'address' => $validated['address'],
            'description' => $validated['description'] ?? null,
            'layout' => $validated['layout'],
            'seat_capacity' => $summary['seat_capacity'],
            'vip_capacity' => $summary['vip_capacity'],
            'dancefloor_capacity' => $summary['dancefloor_capacity'],
            'total_capacity' => $summary['total_capacity'],
            'status' => $validated['status'] ?? Hall::STATUS_DRAFT,
        ]);

        return response()->json([
            'message' => 'Hall created successfully.',
            'hall' => $this->transformHallDetails($hall),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $hall = $this->findOrganizerHallOrFail($id, $organizer['id']);

        return response()->json($this->transformHallDetails($hall));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        [$validated, $summary] = $this->validateHallPayload($request);

        $hall = $this->findOrganizerHallOrFail($id, $organizer['id']);

        $hall->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'description' => $validated['description'] ?? null,
            'layout' => $validated['layout'],
            'seat_capacity' => $summary['seat_capacity'],
            'vip_capacity' => $summary['vip_capacity'],
            'dancefloor_capacity' => $summary['dancefloor_capacity'],
            'total_capacity' => $summary['total_capacity'],
            'status' => $validated['status'] ?? $hall->status,
        ]);

        return response()->json([
            'message' => 'Hall updated successfully.',
            'hall' => $this->transformHallDetails($hall->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $hall = $this->findOrganizerHallOrFail($id, $organizer['id']);
        $token = $this->requireBearerToken($request);
        $usage = $this->loadHallUsage($token, $hall->id);

        if ((bool) ($usage['has_future_sessions'] ?? false)) {
            throw ValidationException::withMessages([
                'hall' => ['This hall cannot be archived while it is used in future scheduled sessions.'],
            ]);
        }

        $hall->update([
            'status' => Hall::STATUS_ARCHIVED,
        ]);

        return response()->json([
            'message' => 'Hall archived successfully.',
            'hall' => $this->transformHallDetails($hall->fresh()),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, int|bool>}
     */
    private function validateHallPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active'],
            'layout' => ['required', 'array'],
            'layout.canvas' => ['nullable', 'array'],
            'layout.canvas.width' => ['nullable', 'numeric', 'min:1'],
            'layout.canvas.height' => ['nullable', 'numeric', 'min:1'],
            'layout.levels' => ['nullable', 'array'],
            'layout.levels.*.id' => ['required', 'string', 'max:100'],
            'layout.levels.*.name' => ['required', 'string', 'max:255'],
            'layout.levels.*.order' => ['nullable', 'integer', 'min:1'],
            'layout.elements' => ['required', 'array', 'min:1'],
            'layout.elements.*.id' => ['required', 'string', 'max:100'],
            'layout.elements.*.type' => ['required', 'string', 'in:stage,seat,vip_seat,dancefloor'],
            'layout.elements.*.label' => ['nullable', 'string', 'max:255'],
            'layout.elements.*.level_id' => ['nullable', 'string', 'max:100'],
            'layout.elements.*.row' => ['nullable', 'string', 'max:50'],
            'layout.elements.*.number' => ['nullable', 'string', 'max:50'],
            'layout.elements.*.capacity' => ['nullable', 'integer', 'min:1'],
            'layout.elements.*.x' => ['required', 'numeric'],
            'layout.elements.*.y' => ['required', 'numeric'],
            'layout.elements.*.width' => ['nullable', 'numeric', 'min:0'],
            'layout.elements.*.height' => ['nullable', 'numeric', 'min:0'],
        ]);

        $summary = $this->hallLayoutService->validateAndSummarize($validated['layout']);

        return [$validated, $summary];
    }

    private function findOrganizerHallOrFail(int $hallId, int $organizerId): Hall
    {
        return Hall::query()
            ->whereKey($hallId)
            ->where('organizer_id', $organizerId)
            ->firstOrFail();
    }

    private function requireBearerToken(Request $request): string
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            abort(401, 'Missing bearer token.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadHallUsage(string $token, int $hallId): array
    {
        try {
            return $this->eventServiceClient->getOrganizerHallUsage($token, $hallId) ?? [];
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Event service is unavailable.', $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformHallSummary(Hall $hall): array
    {
        $levels = $hall->layout['levels'] ?? [];
        $elements = $hall->layout['elements'] ?? [];

        return [
            'id' => $hall->id,
            'name' => $hall->name,
            'address' => $hall->address,
            'description' => $hall->description,
            'organizer_id' => $hall->organizer_id,
            'status' => $hall->status,
            'capacities' => [
                'seat' => $hall->seat_capacity,
                'vip' => $hall->vip_capacity,
                'dancefloor' => $hall->dancefloor_capacity,
                'total' => $hall->total_capacity,
            ],
            'layout_meta' => [
                'levels_count' => is_array($levels) ? count($levels) : 0,
                'elements_count' => is_array($elements) ? count($elements) : 0,
                'has_dancefloor' => collect($elements)->contains(
                    fn ($element) => is_array($element) && ($element['type'] ?? null) === 'dancefloor'
                ),
            ],
            'created_at' => $hall->created_at?->toISOString(),
            'updated_at' => $hall->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformHallDetails(Hall $hall): array
    {
        $payload = $this->transformHallSummary($hall);
        $payload['layout'] = $hall->layout;

        return $payload;
    }
}
