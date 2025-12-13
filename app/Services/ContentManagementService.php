<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Services\Content\ContentCreationService;
use App\Services\Content\ContentDeletionService;
use App\Services\Content\ContentDuplicationService;
use App\Services\Content\ContentReorderService;
use App\Services\Content\ContentUpdateService;
use App\Services\Content\ContentValidationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Generic content management service for polymorphic content blocks
 * Supports BlogPost, BlogPostDraft, Creation, CreationDraft
 *
 * @deprecated This service is being refactored. Use the specialized services instead:
 * - ContentCreationService for creating content blocks
 * - ContentUpdateService for updating content blocks
 * - ContentReorderService for reordering content blocks
 * - ContentDeletionService for deleting content blocks
 * - ContentDuplicationService for duplicating content blocks
 * - ContentValidationService for validating content structure
 */
class ContentManagementService
{
    public function __construct(
        private readonly ?ContentCreationService $creationService = null,
        private readonly ?ContentUpdateService $updateService = null,
        private readonly ?ContentReorderService $reorderService = null,
        private readonly ?ContentDeletionService $deletionService = null,
        private readonly ?ContentDuplicationService $duplicationService = null,
        private readonly ?ContentValidationService $validationService = null
    ) {}

    /**
     * Create markdown content for a parent entity
     *
     * @deprecated Use ContentCreationService::createMarkdown() instead
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function createMarkdownContent(Model $parent, int $translationKeyId, int $order): Model
    {
        if ($this->creationService) {
            return $this->creationService->createMarkdown($parent, $translationKeyId, $order);
        }

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKeyId,
        ]);

        return $parent->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => $order,
        ]);
    }

    /**
     * Create gallery content for a parent entity
     *
     * @deprecated Use ContentCreationService::createGallery() instead
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(Model $parent, array $galleryData, int $order): Model
    {
        if ($this->creationService) {
            return $this->creationService->createGallery($parent, $galleryData, $order);
        }

        $gallery = ContentGallery::create([
            'layout' => $galleryData['layout'],
            'columns' => $galleryData['columns'] ?? null,
        ]);

        if (! empty($galleryData['pictures'])) {
            $pictureData = [];
            foreach ($galleryData['pictures'] as $index => $pictureId) {
                $pictureData[$pictureId] = ['order' => $index + 1];
            }
            $gallery->pictures()->attach($pictureData);
        }

        return $parent->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => $order,
        ]);
    }

    /**
     * Create video content for a parent entity
     *
     * @deprecated Use ContentCreationService::createVideo() instead
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function createVideoContent(
        Model $parent,
        int $videoId,
        int $order,
        ?int $captionTranslationKeyId = null
    ): Model {
        if ($this->creationService) {
            return $this->creationService->createVideo($parent, $videoId, $order, $captionTranslationKeyId);
        }

        $videoContent = ContentVideo::create([
            'video_id' => $videoId,
            'caption_translation_key_id' => $captionTranslationKeyId,
        ]);

        return $parent->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => $order,
        ]);
    }

    /**
     * Update markdown content
     *
     * @deprecated Use ContentUpdateService::updateMarkdown() instead
     */
    public function updateMarkdownContent(ContentMarkdown $markdown, int $translationKeyId): ContentMarkdown
    {
        if ($this->updateService) {
            return $this->updateService->updateMarkdown($markdown, $translationKeyId);
        }

        $markdown->update([
            'translation_key_id' => $translationKeyId,
        ]);

        $markdown->refresh();

        return $markdown;
    }

    /**
     * Update gallery content
     *
     * @deprecated Use ContentUpdateService::updateGallery() instead
     *
     * @param  array<string, mixed>  $updateData
     */
    public function updateGalleryContent(ContentGallery $gallery, array $updateData): ContentGallery
    {
        if ($this->updateService) {
            return $this->updateService->updateGallery($gallery, $updateData);
        }

        $gallery->update([
            'layout' => $updateData['layout'],
            'columns' => $updateData['columns'] ?? null,
        ]);

        if (isset($updateData['pictures'])) {
            $pictureData = [];
            foreach ($updateData['pictures'] as $index => $pictureId) {
                $pictureData[$pictureId] = ['order' => $index + 1];
            }
            $gallery->pictures()->sync($pictureData);
        }

        $gallery->refresh();
        $gallery->load('pictures');

        return $gallery;
    }

