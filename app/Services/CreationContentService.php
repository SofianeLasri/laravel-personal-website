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
use App\Services\Content\ContentCreationService;
use App\Services\Content\ContentDeletionService;
use App\Services\Content\ContentDuplicationService;
use App\Services\Content\ContentReorderService;
use App\Services\Content\ContentUpdateService;
use App\Services\Content\ContentValidationService;
use Throwable;

/**
 * Creation-specific content service
 * Provides type-safe wrapper for creation content operations
 */
readonly class CreationContentService
{
    public function __construct(
        private ContentCreationService $creationService,
        private ContentUpdateService $updateService,
        private ContentReorderService $reorderService,
        private ContentDeletionService $deletionService,
        private ContentDuplicationService $duplicationService,
        private ContentValidationService $validationService
    ) {}

    /**
     * Create markdown content for a draft
     */
    public function createMarkdownContent(CreationDraft $draft, int $translationKeyId, int $order): CreationDraftContent
    {
        return $this->creationService->createMarkdown($draft, $translationKeyId, $order);
    }

    /**
     * Create gallery content for a draft
     *
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(CreationDraft $draft, array $galleryData, int $order): CreationDraftContent
    {
        return $this->creationService->createGallery($draft, $galleryData, $order);
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
    public function reorderContent(CreationDraft|Creation $parent, array $newOrder): void
    {
        $this->reorderService->reorder($parent, $newOrder);
    }

    /**
     * Delete a content block
     */
    public function deleteContent(CreationDraftContent|CreationContent $content): bool
    {
        return $this->deletionService->delete($content);
    }

    /**
     * Duplicate a content block
     */
    public function duplicateContent(CreationDraftContent|CreationContent $content): CreationDraftContent|CreationContent
    {
        return $this->duplicationService->duplicate($content);
    }

    /**
     * Validate content structure
     */
    public function validateContentStructure(CreationDraft|Creation $parent): bool
    {
        return $this->validationService->validateStructure($parent);
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
