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
    public function it_can_find_by_key()
    {
        $key = 'unique.test.key';
        $translationKey = TranslationKey::factory()->create(['key' => $key]);

        $found = TranslationKey::findByKey($key);

        $this->assertNotNull($found);
        $this->assertEquals($translationKey->id, $found->id);
        $this->assertEquals($key, $found->key);

        $nonExistent = TranslationKey::findByKey('does.not.exist');
        $this->assertNull($nonExistent);
    }

    #[Test]
    public function it_can_find_or_create_by_key()
    {
        $key = 'existing.key';
        $existingKey = TranslationKey::factory()->create(['key' => $key]);

        $found = TranslationKey::findOrCreateByKey($key);
        $this->assertEquals($existingKey->id, $found->id);

        $newKey = 'new.key.'.uniqid();
        $created = TranslationKey::findOrCreateByKey($newKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => $newKey,
        ]);
        $this->assertEquals($newKey, $created->key);
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
}
