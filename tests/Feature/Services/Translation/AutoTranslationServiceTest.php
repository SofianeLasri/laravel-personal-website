<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Translation;

use App\Jobs\TranslateToEnglishJob;
use App\Models\TranslationKey;
use App\Services\Translation\AutoTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AutoTranslationService::class)]
class AutoTranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AutoTranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutoTranslationService::class);
        Queue::fake();
    }

    #[Test]
    public function it_dispatches_translation_job_when_target_missing(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Texte source']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class, function ($job) use ($translationKey) {
            return $job->translationKeyId === $translationKey->id;
        });
    }

    #[Test]
    public function it_dispatches_translation_job_when_target_empty(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Texte source']);
        $translationKey->translations()->create(['locale' => 'en', 'text' => '']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_dispatches_translation_job_when_target_whitespace_only(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Texte source']);
        $translationKey->translations()->create(['locale' => 'en', 'text' => '   ']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_skips_when_source_missing(): void
    {
        $translationKey = TranslationKey::factory()->create();
        // No French translation

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertFalse($result);
        Queue::assertNotPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_skips_when_source_empty(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => '']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertFalse($result);
        Queue::assertNotPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_skips_when_source_whitespace_only(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => '   ']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertFalse($result);
        Queue::assertNotPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_skips_when_target_already_exists(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Texte source']);
        $translationKey->translations()->create(['locale' => 'en', 'text' => 'Existing translation']);

        $result = $this->service->translateIfMissing($translationKey);

        $this->assertFalse($result);
        Queue::assertNotPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_uses_custom_source_locale(): void
    {
        // Test with reversed locales: en -> fr (default is fr -> en)
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'en', 'text' => 'English text']);

        $result = $this->service->translateIfMissing($translationKey, 'en', 'fr');

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_uses_custom_target_locale(): void
    {
        // Test with reversed target: fr -> en is the default, but we can specify it explicitly
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'en', 'text' => 'English text']);
        // No French translation exists

        $result = $this->service->translateIfMissing($translationKey, 'en', 'fr');

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_translates_french_to_english_if_missing(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Bonjour le monde']);

        $result = $this->service->translateFrenchToEnglishIfMissing($translationKey);

        $this->assertTrue($result);
        Queue::assertPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function translate_french_to_english_skips_when_english_exists(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $translationKey->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $translationKey->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $result = $this->service->translateFrenchToEnglishIfMissing($translationKey);

        $this->assertFalse($result);
        Queue::assertNotPushed(TranslateToEnglishJob::class);
    }

    #[Test]
    public function it_returns_correct_boolean_values(): void
    {
        $keyWithTranslation = TranslationKey::factory()->create();
        $keyWithTranslation->translations()->create(['locale' => 'fr', 'text' => 'Source']);

        $keyWithBothTranslations = TranslationKey::factory()->create();
        $keyWithBothTranslations->translations()->create(['locale' => 'fr', 'text' => 'Source']);
        $keyWithBothTranslations->translations()->create(['locale' => 'en', 'text' => 'Target']);

        // Should return true (job dispatched)
        $this->assertTrue($this->service->translateIfMissing($keyWithTranslation));

        // Should return false (translation exists)
        $this->assertFalse($this->service->translateIfMissing($keyWithBothTranslations));
    }
}
