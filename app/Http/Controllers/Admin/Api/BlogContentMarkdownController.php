<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentMarkdown;
use App\Models\TranslationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogContentMarkdownController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create translation key
        $translationKey = TranslationKey::create([
            'key' => 'blog_content_markdown_'.uniqid(),
        ]);

        // Create empty translations for both locales
        $translationKey->translations()->createMany([
            ['locale' => 'fr', 'text' => ''],
            ['locale' => 'en', 'text' => ''],
        ]);

        // Update the translation for the current locale if content is provided
        if ($request->content !== null && $request->content !== '') {
            $translationKey->translations()->updateOrCreate(
                ['locale' => $request->locale],
                ['text' => $request->content]
            );
        }

        // Create the markdown content
        $markdownContent = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $markdownContent->load('translationKey.translations');

        return response()->json($markdownContent, 201);
    }

    public function show(ContentMarkdown $blogContentMarkdown): JsonResponse
    {
        $blogContentMarkdown->load('translationKey.translations');

        return response()->json($blogContentMarkdown);
    }

    public function update(Request $request, ContentMarkdown $blogContentMarkdown): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update the translation for the current locale
        $translationKey = $blogContentMarkdown->translationKey;
        if ($translationKey) {
            $translationKey->translations()->updateOrCreate(
                ['locale' => $request->locale],
                ['text' => $request->content]
            );
        }

        $blogContentMarkdown->load('translationKey.translations');

        return response()->json($blogContentMarkdown);
    }

    public function destroy(ContentMarkdown $blogContentMarkdown): JsonResponse
    {
        // Delete translation key and its translations
        $translationKey = $blogContentMarkdown->translationKey;
        if ($translationKey) {
            $translationKey->translations()->delete();
            $translationKey->delete();
        }

        // Delete the markdown content
        $blogContentMarkdown->delete();

        return response()->json(['message' => 'Markdown content deleted successfully']);
    }
}
