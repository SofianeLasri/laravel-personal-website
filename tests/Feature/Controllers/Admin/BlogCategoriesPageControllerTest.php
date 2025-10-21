<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\BlogCategoriesPageController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogCategoriesPageController::class)]
class BlogCategoriesPageControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_index_renders_correct_page()
    {
        $response = $this->get('/dashboard/blog-categories');

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard/blog-categories/List')
                ->has('blogCategories')
        );
    }

    #[Test]
    public function test_index_returns_categories_with_counts()
    {
        $category = BlogCategory::factory()->create();

        // Create some blog posts and drafts
        BlogPost::factory()->count(3)->create(['category_id' => $category->id]);
        BlogPostDraft::factory()->count(2)->create(['category_id' => $category->id]);

        $response = $this->get('/dashboard/blog-categories');

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard/blog-categories/List')
                ->has('blogCategories', 1)
                ->where('blogCategories.0.id', $category->id)
                ->where('blogCategories.0.blog_posts_count', 3)
                ->where('blogCategories.0.blog_post_drafts_count', 2)
        );
    }

    #[Test]
    public function test_index_returns_categories_with_translations()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->get('/dashboard/blog-categories');

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard/blog-categories/List')
                ->has('blogCategories', 1)
                ->has('blogCategories.0.name_translation_key')
                ->has('blogCategories.0.name_translation_key.translations')
        );
    }

    #[Test]
    public function test_index_orders_categories_by_order_column()
    {
        $category1 = BlogCategory::factory()->create(['order' => 3]);
        $category2 = BlogCategory::factory()->create(['order' => 1]);
        $category3 = BlogCategory::factory()->create(['order' => 2]);

        $response = $this->get('/dashboard/blog-categories');

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard/blog-categories/List')
                ->has('blogCategories', 3)
                ->where('blogCategories.0.id', $category2->id)
                ->where('blogCategories.1.id', $category3->id)
                ->where('blogCategories.2.id', $category1->id)
        );
    }

    #[Test]
    public function test_index_returns_empty_array_when_no_categories()
    {
        $response = $this->get('/dashboard/blog-categories');

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard/blog-categories/List')
                ->has('blogCategories', 0)
        );
    }

    #[Test]
    public function test_index_requires_authentication()
    {
        auth()->logout();

        $response = $this->get('/dashboard/blog-categories');

        $response->assertRedirect('/login');
    }
}
