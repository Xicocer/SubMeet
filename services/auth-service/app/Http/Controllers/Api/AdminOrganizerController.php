<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOrganizerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,blocked'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $organizers = OrganizerProfile::query()
            ->with(['user.role'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('moderation_status', $status)
            )
            ->when(
                $validated['search'] ?? null,
                function ($query, string $search) {
                    $like = '%' . trim($search) . '%';

                    $query->where(function ($builder) use ($like) {
                        $builder
                            ->where('company_name', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('full_name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('phone', 'like', $like);
                            });
                    });
                }
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString()
            ->through(fn (OrganizerProfile $profile) => $this->transformProfile($profile));

        return response()->json($organizers);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,blocked'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = OrganizerProfile::query()
            ->with(['user.role'])
            ->where('user_id', $id)
            ->firstOrFail();

        if (($profile->user?->role?->role ?? null) !== 'organizer') {
            throw ValidationException::withMessages([
                'user' => ['The selected user is not an organizer account.'],
            ]);
        }

        $profile->update([
            'moderation_status' => $validated['status'],
            'moderation_note' => $validated['note'] ?? null,
            'moderated_at' => now(),
        ]);

        $profile->user->update([
            'status' => $validated['status'] === 'blocked' ? 0 : 1,
        ]);

        return response()->json([
            'message' => 'Статус организатора обновлен.',
            'organizer' => $this->transformProfile($profile->fresh(['user.role'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProfile(OrganizerProfile $profile): array
    {
        return [
            'user_id' => $profile->user_id,
            'company_name' => $profile->company_name,
            'moderation_status' => $profile->moderation_status,
            'moderation_note' => $profile->moderation_note,
            'moderated_at' => $profile->moderated_at?->toISOString(),
            'created_at' => $profile->created_at?->toISOString(),
            'updated_at' => $profile->updated_at?->toISOString(),
            'user' => [
                'id' => $profile->user?->id,
                'full_name' => $profile->user?->full_name,
                'email' => $profile->user?->email,
                'phone' => $profile->user?->phone,
                'status' => $profile->user?->status,
                'role' => $profile->user?->role?->role,
            ],
        ];
    }
}
