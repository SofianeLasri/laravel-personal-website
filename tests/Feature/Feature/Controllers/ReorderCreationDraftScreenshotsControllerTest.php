<?php

namespace Tests\Feature\Feature\Controllers;

use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderCreationDraftScreenshotsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_reorder_screenshots_successfully(): void
    {
        $user = User::factory()->create();
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

        $response = $this->actingAs($user)->putJson(
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

    public function test_can_swap_two_screenshots_without_constraint_violation(): void
    {
        $user = User::factory()->create();
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
        $response = $this->actingAs($user)->putJson(
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

    public function test_validates_continuous_sequence(): void
    {
        $user = User::factory()->create();
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
        $response = $this->actingAs($user)->putJson(
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

    public function test_validates_all_screenshot_ids_belong_to_draft(): void
    {
        $user = User::factory()->create();
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

        $response = $this->actingAs($user)->putJson(
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

    public function test_prevents_duplicate_orders(): void
    {
        $user = User::factory()->create();
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

        $response = $this->actingAs($user)->putJson(
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

    public function test_rejects_invalid_screenshot_ids(): void
    {
        $user = User::factory()->create();
        $draft = CreationDraft::factory()->create();

        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'picture_id' => Picture::factory()->create()->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->putJson(
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

    public function test_requires_authentication(): void
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
                ],
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_requires_all_screenshots_to_be_included(): void
    {
        $user = User::factory()->create();
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
        $response = $this->actingAs($user)->putJson(
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
