<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Models\TranslationKey;

/**
 * Service for generating unique translation keys
 */
class TranslationKeyGeneratorService
{
    /**
     * Generate a unique translation key based on the original key
     *
     * @param  string  $originalKey  The original translation key
     * @param  string  $suffix  The suffix to append (e.g., 'draft', 'copy')
     */
    public function generate(string $originalKey, string $suffix = 'copy'): string
    {
        $baseKey = $originalKey.'_'.$suffix;
        $key = $baseKey;
        $counter = 1;

        while (TranslationKey::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$counter;
            $counter++;
        }

        return $key;
    }

    /**
     * Generate a unique translation key for draft operations
     */
    public function generateForDraft(string $originalKey): string
    {
        return $this->generate($originalKey, 'draft');
    }

    /**
     * Generate a unique translation key for copy operations
     */
    public function generateForCopy(string $originalKey): string
    {
        return $this->generate($originalKey, 'copy');
    }
}
