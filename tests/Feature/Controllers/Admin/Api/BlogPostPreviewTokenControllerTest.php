<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\BlogPostPreviewTokenController;
use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogPostPreviewTokenController::class)]
class BlogPostPreviewTokenControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function store_generates_new_preview_token(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 7,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'token',
                    'url',
                    'expires_at',
                    'expires_at_human',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Lien de prévisualisation généré avec succès',
            ]);

        $this->assertDatabaseHas('blog_post_preview_tokens', [
            'blog_post_draft_id' => $draft->id,
        ]);

        $this->assertEquals(32, strlen($response->json('data.token')));
        $this->assertStringContainsString('/blog/preview/', $response->json('data.url'));
    }

    #[Test]
    public function store_regenerates_existing_token(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create first token
        $firstToken = BlogPostPreviewToken::createForDraft($draft, 7);
        $firstTokenId = $firstToken->id;

        // Generate new token (should delete first one)
        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 7,
        ]);

        $response->assertOk();

        // Check old token was deleted
        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $firstTokenId]);

        // Check only one token exists for this draft
        $this->assertDatabaseCount('blog_post_preview_tokens', 1);
        $this->assertDatabaseHas('blog_post_preview_tokens', [
            'blog_post_draft_id' => $draft->id,
        ]);
    }

    #[Test]
    public function store_uses_custom_expiration_days(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 14,
        ]);

        $response->assertOk();

        $token = BlogPostPreviewToken::where('blog_post_draft_id', $draft->id)->first();
        $expectedDate = now()->addDays(14);

        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $token->expires_at->format('Y-m-d')
        );
    }

    #[Test]
    public function store_uses_default_expiration_when_not_provided(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]));

        $response->assertOk();

        $token = BlogPostPreviewToken::where('blog_post_draft_id', $draft->id)->first();
        $expectedDate = now()->addDays(7); // Default

        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $token->expires_at->format('Y-m-d')
        );
    }

    #[Test]
    public function store_validates_expires_in_days_minimum(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_in_days']);
    }

    #[Test]
    public function store_validates_expires_in_days_maximum(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 31,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_in_days']);
    }

    #[Test]
    public function store_validates_expires_in_days_is_integer(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_in_days']);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        auth()->logout();
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]));

        $response->assertUnauthorized();
    }

    #[Test]
    public function show_returns_active_preview_token(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $token = BlogPostPreviewToken::createForDraft($draft, 7);

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'token',
                    'url',
                    'expires_at',
                    'expires_at_human',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $token->id,
                    'token' => $token->token,
                ],
            ]);
    }

    #[Test]
    public function show_returns_404_when_no_token_exists(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Aucun lien de prévisualisation actif',
                'data' => null,
            ]);
    }

    #[Test]
    public function show_returns_404_when_token_is_expired(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostPreviewToken::factory()->expired()->create(['blog_post_draft_id' => $draft->id]);

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Aucun lien de prévisualisation actif',
            ]);
    }

    #[Test]
    public function show_returns_most_recent_valid_token(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create older token
        $olderToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'created_at' => now()->subHours(2),
        ]);

        // Create newer token
        $newerToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'created_at' => now()->subHour(),
        ]);

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $newerToken->id,
                    'token' => $newerToken->token,
                ],
            ]);
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        auth()->logout();
        $draft = BlogPostDraft::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertUnauthorized();
    }

    #[Test]
    public function destroy_revokes_preview_token(): void
    {
        $token = BlogPostPreviewToken::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.blog-post-preview-tokens.destroy', ['blog_post_preview_token' => $token->id]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lien de prévisualisation révoqué avec succès',
            ]);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $token->id]);
    }

    #[Test]
    public function destroy_returns_404_for_non_existent_token(): void
    {
        $response = $this->deleteJson(route('dashboard.api.blog-post-preview-tokens.destroy', ['blog_post_preview_token' => 99999]));

        $response->assertNotFound();
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        auth()->logout();
        $token = BlogPostPreviewToken::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.blog-post-preview-tokens.destroy', ['blog_post_preview_token' => $token->id]));

        $response->assertUnauthorized();
    }

    #[Test]
    public function store_returns_correctly_formatted_french_date(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]), [
            'expires_in_days' => 7,
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('data.expires_at_human'));
        $this->assertNotEmpty($response->json('data.expires_at_human'));
    }

    #[Test]
    public function show_returns_correctly_formatted_french_date(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostPreviewToken::createForDraft($draft, 7);

        $response = $this->getJson(route('dashboard.api.blog-post-preview-tokens.show', ['blog_post_draft' => $draft->id]));

        $response->assertOk();
        $this->assertIsString($response->json('data.expires_at_human'));
        $this->assertNotEmpty($response->json('data.expires_at_human'));
    }

    #[Test]
    public function store_returns_valid_iso8601_timestamp(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]));

        $response->assertOk();

        $expiresAt = $response->json('data.expires_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $expiresAt);
    }

    #[Test]
    public function store_returns_preview_url_with_correct_structure(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-preview-tokens.store', ['blog_post_draft' => $draft->id]));

        $response->assertOk();

        $url = $response->json('data.url');
        $token = $response->json('data.token');

        $this->assertStringContainsString('/blog/preview/', $url);
        $this->assertStringContainsString($token, $url);
        $this->assertEquals(route('public.blog.preview', ['token' => $token]), $url);
    }
}
