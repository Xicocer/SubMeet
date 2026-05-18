<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\EventTeaserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $this->ensureNotReservedTagPayload($validated['name'], $slug);

        $tag = Tag::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json($tag, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = Tag::query()->findOrFail($id);
        $this->ensureEditableTag($tag);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255', 'unique:tags,slug,' . $tag->id],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $this->ensureNotReservedTagPayload($validated['name'], $slug);

        $tag->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json($tag);
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::query()->findOrFail($id);
        $this->ensureEditableTag($tag);
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted.',
        ]);
    }

    private function ensureEditableTag(Tag $tag): void
    {
        if ($tag->slug !== EventTeaserService::TAG_SLUG) {
            return;
        }

        throw ValidationException::withMessages([
            'tag' => ['System teaser tag cannot be edited or deleted.'],
        ]);
    }

    private function ensureNotReservedTagPayload(string $name, string $slug): void
    {
        $normalizedName = mb_strtolower(trim($name));

        if ($slug !== EventTeaserService::TAG_SLUG && !in_array($normalizedName, ['тизер', EventTeaserService::TAG_SLUG], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'tag' => ['Teaser is a system tag and is managed automatically.'],
        ]);
    }
}
