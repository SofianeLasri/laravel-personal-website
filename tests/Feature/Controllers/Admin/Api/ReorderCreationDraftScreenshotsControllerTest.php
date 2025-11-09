<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\ReorderCreationDraftScreenshotsController;
use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(ReorderCreationDraftScreenshotsController::class)]
class ReorderCreationDraftScreenshotsControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function reorders_screenshots_successfully(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $screenshot3 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 3,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot3->id, 'order' => 1],
                    ['id' => $screenshot1->id, 'order' => 2],
                    ['id' => $screenshot2->id, 'order' => 3],
                ],
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot3->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot1->id,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot2->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function swaps_two_screenshots_without_constraint_violation(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        // Swap the two screenshots - this is the critical edge case that triggers
        // the unique constraint issue if not handled properly with temporary values
        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot2->id, 'order' => 1], // Was 2, now 1
                    ['id' => $screenshot1->id, 'order' => 2], // Was 1, now 2
                ],
            ]
        );

        $response->assertOk();

        // Verify the swap happened correctly
        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot2->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'id' => $screenshot1->id,
            'order' => 2,
        ]);
    }

    #[Test]
    public function validates_continuous_sequence(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        // Try to set orders with gaps (1, 3 instead of 1, 2)
        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                    ['id' => $screenshot2->id, 'order' => 3],
                ],
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshots');
    }

    #[Test]
    public function validates_all_screenshot_ids_belong_to_draft(): void
    {
        $draft1 = CreationDraft::factory()->create();
        $draft2 = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft1->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft2->id, // Different draft!
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft1->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                    ['id' => $screenshot2->id, 'order' => 2],
                ],
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshots');
    }

    #[Test]
    public function prevents_duplicate_orders(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                    ['id' => $screenshot2->id, 'order' => 1], // Duplicate!
                ],
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshots');
    }

    #[Test]
    public function rejects_invalid_screenshot_ids(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                    ['id' => 99999, 'order' => 2], // Non-existent ID
                ],
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshots.1.id');
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->app['auth']->forgetGuards(); // Logout

        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                ],
            ]
        );

        $response->assertUnauthorized();
    }

    #[Test]
    public function requires_all_screenshots_to_be_included(): void
    {
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 2,
        ]);

        // Only sending one screenshot when there are two
        $response = $this->putJson(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', $draft->id),
            [
                'screenshots' => [
                    ['id' => $screenshot1->id, 'order' => 1],
                ],
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshots');
    }
}
