<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Category::query()->orderBy('name')->get(['id', 'name', 'slug'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255', 'unique:categories,slug'],
        ]);

        $category = Category::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255', 'unique:categories,slug,' . $category->id],
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        return response()->json($category);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::query()->findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted.',
        ]);
    }
}
