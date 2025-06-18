<?php

namespace Tests\Feature\Models\Translation;

use App\Models\Translation;
use App\Models\TranslationKey;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TranslationKey::class)]
class TranslationKeyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_translation_key()
    {
        $translationKey = TranslationKey::factory()->create([
            'key' => 'test.key',
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'id' => $translationKey->id,
            'key' => 'test.key',
        ]);
    }

    #[Test]
    public function it_can_have_many_translations()
    {
        $translationKey = TranslationKey::factory()->create(['key' => 'test.translations']);

        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'text' => 'Texte en français',
        ]);

        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Text in English',
        ]);

        $this->assertCount(2, $translationKey->translations);
        $this->assertInstanceOf(Translation::class, $translationKey->translations->first());
    }

    #[Test]
    public function it_enforces_unique_keys()
    {
        $key = 'unique.enforced.key';
        TranslationKey::factory()->create(['key' => $key]);

        try {
            TranslationKey::factory()->create(['key' => $key]);
            $this->fail('Expected exception for duplicate key was not thrown');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Exception for duplicate key correctly thrown');
        }
    }

    #[Test]
    public function it_deletes_related_translations()
    {
        $translationKey = TranslationKey::factory()->create([
            'key' => 'key.to.delete',
        ]);

        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'text' => 'Texte en français',
        ]);

        $this->assertDatabaseCount('translations', 1);

        $translationKey->delete();

        $this->assertDatabaseCount('translations', 0);
        $this->assertDatabaseMissing('translation_keys', [
            'id' => $translationKey->id,
        ]);
        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $translationKey->id,
        ]);
    }
}
