<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Services\Content\ContentReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentReorderService::class)]
class ContentReorderServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentReorderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentReorderService::class);
    }

    #[Test]
    public function it_reorders_content_blocks(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);
        $content3 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 3,
        ]);

        // Reverse the order
        $this->service->reorder($draft, [$content3->id, $content2->id, $content1->id]);

        $content1->refresh();
        $content2->refresh();
        $content3->refresh();

        $this->assertEquals(3, $content1->order);
        $this->assertEquals(2, $content2->order);
        $this->assertEquals(1, $content3->order);
    }

    #[Test]
    public function it_moves_content_to_specific_position(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);
        $content3 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 3,
        ]);

        // Move content3 to position 1
        $this->service->moveTo($draft, $content3->id, 1);

        $content1->refresh();
        $content2->refresh();
        $content3->refresh();

        $this->assertEquals(1, $content3->order);
        $this->assertEquals(2, $content1->order);
        $this->assertEquals(3, $content2->order);
    }

    #[Test]
    public function it_moves_content_up(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->service->moveUp($draft, $content2->id);

        $content1->refresh();
        $content2->refresh();

        $this->assertEquals(1, $content2->order);
        $this->assertEquals(2, $content1->order);
    }

    #[Test]
    public function it_does_not_move_first_content_up(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->service->moveUp($draft, $content1->id);

        $content1->refresh();
        $content2->refresh();

        $this->assertEquals(1, $content1->order);
        $this->assertEquals(2, $content2->order);
    }

    #[Test]
    public function it_moves_content_down(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->service->moveDown($draft, $content1->id);

        $content1->refresh();
        $content2->refresh();

        $this->assertEquals(2, $content1->order);
        $this->assertEquals(1, $content2->order);
    }

    #[Test]
    public function it_does_not_move_last_content_down(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->service->moveDown($draft, $content2->id);

        $content1->refresh();
        $content2->refresh();

        $this->assertEquals(1, $content1->order);
        $this->assertEquals(2, $content2->order);
    }

    #[Test]
    public function it_handles_move_to_with_non_existent_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);

        // Should not throw exception
        $this->service->moveTo($draft, 99999, 1);

        $content1->refresh();
        $this->assertEquals(1, $content1->order);
    }

    #[Test]
    public function it_handles_move_up_with_non_existent_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);

        // Should not throw exception
        $this->service->moveUp($draft, 99999);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_move_down_with_non_existent_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);

        // Should not throw exception
        $this->service->moveDown($draft, 99999);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_moves_content_to_middle_position(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        $content2 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);
        $content3 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 3,
        ]);
        $content4 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 4,
        ]);

        // Move content4 to position 2
        $this->service->moveTo($draft, $content4->id, 2);

        $content1->refresh();
        $content2->refresh();
        $content3->refresh();
        $content4->refresh();

        $this->assertEquals(1, $content1->order);
        $this->assertEquals(2, $content4->order);
        $this->assertEquals(3, $content2->order);
        $this->assertEquals(4, $content3->order);
    }
}
