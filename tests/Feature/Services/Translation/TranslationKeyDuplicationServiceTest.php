<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Translation;

use App\Models\TranslationKey;
use App\Services\Translation\TranslationKeyDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TranslationKeyDuplicationService::class)]
class TranslationKeyDuplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TranslationKeyDuplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TranslationKeyDuplicationService::class);
    }

    #[Test]
    public function it_duplicates_translation_key_with_all_translations(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'original_key']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => 'Texte français']);
        $originalKey->translations()->create(['locale' => 'en', 'text' => 'English text']);

        $duplicatedKey = $this->service->duplicate($originalKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'original_key_copy',
        ]);

        $this->assertCount(2, $duplicatedKey->translations);

        $frTranslation = $duplicatedKey->translations->firstWhere('locale', 'fr');
        $enTranslation = $duplicatedKey->translations->firstWhere('locale', 'en');

        $this->assertEquals('Texte français', $frTranslation->text);
        $this->assertEquals('English text', $enTranslation->text);
    }

    #[Test]
    public function it_duplicates_for_draft_with_draft_suffix(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'draft_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => 'Draft texte']);

        $duplicatedKey = $this->service->duplicateForDraft($originalKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'draft_test_draft',
        ]);

        $this->assertCount(1, $duplicatedKey->translations);
        $this->assertEquals('Draft texte', $duplicatedKey->translations->first()->text);
    }

    #[Test]
    public function it_duplicates_for_copy_with_copy_suffix(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'copy_test']);
        $originalKey->translations()->create(['locale' => 'en', 'text' => 'Copy text']);

        $duplicatedKey = $this->service->duplicateForCopy($originalKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'copy_test_copy',
        ]);

        $this->assertCount(1, $duplicatedKey->translations);
        $this->assertEquals('Copy text', $duplicatedKey->translations->first()->text);
    }

    #[Test]
    public function it_creates_new_translation_key_instance(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'instance_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => 'Test']);

        $duplicatedKey = $this->service->duplicate($originalKey);

        $this->assertNotEquals($originalKey->id, $duplicatedKey->id);
        $this->assertInstanceOf(TranslationKey::class, $duplicatedKey);
    }

    #[Test]
    public function it_duplicates_key_with_no_translations(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'empty_translations']);

        $duplicatedKey = $this->service->duplicate($originalKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'empty_translations_copy',
        ]);

        $this->assertCount(0, $duplicatedKey->translations);
    }

    #[Test]
    public function it_handles_custom_suffix(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'custom_suffix_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => 'Texte']);

        $duplicatedKey = $this->service->duplicate($originalKey, 'backup');

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'custom_suffix_test_backup',
        ]);
    }

    #[Test]
    public function it_preserves_original_translations(): void
    {
        $originalKey = TranslationKey::factory()->create(['key' => 'preservation_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => 'Original français']);
        $originalKey->translations()->create(['locale' => 'en', 'text' => 'Original english']);

        $this->service->duplicate($originalKey);

        // Reload original to check it wasn't modified
        $originalKey->refresh();

        $this->assertEquals('preservation_test', $originalKey->key);
        $this->assertCount(2, $originalKey->translations);
        $this->assertEquals('Original français', $originalKey->translations->firstWhere('locale', 'fr')->text);
        $this->assertEquals('Original english', $originalKey->translations->firstWhere('locale', 'en')->text);
    }

    #[Test]
    public function it_duplicates_translation_with_long_text(): void
    {
        $longText = str_repeat('Lorem ipsum dolor sit amet. ', 100);
        $originalKey = TranslationKey::factory()->create(['key' => 'long_text_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => $longText]);

        $duplicatedKey = $this->service->duplicate($originalKey);

        $duplicatedTranslation = $duplicatedKey->translations->first();
        $this->assertEquals($longText, $duplicatedTranslation->text);
    }

    #[Test]
    public function it_handles_unicode_in_translations(): void
    {
        $unicodeText = 'Texte avec émojis 🎉 et caractères spéciaux: é, ñ, 中文';
        $originalKey = TranslationKey::factory()->create(['key' => 'unicode_test']);
        $originalKey->translations()->create(['locale' => 'fr', 'text' => $unicodeText]);

        $duplicatedKey = $this->service->duplicate($originalKey);

        $duplicatedTranslation = $duplicatedKey->translations->first();
        $this->assertEquals($unicodeText, $duplicatedTranslation->text);
    }
}
