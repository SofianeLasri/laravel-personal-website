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

    public function test_translations_index_with_pagination()
    {
        $user = User::factory()->create();

        // Create 20 translation keys to test pagination
        for ($i = 1; $i <= 20; $i++) {
            $key = TranslationKey::factory()->create(['key' => "test.item{$i}"]);
            $key->translations()->create(['locale' => 'fr', 'text' => "Texte {$i}"]);
        }

        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['per_page' => 5]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 5)
                ->where('translationKeys.per_page', 5)
                ->where('translationKeys.current_page', 1)
            );
    }

    public function test_translations_index_search_by_text_content()
    {
        $user = User::factory()->create();

        $key1 = TranslationKey::factory()->create(['key' => 'app.title']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Mon Application']);

        $key2 = TranslationKey::factory()->create(['key' => 'app.description']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Une belle description']);

        // Search by text content
        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['search' => 'Application']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 1)
                ->where('translationKeys.data.0.key', 'app.title')
            );
    }

    public function test_translate_single_fails_when_no_french_translation()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        // No French translation created

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-single', $key));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No French translation found to translate from',
            ]);
    }

    public function test_translate_batch_with_no_french_translations()
    {
        $user = User::factory()->create();

        // Create keys with no French translations
        TranslationKey::factory()->create(['key' => 'test.item1']);
        TranslationKey::factory()->create(['key' => 'test.item2']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-batch'), [
                'mode' => 'missing',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'jobs_dispatched' => 0,
            ]);
    }

    public function test_translations_index_with_empty_search()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        // Test with empty search - should return all results
        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['search' => '']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 1)
            );
    }

    public function test_translations_index_with_nonexistent_search()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        // Search for something that doesn't exist
        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index', ['search' => 'nonexistent']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->has('translationKeys.data', 0)
            );
    }

    public function test_translation_stats_calculation()
    {
        $user = User::factory()->create();

        // Create various scenarios for stats
        $key1 = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $key1->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $key2 = TranslationKey::factory()->create(['key' => 'test.goodbye']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Au revoir']);
        // No English translation for key2

        $key3 = TranslationKey::factory()->create(['key' => 'test.orphan']);
        // No translations at all for key3

        $response = $this->actingAs($user)
            ->get(route('dashboard.translations.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Translations')
                ->where('stats.total_keys', 3)
                ->where('stats.french_translations', 2)
                ->where('stats.english_translations', 1)
                ->where('stats.missing_english', 1)
            );
    }

    public function test_translate_batch_mode_all_deletes_existing_english()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $existingEnglish = $key->translations()->create(['locale' => 'en', 'text' => 'Old Hello']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-batch'), [
                'mode' => 'all',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'jobs_dispatched' => 1,
            ]);

        // Verify the existing English translation was deleted
        $this->assertDatabaseMissing('translations', [
            'id' => $existingEnglish->id,
        ]);
    }
}
