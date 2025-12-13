<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;

/**
 * Service for updating content blocks
 */
class ContentUpdateService
{
    /**
     * Update markdown content
     */
    public function updateMarkdown(ContentMarkdown $markdown, int $translationKeyId): ContentMarkdown
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
    public function updateGallery(ContentGallery $gallery, array $updateData): ContentGallery
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
    public function updateVideo(
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
}
