<?php

namespace Tests\Feature\Models\Screenshot;

use App\Http\Controllers\Admin\Api\CreationDraftScreenshotController;
use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(CreationDraftScreenshotController::class)]
class CreationDraftScreenshotControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected CreationDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->draft = CreationDraft::factory()->create();
    }

    #[Test]
    public function test_index_returns_draft_screenshots(): void
    {
        CreationDraftScreenshot::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create([
                'creation_draft_id' => $this->draft->id,
            ]);

        $response = $this->getJson(route('dashboard.api.creation-drafts.draft-screenshots.index', $this->draft));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'picture_id', 'caption_translation_key_id'],
            ]);
    }

    #[Test]
    public function test_store_creates_new_screenshot_with_caption(): void
    {
        $picture = Picture::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-screenshots.store', $this->draft),
            [
                'picture_id' => $picture->id,
                'caption' => 'Test Caption',
                'locale' => 'en',
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('picture_id', $picture->id);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'creation_draft_id' => $this->draft->id,
            'picture_id' => $picture->id,
        ]);

        $screenshot = CreationDraftScreenshot::first();
        $translation = Translation::where('translation_key_id', $screenshot->caption_translation_key_id)
            ->where('locale', 'en')
            ->first();

        $this->assertEquals('Test Caption', $translation->text);
    }

    #[Test]
    public function test_store_creates_new_screenshot_without_caption(): void
    {
        $picture = Picture::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-screenshots.store', $this->draft),
            [
                'picture_id' => $picture->id,
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('picture_id', $picture->id);

        $this->assertDatabaseHas('creation_draft_screenshots', [
            'creation_draft_id' => $this->draft->id,
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-screenshots.store', $this->draft)
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'picture_id',
            ]);
    }

    #[Test]
    public function test_show_returns_specific_screenshot(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->create(['order' => 1]);

        $response = $this->getJson(
            route('dashboard.api.draft-screenshots.show', $screenshot)
        );

        $response->assertOk()
            ->assertJson([
                'id' => $screenshot->id,
                'picture_id' => $screenshot->picture_id,
            ]);
    }

    #[Test]
    public function test_update_modifies_caption(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->withCaption()->create(['order' => 1]);

        $this->putJson(
            route('dashboard.api.draft-screenshots.update', $screenshot),
            [
                'caption' => 'Updated Caption',
                'locale' => 'en',
            ]
        );

        $translation = Translation::where('translation_key_id', $screenshot->caption_translation_key_id)
            ->where('locale', 'en')
            ->first();

        $this->assertEquals('Updated Caption', $translation->text);
    }

    #[Test]
    public function test_update_with_no_caption_removes_caption_translation_id(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->withCaption()->create(['order' => 1]);

        $response = $this->putJson(
            route('dashboard.api.draft-screenshots.update', $screenshot),
            [
                'caption' => null,
                'locale' => 'en',
            ]
        );

        $response->assertOk();

        $screenshot->refresh();
        $this->assertNull($screenshot->caption_translation_key_id);
    }

    #[TestDox('It test that creating a screenshots with no caption, and then, updating it with a caption, will create a new translation key')]
    public function test_update_creates_new_translation_key(): void
    {
        $screenshot = CreationDraftScreenshot::factory([
            'caption_translation_key_id' => null,
            'order' => 1,
        ])->create();

        $response = $this->putJson(
            route('dashboard.api.draft-screenshots.update', $screenshot),
            [
                'caption' => 'Updated Caption',
                'locale' => 'en',
            ]
        );

        $response->assertOk();

        $screenshot->refresh();
        $translation = Translation::where('translation_key_id', $screenshot->caption_translation_key_id)
            ->where('locale', 'en')
            ->first();

        $this->assertEquals('Updated Caption', $translation->text);
    }

    #[TestDox('Test that updating a screenshot that has a caption with no data will remove the caption')]
    public function test_update_removes_caption(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->withCaption()->create(['order' => 1]);

        $this->putJson(
            route('dashboard.api.draft-screenshots.update', $screenshot),
            [
                'caption' => '',
                'locale' => 'en',
            ]
        );

        $screenshot->refresh();
        $this->assertNull($screenshot->caption_translation_key_id);
    }

    #[Test]
    public function test_destroy_deletes_screenshot(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->create(['order' => 1]);

        $response = $this->delete(
            route('dashboard.api.draft-screenshots.destroy', $screenshot)
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('creation_draft_screenshots', ['id' => $screenshot->id]);
    }

    #[Test]
    public function test_screenshot_belongs_to_correct_draft(): void
    {
        $otherDraft = CreationDraft::factory()->create();
        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $this->draft->id,
            'order' => 1,
        ]);

        $response = $this->getJson(
            route('dashboard.api.creation-drafts.draft-screenshots.index', $otherDraft)
        );

        $response->assertJsonCount(0);
    }

    #[Test]
    public function test_picture_validation(): void
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-screenshots.store', $this->draft),
            [
                'creation_draft_id' => $this->draft->id,
                'picture_id' => 999,
                'caption' => 'Test',
                'locale' => 'en',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['picture_id']);
    }

    #[Test]
    public function test_update_reuses_existing_translation_key(): void
    {
        $screenshot = CreationDraftScreenshot::factory()->withCaption()->create(['order' => 1]);
        $originalKey = $screenshot->caption_translation_key_id;

        $this->putJson(
            route('dashboard.api.draft-screenshots.update', $screenshot),
            [
                'caption' => 'Updated Caption',
                'locale' => 'en',
            ]
        );

        $screenshot->refresh();
        $this->assertEquals($originalKey, $screenshot->caption_translation_key_id);
    }
}
