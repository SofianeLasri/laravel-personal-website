<?php

declare(strict_types=1);

namespace App\Services\Conversion\BlogPost;

use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\TranslationKey;
use App\Services\Content\ContentBlockDuplicationService;

/**
 * Service for syncing content between blog post drafts and published posts
 */
readonly class BlogPostContentSyncService
{
    public function __construct(
        private ContentBlockDuplicationService $contentDuplication
    ) {}

    /**
     * Sync all blog post contents from draft to published by duplicating content
     */
    public function sync(BlogPostDraft $draft, BlogPost $blogPost): void
    {
        // Delete existing contents
        $existingContents = $blogPost->contents;

        foreach ($existingContents as $existingContent) {
            $this->deleteRecord($existingContent);
        }

        $blogPost->contents()->delete();

        // Duplicate contents from draft
        $duplicatedContents = $this->contentDuplication->duplicateAllContents($draft->contents);

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
    public function deleteRecord(BlogPostContent $blogContent): void
    {
        $content = $blogContent->content;

        if ($content) {
            if ($content instanceof ContentMarkdown) {
                $translationKey = $content->translationKey;
                if ($translationKey instanceof TranslationKey) {
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                }
            } elseif ($content instanceof ContentGallery) {
                foreach ($content->pictures as $picture) {
                    if ($picture->pivot->caption_translation_key_id) {
                        $captionTranslationKey = TranslationKey::find($picture->pivot->caption_translation_key_id);
                        if ($captionTranslationKey instanceof TranslationKey) {
                            $captionTranslationKey->translations()->delete();
                            $captionTranslationKey->delete();
                        }
                    }
                }
                $content->pictures()->detach();
            } elseif ($content instanceof ContentVideo) {
                if ($content->caption_translation_key_id) {
                    $content->captionTranslationKey?->translations()?->delete();
                    $content->captionTranslationKey?->delete();
                }
            }

            $content->delete();
        }
    }
}
