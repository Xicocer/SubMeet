<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use Illuminate\Http\JsonResponse;

class HallController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $hall = Hall::query()
            ->active()
            ->findOrFail($id);

        return response()->json([
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
            'layout' => $hall->layout,
            'created_at' => $hall->created_at?->toISOString(),
            'updated_at' => $hall->updated_at?->toISOString(),
        ]);
    }
}
