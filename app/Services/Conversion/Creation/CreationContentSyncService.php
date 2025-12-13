<?php

declare(strict_types=1);

namespace App\Services\Conversion\Creation;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Feature;
use App\Models\Screenshot;
use App\Services\Content\ContentBlockDuplicationService;
use RuntimeException;

/**
 * Service for syncing content between creation drafts and published creations
 */
class CreationContentSyncService
{
    public function __construct(
        private readonly ContentBlockDuplicationService $contentDuplication
    ) {}

    /**
     * Sync all relationships from draft to creation
     */
    public function syncRelationships(CreationDraft $draft, Creation $creation): void
    {
        $creation->technologies()->sync($draft->technologies);
        $creation->people()->sync($draft->people);
        $creation->tags()->sync($draft->tags);
        $creation->videos()->sync($draft->videos);
    }

    /**
     * Create features from draft
     */
    public function createFeatures(CreationDraft $draft, Creation $creation): void
    {
        foreach ($draft->features as $draftFeature) {
            Feature::create([
                'creation_id' => $creation->id,
                'title_translation_key_id' => $draftFeature->title_translation_key_id,
                'description_translation_key_id' => $draftFeature->description_translation_key_id,
                'picture_id' => $draftFeature->picture_id,
            ]);
        }
    }

    /**
     * Recreate features from draft (delete existing and create new)
     */
    public function recreateFeatures(CreationDraft $draft, Creation $creation): void
    {
        $creation->features()->delete();
        $this->createFeatures($draft, $creation);
    }

    /**
     * Create screenshots from draft
     */
    public function createScreenshots(CreationDraft $draft, Creation $creation): void
    {
        foreach ($draft->screenshots as $draftScreenshot) {
            Screenshot::create([
                'creation_id' => $creation->id,
                'picture_id' => $draftScreenshot->picture_id,
                'caption_translation_key_id' => $draftScreenshot->caption_translation_key_id,
                'order' => $draftScreenshot->order,
            ]);
        }
    }

    /**
     * Recreate screenshots from draft (delete existing and create new)
     */
    public function recreateScreenshots(CreationDraft $draft, Creation $creation): void
    {
        $creation->screenshots()->delete();
        $this->createScreenshots($draft, $creation);
    }

    /**
     * Create contents from draft
     */
    public function createContents(CreationDraft $draft, Creation $creation): void
    {
        foreach ($draft->contents()->with('content')->orderBy('order')->get() as $draftContent) {
            $newContent = $this->duplicateContent($draftContent->content);

            $creation->contents()->create([
                'content_type' => $draftContent->content_type,
                'content_id' => $newContent->id,
                'order' => $draftContent->order,
            ]);
        }
    }

    /**
     * Recreate contents from draft (delete existing and create new)
     */
    public function recreateContents(CreationDraft $draft, Creation $creation): void
    {
        foreach ($creation->contents as $content) {
            if ($content->content) {
                if ($content->content instanceof ContentGallery) {
                    $content->content->pictures()->detach();
                }
                $content->content->delete();
            }
        }
        $creation->contents()->delete();

        $this->createContents($draft, $creation);
    }

    /**
     * Duplicate a content entity (markdown/gallery/video)
     *
     * @param  ContentMarkdown|ContentGallery|ContentVideo  $content
     * @return ContentMarkdown|ContentGallery|ContentVideo
     */
    private function duplicateContent($content)
    {
        return match (get_class($content)) {
            ContentMarkdown::class => $this->contentDuplication->duplicateMarkdownContent($content),
            ContentGallery::class => $this->contentDuplication->duplicateGalleryContent($content),
            ContentVideo::class => $this->contentDuplication->duplicateVideoContent($content),
            default => throw new RuntimeException('Unknown content type: '.get_class($content)),
        };
    }
}
