<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Models\TranslationKey;

/**
 * Service for duplicating translation keys with their translations
 */
class TranslationKeyDuplicationService
{
    public function __construct(
        private readonly TranslationKeyGeneratorService $keyGenerator
    ) {}

    /**
     * Duplicate a translation key with all its translations
     *
     * @param  TranslationKey  $originalTranslationKey  The translation key to duplicate
     * @param  string  $suffix  The suffix for the new key (e.g., 'draft', 'copy')
     */
    public function duplicate(TranslationKey $originalTranslationKey, string $suffix = 'copy'): TranslationKey
    {
        $newTranslationKey = TranslationKey::create([
            'key' => $this->keyGenerator->generate($originalTranslationKey->key, $suffix),
        ]);

        foreach ($originalTranslationKey->translations as $translation) {
            $newTranslationKey->translations()->create([
                'locale' => $translation->locale,
                'text' => $translation->text,
            ]);
        }

        return $newTranslationKey;
    }

    /**
     * Duplicate a translation key for draft operations
     */
    public function duplicateForDraft(TranslationKey $originalTranslationKey): TranslationKey
    {
        return $this->duplicate($originalTranslationKey, 'draft');
    }

    /**
     * Duplicate a translation key for copy operations
     */
    public function duplicateForCopy(TranslationKey $originalTranslationKey): TranslationKey
    {
        return $this->duplicate($originalTranslationKey, 'copy');
    }
}
