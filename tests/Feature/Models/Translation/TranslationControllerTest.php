<?php

namespace Tests\Feature\Models\Translation;

use App\Http\Controllers\TranslationController;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TranslationController::class)]
class TranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_with_key()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.translation.store'), [
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'key' => 'test.key',
                'locale' => 'en',
                'text' => 'Hello, world!',
            ],
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'test.key',
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => TranslationKey::where('key', 'test.key')->first()->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    public function test_create_with_translation_key_id()
    {
        $user = User::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.translation.store'), [
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'key' => $translationKey->key,
                'locale' => 'en',
                'text' => 'Hello, world!',
            ],
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);
    }

    public function test_it_returns_error_if_key_and_translation_key_id_are_missing()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.translation.store'), [
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['key', 'translation_key_id']);
    }

    public function test_it_cannot_create_with_invalid_locale()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.translation.store'), [
            'key' => 'test.key',
            'locale' => 'invalid',
            'text' => 'Hello, world!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['locale']);
    }

    public function test_it_cannot_create_as_guest()
    {
        $response = $this->postJson(route('api.translation.store'), [
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response->assertForbidden();
    }

    public function test_it_can_update_with_translation_key_locale_and_text()
    {
        $user = User::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response = $this->actingAs($user)->putJson(route('api.translation.update'), [
            'key' => $translationKey->key,
            'locale' => 'en',
            'text' => 'Hello, world! (updated)',
        ]);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'key' => $translationKey->key,
                'locale' => 'en',
                'text' => 'Hello, world! (updated)',
            ],
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world! (updated)',
        ]);
    }

    public function test_it_can_update_with_translation_key_id_locale_and_text()
    {
        $user = User::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $translation = Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response = $this->actingAs($user)->putJson(route('api.translation.update'), [
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world! (updated)',
        ]);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'key' => $translationKey->key,
                'locale' => 'en',
                'text' => 'Hello, world! (updated)',
            ],
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world! (updated)',
        ]);
    }

    public function test_it_cannot_update_with_invalid_locale()
    {
        $user = User::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response = $this->actingAs($user)->putJson(route('api.translation.update'), [
            'key' => $translationKey->key,
            'locale' => 'invalid',
            'text' => 'Hello, world! (updated)',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['locale']);
    }

    public function test_it_can_create_in_fr_locale_and_cannot_update_in_english_locale()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.translation.store'), [
            'key' => 'test.key',
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'key' => 'test.key',
                'locale' => 'fr',
                'text' => 'Bonjour, le monde!',
            ],
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Bonjour, le monde!',
        ]);

        $response = $this->actingAs($user)->putJson('/api/translations', [
            'key' => 'test.key',
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response->assertStatus(404);
    }

    public function test_it_cannot_update_as_guest()
    {
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response = $this->putJson(route('api.translation.update'), [
            'key' => $translationKey->key,
            'locale' => 'en',
            'text' => 'Hello, world! (updated)',
        ]);

        $response->assertForbidden();
    }

    public function test_it_can_view_all_as_guest()
    {
        Translation::factory()->count(3)->create();

        $response = $this->getJson(route('api.translation.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_it_can_show_one_with_key_as_guest()
    {
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'Hello, world!',
        ]);

        $response = $this->getJson(route('api.translation.show', ['key' => $translationKey->key, 'locale' => 'en']));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'key' => $translationKey->key,
                'locale' => 'en',
                'text' => 'Hello, world!',
            ],
        ]);
    }

    public function test_show_bad_translation_returns_404()
    {
        $response = $this->getJson(route('api.translation.show', ['key' => 'bad.key', 'locale' => 'en']));

        $response->assertStatus(404);
    }
}
