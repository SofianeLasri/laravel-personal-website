<?php

namespace Tests\Unit\Unit\Models;

use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreationDraftScreenshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_fillable(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->create(['order' => 5]);

        $this->assertEquals(5, $screenshot->order);
    }

    public function test_screenshots_ordered_by_order_by_default(): void
    {
        $draft = CreationDraft::factory()->create();

        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 3,
        ]);

        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $screenshots = $draft->screenshots;

        $this->assertEquals(1, $screenshots[0]->order);
        $this->assertEquals(2, $screenshots[1]->order);
        $this->assertEquals(3, $screenshots[2]->order);
    }

    public function test_order_scope_works_correctly(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 3,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot3 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $orderedScreenshots = CreationDraftScreenshot::where('creation_draft_id', $draft->id)
            ->orderByOrder()
            ->get();

        $this->assertEquals($screenshot2->id, $orderedScreenshots[0]->id);
        $this->assertEquals($screenshot3->id, $orderedScreenshots[1]->id);
        $this->assertEquals($screenshot1->id, $orderedScreenshots[2]->id);
    }
}
