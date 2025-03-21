<?php

namespace App\Services;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Feature;
use App\Models\Screenshot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreationConversionService
{
    /**
     * @throws ValidationException
     */
    public function convertDraftToCreation(CreationDraft $draft): Creation
    {
        $this->validateDraft($draft);

        $creation = Creation::create($this->mapDraftAttributes($draft));

        $this->syncRelationships($draft, $creation);
        $this->createFeatures($draft, $creation);
        $this->createScreenshots($draft, $creation);

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

        return $creation->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function validateDraft(CreationDraft $draft): void
    {
        if (! $draft->short_description_translation_key_id || ! $draft->full_description_translation_key_id) {
            $validator = Validator::make([], [
                'short_description_translation_key_id' => ['required'],
                'full_description_translation_key_id' => ['required'],
            ]);
            throw new ValidationException($validator);
        }
    }

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
            ]);
        }
    }

    private function recreateScreenshots(CreationDraft $draft, Creation $creation): void
    {
        $creation->screenshots()->delete();
        $this->createScreenshots($draft, $creation);
    }
}
