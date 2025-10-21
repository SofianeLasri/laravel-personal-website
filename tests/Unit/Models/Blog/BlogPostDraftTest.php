<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Blog;

use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostDraftTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_many_preview_tokens(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $token1 = BlogPostPreviewToken::factory()->create(['blog_post_draft_id' => $draft->id]);
        $token2 = BlogPostPreviewToken::factory()->create(['blog_post_draft_id' => $draft->id]);
        $token3 = BlogPostPreviewToken::factory()->create(['blog_post_draft_id' => $draft->id]);

        $this->assertCount(3, $draft->previewTokens);
        $this->assertTrue($draft->previewTokens->contains($token1));
        $this->assertTrue($draft->previewTokens->contains($token2));
        $this->assertTrue($draft->previewTokens->contains($token3));
    }
}
