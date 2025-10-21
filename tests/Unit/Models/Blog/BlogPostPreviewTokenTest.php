<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Blog;

use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostPreviewTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_blog_post_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $token = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $this->assertInstanceOf(BlogPostDraft::class, $token->blogPostDraft);
        $this->assertEquals($draft->id, $token->blogPostDraft->id);
    }

    #[Test]
    public function generate_unique_token_returns_32_character_string(): void
    {
        $token = BlogPostPreviewToken::generateUniqueToken();

        $this->assertIsString($token);
        $this->assertEquals(32, strlen($token));
    }

    #[Test]
    public function generate_unique_token_returns_unique_values(): void
    {
        $token1 = BlogPostPreviewToken::generateUniqueToken();
        $token2 = BlogPostPreviewToken::generateUniqueToken();

        $this->assertNotEquals($token1, $token2);
    }

    #[Test]
    public function scope_valid_filters_expired_tokens(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create expired token
        $expiredToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDay(),
        ]);

        // Create valid token
        $validToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->addDay(),
        ]);

        $validTokens = BlogPostPreviewToken::valid()->get();

        $this->assertCount(1, $validTokens);
        $this->assertEquals($validToken->id, $validTokens->first()->id);
        $this->assertFalse($validTokens->contains($expiredToken));
    }

    #[Test]
    public function is_expired_returns_true_for_expired_token(): void
    {
        $token = BlogPostPreviewToken::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($token->isExpired());
    }

    #[Test]
    public function is_expired_returns_false_for_valid_token(): void
    {
        $token = BlogPostPreviewToken::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($token->isExpired());
    }

    #[Test]
    public function get_preview_url_generates_correct_url(): void
    {
        $token = BlogPostPreviewToken::factory()->create([
            'token' => 'test-token-123',
        ]);

        $url = $token->getPreviewUrl();

        $this->assertStringContainsString('/blog/preview/test-token-123', $url);
        $this->assertEquals(route('public.blog.preview', ['token' => 'test-token-123']), $url);
    }

    #[Test]
    public function create_for_draft_creates_new_token(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $token = BlogPostPreviewToken::createForDraft($draft, 7);

        $this->assertInstanceOf(BlogPostPreviewToken::class, $token);
        $this->assertEquals($draft->id, $token->blog_post_draft_id);
        $this->assertNotNull($token->token);
        $this->assertNotNull($token->expires_at);
    }

    #[Test]
    public function create_for_draft_deletes_existing_tokens(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create first token
        $oldToken = BlogPostPreviewToken::createForDraft($draft, 7);
        $oldTokenId = $oldToken->id;

        // Create second token (should delete the first)
        $newToken = BlogPostPreviewToken::createForDraft($draft, 7);

        $this->assertNotEquals($oldTokenId, $newToken->id);
        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $oldTokenId]);
        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $newToken->id]);
    }

    #[Test]
    public function create_for_draft_sets_correct_expiration_date(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $expiresInDays = 14;

        $token = BlogPostPreviewToken::createForDraft($draft, $expiresInDays);

        $expectedDate = now()->addDays($expiresInDays);
        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $token->expires_at->format('Y-m-d')
        );
    }

    #[Test]
    public function create_for_draft_uses_default_expiration(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $token = BlogPostPreviewToken::createForDraft($draft);

        $expectedDate = now()->addDays(7); // Default is 7 days
        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $token->expires_at->format('Y-m-d')
        );
    }

    #[Test]
    public function it_has_fillable_fields(): void
    {
        $expectedFillable = [
            'token',
            'blog_post_draft_id',
            'expires_at',
        ];

        $token = new BlogPostPreviewToken;

        $this->assertEquals($expectedFillable, $token->getFillable());
    }

    #[Test]
    public function it_casts_expires_at_to_datetime(): void
    {
        $token = BlogPostPreviewToken::factory()->create([
            'expires_at' => '2025-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->expires_at);
        $this->assertEquals('2025-12-31', $token->expires_at->format('Y-m-d'));
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $token = BlogPostPreviewToken::factory()->create();

        $this->assertNotNull($token->created_at);
        $this->assertNotNull($token->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $token->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $token->updated_at);
    }

    #[Test]
    public function it_stores_and_retrieves_token_correctly(): void
    {
        $tokenString = 'abc123xyz789test';
        $token = BlogPostPreviewToken::factory()->create(['token' => $tokenString]);

        $this->assertEquals($tokenString, $token->token);
        $this->assertIsString($token->token);

        $retrievedToken = BlogPostPreviewToken::find($token->id);
        $this->assertEquals($tokenString, $retrievedToken->token);
    }

    #[Test]
    public function scope_valid_includes_tokens_expiring_today(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create token expiring later today
        $tokenExpiringToday = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->endOfDay(),
        ]);

        $validTokens = BlogPostPreviewToken::valid()->get();

        $this->assertCount(1, $validTokens);
        $this->assertEquals($tokenExpiringToday->id, $validTokens->first()->id);
    }

    #[Test]
    public function multiple_drafts_can_have_separate_tokens(): void
    {
        $draft1 = BlogPostDraft::factory()->create();
        $draft2 = BlogPostDraft::factory()->create();

        $token1 = BlogPostPreviewToken::createForDraft($draft1);
        $token2 = BlogPostPreviewToken::createForDraft($draft2);

        $this->assertNotEquals($token1->token, $token2->token);
        $this->assertEquals($draft1->id, $token1->blog_post_draft_id);
        $this->assertEquals($draft2->id, $token2->blog_post_draft_id);
    }
}
