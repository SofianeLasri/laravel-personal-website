<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Dashboard;

use App\Models\BlogCategory;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_lists_all_categories(): void
    {
        BlogCategory::factory()->count(3)->create();

        $response = $this->getJson('/dashboard/api/blog/categories');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'slug',
                    'name',
                    'icon',
                    'color',
                    'order',
                    'posts_count',
                ],
            ],
        ]);
    }

    #[Test]
    public function it_shows_a_single_category(): void
    {
        $category = BlogCategory::factory()->create();

        $response = $this->getJson("/dashboard/api/blog/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $category->id,
            'slug' => $category->slug,
        ]);
    }

    #[Test]
    public function it_creates_a_new_category(): void
    {
        $nameKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'slug' => 'test-category',
            'name_translation_key_id' => $nameKey->id,
            'icon' => 'fas fa-gamepad',
            'color' => '#FF5733',
            'order' => 1,
        ];

        $response = $this->postJson('/dashboard/api/blog/categories', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'test-category',
            'icon' => 'fas fa-gamepad',
            'color' => '#FF5733',
            'order' => 1,
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating(): void
    {
        $response = $this->postJson('/dashboard/api/blog/categories', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug', 'name_translation_key_id']);
    }

    #[Test]
    public function it_validates_unique_slug(): void
    {
        $existingCategory = BlogCategory::factory()->create(['slug' => 'existing-slug']);
        $nameKey = TranslationKey::factory()->withTranslations()->create();

        $response = $this->postJson('/dashboard/api/blog/categories', [
            'slug' => 'existing-slug',
            'name_translation_key_id' => $nameKey->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_updates_a_category(): void
    {
        $category = BlogCategory::factory()->create([
            'slug' => 'old-slug',
            'order' => 1,
        ]);

        $response = $this->putJson("/dashboard/api/blog/categories/{$category->id}", [
            'slug' => 'new-slug',
            'icon' => 'fas fa-code',
            'color' => '#00FF00',
            'order' => 5,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'slug' => 'new-slug',
            'icon' => 'fas fa-code',
            'color' => '#00FF00',
            'order' => 5,
        ]);
    }

    #[Test]
    public function it_deletes_a_category_without_posts(): void
    {
        $category = BlogCategory::factory()->create();

        $response = $this->deleteJson("/dashboard/api/blog/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
    }

    #[Test]
    public function it_prevents_deleting_category_with_posts(): void
    {
        $category = BlogCategory::factory()->create();
        \App\Models\BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/dashboard/api/blog/categories/{$category->id}");

        $response->assertStatus(409);
        $response->assertJson(['message' => 'Cannot delete category with existing posts']);
        $this->assertDatabaseHas('blog_categories', ['id' => $category->id]);
    }

    #[Test]
    public function it_reorders_categories(): void
    {
        $category1 = BlogCategory::factory()->create(['order' => 1]);
        $category2 = BlogCategory::factory()->create(['order' => 2]);
        $category3 = BlogCategory::factory()->create(['order' => 3]);

        $response = $this->postJson('/dashboard/api/blog/categories/reorder', [
            'order' => [$category3->id, $category1->id, $category2->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('blog_categories', ['id' => $category3->id, 'order' => 1]);
        $this->assertDatabaseHas('blog_categories', ['id' => $category1->id, 'order' => 2]);
        $this->assertDatabaseHas('blog_categories', ['id' => $category2->id, 'order' => 3]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/dashboard/api/blog/categories');

        $response->assertUnauthorized();
    }
}
