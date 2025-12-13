<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service for duplicating content blocks
 */
readonly class ContentDuplicationService
{
    public function __construct(
        private ContentValidationService $validationService
    ) {}

    /**
     * Duplicate a content block
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     * @return Model The duplicated content block
     */
    public function duplicate(Model $content): Model
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
            $parent = $this->validationService->resolveParent($content);

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
}
