<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $organizerRoleId = Role::query()->where('role', 'organizer')->value('id');
        $venueOwnerRoleId = Role::query()->where('role', 'venue_owner')->value('id');

        $usersTotal = User::query()->count();
        $organizersTotal = $organizerRoleId !== null
            ? User::query()->where('role_id', $organizerRoleId)->count()
            : 0;
        $venueOwnersTotal = $venueOwnerRoleId !== null
            ? User::query()->where('role_id', $venueOwnerRoleId)->count()
            : 0;

        $moderationCounts = OrganizerProfile::query()
            ->selectRaw('moderation_status, COUNT(*) as aggregate')
            ->groupBy('moderation_status')
            ->pluck('aggregate', 'moderation_status');

        return response()->json([
            'metrics' => [
                'users_total' => $usersTotal,
                'organizers_total' => $organizersTotal,
                'venue_owners_total' => $venueOwnersTotal,
                'organizers_pending' => (int) ($moderationCounts['pending'] ?? 0),
                'organizers_approved' => (int) ($moderationCounts['approved'] ?? 0),
                'organizers_rejected' => (int) ($moderationCounts['rejected'] ?? 0),
                'organizers_blocked' => (int) ($moderationCounts['blocked'] ?? 0),
                'accounts_blocked_total' => (int) User::query()->where('status', 0)->count(),
            ],
        ]);
    }
}
