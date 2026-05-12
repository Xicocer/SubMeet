<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Tag::query()->orderBy('name')->get(['id', 'name', 'slug'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255', 'unique:tags,slug'],
        ]);

        $tag = Tag::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        return response()->json($tag, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = Tag::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255', 'unique:tags,slug,' . $tag->id],
        ]);

        $tag->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        return response()->json($tag);
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::query()->findOrFail($id);
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted.',
        ]);
    }
}
