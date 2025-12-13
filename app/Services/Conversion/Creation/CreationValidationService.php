<?php

declare(strict_types=1);

namespace App\Services\Conversion\Creation;

use App\Models\CreationDraft;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Service for validating creation drafts before publication
 */
class CreationValidationService
{
    /**
     * Validate that the draft is ready for publication
     *
     * @throws ValidationException
     */
    public function validate(CreationDraft $draft): void
    {
        if (! $draft->short_description_translation_key_id
            || ! $draft->full_description_translation_key_id
            || ! $draft->logo_id
            || ! $draft->cover_image_id) {
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
     * Check if a draft has all required fields
     */
    public function isValid(CreationDraft $draft): bool
    {
        return $draft->short_description_translation_key_id
            && $draft->full_description_translation_key_id
            && $draft->logo_id
            && $draft->cover_image_id;
    }
}
