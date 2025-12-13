<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Services\Content\ContentCreationService;
use App\Services\Content\ContentDeletionService;
use App\Services\Content\ContentDuplicationService;
use App\Services\Content\ContentReorderService;
use App\Services\Content\ContentUpdateService;
use App\Services\Content\ContentValidationService;
use Throwable;

/**
 * Blog-specific content service
 * Provides type-safe wrapper for blog content operations
 */
class BlogContentService
{
    public function __construct(
        private readonly ContentCreationService $creationService,
        private readonly ContentUpdateService $updateService,
        private readonly ContentReorderService $reorderService,
        private readonly ContentDeletionService $deletionService,
        private readonly ContentDuplicationService $duplicationService,
        private readonly ContentValidationService $validationService
    ) {}

    /**
     * Create markdown content for a draft
     */
    public function createMarkdownContent(BlogPostDraft $draft, int $translationKeyId, int $order): BlogPostDraftContent
    {
        return $this->creationService->createMarkdown($draft, $translationKeyId, $order);
    }

    /**
     * Create gallery content for a draft
     *
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(BlogPostDraft $draft, array $galleryData, int $order): BlogPostDraftContent
    {
        return $this->creationService->createGallery($draft, $galleryData, $order);
    }

    /**
     * Create video content for a draft
     */
    public function createVideoContent(
        BlogPostDraft $draft,
        int $videoId,
        int $order,
        ?int $captionTranslationKeyId = null
    ): BlogPostDraftContent {
        return $this->creationService->createVideo($draft, $videoId, $order, $captionTranslationKeyId);
    }

    /**
     * Update markdown content
     */
    public function updateMarkdownContent(ContentMarkdown $markdown, int $translationKeyId): ContentMarkdown
    {
        return $this->updateService->updateMarkdown($markdown, $translationKeyId);
    }

    /**
     * Update gallery content
     *
     * @param  array<string, mixed>  $updateData
     */
    public function updateGalleryContent(ContentGallery $gallery, array $updateData): ContentGallery
    {
        return $this->updateService->updateGallery($gallery, $updateData);
    }

    /**
     * Update video content
     */
    public function updateVideoContent(
        ContentVideo $videoContent,
        int $videoId,
        ?int $captionTranslationKeyId = null
    ): ContentVideo {
        return $this->updateService->updateVideo($videoContent, $videoId, $captionTranslationKeyId);
    }

    /**
     * Reorder content blocks
     *
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorderContent(BlogPostDraft|BlogPost $parent, array $newOrder): void
    {
        $this->reorderService->reorder($parent, $newOrder);
    }

    /**
     * Delete a content block
     */
    public function deleteContent(BlogPostDraftContent|BlogPostContent $content): bool
    {
        return $this->deletionService->delete($content);
    }

    /**
     * Duplicate a content block
     */
    public function duplicateContent(BlogPostDraftContent|BlogPostContent $content): BlogPostDraftContent|BlogPostContent
    {
        return $this->duplicationService->duplicate($content);
    }

    /**
     * Validate content structure
     */
    public function validateContentStructure(BlogPostDraft|BlogPost $parent): bool
    {
        return $this->validationService->validateStructure($parent);
    }

    /**
     * Create content for published post (used during publishing)
     */
    public function createPostContent(BlogPost $post, string $contentType, int $contentId, int $order): BlogPostContent
    {
        return $post->contents()->create([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'order' => $order,
        ]);
    }
}
