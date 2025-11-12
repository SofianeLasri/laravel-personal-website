<?php

namespace App\Services;

use App\Enums\CreationType;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Feature;
use App\Models\Screenshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreationConversionService
{
    public function __construct(
        protected BlogContentDuplicationService $contentDuplicationService
    ) {}

    /**
     * @throws ValidationException
     */
    public function convertDraftToCreation(CreationDraft $draft): Creation
    {
        $this->validateDraft($draft);

        if ($draft->originalCreation) {
            $creation = $draft->originalCreation;
            $creation->update($this->mapDraftAttributes($draft));

            $this->recreateFeatures($draft, $creation);
            $this->recreateScreenshots($draft, $creation);
            $this->recreateContents($draft, $creation);
        } else {
            $creation = Creation::create($this->mapDraftAttributes($draft));
            $this->createFeatures($draft, $creation);
            $this->createScreenshots($draft, $creation);
            $this->createContents($draft, $creation);
        }
        $this->syncRelationships($draft, $creation);

        return $creation;
    }

    /**
     * @throws ValidationException
     */
    public function updateCreationFromDraft(CreationDraft $draft, Creation $creation): Creation
    {
        $this->validateDraft($draft);

        $creation->update($this->mapDraftAttributes($draft));

        $this->syncRelationships($draft, $creation);
        $this->recreateFeatures($draft, $creation);
        $this->recreateScreenshots($draft, $creation);
        $this->recreateContents($draft, $creation);

        return $creation->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function validateDraft(CreationDraft $draft): void
    {
        if (! $draft->short_description_translation_key_id || ! $draft->full_description_translation_key_id || ! $draft->logo_id || ! $draft->cover_image_id) {
            $validator = Validator::make([], [
                'short_description_translation_key_id' => ['required'],
                'full_description_translation_key_id' => ['required'],
                'logo_id' => ['required'],
                'cover_image_id' => ['required'],
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * @param  CreationDraft  $draft  The draft to map
     * @return array{
     *     name: string,
     *     slug: string,
     *     logo_id: int|null,
     *     cover_image_id: int|null,
     *     type: CreationType, started_at: Carbon,
     *     ended_at: Carbon|null,
     *     external_url: string|null,
     *     source_code_url: string|null,
     *     featured: bool,
     *     short_description_translation_key_id: int|null,
     *     full_description_translation_key_id: int|null,
     * }
     */
    private function mapDraftAttributes(CreationDraft $draft): array
    {
        return $draft->only([
            'name', 'slug', 'logo_id', 'cover_image_id', 'type',
            'started_at', 'ended_at', 'external_url', 'source_code_url', 'featured',
            'short_description_translation_key_id', 'full_description_translation_key_id',
        ]);
    }

    public function syncRelationships(CreationDraft $draft, Creation $creation): void
    {
        $creation->technologies()->sync($draft->technologies);
        $creation->people()->sync($draft->people);
        $creation->tags()->sync($draft->tags);
        $creation->videos()->sync($draft->videos);
    }

    private function createFeatures(CreationDraft $draft, Creation $creation): void
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

    private function recreateFeatures(CreationDraft $draft, Creation $creation): void
    {
        $creation->features()->delete();
        $this->createFeatures($draft, $creation);
    }

    private function createScreenshots(CreationDraft $draft, Creation $creation): void
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

    private function recreateScreenshots(CreationDraft $draft, Creation $creation): void
    {
        $creation->screenshots()->delete();
        $this->createScreenshots($draft, $creation);
    }

    private function createContents(CreationDraft $draft, Creation $creation): void
    {
        foreach ($draft->contents()->with('content')->orderBy('order')->get() as $draftContent) {
            // Duplicate the content (markdown/gallery/video)
            $newContent = $this->duplicateContent($draftContent->content);

            // Create the creation content pivot
            $creation->contents()->create([
                'content_type' => $draftContent->content_type,
                'content_id' => $newContent->id,
                'order' => $draftContent->order,
            ]);
        }
    }

    private function recreateContents(CreationDraft $draft, Creation $creation): void
    {
        // Delete old content blocks and their associated content entities
        foreach ($creation->contents as $content) {
            if ($content->content) {
                // Delete the actual content (markdown/gallery/video)
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
            ContentMarkdown::class => $this->contentDuplicationService->duplicateMarkdownContent($content),
            ContentGallery::class => $this->contentDuplicationService->duplicateGalleryContent($content),
            ContentVideo::class => $this->contentDuplicationService->duplicateVideoContent($content),
            default => throw new \RuntimeException('Unknown content type: '.get_class($content)),
        };
    }
}