    /**
     * Update video content
     *
     * @deprecated Use ContentUpdateService::updateVideo() instead
     */
    public function updateVideoContent(
        ContentVideo $videoContent,
        int $videoId,
        ?int $captionTranslationKeyId = null
    ): ContentVideo {
        if ($this->updateService) {
            return $this->updateService->updateVideo($videoContent, $videoId, $captionTranslationKeyId);
        }

        $videoContent->update([
            'video_id' => $videoId,
            'caption_translation_key_id' => $captionTranslationKeyId,
        ]);

        $videoContent->refresh();

        return $videoContent;
    }

    /**
     * Reorder content blocks
     *
     * @deprecated Use ContentReorderService::reorder() instead
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorderContent(Model $parent, array $newOrder): void
    {
        if ($this->reorderService) {
            $this->reorderService->reorder($parent, $newOrder);

            return;
        }

        DB::transaction(function () use ($parent, $newOrder) {
            foreach ($newOrder as $index => $contentId) {
                $parent->contents()
                    ->where('id', $contentId)
                    ->update(['order' => $index + 1]);
            }
        });
    }

    /**
     * Delete a content block
     *
     * @deprecated Use ContentDeletionService::delete() instead
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     */
    public function deleteContent(Model $content): bool
    {
        if ($this->deletionService) {
            return $this->deletionService->delete($content);
        }

        return DB::transaction(function () use ($content): bool {
            // Delete the actual content
            if ($content->content) {
                if ($content->content instanceof ContentGallery) {
                    $content->content->pictures()->detach();
                }
                $content->content->delete();
            }

            // Delete the pivot record
            return (bool) $content->delete();
        });
    }

    /**
     * Duplicate a content block
     *
     * @deprecated Use ContentDuplicationService::duplicate() instead
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     * @return Model The duplicated content block
     */
    public function duplicateContent(Model $content): Model
    {
        if ($this->duplicationService) {
            return $this->duplicationService->duplicate($content);
        }

        return DB::transaction(function () use ($content) {
            $newContent = null;

            // Duplicate the actual content
            switch ($content->content_type) {
                case ContentMarkdown::class:
                    $original = $content->content;
                    if ($original instanceof ContentMarkdown) {
                        $newContent = ContentMarkdown::create([
                            'translation_key_id' => $original->translation_key_id,
                        ]);
                    }
                    break;

                case ContentGallery::class:
                    $original = $content->content;
                    if ($original instanceof ContentGallery) {
                        $newContent = ContentGallery::create([
                            'layout' => $original->layout,
                            'columns' => $original->columns,
                        ]);

                        // Copy picture relationships
                        $pictureData = [];
                        foreach ($original->pictures as $picture) {
                            $pictureData[$picture->id] = [
                                'order' => $picture->pivot->order,
                                'caption_translation_key_id' => $picture->pivot->caption_translation_key_id,
                            ];
                        }
                        if (! empty($pictureData)) {
                            $newContent->pictures()->attach($pictureData);
                        }
                    }
                    break;

                case ContentVideo::class:
                    $original = $content->content;
                    if ($original instanceof ContentVideo) {
                        $newContent = ContentVideo::create([
                            'video_id' => $original->video_id,
                            'caption_translation_key_id' => $original->caption_translation_key_id,
                        ]);
                    }
                    break;
            }

            // Get the parent (draft or published entity)
            $parent = $this->getParentFromContent($content);

            if (! $parent) {
                throw new RuntimeException('Parent entity not found');
            }

            if (! $newContent) {
                throw new RuntimeException('Failed to duplicate content');
            }

            // Get the max order value
            $maxOrder = $parent->contents()->max('order') ?? 0;

            // Create the duplicate pivot record
            return $parent->contents()->create([
                'content_type' => $content->content_type,
                'content_id' => $newContent->id,
                'order' => $maxOrder + 1,
            ]);
        });
    }

    /**
     * Validate content structure
     *
     * @deprecated Use ContentValidationService::validateStructure() instead
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function validateContentStructure(Model $parent): bool
    {
        if ($this->validationService) {
            return $this->validationService->validateStructure($parent);
        }

        // A valid entity must have at least one content block
        return $parent->contents()->exists();
    }

    /**
     * Get the parent entity from a content block
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     * @return Model|null The parent entity (BlogPostDraft|BlogPost|CreationDraft|Creation)
     */
    protected function getParentFromContent(Model $content): ?Model
    {
        // Try different relationship names based on content type
        $possibleRelations = [
            'blogPostDraft',
            'blogPost',
            'creationDraft',
            'creation',
        ];

        foreach ($possibleRelations as $relation) {
            if (method_exists($content, $relation)) {
                $parent = $content->$relation;
                if ($parent) {
                    return $parent;
                }
            }
        }

        return null;
    }
}
