<?php

namespace Tests\Feature\Models\Feature;

use App\Http\Controllers\Admin\Api\CreationDraftFeatureController;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(CreationDraftFeatureController::class)]
class CreationDraftFeatureControllerTest extends TestCase
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
    public function test_index_returns_draft_features()
    {
        CreationDraftFeature::factory()->count(3)->create([
            'creation_draft_id' => $this->draft->id,
        ]);

        $response = $this->getJson(route('dashboard.api.creation-drafts.draft-features.index', $this->draft));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'title_translation_key_id', 'description_translation_key_id', 'picture_id'],
            ]);
    }

    #[Test]
    public function test_store_creates_new_feature()
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-features.store', $this->draft->id),
            [
                'locale' => 'en',
                'title' => 'Feature title',
                'description' => 'Feature description',
                'picture_id' => Picture::factory()->create()->id,
            ]
        );

        $response->assertCreated();

        $this->assertDatabaseHas(Translation::class, [
            'locale' => 'en',
            'text' => 'Feature title',
        ]);

        $this->assertDatabaseHas(Translation::class, [
            'locale' => 'en',
            'text' => 'Feature description',
        ]);

        $this->assertDatabaseHas(CreationDraftFeature::class, [
            'creation_draft_id' => $this->draft->id,
        ]);
    }

    #[Test]
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-features.store', $this->draft)
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'description',
                'locale',
            ]);
    }

    #[Test]
    public function test_show_returns_specific_feature()
    {
        $feature = CreationDraftFeature::factory()->create([
            'creation_draft_id' => $this->draft->id,
        ]);

        $response = $this->getJson(route('dashboard.api.draft-features.show', ['draft_feature' => $feature->id]));

        $response->assertOk()
            ->assertJsonPath('id', $feature->id)
            ->assertJsonPath('creation_draft_id', $this->draft->id);
    }

    #[Test]
    public function test_update_modifies_feature_picture()
    {
        $feature = CreationDraftFeature::factory()->create();
        $newPicture = Picture::factory()->create();

        $response = $this->putJson(route('dashboard.api.draft-features.update', $feature), [
            'picture_id' => $newPicture->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('picture_id', $newPicture->id);

        $this->assertDatabaseHas('creation_draft_features', [
            'id' => $feature->id,
            'picture_id' => $newPicture->id,
            'title_translation_key_id' => $feature->title_translation_key_id,
            'description_translation_key_id' => $feature->description_translation_key_id,
        ]);
    }

    #[Test]
    public function test_update_modifies_feature_texts()
    {
        $feature = CreationDraftFeature::factory()->create();
        $newTitle = 'New title';
        $newDescription = 'New description';

        $response = $this->putJson(route('dashboard.api.draft-features.update', $feature), [
            'locale' => 'en',
            'title' => $newTitle,
            'description' => $newDescription,
        ]);

        $response->assertOk()
            ->assertJsonPath('title_translation_key_id', $feature->title_translation_key_id)
            ->assertJsonPath('description_translation_key_id', $feature->description_translation_key_id);
    }

    #[Test]
    public function test_update_validates_required_fields()
    {
        $feature = CreationDraftFeature::factory()->create();

        $response = $this->putJson(route('dashboard.api.draft-features.update', $feature), [
            'title' => 'New title',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'locale',
            ]);
    }

    #[Test]
    public function test_destroy_deletes_feature()
    {
        $feature = CreationDraftFeature::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.draft-features.destroy', $feature));

        $response->assertNoContent();

        $this->assertDatabaseMissing('creation_draft_features', ['id' => $feature->id]);
    }

    #[Test]
    public function test_feature_belongs_to_correct_draft()
    {
        $otherDraft = CreationDraft::factory()->create();
        $feature = CreationDraftFeature::factory()->create([
            'creation_draft_id' => $this->draft->id,
        ]);

        $response = $this->get(route('dashboard.api.creation-drafts.draft-features.index', $otherDraft));
        $response->assertJsonCount(0);
    }

    #[Test]
    public function test_picture_relationship_validation()
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-features.store', $this->draft),
            [
                'picture_id' => 999,
                'title_translation_key_id' => TranslationKey::factory()->create()->id,
                'description_translation_key_id' => TranslationKey::factory()->create()->id,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picture_id']);
    }

    #[Test]
    public function test_translation_key_relationships()
    {
        $titleKey = TranslationKey::factory()->create();
        $descriptionKey = TranslationKey::factory()->create();

        $feature = CreationDraftFeature::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);

        $response = $this->getJson(route('dashboard.api.draft-features.show', $feature));

        $response->assertJson([
            'title_translation_key_id' => $titleKey->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);
    }
}
