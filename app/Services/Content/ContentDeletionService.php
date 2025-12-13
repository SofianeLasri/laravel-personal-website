<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Service for deleting content blocks
 */
class ContentDeletionService
{
    /**
     * Delete a content block
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     */
    public function delete(Model $content): bool
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
     * Delete all content blocks for a parent entity
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function deleteAll(Model $parent): int
    {
        return DB::transaction(function () use ($parent): int {
            $count = 0;
            foreach ($parent->contents as $content) {
                if ($this->delete($content)) {
                    $count++;
                }
            }

            return $count;
        });
    }
}
