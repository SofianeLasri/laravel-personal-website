<?php

declare(strict_types=1);

namespace App\Services\Conversion\Creation;

use App\Enums\CreationType;
use App\Models\Creation;
use App\Models\CreationDraft;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Service for converting creation drafts to published creations
 */
class DraftToCreationConverter
{
    public function __construct(
        private readonly CreationValidationService $validation,
        private readonly CreationContentSyncService $contentSync
    ) {}

    /**
     * Convert a draft to a published creation
     *
     * @throws ValidationException
     */
    public function convert(CreationDraft $draft): Creation
    {
        $this->validation->validate($draft);

        if ($draft->originalCreation) {
            $creation = $draft->originalCreation;
            $creation->update($this->mapAttributes($draft));

            $this->contentSync->recreateFeatures($draft, $creation);
            $this->contentSync->recreateScreenshots($draft, $creation);
            $this->contentSync->recreateContents($draft, $creation);
        } else {
            $creation = Creation::create($this->mapAttributes($draft));
            $this->contentSync->createFeatures($draft, $creation);
            $this->contentSync->createScreenshots($draft, $creation);
            $this->contentSync->createContents($draft, $creation);
        }

        $this->contentSync->syncRelationships($draft, $creation);

        return $creation->refresh();
    }

    /**
     * Update an existing creation from a draft
     *
     * @throws ValidationException
     */
    public function update(CreationDraft $draft, Creation $creation): Creation
    {
        $this->validation->validate($draft);

        $creation->update($this->mapAttributes($draft));

        $this->contentSync->syncRelationships($draft, $creation);
        $this->contentSync->recreateFeatures($draft, $creation);
        $this->contentSync->recreateScreenshots($draft, $creation);
        $this->contentSync->recreateContents($draft, $creation);

        return $creation->refresh();
    }

    /**
     * Map draft attributes to creation attributes
     *
     * @return array{
     *     name: string,
     *     slug: string,
     *     logo_id: int|null,
     *     cover_image_id: int|null,
     *     type: CreationType,
     *     started_at: Carbon,
     *     ended_at: Carbon|null,
     *     external_url: string|null,
     *     source_code_url: string|null,
     *     featured: bool,
     *     short_description_translation_key_id: int|null,
     *     full_description_translation_key_id: int|null,
     * }
     */
    private function mapAttributes(CreationDraft $draft): array
    {
        return $draft->only([
            'name', 'slug', 'logo_id', 'cover_image_id', 'type',
            'started_at', 'ended_at', 'external_url', 'source_code_url', 'featured',
            'short_description_translation_key_id', 'full_description_translation_key_id',
        ]);
    }
}
