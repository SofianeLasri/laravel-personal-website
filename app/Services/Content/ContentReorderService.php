<?php

declare(strict_types=1);

namespace App\Services\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service for reordering content blocks
 */
class ContentReorderService
{
    /**
     * Reorder content blocks
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  array<int>  $newOrder  Array of content IDs in new order
     *
     * @throws Throwable
     */
    public function reorder(Model $parent, array $newOrder): void
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
     * Move a content block to a specific position
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  int  $contentId  The content ID to move
     * @param  int  $newPosition  The new position (1-based)
     *
     * @throws Throwable
     */
    public function moveTo(Model $parent, int $contentId, int $newPosition): void
    {
        DB::transaction(function () use ($parent, $contentId, $newPosition) {
            $contents = $parent->contents()->orderBy('order')->get();
            $currentIndex = $contents->search(fn ($c) => $c->id === $contentId);

            if ($currentIndex === false) {
                return;
            }

            $content = $contents->pull($currentIndex);
            $contents->splice($newPosition - 1, 0, [$content]);

            foreach ($contents as $index => $item) {
                $item->update(['order' => $index + 1]);
            }
        });
    }

    /**
     * Move a content block up by one position
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  int  $contentId  The content ID to move
     *
     * @throws Throwable
     */
    public function moveUp(Model $parent, int $contentId): void
    {
        $content = $parent->contents()->find($contentId);
        if ($content && $content->order > 1) {
            $this->moveTo($parent, $contentId, $content->order - 1);
        }
    }

    /**
     * Move a content block down by one position
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     * @param  int  $contentId  The content ID to move
     *
     * @throws Throwable
     */
    public function moveDown(Model $parent, int $contentId): void
    {
        $content = $parent->contents()->find($contentId);
        $maxOrder = $parent->contents()->max('order');
        if ($content && $content->order < $maxOrder) {
            $this->moveTo($parent, $contentId, $content->order + 1);
        }
    }
}
