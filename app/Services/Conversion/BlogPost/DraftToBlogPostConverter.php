<?php

declare(strict_types=1);

namespace App\Services\Conversion\BlogPost;

use App\Enums\BlogPostType;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Services\Translation\AutoTranslationService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service for converting blog post drafts to published posts
 */
class DraftToBlogPostConverter
{
    public function __construct(
        private readonly BlogPostValidationService $validation,
        private readonly BlogPostContentSyncService $contentSync,
        private readonly GameReviewConversionService $gameReviewConversion,
        private readonly AutoTranslationService $autoTranslation
    ) {}

    /**
     * Convert a BlogPostDraft to a published BlogPost
     *
     * @throws Throwable
     */
    public function convert(BlogPostDraft $draft): BlogPost
    {
        $this->validation->validate($draft);

        return DB::transaction(function () use ($draft) {
            if ($draft->originalBlogPost) {
                $blogPost = $draft->originalBlogPost;
                $blogPost->update($this->mapAttributes($draft));

                $this->contentSync->sync($draft, $blogPost);
                $this->gameReviewConversion->syncToPublished($draft, $blogPost);
            } else {
                $blogPost = BlogPost::create($this->mapAttributes($draft));

                $this->contentSync->sync($draft, $blogPost);
                $this->gameReviewConversion->syncToPublished($draft, $blogPost);

                $draft->update(['original_blog_post_id' => $blogPost->id]);
            }

            // Auto-translate title to English if missing
            $this->autoTranslation->translateFrenchToEnglishIfMissing($blogPost->titleTranslationKey);

            $blogPost->refresh();

            return $blogPost;
        });
    }

    /**
     * Map draft attributes to blog post attributes
     *
     * @return array{slug: string, title_translation_key_id: int, type: BlogPostType, category_id: int, cover_picture_id: int|null}
     */
    private function mapAttributes(BlogPostDraft $draft): array
    {
        return [
            'slug' => $draft->slug,
            'title_translation_key_id' => $draft->title_translation_key_id,
            'type' => $draft->type,
            'category_id' => $draft->category_id,
            'cover_picture_id' => $draft->cover_picture_id,
        ];
    }
}
