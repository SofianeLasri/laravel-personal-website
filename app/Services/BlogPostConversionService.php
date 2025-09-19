<?php

namespace App\Services;

use App\Enums\BlogPostType;
use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlogPostConversionService
{
    public function __construct(
        private BlogContentDuplicationService $contentDuplicationService
    ) {}

    /**
     * Create a draft from an existing published blog post
     * This allows editing a published post through the draft workflow
     */
    public function createDraftFromBlogPost(BlogPost $blogPost): BlogPostDraft
    {
        return DB::transaction(function () use ($blogPost) {
            // Create draft from published post
            $draft = BlogPostDraft::create([
                'original_blog_post_id' => $blogPost->id,
                'slug' => $blogPost->slug,
                'title_translation_key_id' => $this->duplicateTranslationKey($blogPost->titleTranslationKey)->id,
                'type' => $blogPost->type,
                'category_id' => $blogPost->category_id,
                'cover_picture_id' => $blogPost->cover_picture_id,
            ]);

            // Duplicate all contents from published post to draft
            $duplicatedContents = $this->contentDuplicationService->duplicateAllContents($blogPost->contents);

            foreach ($duplicatedContents as $contentData) {
                $draft->contents()->create([
                    'content_type' => $contentData['content_type'],
                    'content_id' => $contentData['content_id'],
                    'order' => $contentData['order'],
                ]);
            }

            // Duplicate game review if it exists
            if ($blogPost->gameReview) {
                $this->createGameReviewDraft($blogPost->gameReview, $draft);
            }

            return $draft;
        });
    }

    /**
     * Create a game review draft from a published game review
     */
    private function createGameReviewDraft(GameReview $gameReview, BlogPostDraft $draft): void
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
            $gameReviewDraftData['pros_translation_key_id'] = $this->duplicateTranslationKey($gameReview->prosTranslationKey)->id;
        }

        if ($gameReview->cons_translation_key_id) {
            $gameReviewDraftData['cons_translation_key_id'] = $this->duplicateTranslationKey($gameReview->consTranslationKey)->id;
        }

        $gameReviewDraft = GameReviewDraft::create($gameReviewDraftData);

        // Duplicate game review links
        foreach ($gameReview->links as $link) {
            $gameReviewDraft->links()->create([
                'type' => $link->type,
                'url' => $link->url,
                'label_translation_key_id' => $this->duplicateTranslationKey($link->labelTranslationKey)->id,
                'order' => $link->order,
            ]);
        }
    }

    /**
     * Duplicate a translation key with all its translations
     */
    private function duplicateTranslationKey(TranslationKey $originalTranslationKey): TranslationKey
    {
        // Create new translation key with a unique key
        $newTranslationKey = TranslationKey::create([
            'key' => $this->generateUniqueTranslationKey($originalTranslationKey->key),
        ]);

        // Duplicate all translations
        foreach ($originalTranslationKey->translations as $translation) {
            $newTranslationKey->translations()->create([
                'locale' => $translation->locale,
                'text' => $translation->text,
            ]);
        }

        return $newTranslationKey;
    }

    /**
     * Generate a unique translation key based on the original key
     */
    private function generateUniqueTranslationKey(string $originalKey): string
    {
        $baseKey = $originalKey.'_draft';
        $key = $baseKey;
        $counter = 1;

        while (TranslationKey::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$counter;
            $counter++;
        }

        return $key;
    }

    /**
     * Convert a BlogPostDraft to a published BlogPost
     *
     * @param BlogPostDraft $draft
     * @return BlogPost
     * @throws Throwable
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
     * Sync all blog post contents from draft to published by duplicating content
     */
    private function syncContents(BlogPostDraft $draft, BlogPost $blogPost): void
    {
        // Delete existing contents (this will not affect draft contents since we duplicate)
        $existingContents = $blogPost->contents;

        // Clean up old content records that are no longer needed
        foreach ($existingContents as $existingContent) {
            $this->deleteContentRecord($existingContent);
        }

        $blogPost->contents()->delete();

        // Duplicate contents from draft to create independent published content
        $duplicatedContents = $this->contentDuplicationService->duplicateAllContents($draft->contents);

        foreach ($duplicatedContents as $contentData) {
            BlogPostContent::create([
                'blog_post_id' => $blogPost->id,
                'content_type' => $contentData['content_type'],
                'content_id' => $contentData['content_id'],
                'order' => $contentData['order'],
            ]);
        }
    }

    /**
     * Delete the actual content record (markdown, gallery, video) when cleaning up
     */
    private function deleteContentRecord(BlogPostContent $blogContent): void
    {
        $content = $blogContent->content;

        if ($content) {
            // Handle specific cleanup based on content type
            if ($content instanceof BlogContentMarkdown) {
                // Delete translation key and its translations
                $content->translationKey?->translations()?->delete();
                $content->translationKey?->delete();
            } elseif ($content instanceof BlogContentGallery) {
                // Detach pictures and delete caption translation keys
                foreach ($content->pictures as $picture) {
                    if ($picture->pivot->caption_translation_key_id) {
                        $captionTranslationKey = TranslationKey::find($picture->pivot->caption_translation_key_id);
                        if ($captionTranslationKey) {
                            $captionTranslationKey->translations()->delete();
                            $captionTranslationKey->delete();
                        }
                    }
                }
                $content->pictures()->detach();
            } elseif ($content instanceof BlogContentVideo) {
                // Delete caption translation key if it exists
                if ($content->caption_translation_key_id) {
                    $content->captionTranslationKey?->translations()?->delete();
                    $content->captionTranslationKey?->delete();
                }
            }

            // Delete the content record itself
            $content->delete();
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
