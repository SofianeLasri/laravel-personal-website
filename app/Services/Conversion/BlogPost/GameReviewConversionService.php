<?php

declare(strict_types=1);

namespace App\Services\Conversion\BlogPost;

use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Services\Translation\TranslationKeyDuplicationService;
use RuntimeException;

/**
 * Service for handling GameReview conversion between drafts and published posts
 */
class GameReviewConversionService
{
    public function __construct(
        private readonly TranslationKeyDuplicationService $translationDuplication
    ) {}

    /**
     * Create a game review draft from a published game review
     */
    public function createDraftFromReview(GameReview $gameReview, BlogPostDraft $draft): void
    {
        $gameReviewDraftData = [
            'blog_post_draft_id' => $draft->id,
            'game_title' => $gameReview->game_title,
            'release_date' => $gameReview->release_date,
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'cover_picture_id' => $gameReview->cover_picture_id,
            'rating' => $gameReview->rating,
        ];

        // Duplicate translation keys for pros and cons if they exist
        if ($gameReview->pros_translation_key_id) {
            $prosTranslationKey = $gameReview->prosTranslationKey;
            if (! $prosTranslationKey) {
                throw new RuntimeException('Game review missing pros translation key');
            }
            $gameReviewDraftData['pros_translation_key_id'] = $this->translationDuplication->duplicateForDraft($prosTranslationKey)->id;
        }

        if ($gameReview->cons_translation_key_id) {
            $consTranslationKey = $gameReview->consTranslationKey;
            if (! $consTranslationKey) {
                throw new RuntimeException('Game review missing cons translation key');
            }
            $gameReviewDraftData['cons_translation_key_id'] = $this->translationDuplication->duplicateForDraft($consTranslationKey)->id;
        }

        $gameReviewDraft = GameReviewDraft::create($gameReviewDraftData);

        // Duplicate game review links
        foreach ($gameReview->links as $link) {
            $gameReviewDraft->links()->create([
                'type' => $link->type,
                'url' => $link->url,
                'label_translation_key_id' => $this->translationDuplication->duplicateForDraft($link->labelTranslationKey)->id,
                'order' => $link->order,
            ]);
        }
    }

    /**
     * Sync game review from draft to published blog post
     */
    public function syncToPublished(BlogPostDraft $draft, BlogPost $blogPost): void
    {
        if (! $draft->gameReviewDraft) {
            $blogPost->gameReview?->delete();

            return;
        }

        $gameReviewData = [
            'blog_post_id' => $blogPost->id,
            'game_title' => $draft->gameReviewDraft->game_title,
            'release_date' => $draft->gameReviewDraft->release_date,
            'genre' => $draft->gameReviewDraft->genre,
            'developer' => $draft->gameReviewDraft->developer,
            'publisher' => $draft->gameReviewDraft->publisher,
            'platforms' => $draft->gameReviewDraft->platforms,
            'cover_picture_id' => $draft->gameReviewDraft->cover_picture_id,
            'pros_translation_key_id' => $draft->gameReviewDraft->pros_translation_key_id,
            'cons_translation_key_id' => $draft->gameReviewDraft->cons_translation_key_id,
            'rating' => $draft->gameReviewDraft->rating,
        ];

        if ($blogPost->gameReview) {
            $blogPost->gameReview->update($gameReviewData);
            $gameReview = $blogPost->gameReview;
        } else {
            $gameReview = GameReview::create($gameReviewData);
        }

        $this->syncLinks($draft->gameReviewDraft, $gameReview);
    }

    /**
     * Sync game review links from draft to published
     */
    public function syncLinks(GameReviewDraft $gameReviewDraft, GameReview $gameReview): void
    {
        $gameReview->links()->delete();

        foreach ($gameReviewDraft->links as $draftLink) {
            $gameReview->links()->create([
                'type' => $draftLink->type,
                'url' => $draftLink->url,
                'label_translation_key_id' => $draftLink->label_translation_key_id,
                'order' => $draftLink->order,
            ]);
        }
    }
}
