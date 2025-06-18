<?php

namespace Tests\Feature\Models\Translation;

use App\Models\Translation;
use App\Models\TranslationKey;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Translation::class)]
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_it_can_create_with_translation_key_locale_and_text()
    {
        $translationKey = TranslationKey::factory()->create();
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    #[Test]
    public function test_it_can_create_with_key_locale_and_text()
    {
        $translation = Translation::factory()->create([
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'test.key',
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => TranslationKey::where('key', 'test.key')->first()->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    #[Test]
    public function test_it_cannot_create_two_translations_with_same_key_and_locale()
    {
        $translation = Translation::factory()->create([
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $this->expectException(Exception::class);

        Translation::factory()->create([
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    #[Test]
    public function test_it_can_update_with_translation_key_locale_and_text()
    {
        $translationKey = TranslationKey::factory()->create();
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $translation->update([
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);
    }

    #[Test]
    public function test_it_can_update_with_key_locale_and_text()
    {
        $translation = Translation::factory()->create([
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $translation->update([
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'test.key',
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => TranslationKey::where('key', 'test.key')->first()->id,
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);
    }

    #[Test]
    public function test_create_or_update()
    {
        Translation::createOrUpdate('test.key', 'en', 'Hello, world!');

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'test.key',
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => TranslationKey::where('key', 'test.key')->first()->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    #[Test]
    public function test_create_or_update_updates_existing_translation_with_key_string()
    {
        $translation = Translation::createOrUpdate('test.key', 'en', 'Hello, world!');

        $translation->update([
            'text' => 'Bonjour, le monde!',
        ]);

        $updatedTranslation = Translation::createOrUpdate('test.key', 'en', 'Hello, world!');

        $this->assertEquals($translation->id, $updatedTranslation->id);
        $this->assertEquals('Hello, world!', $updatedTranslation->text);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'text' => 'Hello, world!',
        ]);

        $this->assertDatabaseMissing('translations', [
            'id' => $translation->id,
            'text' => 'Bonjour, le monde!',
        ]);
    }

    #[Test]
    public function test_update_existing_translation_with_translation_key_instance()
    {
        $translationKey = TranslationKey::factory()->create(['key' => 'test.key']);
        $translation = Translation::createOrUpdate($translationKey, 'en', 'Hello, world!');

        $translation->update([
            'text' => 'Bonjour, le monde!',
        ]);

        $updatedTranslation = Translation::createOrUpdate($translationKey, 'en', 'Hello, world!');

        $this->assertEquals($translation->id, $updatedTranslation->id);
        $this->assertEquals('Hello, world!', $updatedTranslation->text);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'text' => 'Hello, world!',
        ]);

        $this->assertDatabaseMissing('translations', [
            'id' => $translation->id,
            'text' => 'Bonjour, le monde!',
        ]);
    }

    #[Test]
    public function test_it_can_have_translation_key()
    {
        $translationKey = TranslationKey::factory()->create();
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $this->assertEquals($translationKey->id, $translation->translationKey->id);
    }
}
