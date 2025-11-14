<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Generic content management service for polymorphic content blocks
 * Supports BlogPost, BlogPostDraft, Creation, CreationDraft
 */
class ContentManagementService
{
    /**
     * Create markdown content for a parent entity
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function createMarkdownContent(Model $parent, int $translationKeyId, int $order): Model
    {
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
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(Model $parent, array $galleryData, int $order): Model
    {
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
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function createVideoContent(
        Model $parent,
        int $videoId,
        int $order,
        ?int $captionTranslationKeyId = null
    ): Model {
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
     */
    public function updateMarkdownContent(ContentMarkdown $markdown, int $translationKeyId): ContentMarkdown
    {
        $markdown->update([
            'translation_key_id' => $translationKeyId,
        ]);

        $markdown->refresh();

        return $markdown;
    }

    /**
     * Update gallery content
     *
     * @param  array<string, mixed>  $updateData
     */
    public function updateGalleryContent(ContentGallery $gallery, array $updateData): ContentGallery
    {
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
     */
    public function updateVideoContent(
        ContentVideo $videoContent,
        int $videoId,
        ?int $captionTranslationKeyId = null
    ): ContentVideo {
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
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorderContent(Model $parent, array $newOrder): void
    {
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
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     */
    public function deleteContent(Model $content): bool
    {
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
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     * @return Model The duplicated content block
     */
    public function duplicateContent(Model $content): Model
    {
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
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function validateContentStructure(Model $parent): bool
    {
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
