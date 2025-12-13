<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Jobs\TranslateToEnglishJob;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling automatic translations
 */
class AutoTranslationService
{
    /**
     * Auto-translate to English if the translation is missing or empty
     *
     * @param  TranslationKey  $translationKey  The translation key to check
     * @param  string  $sourceLocale  The source locale to translate from
     * @param  string  $targetLocale  The target locale to translate to
     * @return bool Whether a translation job was dispatched
     */
    public function translateIfMissing(
        TranslationKey $translationKey,
        string $sourceLocale = 'fr',
        string $targetLocale = 'en'
    ): bool {
        $sourceTranslation = $translationKey->translations()
            ->where('locale', $sourceLocale)
            ->first();

        if (! $sourceTranslation || empty(trim($sourceTranslation->text))) {
            Log::info('No source translation found or empty, skipping auto-translation', [
                'translation_key_id' => $translationKey->id,
                'source_locale' => $sourceLocale,
            ]);

            return false;
        }

        $targetTranslation = $translationKey->translations()
            ->where('locale', $targetLocale)
            ->first();

        if (! $targetTranslation || empty(trim($targetTranslation->text))) {
            TranslateToEnglishJob::dispatch($translationKey->id);

            Log::info('Auto-translation job dispatched', [
                'translation_key_id' => $translationKey->id,
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
                'source_text' => $sourceTranslation->text,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Auto-translate French to English if missing
     */
    public function translateFrenchToEnglishIfMissing(TranslationKey $translationKey): bool
    {
        return $this->translateIfMissing($translationKey, 'fr', 'en');
    }
}
