<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use Throwable;

/**
 * Creation-specific content service that delegates to ContentManagementService
 * Provides type-safe wrapper for creation content operations
 */
class CreationContentService
{
    public function __construct(
        protected ContentManagementService $contentManagementService
    ) {}

    /**
     * Create markdown content for a draft
     */
    public function createMarkdownContent(CreationDraft $draft, int $translationKeyId, int $order): CreationDraftContent
    {
        return $this->contentManagementService->createMarkdownContent($draft, $translationKeyId, $order);
    }

    /**
     * Create gallery content for a draft
     *
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(CreationDraft $draft, array $galleryData, int $order): CreationDraftContent
    {
        return $this->contentManagementService->createGalleryContent($draft, $galleryData, $order);
    }

    /**
     * Create video content for a draft
     */
    public function createVideoContent(
        CreationDraft $draft,
        int $videoId,
        int $order,
        ?int $captionTranslationKeyId = null
    ): CreationDraftContent {
        return $this->contentManagementService->createVideoContent($draft, $videoId, $order, $captionTranslationKeyId);
    }

    /**
     * Update markdown content
     */
    public function updateMarkdownContent(ContentMarkdown $markdown, int $translationKeyId): ContentMarkdown
    {
        return $this->contentManagementService->updateMarkdownContent($markdown, $translationKeyId);
    }

    /**
     * Update gallery content
     *
     * @param  array<string, mixed>  $updateData
     */
    public function updateGalleryContent(ContentGallery $gallery, array $updateData): ContentGallery
    {
        return $this->contentManagementService->updateGalleryContent($gallery, $updateData);
    }

    /**
     * Update video content
     */
    public function updateVideoContent(
        ContentVideo $videoContent,
        int $videoId,
        ?int $captionTranslationKeyId = null
    ): ContentVideo {
        return $this->contentManagementService->updateVideoContent($videoContent, $videoId, $captionTranslationKeyId);
    }

    /**
     * Reorder content blocks
     *
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorderContent(CreationDraft|Creation $parent, array $newOrder): void
    {
        $this->contentManagementService->reorderContent($parent, $newOrder);
    }

    /**
     * Delete a content block
     */
    public function deleteContent(CreationDraftContent|CreationContent $content): bool
    {
        return $this->contentManagementService->deleteContent($content);
    }

    /**
     * Duplicate a content block
     */
    public function duplicateContent(CreationDraftContent|CreationContent $content): CreationDraftContent|CreationContent
    {
        return $this->contentManagementService->duplicateContent($content);
    }

    /**
     * Validate content structure
     */
    public function validateContentStructure(CreationDraft|Creation $parent): bool
    {
        return $this->contentManagementService->validateContentStructure($parent);
    }

    /**
     * Create content for published creation (used during publishing)
     */
    public function createCreationContent(Creation $creation, string $contentType, int $contentId, int $order): CreationContent
    {
        return $creation->contents()->create([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'order' => $order,
        ]);
    }
}
