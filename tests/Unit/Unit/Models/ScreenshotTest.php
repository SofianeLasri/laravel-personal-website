<?php

namespace Tests\Unit\Unit\Models;

use App\Models\Creation;
use App\Models\Picture;
use App\Models\Screenshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_fillable(): void
    {
        $screenshot = Screenshot::factory()->create(['order' => 5]);

        $this->assertEquals(5, $screenshot->order);
    }

    public function test_screenshots_ordered_by_order_by_default(): void
    {
        $creation = Creation::factory()->create();

        Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 3,
        ]);

        Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $screenshots = $creation->screenshots;

        $this->assertEquals(1, $screenshots[0]->order);
        $this->assertEquals(2, $screenshots[1]->order);
        $this->assertEquals(3, $screenshots[2]->order);
    }

    public function test_order_scope_works_correctly(): void
    {
        $creation = Creation::factory()->create();

        $screenshot1 = Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 3,
        ]);

        $screenshot2 = Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot3 = Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $orderedScreenshots = Screenshot::where('creation_id', $creation->id)
            ->orderByOrder()
            ->get();

        $this->assertEquals($screenshot2->id, $orderedScreenshots[0]->id);
        $this->assertEquals($screenshot3->id, $orderedScreenshots[1]->id);
        $this->assertEquals($screenshot1->id, $orderedScreenshots[2]->id);
    }
}
