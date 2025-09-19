<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\BlogContentMarkdownController;
use App\Models\BlogContentMarkdown;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogContentMarkdownController::class)]
class BlogContentMarkdownControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_store_creates_markdown_content_with_content()
    {
        $response = $this->postJson('/dashboard/api/blog-content-markdown', [
            'content' => '# Test Markdown Content',
            'locale' => 'fr',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'translation_key_id',
                'created_at',
                'updated_at',
                'translation_key' => [
                    'translations' => [
                        '*' => ['locale', 'text'],
                    ],
                ],
            ]);

        $markdownContent = BlogContentMarkdown::first();
        $this->assertNotNull($markdownContent);

        $this->assertDatabaseHas('translation_keys', [
            'id' => $markdownContent->translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => '# Test Markdown Content',
            'translation_key_id' => $markdownContent->translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'text' => '',
            'translation_key_id' => $markdownContent->translation_key_id,
        ]);
    }

    #[Test]
    public function test_store_creates_markdown_content_without_content()
    {
        $response = $this->postJson('/dashboard/api/blog-content-markdown', [
            'content' => null,
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $markdownContent = BlogContentMarkdown::first();
        $this->assertNotNull($markdownContent);

        $translations = Translation::where('translation_key_id', $markdownContent->translation_key_id)->get();
        $this->assertCount(2, $translations);

        foreach ($translations as $translation) {
            $this->assertEquals('', $translation->text);
        }
    }

    #[Test]
    public function test_store_creates_markdown_content_with_empty_string()
    {
        $response = $this->postJson('/dashboard/api/blog-content-markdown', [
            'content' => '',
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $markdownContent = BlogContentMarkdown::first();
        $this->assertNotNull($markdownContent);

        $translations = Translation::where('translation_key_id', $markdownContent->translation_key_id)->get();
        $this->assertCount(2, $translations);

        foreach ($translations as $translation) {
            $this->assertEquals('', $translation->text);
        }
    }

    #[Test]
    public function test_store_validates_missing_locale()
    {
        $response = $this->postJson('/dashboard/api/blog-content-markdown', [
            'content' => '# Test Content',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function test_store_validates_invalid_locale()
    {
        $response = $this->postJson('/dashboard/api/blog-content-markdown', [
            'content' => '# Test Content',
            'locale' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function test_show_returns_markdown_content_with_relations()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->getJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'translation_key_id',
                'created_at',
                'updated_at',
                'translation_key' => [
                    'id',
                    'key',
                    'translations' => [
                        '*' => ['id', 'locale', 'text'],
                    ],
                ],
            ])
            ->assertJson([
                'id' => $markdownContent->id,
                'translation_key_id' => $markdownContent->translation_key_id,
            ]);
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_content()
    {
        $response = $this->getJson('/dashboard/api/blog-content-markdown/99999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_modifies_french_translation()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}", [
            'content' => '## Updated French Content',
            'locale' => 'fr',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'translation_key_id',
                'translation_key' => [
                    'translations',
                ],
            ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $markdownContent->translation_key_id,
            'locale' => 'fr',
            'text' => '## Updated French Content',
        ]);
    }

    #[Test]
    public function test_update_modifies_english_translation()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}", [
            'content' => '## Updated English Content',
            'locale' => 'en',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $markdownContent->translation_key_id,
            'locale' => 'en',
            'text' => '## Updated English Content',
        ]);
    }

    #[Test]
    public function test_update_validates_missing_content()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}", [
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function test_update_validates_missing_locale()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}", [
            'content' => '# Updated Content',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function test_update_validates_invalid_locale()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}", [
            'content' => '# Updated Content',
            'locale' => 'de',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function test_update_returns_404_for_non_existent_content()
    {
        $response = $this->putJson('/dashboard/api/blog-content-markdown/99999', [
            'content' => '# Content',
            'locale' => 'fr',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function test_destroy_deletes_markdown_content_and_translations()
    {
        $markdownContent = BlogContentMarkdown::factory()->create();
        $translationKeyId = $markdownContent->translation_key_id;

        $response = $this->deleteJson("/dashboard/api/blog-content-markdown/{$markdownContent->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Markdown content deleted successfully',
            ]);

        $this->assertDatabaseMissing('blog_content_markdown', [
            'id' => $markdownContent->id,
        ]);

        $this->assertDatabaseMissing('translation_keys', [
            'id' => $translationKeyId,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $translationKeyId,
        ]);
    }

    #[Test]
    public function test_destroy_returns_404_for_non_existent_content()
    {
        $response = $this->deleteJson('/dashboard/api/blog-content-markdown/99999');

        $response->assertNotFound();
    }
}
