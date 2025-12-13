<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Services\Content\ContentValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentValidationService::class)]
class ContentValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentValidationService::class);
    }

    #[Test]
    public function it_validates_parent_with_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $result = $this->service->validate($draft);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_invalidates_parent_without_content(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $result = $this->service->validate($draft);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_checks_if_parent_has_content(): void
    {
        $draftWithContent = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draftWithContent->id,
        ]);

        $draftWithoutContent = BlogPostDraft::factory()->create();

        $this->assertTrue($this->service->hasContent($draftWithContent));
        $this->assertFalse($this->service->hasContent($draftWithoutContent));
    }

    #[Test]
    public function it_returns_content_count(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->count(3)->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $count = $this->service->getContentCount($draft);

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_returns_zero_for_empty_parent(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $count = $this->service->getContentCount($draft);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_resolves_parent_from_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $parent = $this->service->resolveParent($content);

        $this->assertNotNull($parent);
        $this->assertEquals($draft->id, $parent->id);
        $this->assertInstanceOf(BlogPostDraft::class, $parent);
    }

    #[Test]
    public function it_returns_null_for_orphan_content(): void
    {
        // Create valid content first, then delete the parent
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        // Delete the parent to create an orphan
        $draft->delete();

        $parent = $this->service->resolveParent($content);

        $this->assertNull($parent);
    }

    #[Test]
    public function it_detects_draft_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $isDraft = $this->service->isDraft($content);

        $this->assertTrue($isDraft);
    }

    #[Test]
    public function it_returns_false_for_orphan_content_is_draft(): void
    {
        // Create valid content first, then delete the parent
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        // Delete the parent to create an orphan
        $draft->delete();

        $isDraft = $this->service->isDraft($content);

        $this->assertFalse($isDraft);
    }

    #[Test]
    public function it_validates_with_multiple_content_blocks(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);
        BlogPostDraftContent::factory()->video()->create([
            'blog_post_draft_id' => $draft->id,
        ]);
        BlogPostDraftContent::factory()->gallery()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $this->assertTrue($this->service->validate($draft));
        $this->assertEquals(3, $this->service->getContentCount($draft));
    }
}
