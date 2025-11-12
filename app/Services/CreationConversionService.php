<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreationType;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\CreationDraft;
use App\Models\Feature;
use App\Models\Screenshot;
use App\Models\TranslationKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreationConversionService
{
    public function __construct(
        private readonly ContentDuplicationService $contentDuplicationService
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
            $this->syncContents($draft, $creation);
        } else {
            $creation = Creation::create($this->mapDraftAttributes($draft));
            $this->createFeatures($draft, $creation);
            $this->createScreenshots($draft, $creation);
            $this->syncContents($draft, $creation);
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
        $this->syncContents($draft, $creation);

        return $creation->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function validateDraft(CreationDraft $draft): void
    {
        $errors = [];

        if (! $draft->short_description_translation_key_id) {
            $errors['short_description'] = 'La description courte est requise.';
        }

        if (! $draft->logo_id) {
            $errors['logo'] = 'Le logo est requis.';
        }

        if (! $draft->cover_image_id) {
            $errors['cover_image'] = 'L\'image de couverture est requise.';
        }

        // Validate that draft has at least one content block
        if (! $draft->contents()->exists()) {
            $errors['contents'] = 'Au moins un bloc de contenu est requis.';
        }

        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $field => $error) {
                $validator->errors()->add($field, $error);
            }
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
     * }
     */
    private function mapDraftAttributes(CreationDraft $draft): array
    {
        return $draft->only([
            'name', 'slug', 'logo_id', 'cover_image_id', 'type',
            'started_at', 'ended_at', 'external_url', 'source_code_url', 'featured',
            'short_description_translation_key_id',
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

    /**
     * Sync all creation contents from draft to published by duplicating content
     */
    private function syncContents(CreationDraft $draft, Creation $creation): void
    {
        // Delete existing contents (this will not affect draft contents since we duplicate)
        $existingContents = $creation->contents;

        // Clean up old content records that are no longer needed
        foreach ($existingContents as $existingContent) {
            $this->deleteContentRecord($existingContent);
        }

        $creation->contents()->delete();

        // Duplicate contents from draft to create independent published content
        $duplicatedContents = $this->contentDuplicationService->duplicateAllContents($draft->contents);

        foreach ($duplicatedContents as $contentData) {
            CreationContent::create([
                'creation_id' => $creation->id,
                'content_type' => $contentData['content_type'],
                'content_id' => $contentData['content_id'],
                'order' => $contentData['order'],
            ]);
        }
    }

    /**
     * Delete the actual content record (markdown, gallery, video) when cleaning up
     */
    private function deleteContentRecord(CreationContent $creationContent): void
    {
        $content = $creationContent->content;

        if ($content) {
            // Handle specific cleanup based on content type
            if ($content instanceof ContentMarkdown) {
                // Delete translation key and its translations
                $translationKey = $content->translationKey;
                if ($translationKey instanceof TranslationKey) {
                    $translationKey->translations()->delete();
                    $translationKey->delete();
                }
            } elseif ($content instanceof ContentGallery) {
                // Detach pictures and delete caption translation keys
                foreach ($content->pictures as $picture) {
                    if ($picture->pivot->caption_translation_key_id) {
                        $captionTranslationKey = TranslationKey::find($picture->pivot->caption_translation_key_id);
                        if ($captionTranslationKey instanceof TranslationKey) {
                            $captionTranslationKey->translations()->delete();
                            $captionTranslationKey->delete();
                        }
                    }
                }
                $content->pictures()->detach();
            } elseif ($content instanceof ContentVideo) {
                // Delete caption translation key if it exists
                if ($content->caption_translation_key_id) {
                    $content->captionTranslationKey?->translations()?->delete();
                    $content->captionTranslationKey?->delete();
                }
            }

            // Delete the content record itself
            $content->delete();
        }
    }
}
