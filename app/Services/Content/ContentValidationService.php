<?php

declare(strict_types=1);

namespace App\Services\Content;

use Illuminate\Database\Eloquent\Model;

/**
 * Service for validating content blocks and resolving parent entities
 */
class ContentValidationService
{
    /**
     * Validate content structure
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function validate(Model $parent): bool
    {
        // A valid entity must have at least one content block
        return $parent->contents()->exists();
    }

    /**
     * Check if parent has any content blocks
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function hasContent(Model $parent): bool
    {
        return $parent->contents()->exists();
    }

    /**
     * Get content count for a parent entity
     *
     * @param  Model  $parent  BlogPostDraft|BlogPost|CreationDraft|Creation
     */
    public function getContentCount(Model $parent): int
    {
        return $parent->contents()->count();
    }

    /**
     * Get the parent entity from a content block
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     * @return Model|null The parent entity (BlogPostDraft|BlogPost|CreationDraft|Creation)
     */
    public function resolveParent(Model $content): ?Model
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

    /**
     * Check if content belongs to a draft entity
     *
     * @param  Model  $content  BlogPostDraftContent|BlogPostContent|CreationDraftContent|CreationContent
     */
    public function isDraft(Model $content): bool
    {
        $parent = $this->resolveParent($content);
        if (! $parent) {
            return false;
        }

        $className = get_class($parent);

        return str_contains($className, 'Draft');
    }
}
