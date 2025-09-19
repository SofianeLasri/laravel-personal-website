<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\BlogCategoryController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogCategoryController::class)]
class BlogCategoryControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_index_returns_categories_ordered_by_order_with_relations()
    {
        // Créer des catégories avec des ordres différents
        $category1 = BlogCategory::factory()->create(['order' => 2]);
        $category2 = BlogCategory::factory()->create(['order' => 1]);
        $category3 = BlogCategory::factory()->create(['order' => 3]);

        $response = $this->getJson('/dashboard/api/blog-categories');

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'slug',
                    'color',
                    'order',
                    'created_at',
                    'updated_at',
                    'name_translation_key' => [
                        'translations' => [
                            '*' => ['locale', 'text'],
                        ],
                    ],
                ],
            ]);

        // Vérifier l'ordre
        $data = $response->json();
        $this->assertEquals($category2->id, $data[0]['id']);
        $this->assertEquals($category1->id, $data[1]['id']);
        $this->assertEquals($category3->id, $data[2]['id']);
    }

    #[Test]
    public function test_index_returns_empty_array_when_no_categories()
    {
        $response = $this->getJson('/dashboard/api/blog-categories');

        $response->assertOk()
            ->assertJsonCount(0);
    }

    #[Test]
    public function test_store_creates_category_with_valid_data()
    {
        $data = [
            'slug' => 'test-category',
            'name_fr' => 'Catégorie Test',
            'name_en' => 'Test Category',
            'color' => 'red',
        ];

        $response = $this->postJson('/dashboard/api/blog-categories', $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'slug',
                'color',
                'order',
                'created_at',
                'updated_at',
                'name_translation_key' => [
                    'translations' => [
                        '*' => ['locale', 'text'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'test-category',
            'color' => 'red',
            'order' => 1,
        ]);

        $category = BlogCategory::where('slug', 'test-category')->first();
        $this->assertNotNull($category->nameTranslationKey);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'blog_category_test-category',
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Catégorie Test',
            'translation_key_id' => $category->name_translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'text' => 'Test Category',
            'translation_key_id' => $category->name_translation_key_id,
        ]);
    }

    #[Test]
    public function test_store_calculates_correct_order_when_categories_exist()
    {
        // Créer des catégories existantes
        BlogCategory::factory()->create(['order' => 5]);
        BlogCategory::factory()->create(['order' => 3]);

        $data = [
            'slug' => 'new-category',
            'name_fr' => 'Nouvelle Catégorie',
            'name_en' => 'New Category',
            'color' => 'blue',
        ];

        $response = $this->postJson('/dashboard/api/blog-categories', $data);

        $response->assertCreated();

        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'new-category',
            'order' => 6, // max(5, 3) + 1
        ]);
    }

    #[Test]
    public function test_store_fails_with_invalid_data()
    {
        $data = [
            'slug' => '', // Required
            'name_fr' => '', // Required
            'name_en' => '', // Required
            'color' => '', // Required
        ];

        $response = $this->postJson('/dashboard/api/blog-categories', $data);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'slug',
                    'name_fr',
                    'name_en',
                    'color',
                ],
            ]);
    }

    #[Test]
    public function test_store_fails_with_duplicate_slug()
    {
        $existingCategory = BlogCategory::factory()->create(['slug' => 'duplicate-slug']);

        $data = [
            'slug' => 'duplicate-slug',
            'name_fr' => 'Catégorie Test',
            'name_en' => 'Test Category',
            'color' => 'green',
        ];

        $response = $this->postJson('/dashboard/api/blog-categories', $data);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'slug',
                ],
            ]);
    }

    #[Test]
    public function test_show_returns_category_with_relations()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->getJson("/dashboard/api/blog-categories/{$category->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'slug',
                'color',
                'order',
                'created_at',
                'updated_at',
                'name_translation_key' => [
                    'translations' => [
                        '*' => ['locale', 'text'],
                    ],
                ],
            ])
            ->assertJson([
                'id' => $category->id,
                'slug' => $category->slug,
            ]);
    }

    #[Test]
    public function test_show_returns_404_for_nonexistent_category()
    {
        $response = $this->getJson('/dashboard/api/blog-categories/999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_modifies_category_with_valid_data()
    {
        $category = BlogCategory::factory()->create([
            'slug' => 'original-slug',
            'order' => 5,
        ]);

        $data = [
            'slug' => 'updated-slug',
            'name_fr' => 'Catégorie Mise à Jour',
            'name_en' => 'Updated Category',
            'color' => 'purple',
            'order' => 10,
        ];

        $response = $this->putJson("/dashboard/api/blog-categories/{$category->id}", $data);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'slug',
                'color',
                'order',
                'created_at',
                'updated_at',
                'name_translation_key' => [
                    'translations' => [
                        '*' => ['locale', 'text'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'slug' => 'updated-slug',
            'color' => 'purple',
            'order' => 10,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Catégorie Mise à Jour',
            'translation_key_id' => $category->name_translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'text' => 'Updated Category',
            'translation_key_id' => $category->name_translation_key_id,
        ]);
    }

    #[Test]
    public function test_update_preserves_order_when_not_provided()
    {
        $category = BlogCategory::factory()->create(['order' => 5]);

        $data = [
            'slug' => 'updated-slug',
            'name_fr' => 'Catégorie Mise à Jour',
            'name_en' => 'Updated Category',
            'color' => 'orange',
            // order not provided
        ];

        $response = $this->putJson("/dashboard/api/blog-categories/{$category->id}", $data);

        $response->assertOk();

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'order' => 5, // Should remain unchanged
        ]);
    }

    #[Test]
    public function test_update_allows_same_slug_for_same_category()
    {
        $category = BlogCategory::factory()->create(['slug' => 'original-slug']);

        $data = [
            'slug' => 'original-slug', // Same slug
            'name_fr' => 'Catégorie Mise à Jour',
            'name_en' => 'Updated Category',
            'color' => 'pink',
        ];

        $response = $this->putJson("/dashboard/api/blog-categories/{$category->id}", $data);

        $response->assertOk();
    }

    #[Test]
    public function test_update_fails_with_duplicate_slug_from_other_category()
    {
        $category1 = BlogCategory::factory()->create(['slug' => 'category-1']);
        $category2 = BlogCategory::factory()->create(['slug' => 'category-2']);

        $data = [
            'slug' => 'category-1', // Trying to use category1's slug
            'name_fr' => 'Catégorie Mise à Jour',
            'name_en' => 'Updated Category',
            'color' => 'yellow',
        ];

        $response = $this->putJson("/dashboard/api/blog-categories/{$category2->id}", $data);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'slug',
                ],
            ]);
    }

    #[Test]
    public function test_destroy_deletes_category_without_associations()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->deleteJson("/dashboard/api/blog-categories/{$category->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Category deleted successfully',
            ]);

        $this->assertDatabaseMissing('blog_categories', [
            'id' => $category->id,
        ]);

        // Vérifier que la clé de traduction et les traductions sont supprimées
        $this->assertDatabaseMissing('translation_keys', [
            'id' => $category->name_translation_key_id,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $category->name_translation_key_id,
        ]);
    }

    #[Test]
    public function test_destroy_fails_when_category_has_blog_posts()
    {
        $category = BlogCategory::factory()->create();
        $blogPost = BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/dashboard/api/blog-categories/{$category->id}");

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'posts_count',
                'drafts_count',
            ])
            ->assertJson([
                'posts_count' => 1,
                'drafts_count' => 0,
            ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function test_destroy_fails_when_category_has_blog_post_drafts()
    {
        $category = BlogCategory::factory()->create();
        $blogPostDraft = BlogPostDraft::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/dashboard/api/blog-categories/{$category->id}");

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'posts_count',
                'drafts_count',
            ])
            ->assertJson([
                'posts_count' => 0,
                'drafts_count' => 1,
            ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function test_reorder_updates_category_orders()
    {
        $category1 = BlogCategory::factory()->create(['order' => 1]);
        $category2 = BlogCategory::factory()->create(['order' => 2]);
        $category3 = BlogCategory::factory()->create(['order' => 3]);

        $data = [
            'categories' => [
                ['id' => $category3->id, 'order' => 0],
                ['id' => $category1->id, 'order' => 1],
                ['id' => $category2->id, 'order' => 2],
            ],
        ];

        $response = $this->postJson('/dashboard/api/blog-categories/reorder', $data);

        $response->assertOk()
            ->assertJson([
                'message' => 'Categories reordered successfully',
            ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category3->id,
            'order' => 0,
        ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category1->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category2->id,
            'order' => 2,
        ]);
    }

    #[Test]
    public function test_reorder_fails_with_invalid_data()
    {
        $data = [
            'categories' => [
                ['id' => 'invalid', 'order' => 0], // Invalid ID
                ['id' => 999, 'order' => -1], // Negative order
            ],
        ];

        $response = $this->postJson('/dashboard/api/blog-categories/reorder', $data);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    #[Test]
    public function test_reorder_fails_with_nonexistent_category_id()
    {
        $data = [
            'categories' => [
                ['id' => 999, 'order' => 0], // Nonexistent category
            ],
        ];

        $response = $this->postJson('/dashboard/api/blog-categories/reorder', $data);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'categories.0.id',
                ],
            ]);
    }
}
