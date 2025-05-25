<?php

namespace App\Jobs;

use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\AiProviderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TranslateToEnglishJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $translationKeyId
    ) {}

    public function handle(AiProviderService $aiService): void
    {
        $translationKey = TranslationKey::find($this->translationKeyId);

        if (! $translationKey) {
            Log::warning('Translation key not found', ['id' => $this->translationKeyId]);

            return;
        }

        // Check if English translation already exists
        $existingEnglishTranslation = $translationKey->translations()
            ->where('locale', 'en')
            ->first();

        if ($existingEnglishTranslation) {
            Log::info('English translation already exists', [
                'key' => $translationKey->key,
                'id' => $this->translationKeyId,
            ]);

            return;
        }

        // Get French translation
        $frenchTranslation = $translationKey->translations()
            ->where('locale', 'fr')
            ->first();

        if (! $frenchTranslation) {
            Log::warning('No French translation found to translate from', [
                'key' => $translationKey->key,
                'id' => $this->translationKeyId,
            ]);

            return;
        }

        try {
            $systemPrompt = 'You are a helpful assistant that translates french markdown text in english and that outputs JSON in the format {message:string}. Markdown is supported.';
            $userPrompt = "Translate this French text to English: {$frenchTranslation->text}";

            $response = $aiService->prompt($systemPrompt, $userPrompt);

            if (! isset($response['message'])) {
                throw new RuntimeException('Invalid response format from AI service');
            }

            // Create English translation
            Translation::create([
                'translation_key_id' => $translationKey->id,
                'locale' => 'en',
                'text' => $response['message'],
            ]);

            Log::info('Successfully translated to English', [
                'key' => $translationKey->key,
                'french_text' => $frenchTranslation->text,
                'english_text' => $response['message'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to translate to English', [
                'key' => $translationKey->key,
                'french_text' => $frenchTranslation->text,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
