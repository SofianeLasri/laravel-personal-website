<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlogCategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(): JsonResponse
    {
        $categories = BlogCategory::query()
            ->withCount('blogPosts')
            ->orderBy('order')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $this->getCategoryName($category),
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'order' => $category->order,
                    'posts_count' => $category->blog_posts_count,
                ];
            });

        return response()->json(['data' => $categories]);
    }

    /**
     * Display the specified category
     */
    public function show(BlogCategory $category): JsonResponse
    {
        return response()->json([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $this->getCategoryName($category),
            'icon' => $category->icon,
            'color' => $category->color,
            'order' => $category->order,
        ]);
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:blog_categories,slug'],
            'name_translation_key_id' => ['required', 'exists:translation_keys,id'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = DB::transaction(function () use ($validated) {
            // Store name translation key separately
            $nameKeyId = $validated['name_translation_key_id'];
            unset($validated['name_translation_key_id']);

            $category = BlogCategory::create(array_merge($validated, [
                'order' => $validated['order'] ?? 0,
            ]));

            // Store the name translation key relationship (we'll add this column later if needed)
            // For now, we'll use a naming convention where the slug matches a translation key

            return $category;
        });

        return response()->json([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $this->getCategoryName($category),
            'icon' => $category->icon,
            'color' => $category->color,
            'order' => $category->order,
        ], 201);
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, BlogCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('blog_categories')->ignore($category->id)],
            'name_translation_key_id' => ['sometimes', 'exists:translation_keys,id'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Remove name_translation_key_id as we handle it separately
        if (isset($validated['name_translation_key_id'])) {
            unset($validated['name_translation_key_id']);
        }

        $category->update($validated);

        return response()->json([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $this->getCategoryName($category),
            'icon' => $category->icon,
            'color' => $category->color,
            'order' => $category->order,
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(BlogCategory $category): JsonResponse
    {
        if ($category->blogPosts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing posts',
            ], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'exists:blog_categories,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $index => $categoryId) {
                BlogCategory::where('id', $categoryId)->update(['order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Categories reordered successfully']);
    }

    /**
     * Get category name from translations
     */
    private function getCategoryName(BlogCategory $category): string
    {
        // For now, we'll use the slug as the name
        // In a full implementation, we'd have a name_translation_key_id column
        // and fetch the translation based on the current locale
        return ucfirst(str_replace('-', ' ', $category->slug));
    }
}
