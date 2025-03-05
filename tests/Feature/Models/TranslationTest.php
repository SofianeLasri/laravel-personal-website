<?php

namespace Tests\Feature\Models;

use App\Models\Translation;
use App\Models\TranslationKey;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Translation::class)]
class TranslationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_find_by_key_and_locale()
    {
        $translationKey = TranslationKey::factory()->create(['key' => 'test.key']);
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $foundTranslation = Translation::findByKeyAndLocale('test.key', 'en');

        $this->assertEquals($translation->id, $foundTranslation->id);
    }

    public function test_trans_returns_text_for_key_and_locale()
    {
        $translationKey = TranslationKey::factory()->create(['key' => 'test.key']);
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $foundText = Translation::trans($translationKey->key, 'en');

        $this->assertEquals($translation->text, $foundText);
    }

    public function test_trans_returns_key_for_missing_translation()
    {
        $foundText = Translation::trans('missing.key', 'en');

        $this->assertEquals('missing.key', $foundText);
    }

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

    public function test_create_or_update_updates_existing_translation()
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
}
