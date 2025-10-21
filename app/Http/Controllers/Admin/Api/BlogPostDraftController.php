<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\BlogPostType;
use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BlogPostDraftController extends Controller
{
    public function index(): JsonResponse
    {
        $drafts = BlogPostDraft::with([
            'titleTranslationKey.translations',
            'category',
            'coverPicture',
            'originalBlogPost',
        ])->get();

        return response()->json($drafts);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
            'title_content' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(BlogPostType::values())],
            'category_id' => 'required|integer|exists:blog_categories,id',
            'cover_picture_id' => 'nullable|integer|exists:pictures,id',
            'original_blog_post_id' => 'nullable|integer|exists:blog_posts,id',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create or update translation using the same pattern as CreationDraft
        $translationKey = null;
        if ($request->title_translation_key_id) {
            $translationKey = TranslationKey::find($request->title_translation_key_id);
        }

        $titleTranslation = Translation::createOrUpdate(
            $translationKey ?? 'blog_post_draft_title_'.uniqid(),
            $request->locale,
            $request->title_content
        );

        // Create the draft
        $draft = BlogPostDraft::create([
            'slug' => $request->slug,
            'title_translation_key_id' => $titleTranslation->translation_key_id,
            'type' => $request->type,
            'category_id' => $request->category_id,
            'cover_picture_id' => $request->cover_picture_id,
            'original_blog_post_id' => $request->original_blog_post_id,
        ]);

        // Load relationships for response
        $draft->load([
            'titleTranslationKey.translations',
            'category',
            'coverPicture',
            'originalBlogPost',
        ]);

        return response()->json($draft, 201);
    }

    public function show(BlogPostDraft $blogPostDraft): JsonResponse
    {
        $blogPostDraft->load([
            'titleTranslationKey.translations',
            'category',
            'coverPicture',
            'originalBlogPost',
            'contents.content',
            'gameReviewDraft',
        ]);

        return response()->json($blogPostDraft);
    }

    public function update(Request $request, BlogPostDraft $blogPostDraft): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
            'title_content' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(BlogPostType::values())],
            'category_id' => 'required|integer|exists:blog_categories,id',
            'cover_picture_id' => 'nullable|integer|exists:pictures,id',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update translation using the same pattern as CreationDraft
        $titleTranslationKey = $blogPostDraft->titleTranslationKey;

        if (! $titleTranslationKey) {
            return response()->json([
                'message' => 'Translation key not found',
                'errors' => ['title_translation_key_id' => ['The translation key is missing']],
            ], 422);
        }

        Translation::createOrUpdate(
            $titleTranslationKey,
            $request->locale,
            $request->title_content
        );

        // Update draft
        $blogPostDraft->update([
            'slug' => $request->slug,
            'type' => $request->type,
            'category_id' => $request->category_id,
            'cover_picture_id' => $request->cover_picture_id,
        ]);

        // Load relationships for response
        $blogPostDraft->load([
            'titleTranslationKey.translations',
            'category',
            'coverPicture',
            'originalBlogPost',
        ]);

        return response()->json($blogPostDraft);
    }

    public function destroy(BlogPostDraft $blogPostDraft): JsonResponse
    {
        // Get translation key before deleting the draft
        $titleTranslationKey = $blogPostDraft->titleTranslationKey;

        // Delete the draft first to avoid foreign key constraints
        $blogPostDraft->delete();

        // Then delete translation key and its translations
        if ($titleTranslationKey) {
            $titleTranslationKey->translations()->delete();
            $titleTranslationKey->delete();
        }

        return response()->json(['message' => 'Draft deleted successfully']);
    }
}
