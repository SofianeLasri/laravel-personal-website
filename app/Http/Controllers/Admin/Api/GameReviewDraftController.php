<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\GameReviewDraft;
use App\Models\TranslationKey;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GameReviewDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'blog_post_draft_id' => 'required|integer|exists:blog_post_drafts,id',
            'game_title' => 'required|string|max:255',
            'release_date' => 'nullable|date',
            'genre' => 'nullable|string|max:100',
            'developer' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|max:50',
            'cover_picture_id' => 'nullable|integer|exists:pictures,id',
            'pros' => 'nullable|string',
            'cons' => 'nullable|string',
            'rating' => 'nullable|string|in:positive,negative',
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
            $prosTranslationKey = null;
            $consTranslationKey = null;

            // Create pros translation key if provided
            if ($request->pros && ! empty($request->pros)) {
                $prosTranslationKey = TranslationKey::create([
                    'key' => 'game_review_pros_'.uniqid(),
                ]);

                $prosTranslationKey->translations()->createMany([
                    ['locale' => 'fr', 'text' => ''],
                    ['locale' => 'en', 'text' => ''],
                ]);

                $prosTranslationKey->translations()->updateOrCreate(
                    ['locale' => $request->locale],
                    ['text' => $request->pros]
                );
            }

            // Create cons translation key if provided
            if ($request->cons && ! empty($request->cons)) {
                $consTranslationKey = TranslationKey::create([
                    'key' => 'game_review_cons_'.uniqid(),
                ]);

                $consTranslationKey->translations()->createMany([
                    ['locale' => 'fr', 'text' => ''],
                    ['locale' => 'en', 'text' => ''],
                ]);

                $consTranslationKey->translations()->updateOrCreate(
                    ['locale' => $request->locale],
                    ['text' => $request->cons]
                );
            }

            // Create the game review draft
            $gameReviewDraft = GameReviewDraft::create([
                'blog_post_draft_id' => $request->blog_post_draft_id,
                'game_title' => $request->game_title,
                'release_date' => $request->release_date,
                'genre' => $request->genre,
                'developer' => $request->developer,
                'publisher' => $request->publisher,
                'platforms' => $request->platforms,
                'cover_picture_id' => $request->cover_picture_id,
                'pros_translation_key_id' => $prosTranslationKey?->id,
                'cons_translation_key_id' => $consTranslationKey?->id,
                'rating' => $request->rating,
            ]);

            DB::commit();

            $gameReviewDraft->load([
                'blogPostDraft',
                'coverPicture',
                'prosTranslationKey.translations',
                'consTranslationKey.translations',
                'links',
            ]);

            return response()->json($gameReviewDraft, 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create game review draft',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(GameReviewDraft $gameReviewDraft): JsonResponse
    {
        $gameReviewDraft->load([
            'blogPostDraft',
            'coverPicture',
            'prosTranslationKey.translations',
            'consTranslationKey.translations',
            'links',
        ]);

        return response()->json($gameReviewDraft);
    }

    public function update(Request $request, GameReviewDraft $gameReviewDraft): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'game_title' => 'required|string|max:255',
            'release_date' => 'nullable|date',
            'genre' => 'nullable|string|max:100',
            'developer' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|max:50',
            'cover_picture_id' => 'nullable|integer|exists:pictures,id',
            'pros' => 'nullable|string',
            'cons' => 'nullable|string',
            'rating' => 'nullable|string|in:positive,negative',
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
            // Handle pros translation
            if ($request->has('pros') && $request->pros && ! empty($request->pros)) {
                if (! $gameReviewDraft->prosTranslationKey) {
                    $prosTranslationKey = TranslationKey::create([
                        'key' => 'game_review_pros_'.uniqid(),
                    ]);

                    $prosTranslationKey->translations()->createMany([
                        ['locale' => 'fr', 'text' => ''],
                        ['locale' => 'en', 'text' => ''],
                    ]);

                    $gameReviewDraft->update(['pros_translation_key_id' => $prosTranslationKey->id]);
                    $gameReviewDraft->refresh();
                }

                $gameReviewDraft->prosTranslationKey?->translations()->updateOrCreate(
                    ['locale' => $request->locale],
                    ['text' => $request->pros]
                );
            } elseif ($request->has('pros') && ($request->pros === null || $request->pros === '')) {
                // Remove pros only if explicitly provided as empty
                if ($gameReviewDraft->prosTranslationKey) {
                    $translationKey = $gameReviewDraft->prosTranslationKey;
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                    $gameReviewDraft->update(['pros_translation_key_id' => null]);
                }
            }

            // Handle cons translation
            if ($request->has('cons') && $request->cons && ! empty($request->cons)) {
                if (! $gameReviewDraft->consTranslationKey) {
                    $consTranslationKey = TranslationKey::create([
                        'key' => 'game_review_cons_'.uniqid(),
                    ]);

                    $consTranslationKey->translations()->createMany([
                        ['locale' => 'fr', 'text' => ''],
                        ['locale' => 'en', 'text' => ''],
                    ]);

                    $gameReviewDraft->update(['cons_translation_key_id' => $consTranslationKey->id]);
                    $gameReviewDraft->refresh();
                }

                $gameReviewDraft->consTranslationKey?->translations()->updateOrCreate(
                    ['locale' => $request->locale],
                    ['text' => $request->cons]
                );
            } elseif ($request->has('cons') && ($request->cons === null || $request->cons === '')) {
                // Remove cons only if explicitly provided as empty
                if ($gameReviewDraft->consTranslationKey) {
                    $translationKey = $gameReviewDraft->consTranslationKey;
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                    $gameReviewDraft->update(['cons_translation_key_id' => null]);
                }
            }

            // Update other fields
            $gameReviewDraft->update([
                'game_title' => $request->game_title,
                'release_date' => $request->release_date,
                'genre' => $request->genre,
                'developer' => $request->developer,
                'publisher' => $request->publisher,
                'platforms' => $request->platforms,
                'cover_picture_id' => $request->cover_picture_id,
                'rating' => $request->rating,
            ]);

            DB::commit();

            $gameReviewDraft->load([
                'blogPostDraft',
                'coverPicture',
                'prosTranslationKey.translations',
                'consTranslationKey.translations',
                'links',
            ]);

            return response()->json($gameReviewDraft);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update game review draft',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(GameReviewDraft $gameReviewDraft): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Delete pros translation key
            if ($gameReviewDraft->prosTranslationKey) {
                $translationKey = $gameReviewDraft->prosTranslationKey;
                $translationKey->translations()->delete();
                $translationKey->delete();
            }

            // Delete cons translation key
            if ($gameReviewDraft->consTranslationKey) {
                $translationKey = $gameReviewDraft->consTranslationKey;
                $translationKey->translations()->delete();
                $translationKey->delete();
            }

            // Delete links
            $gameReviewDraft->links()->delete();

            // Delete the game review draft
            $gameReviewDraft->delete();

            DB::commit();

            return response()->json(['message' => 'Game review draft deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete game review draft',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
