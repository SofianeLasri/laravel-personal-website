<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\CleanupExpiredPreviewTokens;
use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CleanupExpiredPreviewTokens::class)]
class CleanupExpiredPreviewTokensTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_tokens_expired_for_more_than_default_days(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create tokens expired more than 30 days ago (should be deleted)
        $oldExpiredToken1 = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(35),
        ]);
        $oldExpiredToken2 = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(100),
        ]);

        // Create token expired less than 30 days ago (should NOT be deleted)
        $recentExpiredToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(20),
        ]);

        // Create active token (should NOT be deleted)
        $activeToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Cleaning up preview tokens expired for more than 30 days...')
            ->expectsOutput('Deleted 2 expired preview token(s).')
            ->assertExitCode(0);

        // Verify old expired tokens were deleted
        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $oldExpiredToken1->id]);
        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $oldExpiredToken2->id]);

        // Verify recent expired and active tokens were NOT deleted
        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $recentExpiredToken->id]);
        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $activeToken->id]);
    }

    #[Test]
    public function it_accepts_custom_days_option(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create token expired 15 days ago
        $token = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(15),
        ]);

        // With default 30 days, this should NOT be deleted
        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Cleaning up preview tokens expired for more than 30 days...')
            ->expectsOutput('Deleted 0 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $token->id]);

        // With custom 10 days, this SHOULD be deleted
        $this->artisan(CleanupExpiredPreviewTokens::class, ['--days' => 10])
            ->expectsOutput('Cleaning up preview tokens expired for more than 10 days...')
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $token->id]);
    }

    #[Test]
    public function it_handles_no_expired_tokens_gracefully(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create only active tokens
        BlogPostPreviewToken::factory()->count(3)->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Cleaning up preview tokens expired for more than 30 days...')
            ->expectsOutput('Deleted 0 expired preview token(s).')
            ->assertExitCode(0);

        // Verify all tokens still exist
        $this->assertCount(3, BlogPostPreviewToken::all());
    }

    #[Test]
    public function it_deletes_multiple_expired_tokens(): void
    {
        $drafts = BlogPostDraft::factory()->count(5)->create();

        // Create 10 old expired tokens across multiple drafts
        foreach ($drafts as $draft) {
            BlogPostPreviewToken::factory()->count(2)->create([
                'blog_post_draft_id' => $draft->id,
                'expires_at' => now()->subDays(50),
            ]);
        }

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Cleaning up preview tokens expired for more than 30 days...')
            ->expectsOutput('Deleted 10 expired preview token(s).')
            ->assertExitCode(0);

        // Verify all tokens were deleted
        $this->assertCount(0, BlogPostPreviewToken::all());
    }

    #[Test]
    public function it_only_deletes_tokens_past_cutoff_date(): void
    {
        $this->freezeTime();

        $draft = BlogPostDraft::factory()->create();

        // Create token exactly at the cutoff (30 days ago) - should NOT be deleted
        $cutoffToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(30),
        ]);

        // Create token one day past cutoff - should be deleted
        $pastCutoffToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(31),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $cutoffToken->id]);
        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $pastCutoffToken->id]);
    }

    #[Test]
    public function it_uses_correct_command_signature_and_description(): void
    {
        $command = new CleanupExpiredPreviewTokens;

        $this->assertEquals('blog:cleanup-expired-preview-tokens', $command->getName());
        $this->assertEquals('Clean up expired blog post preview tokens', $command->getDescription());
    }

    #[Test]
    public function it_handles_very_old_expired_tokens(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create token expired 1 year ago
        $veryOldToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subYear(),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $veryOldToken->id]);
    }

    #[Test]
    public function it_accepts_days_option_as_integer(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $token = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(6),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class, ['--days' => '5'])
            ->expectsOutput('Cleaning up preview tokens expired for more than 5 days...')
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $token->id]);
    }

    #[Test]
    public function it_returns_success_exit_code(): void
    {
        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->assertExitCode(0);
    }

    #[Test]
    public function it_deletes_tokens_from_multiple_drafts_independently(): void
    {
        $draft1 = BlogPostDraft::factory()->create();
        $draft2 = BlogPostDraft::factory()->create();

        // Draft 1: create old expired token (should be deleted)
        $oldToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft1->id,
            'expires_at' => now()->subDays(40),
        ]);

        // Draft 2: create recent token (should NOT be deleted)
        $recentToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft2->id,
            'expires_at' => now()->addDays(5),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $oldToken->id]);
        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $recentToken->id]);
    }

    #[Test]
    public function it_handles_edge_case_with_zero_days(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Create token that expired yesterday
        $yesterdayToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDay(),
        ]);

        // Create token that expires tomorrow
        $tomorrowToken = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->addDay(),
        ]);

        // With 0 days, should delete anything that has expired (even if it was yesterday)
        $this->artisan(CleanupExpiredPreviewTokens::class, ['--days' => 0])
            ->expectsOutput('Cleaning up preview tokens expired for more than 0 days...')
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('blog_post_preview_tokens', ['id' => $yesterdayToken->id]);
        $this->assertDatabaseHas('blog_post_preview_tokens', ['id' => $tomorrowToken->id]);
    }

    #[Test]
    public function it_displays_singular_and_plural_messages_correctly(): void
    {
        $draft = BlogPostDraft::factory()->create();

        // Test with 1 token
        $token = BlogPostPreviewToken::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->subDays(40),
        ]);

        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Deleted 1 expired preview token(s).')
            ->assertExitCode(0);

        // Test with 0 tokens
        $this->artisan(CleanupExpiredPreviewTokens::class)
            ->expectsOutput('Deleted 0 expired preview token(s).')
            ->assertExitCode(0);
    }
}
