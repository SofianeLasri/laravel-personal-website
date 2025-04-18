<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Controllers\Admin\CreationPageController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationPageController::class)]
class CreationPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    #[Test]
    public function test_list_page()
    {
        Creation::factory()->count(5)->create();

        $response = $this->get(route('dashboard.creations.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/creations/List')
                ->has('creations', 5)
        );
    }

    #[Test]
    public function test_list_draft_page()
    {
        CreationDraft::factory()->count(5)->create();

        $response = $this->get(route('dashboard.creations.drafts.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/creations/ListDrafts')
                ->has('creationDrafts', 5)
        );
    }

    #[Test]
    public function test_edit_page_without_parameters()
    {
        $response = $this->get(route('dashboard.creations.edit'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/creations/EditPage')
                ->has('creationDraft', null)
        );
    }

    #[Test]
    public function test_edit_page_with_draft_id()
    {
        $creationDraft = CreationDraft::factory()->create();

        $response = $this->get(route('dashboard.creations.edit', ['draft-id' => $creationDraft->id]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/creations/EditPage')
                ->has('creationDraft')
                ->where('creationDraft.id', $creationDraft->id)
        );
    }

    #[Test]
    public function test_edit_page_with_creation_id()
    {
        $creation = Creation::factory()->create();

        $response = $this->get(route('dashboard.creations.edit', ['creation-id' => $creation->id]));

        $response->assertOk();

        $creationDraft = CreationDraft::where('original_creation_id', $creation->id)->first();

        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/creations/EditPage')
                ->has('creationDraft')
                ->where('creationDraft.id', $creationDraft->id)
        );
    }
}
