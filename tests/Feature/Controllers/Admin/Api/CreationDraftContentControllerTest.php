<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\CreationDraftContentController;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(CreationDraftContentController::class)]
class CreationDraftContentControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function store_creates_draft_content_with_automatic_order()
    {
        $draft = CreationDraft::factory()->create();
        $markdown = ContentMarkdown::factory()->create();

        // Create existing content with orders
        CreationDraftContent::factory()->forCreationDraft($draft)->create(['order' => 1]);
        CreationDraftContent::factory()->forCreationDraft($draft)->create(['order' => 2]);

        $response = $this->postJson(route('dashboard.api.creation-draft-contents.store'), [
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'creation_draft_id',
                'content_type',
                'content_id',
                'order',
                'content',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'creation_draft_id' => $draft->id,
                'content_type' => ContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 3, // Should be max order + 1
            ]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function store_creates_draft_content_with_specified_order()
    {
        $draft = CreationDraft::factory()->create();
        $gallery = ContentGallery::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-draft-contents.store'), [
            'creation_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 5,
        ]);

        $response->assertCreated()
            ->assertJson([
                'creation_draft_id' => $draft->id,
                'content_type' => ContentGallery::class,
                'content_id' => $gallery->id,
                'order' => 5,
            ]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'creation_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 5,
        ]);
    }

    #[Test]
    public function store_fails_with_invalid_data()
    {
        $response = $this->postJson(route('dashboard.api.creation-draft-contents.store'), [
            'content_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'creation_draft_id',
                    'content_id',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function store_fails_with_non_existent_creation_draft()
    {
        $markdown = ContentMarkdown::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-draft-contents.store'), [
            'creation_draft_id' => 99999,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'creation_draft_id',
                ],
            ]);
    }

    #[Test]
    public function store_loads_content_relationship()
    {
        $draft = CreationDraft::factory()->create();
        $video = ContentVideo::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-draft-contents.store'), [
            'creation_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $video->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('content.id', $video->id)
            ->assertJsonStructure([
                'content' => [
                    'id',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function update_modifies_order_successfully()
    {
        $draftContent = CreationDraftContent::factory()->markdown()->create([
            'order' => 1,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-draft-contents.update', $draftContent),
            [
                'order' => 5,
            ]
        );

        $response->assertOk()
            ->assertJson([
                'id' => $draftContent->id,
                'order' => 5,
            ])
            ->assertJsonStructure([
                'content',
            ]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $draftContent->id,
            'order' => 5,
        ]);
    }

    #[Test]
    public function update_fails_with_invalid_order_type()
    {
        $draftContent = CreationDraftContent::factory()->create();

        $response = $this->putJson(
            route('dashboard.api.creation-draft-contents.update', $draftContent),
            [
                'order' => 'not_a_number',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'order',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function update_without_order_field_returns_unchanged()
    {
        $draftContent = CreationDraftContent::factory()->gallery()->create([
            'order' => 3,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-draft-contents.update', $draftContent),
            []
        );

        $response->assertOk()
            ->assertJson([
                'id' => $draftContent->id,
                'order' => 3,
            ]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $draftContent->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function update_loads_content_relationship()
    {
        $markdown = ContentMarkdown::factory()->create();
        $draftContent = CreationDraftContent::factory()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response = $this->putJson(
            route('dashboard.api.creation-draft-contents.update', $draftContent),
            [
                'order' => 10,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('content.id', $markdown->id)
            ->assertJsonStructure([
                'content' => [
                    'id',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function reorder_updates_content_order_successfully()
    {
        $draft = CreationDraft::factory()->create();

        $content1 = CreationDraftContent::factory()->forCreationDraft($draft)->create(['order' => 1]);
        $content2 = CreationDraftContent::factory()->forCreationDraft($draft)->create(['order' => 2]);
        $content3 = CreationDraftContent::factory()->forCreationDraft($draft)->create(['order' => 3]);

        $response = $this->postJson(
            route('dashboard.api.creation-draft-contents.reorder', $draft),
            [
                'content_ids' => [$content3->id, $content1->id, $content2->id],
            ]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Content reordered successfully',
            ]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $content3->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $content1->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $content2->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function reorder_fails_with_missing_content_ids()
    {
        $draft = CreationDraft::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.creation-draft-contents.reorder', $draft),
            []
        );

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'content_ids',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function reorder_fails_with_non_existent_content_ids()
    {
        $draft = CreationDraft::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.creation-draft-contents.reorder', $draft),
            [
                'content_ids' => [99999, 88888],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'content_ids.0',
                'content_ids.1',
            ]);
    }

    #[Test]
    public function reorder_only_affects_specified_draft_contents()
    {
        $draft1 = CreationDraft::factory()->create();
        $draft2 = CreationDraft::factory()->create();

        $content1 = CreationDraftContent::factory()->forCreationDraft($draft1)->create(['order' => 1]);
        $content2 = CreationDraftContent::factory()->forCreationDraft($draft1)->create(['order' => 2]);
        $otherDraftContent = CreationDraftContent::factory()->forCreationDraft($draft2)->create(['order' => 1]);

        $response = $this->postJson(
            route('dashboard.api.creation-draft-contents.reorder', $draft1),
            [
                'content_ids' => [$content2->id, $content1->id],
            ]
        );

        $response->assertOk();

        // Verify draft1 contents are reordered
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $content2->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $content1->id,
            'order' => 2,
        ]);

        // Verify draft2 content is unchanged
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $otherDraftContent->id,
            'order' => 1,
        ]);
    }

    #[Test]
    public function destroy_deletes_draft_content_successfully()
    {
        $draftContent = CreationDraftContent::factory()->video()->create();
        $contentId = $draftContent->id;

        $response = $this->deleteJson(
            route('dashboard.api.creation-draft-contents.destroy', $draftContent)
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Content deleted successfully',
            ]);

        $this->assertDatabaseMissing('creation_draft_contents', [
            'id' => $contentId,
        ]);
    }

    #[Test]
    public function destroy_returns_404_for_non_existent_content()
    {
        $response = $this->deleteJson(
            route('dashboard.api.creation-draft-contents.destroy', 99999)
        );

        $response->assertNotFound();
    }
}
