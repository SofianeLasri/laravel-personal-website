<?php

namespace Tests\Feature\Models\Tag;

use App\Http\Controllers\Admin\Api\TagController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(TagController::class)]
class TagControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_index()
    {
        Tag::factory()->count(5)->create();

        $response = $this->get(route('dashboard.api.tags.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(5);
    }

    #[Test]
    public function test_create()
    {
        $data = [
            'name' => 'New Tag',
        ];

        $response = $this->post(route('dashboard.api.tags.store'), $data);

        $response->assertCreated();
        $this->assertDatabaseHas('tags', [
            'name' => 'New Tag',
            'slug' => 'new-tag',
        ]);
    }

    #[Test]
    public function test_update()
    {
        $tag = Tag::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        $data = [
            'name' => 'New Name',
        ];

        $response = $this->put(route('dashboard.api.tags.update', $tag), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    #[Test]
    public function test_show()
    {
        $tag = Tag::factory()->create([
            'name' => 'Tag Name',
            'slug' => 'tag-name',
        ]);

        $response = $this->get(route('dashboard.api.tags.show', $tag));

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $tag->id,
            'name' => 'Tag Name',
            'slug' => 'tag-name',
        ]);
    }

    #[Test]
    public function test_delete()
    {
        $tag = Tag::factory()->create([
            'name' => 'Tag Name',
            'slug' => 'tag-name',
        ]);

        $response = $this->delete(route('dashboard.api.tags.destroy', $tag));

        $response->assertNoContent();
        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
            'name' => 'Tag Name',
            'slug' => 'tag-name',
        ]);
    }

    #[Test]
    public function test_check_associations()
    {
        $tag = Tag::factory()->create([
            'name' => 'Tag Name',
            'slug' => 'tag-name',
        ]);

        $creation = Creation::factory()->create();
        $creationDraft = CreationDraft::factory()->create();

        $tag->creations()->attach($creation);
        $tag->creationDrafts()->attach($creationDraft);

        $response = $this->get(route('dashboard.api.tags.check-associations', $tag));

        $response->assertStatus(200);
        $response->assertJson([
            'has_associations' => true,
            'creations_count' => 1,
            'creation_drafts_count' => 1,
        ]);
    }
}
