<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\TranslationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogCategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::with('nameTranslationKey.translations')
            ->orderBy('order')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|unique:blog_categories,slug',
            'name_fr' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create translation key for name
        $nameTranslationKey = TranslationKey::create([
            'key' => 'blog_category_'.$request->slug,
        ]);

        // Create translations
        $nameTranslationKey->translations()->createMany([
            ['locale' => 'fr', 'text' => $request->name_fr],
            ['locale' => 'en', 'text' => $request->name_en],
        ]);

        // Get next order
        $nextOrder = BlogCategory::max('order') + 1;

        // Create category
        $category = BlogCategory::create([
            'slug' => $request->slug,
            'name_translation_key_id' => $nameTranslationKey->id,
            'color' => $request->color,
            'order' => $nextOrder,
        ]);

        // Load relationships for response
        $category->load('nameTranslationKey.translations');

        return response()->json($category, 201);
    }

    public function show(BlogCategory $blogCategory)
    {
        $blogCategory->load('nameTranslationKey.translations');

        return response()->json($blogCategory);
    }

    public function update(Request $request, BlogCategory $blogCategory)
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|unique:blog_categories,slug,'.$blogCategory->id,
            'name_fr' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update translations
        $nameTranslationKey = $blogCategory->nameTranslationKey;

        $nameTranslationKey->translations()->updateOrCreate(
            ['locale' => 'fr'],
            ['text' => $request->name_fr]
        );

        $nameTranslationKey->translations()->updateOrCreate(
            ['locale' => 'en'],
            ['text' => $request->name_en]
        );

        // Update category
        $blogCategory->update([
            'slug' => $request->slug,
            'color' => $request->color,
            'order' => $request->order ?? $blogCategory->order,
        ]);

        // Load relationships for response
        $blogCategory->load('nameTranslationKey.translations');

        return response()->json($blogCategory);
    }

    public function destroy(BlogCategory $blogCategory)
    {
        // Check if category is used by any blog posts or drafts
        $postsCount = $blogCategory->blogPosts()->count();
        $draftsCount = $blogCategory->blogPostDrafts()->count();

        if ($postsCount > 0 || $draftsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete category that is used by blog posts or drafts',
                'posts_count' => $postsCount,
                'drafts_count' => $draftsCount,
            ], 422);
        }

        // Get translation key before deleting category
        $nameTranslationKey = $blogCategory->nameTranslationKey;

        // Delete category first to avoid foreign key constraint
        $blogCategory->delete();

        // Delete translation key and its translations
        $nameTranslationKey->translations()->delete();
        $nameTranslationKey->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|integer|exists:blog_categories,id',
            'categories.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->categories as $categoryData) {
            BlogCategory::where('id', $categoryData['id'])
                ->update(['order' => $categoryData['order']]);
        }

        return response()->json(['message' => 'Categories reordered successfully']);
    }
}
