<?php

namespace Tests\Feature\Pages;

use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

class TranslationPageTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    public function test_translations_index_page_renders_correctly()
    {
        $user = User::factory()->create();

        // Create test translation keys
        $key1 = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $key1->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $key2 = TranslationKey::factory()->create(['key' => 'test.goodbye']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Au revoir']);

        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 2)
                ->has('stats')
                ->where('stats.total_keys', 2)
                ->where('stats.french_translations', 2)
                ->where('stats.english_translations', 1)
                ->where('stats.missing_english', 1)
            );
    }

    public function test_translations_index_with_search()
    {
        $user = User::factory()->create();

        $key1 = TranslationKey::factory()->create(['key' => 'home.title']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Accueil']);

        $key2 = TranslationKey::factory()->create(['key' => 'about.title']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'À propos']);

        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['search' => 'home']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 1)
                ->where('translationKeys.data.0.key', 'home.title')
            );
    }

    public function test_translations_index_with_locale_filter()
    {
        $user = User::factory()->create();

        $key1 = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $key1->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $key2 = TranslationKey::factory()->create(['key' => 'test.goodbye']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Au revoir']);

        // Filter by English locale should only show keys with English translations
        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['locale' => 'en']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 1)
                ->where('translationKeys.data.0.key', 'test.hello')
            );
    }

    public function test_update_translation()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $translation = $key->translations()->create([
            'locale' => 'fr',
            'text' => 'Bonjour',
        ]);

        $response = $this->actingAs($user)
            ->putJson(route('dashboard.api.translations.update', $translation), [
                'text' => 'Bonjour le monde',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Translation updated successfully',
            ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'text' => 'Bonjour le monde',
        ]);
    }

    public function test_update_translation_requires_text()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $translation = $key->translations()->create([
            'locale' => 'fr',
            'text' => 'Bonjour',
        ]);

        $response = $this->actingAs($user)
            ->putJson(route('dashboard.api.translations.update', $translation), [
                'text' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }
}
