<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogContentGallery;
use App\Models\TranslationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BlogContentGalleryController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'layout' => 'required|string|in:grid,masonry,carousel',
            'columns' => 'required|integer|min:1|max:6',
            'picture_ids' => 'nullable|array',
            'picture_ids.*' => 'integer|exists:pictures,id',
            'captions' => 'nullable|array',
            'captions.*' => 'nullable|string|max:500',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the gallery
            $gallery = BlogContentGallery::create([
                'layout' => $request->layout,
                'columns' => $request->columns,
            ]);

            // Attach pictures with captions if any provided
            if ($request->picture_ids && count($request->picture_ids) > 0) {
                foreach ($request->picture_ids as $index => $pictureId) {
                    $captionTranslationKey = null;

                    // Create caption if provided
                    if (isset($request->captions[$index]) && ! empty($request->captions[$index])) {
                        $captionTranslationKey = TranslationKey::create([
                            'key' => 'blog_gallery_caption_'.uniqid(),
                        ]);

                        // Create empty translations for both locales
                        $captionTranslationKey->translations()->createMany([
                            ['locale' => 'fr', 'text' => ''],
                            ['locale' => 'en', 'text' => ''],
                        ]);

                        // Update the translation for the current locale
                        $captionTranslationKey->translations()->updateOrCreate(
                            ['locale' => $request->locale],
                            ['text' => $request->captions[$index]]
                        );
                    }

                    $gallery->pictures()->attach($pictureId, [
                        'order' => $index + 1,
                        'caption_translation_key_id' => $captionTranslationKey?->id,
                    ]);
                }
            }

            DB::commit();

            $gallery->load('pictures');

            return response()->json($gallery, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create gallery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(BlogContentGallery $blogContentGallery)
    {
        $blogContentGallery->load('pictures');

        return response()->json($blogContentGallery);
    }

    public function update(Request $request, BlogContentGallery $blogContentGallery)
    {
        $validator = Validator::make($request->all(), [
            'layout' => 'required|string|in:grid,masonry,carousel',
            'columns' => 'required|integer|min:1|max:6',
            'picture_ids' => 'nullable|array',
            'picture_ids.*' => 'integer|exists:pictures,id',
            'captions' => 'nullable|array',
            'captions.*' => 'nullable|string|max:500',
            'locale' => 'required|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update gallery properties
            $blogContentGallery->update([
                'layout' => $request->layout,
                'columns' => $request->columns,
            ]);

            // Delete old caption translation keys
            $oldCaptionKeys = DB::table('blog_content_gallery_pictures')
                ->where('gallery_id', $blogContentGallery->id)
                ->whereNotNull('caption_translation_key_id')
                ->pluck('caption_translation_key_id');

            foreach ($oldCaptionKeys as $keyId) {
                $translationKey = TranslationKey::find($keyId);
                if ($translationKey) {
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                }
            }

            // Detach all existing pictures
            $blogContentGallery->pictures()->detach();

            // Attach new pictures with captions if any provided
            if ($request->picture_ids && count($request->picture_ids) > 0) {
                foreach ($request->picture_ids as $index => $pictureId) {
                    $captionTranslationKey = null;

                    // Create caption if provided
                    if (isset($request->captions[$index]) && ! empty($request->captions[$index])) {
                        $captionTranslationKey = TranslationKey::create([
                            'key' => 'blog_gallery_caption_'.uniqid(),
                        ]);

                        // Create empty translations for both locales
                        $captionTranslationKey->translations()->createMany([
                            ['locale' => 'fr', 'text' => ''],
                            ['locale' => 'en', 'text' => ''],
                        ]);

                        // Update the translation for the current locale
                        $captionTranslationKey->translations()->updateOrCreate(
                            ['locale' => $request->locale],
                            ['text' => $request->captions[$index]]
                        );
                    }

                    $blogContentGallery->pictures()->attach($pictureId, [
                        'order' => $index + 1,
                        'caption_translation_key_id' => $captionTranslationKey?->id,
                    ]);
                }
            }

            DB::commit();

            $blogContentGallery->load('pictures');

            return response()->json($blogContentGallery);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update gallery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(BlogContentGallery $blogContentGallery)
    {
        DB::beginTransaction();

        try {
            // Delete caption translation keys
            $captionKeys = DB::table('blog_content_gallery_pictures')
                ->where('gallery_id', $blogContentGallery->id)
                ->whereNotNull('caption_translation_key_id')
                ->pluck('caption_translation_key_id');

            foreach ($captionKeys as $keyId) {
                $translationKey = TranslationKey::find($keyId);
                if ($translationKey) {
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                }
            }

            // Delete the gallery (pivot records will be deleted automatically)
            $blogContentGallery->delete();

            DB::commit();

            return response()->json(['message' => 'Gallery deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete gallery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
