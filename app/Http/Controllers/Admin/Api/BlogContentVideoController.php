<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogContentVideo;
use App\Models\TranslationKey;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogContentVideoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'nullable|integer|exists:videos,id',
            'caption' => 'nullable|string|max:500',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $captionTranslationKey = null;

        // Create caption if provided
        if ($request->caption && ! empty($request->caption)) {
            $captionTranslationKey = TranslationKey::create([
                'key' => 'blog_video_caption_'.uniqid(),
            ]);

            // Create empty translations for both locales
            $captionTranslationKey->translations()->createMany([
                ['locale' => 'fr', 'text' => ''],
                ['locale' => 'en', 'text' => ''],
            ]);

            // Update the translation for the current locale
            $captionTranslationKey->translations()->updateOrCreate(
                ['locale' => $request->locale],
                ['text' => $request->caption]
            );
        }

        // Create the video content
        $videoContent = BlogContentVideo::create([
            'video_id' => $request->video_id,
            'caption_translation_key_id' => $captionTranslationKey?->id,
        ]);

        $videoContent->load(['video', 'captionTranslationKey.translations']);

        return response()->json($videoContent, 201);
    }

    public function show(BlogContentVideo $blogContentVideo)
    {
        $blogContentVideo->load(['video', 'captionTranslationKey.translations']);

        return response()->json($blogContentVideo);
    }

    public function update(Request $request, BlogContentVideo $blogContentVideo)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|integer|exists:videos,id',
            'caption' => 'nullable|string|max:500',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update video_id
        $blogContentVideo->update([
            'video_id' => $request->video_id,
        ]);

        // Handle caption
        if ($request->caption && ! empty($request->caption)) {
            // Create translation key if it doesn't exist
            if (! $blogContentVideo->captionTranslationKey) {
                $captionTranslationKey = TranslationKey::create([
                    'key' => 'blog_video_caption_'.uniqid(),
                ]);

                // Create empty translations for both locales
                $captionTranslationKey->translations()->createMany([
                    ['locale' => 'fr', 'text' => ''],
                    ['locale' => 'en', 'text' => ''],
                ]);

                $blogContentVideo->update([
                    'caption_translation_key_id' => $captionTranslationKey->id,
                ]);
            }

            // Update the translation for the current locale
            $blogContentVideo->captionTranslationKey->translations()->updateOrCreate(
                ['locale' => $request->locale],
                ['text' => $request->caption]
            );
        } else {
            // Remove caption if empty
            if ($blogContentVideo->captionTranslationKey) {
                $translationKey = $blogContentVideo->captionTranslationKey;
                $translationKey->translations()->delete();
                $translationKey->delete();

                $blogContentVideo->update([
                    'caption_translation_key_id' => null,
                ]);
            }
        }

        $blogContentVideo->load(['video', 'captionTranslationKey.translations']);

        return response()->json($blogContentVideo);
    }

    public function destroy(BlogContentVideo $blogContentVideo)
    {
        // Delete caption translation key if exists
        if ($blogContentVideo->captionTranslationKey) {
            $translationKey = $blogContentVideo->captionTranslationKey;
            $translationKey->translations()->delete();
            $translationKey->delete();
        }

        // Delete the video content
        $blogContentVideo->delete();

        return response()->json(['message' => 'Video content deleted successfully']);
    }
}
