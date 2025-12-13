<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for creating content blocks
 */
class ContentCreationService
{
    /**
     * Create markdown content for a parent entity
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function createMarkdown(Model $parent, int $translationKeyId, int $order): Model
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
    public function createGallery(Model $parent, array $galleryData, int $order): Model
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
    public function createVideo(
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
}
