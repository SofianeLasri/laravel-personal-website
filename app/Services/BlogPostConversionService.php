<?php

namespace App\Services;

use App\Enums\BlogPostType;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlogPostConversionService
{
    /**
     * Convert a BlogPostDraft to a published BlogPost
     *
     * @throws ValidationException
     */
    public function convertDraftToBlogPost(BlogPostDraft $draft): BlogPost
    {
        $this->validateDraft($draft);

        return DB::transaction(function () use ($draft) {
            if ($draft->originalBlogPost) {
                // Update existing blog post
                $blogPost = $draft->originalBlogPost;
                $blogPost->update($this->mapDraftAttributes($draft));

                $this->syncContents($draft, $blogPost);
                $this->syncGameReview($draft, $blogPost);
            } else {
                // Create new blog post
                $blogPost = BlogPost::create($this->mapDraftAttributes($draft));

                $this->syncContents($draft, $blogPost);
                $this->syncGameReview($draft, $blogPost);

                // Link the draft to the created blog post
                $draft->update(['original_blog_post_id' => $blogPost->id]);
            }

            return $blogPost->fresh();
        });
    }

    /**
     * Validate that the draft is ready for publication
     *
     * @throws ValidationException
     */
    private function validateDraft(BlogPostDraft $draft): void
    {
        $validator = Validator::make([
            'title_translation_key_id' => $draft->title_translation_key_id,
            'slug' => $draft->slug,
            'type' => $draft->type?->value,
            'category_id' => $draft->category_id,
        ], [
            'title_translation_key_id' => 'required|integer|exists:translation_keys,id',
            'slug' => 'required|string|max:255',
            'type' => ['required', Rule::enum(BlogPostType::class)],
            'category_id' => 'required|integer|exists:blog_categories,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Map draft attributes to blog post attributes
     */
    private function mapDraftAttributes(BlogPostDraft $draft): array
    {
        return [
            'slug' => $draft->slug,
            'title_translation_key_id' => $draft->title_translation_key_id,
            'type' => $draft->type,
            'category_id' => $draft->category_id,
            'cover_picture_id' => $draft->cover_picture_id,
        ];
    }

    /**
     * Sync all blog post contents from draft to published
     */
    private function syncContents(BlogPostDraft $draft, BlogPost $blogPost): void
    {
        // Delete existing contents
        $blogPost->contents()->delete();

        // Create new contents from draft
        foreach ($draft->contents as $draftContent) {
            BlogPostContent::create([
                'blog_post_id' => $blogPost->id,
                'content_type' => $draftContent->content_type,
                'content_id' => $draftContent->content_id,
                'order' => $draftContent->order,
            ]);
        }
    }

    /**
     * Convert GameReviewDraft to GameReview if exists
     */
    private function syncGameReview(BlogPostDraft $draft, BlogPost $blogPost): void
    {
        if (! $draft->gameReviewDraft) {
            // Delete existing game review if draft doesn't have one
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
            // Update existing game review
            $blogPost->gameReview->update($gameReviewData);
            $gameReview = $blogPost->gameReview;
        } else {
            // Create new game review
            $gameReview = GameReview::create($gameReviewData);
        }

        // Sync game review links
        $this->syncGameReviewLinks($draft->gameReviewDraft, $gameReview);
    }

    /**
     * Sync game review links from draft to published
     */
    private function syncGameReviewLinks(GameReviewDraft $gameReviewDraft, GameReview $gameReview): void
    {
        // Delete existing links
        $gameReview->links()->delete();

        // Create new links from draft
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
