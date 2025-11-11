<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BlogContentService
{
    /**
     * Create markdown content for a draft
     */
    public function createMarkdownContent(BlogPostDraft $draft, int $translationKeyId, int $order): BlogPostDraftContent
    {
        $markdown = BlogContentMarkdown::create([
            'translation_key_id' => $translationKeyId,
        ]);

        return $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => $order,
        ]);
    }

    /**
     * Create gallery content for a draft
     *
     * @param  array<string, mixed>  $galleryData
     */
    public function createGalleryContent(BlogPostDraft $draft, array $galleryData, int $order): BlogPostDraftContent
    {
        $gallery = BlogContentGallery::create([
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

        return $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => $order,
        ]);
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
        $videoContent = BlogContentVideo::create([
            'video_id' => $videoId,
            'caption_translation_key_id' => $captionTranslationKeyId,
        ]);

        return $draft->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => $order,
        ]);
    }

    /**
     * Update markdown content
     */
    public function updateMarkdownContent(BlogContentMarkdown $markdown, int $translationKeyId): BlogContentMarkdown
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
    public function updateGalleryContent(BlogContentGallery $gallery, array $updateData): BlogContentGallery
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
        BlogContentVideo $videoContent,
        int $videoId,
        ?int $captionTranslationKeyId = null
    ): BlogContentVideo {
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
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorderContent(BlogPostDraft|BlogPost $parent, array $newOrder): void
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
     */
    public function deleteContent(BlogPostDraftContent|BlogPostContent $content): bool
    {
        return DB::transaction(function () use ($content): bool {
            // Delete the actual content
            if ($content->content) {
                if ($content->content instanceof BlogContentGallery) {
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
     */
    public function duplicateContent(BlogPostDraftContent|BlogPostContent $content): BlogPostDraftContent|BlogPostContent
    {
        return DB::transaction(function () use ($content) {
            $newContent = null;

            // Duplicate the actual content
            switch ($content->content_type) {
                case BlogContentMarkdown::class:
                    $original = $content->content;
                    if ($original instanceof BlogContentMarkdown) {
                        $newContent = BlogContentMarkdown::create([
                            'translation_key_id' => $original->translation_key_id,
                        ]);
                    }
                    break;

                case BlogContentGallery::class:
                    $original = $content->content;
                    if ($original instanceof BlogContentGallery) {
                        $newContent = BlogContentGallery::create([
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

                case BlogContentVideo::class:
                    $original = $content->content;
                    if ($original instanceof BlogContentVideo) {
                        $newContent = BlogContentVideo::create([
                            'video_id' => $original->video_id,
                            'caption_translation_key_id' => $original->caption_translation_key_id,
                        ]);
                    }
                    break;
            }

            // Get the parent (draft or post)
            $parent = $content instanceof BlogPostDraftContent
                ? $content->blogPostDraft
                : $content->blogPost;

            if (! $parent) {
                throw new RuntimeException('Parent blog post or draft not found');
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
     */
    public function validateContentStructure(BlogPostDraft|BlogPost $parent): bool
    {
        // A valid post must have at least one content block
        return $parent->contents()->exists();
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
