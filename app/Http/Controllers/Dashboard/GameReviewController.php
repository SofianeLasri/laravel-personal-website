<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use App\Models\GameReviewDraftLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameReviewController extends Controller
{
    /**
     * Display the game review for a draft
     */
    public function show(BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft()->with(['coverPicture', 'prosTranslationKey', 'consTranslationKey', 'links.labelTranslationKey'])->first();

        if (! $gameReview) {
            return response()->json(['message' => 'Game review not found'], 404);
        }

        return response()->json([
            'id' => $gameReview->id,
            'blog_post_draft_id' => $gameReview->blog_post_draft_id,
            'game_title' => $gameReview->game_title,
            'release_date' => $gameReview->release_date?->format('Y-m-d'),
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'cover_picture_id' => $gameReview->cover_picture_id,
            'cover_picture' => $gameReview->coverPicture,
            'pros_translation_key_id' => $gameReview->pros_translation_key_id,
            'pros_translation_key' => $gameReview->prosTranslationKey,
            'cons_translation_key_id' => $gameReview->cons_translation_key_id,
            'cons_translation_key' => $gameReview->consTranslationKey,
            'score' => $gameReview->score,
            'links' => $gameReview->links,
            'created_at' => $gameReview->created_at,
            'updated_at' => $gameReview->updated_at,
        ]);
    }

    /**
     * Create a new game review for a draft
     */
    public function store(Request $request, BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        // Check if game review already exists
        if ($draft->gameReviewDraft()->exists()) {
            return response()->json(['message' => 'Game review already exists for this draft'], 409);
        }

        $validated = $request->validate([
            'game_title' => ['required', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'genre' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', 'max:50'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
            'pros_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
            'cons_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $gameReview = $draft->gameReviewDraft()->create($validated);
        $gameReview->load(['coverPicture', 'prosTranslationKey', 'consTranslationKey', 'links']);

        return response()->json([
            'id' => $gameReview->id,
            'blog_post_draft_id' => $gameReview->blog_post_draft_id,
            'game_title' => $gameReview->game_title,
            'release_date' => $gameReview->release_date?->format('Y-m-d'),
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'cover_picture_id' => $gameReview->cover_picture_id,
            'cover_picture' => $gameReview->coverPicture,
            'pros_translation_key_id' => $gameReview->pros_translation_key_id,
            'pros_translation_key' => $gameReview->prosTranslationKey,
            'cons_translation_key_id' => $gameReview->cons_translation_key_id,
            'cons_translation_key' => $gameReview->consTranslationKey,
            'score' => $gameReview->score,
            'links' => $gameReview->links,
            'created_at' => $gameReview->created_at,
            'updated_at' => $gameReview->updated_at,
        ], 201);
    }

    /**
     * Update the game review for a draft
     */
    public function update(Request $request, BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview) {
            return response()->json(['message' => 'Game review not found'], 404);
        }

        $validated = $request->validate([
            'game_title' => ['sometimes', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'genre' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', 'max:50'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
            'pros_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
            'cons_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $gameReview->update($validated);
        $gameReview->load(['coverPicture', 'prosTranslationKey', 'consTranslationKey', 'links']);

        return response()->json([
            'id' => $gameReview->id,
            'blog_post_draft_id' => $gameReview->blog_post_draft_id,
            'game_title' => $gameReview->game_title,
            'release_date' => $gameReview->release_date?->format('Y-m-d'),
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'cover_picture_id' => $gameReview->cover_picture_id,
            'cover_picture' => $gameReview->coverPicture,
            'pros_translation_key_id' => $gameReview->pros_translation_key_id,
            'pros_translation_key' => $gameReview->prosTranslationKey,
            'cons_translation_key_id' => $gameReview->cons_translation_key_id,
            'cons_translation_key' => $gameReview->consTranslationKey,
            'score' => $gameReview->score,
            'links' => $gameReview->links,
            'created_at' => $gameReview->created_at,
            'updated_at' => $gameReview->updated_at,
        ]);
    }

    /**
     * Delete the game review for a draft
     */
    public function destroy(BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview) {
            return response()->json(['message' => 'Game review not found'], 404);
        }

        DB::transaction(function () use ($gameReview) {
            $gameReview->links()->delete();
            $gameReview->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Add a link to the game review
     */
    public function storeLink(Request $request, BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview) {
            return response()->json(['message' => 'Game review not found'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:steam,playstation,xbox,nintendo,epic,gog,official,other'],
            'url' => ['required', 'url', 'max:500'],
            'label_translation_key_id' => ['required', 'exists:translation_keys,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $validated['order'] ?? ($gameReview->links()->max('order') ?? 0) + 1;

        $link = $gameReview->links()->create([
            'type' => $validated['type'],
            'url' => $validated['url'],
            'label_translation_key_id' => $validated['label_translation_key_id'],
            'order' => $order,
        ]);

        $link->load('labelTranslationKey');

        return response()->json([
            'id' => $link->id,
            'game_review_draft_id' => $link->game_review_draft_id,
            'type' => $link->type,
            'url' => $link->url,
            'label_translation_key_id' => $link->label_translation_key_id,
            'label_translation_key' => $link->labelTranslationKey,
            'order' => $link->order,
            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ], 201);
    }

    /**
     * Update a game review link
     */
    public function updateLink(Request $request, BlogPostDraft $draft, GameReviewDraftLink $link): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview || $link->game_review_draft_id !== $gameReview->id) {
            return response()->json(['message' => 'Link not found'], 404);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:steam,playstation,xbox,nintendo,epic,gog,official,other'],
            'url' => ['sometimes', 'url', 'max:500'],
            'label_translation_key_id' => ['sometimes', 'exists:translation_keys,id'],
        ]);

        $link->update($validated);
        $link->load('labelTranslationKey');

        return response()->json([
            'id' => $link->id,
            'game_review_draft_id' => $link->game_review_draft_id,
            'type' => $link->type,
            'url' => $link->url,
            'label_translation_key_id' => $link->label_translation_key_id,
            'label_translation_key' => $link->labelTranslationKey,
            'order' => $link->order,
            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ]);
    }

    /**
     * Delete a game review link
     */
    public function destroyLink(BlogPostDraft $draft, GameReviewDraftLink $link): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview || $link->game_review_draft_id !== $gameReview->id) {
            return response()->json(['message' => 'Link not found'], 404);
        }

        $link->delete();

        return response()->json(null, 204);
    }

    /**
     * Reorder game review links
     */
    public function reorderLinks(Request $request, BlogPostDraft $draft): JsonResponse
    {
        // Ensure this is a game review type
        if ($draft->type !== 'game_review') {
            return response()->json(['message' => 'Draft is not a game review'], 422);
        }

        $gameReview = $draft->gameReviewDraft;

        if (! $gameReview) {
            return response()->json(['message' => 'Game review not found'], 404);
        }

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'exists:game_review_draft_links,id'],
        ]);

        // Verify all link IDs belong to this game review
        $linkIds = $gameReview->links()->pluck('id')->toArray();
        $requestedIds = $validated['order'];

        if (count(array_diff($requestedIds, $linkIds)) > 0) {
            return response()->json(['message' => 'Invalid link IDs provided'], 422);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $index => $linkId) {
                GameReviewDraftLink::where('id', $linkId)->update(['order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Links reordered successfully']);
    }
}
