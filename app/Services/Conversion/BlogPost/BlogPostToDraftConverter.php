<?php

declare(strict_types=1);

namespace App\Services\Conversion\BlogPost;

use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Services\Content\ContentBlockDuplicationService;
use App\Services\Translation\TranslationKeyDuplicationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service for converting published blog posts to drafts
 */
readonly class BlogPostToDraftConverter
{
    public function __construct(
        private TranslationKeyDuplicationService $translationDuplication,
        private ContentBlockDuplicationService $contentDuplication,
        private GameReviewConversionService $gameReviewConversion
    ) {}

    /**
     * Create a draft from an existing published blog post
     */
    public function convert(BlogPost $blogPost): BlogPostDraft
    {
        return DB::transaction(function () use ($blogPost) {
            $titleTranslationKey = $blogPost->titleTranslationKey;
            if (! $titleTranslationKey) {
                throw new RuntimeException('Blog post missing title translation key');
            }

            // Create draft from published post
            $draft = BlogPostDraft::create([
                'original_blog_post_id' => $blogPost->id,
                'slug' => $blogPost->slug,
                'title_translation_key_id' => $this->translationDuplication->duplicateForDraft($titleTranslationKey)->id,
                'type' => $blogPost->type,
                'category_id' => $blogPost->category_id,
                'cover_picture_id' => $blogPost->cover_picture_id,
            ]);

            // Duplicate all contents
            $duplicatedContents = $this->contentDuplication->duplicateAllContents($blogPost->contents);

            foreach ($duplicatedContents as $contentData) {
                $draft->contents()->create([
                    'content_type' => $contentData['content_type'],
                    'content_id' => $contentData['content_id'],
                    'order' => $contentData['order'],
                ]);
            }

            // Duplicate game review if it exists
            if ($blogPost->gameReview) {
                $this->gameReviewConversion->createDraftFromReview($blogPost->gameReview, $draft);
            }

            return $draft;
        });
    }
}
