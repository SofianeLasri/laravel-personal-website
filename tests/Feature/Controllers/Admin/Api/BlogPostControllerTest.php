<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use App\Services\Conversion\BlogPost\DraftToBlogPostConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BlogCategory $category;

    private BlogPostDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create blog category with translation
        $categoryNameKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'en',
            'text' => 'Technology',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'fr',
            'text' => 'Technologie',
        ]);

        $this->category = BlogCategory::factory()->create([
            'slug' => 'technology',
            'name_translation_key_id' => $categoryNameKey->id,
            'color' => CategoryColor::BLUE,
            'order' => 1,
        ]);

        // Create blog post draft with translation
        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'My First Blog Post',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Mon Premier Article de Blog',
        ]);

        $coverPicture = Picture::factory()->create();

        $this->draft = BlogPostDraft::factory()->create([
            'slug' => 'my-first-blog-post',
            'title_translation_key_id' => $titleKey->id,
            'type' => BlogPostType::ARTICLE,
            'category_id' => $this->category->id,
            'cover_picture_id' => $coverPicture->id,
        ]);
    }

    #[Test]
    public function index_returns_all_blog_posts(): void
    {
        // Create some blog posts
        $blogPost1 = BlogPost::factory()->create();
        $blogPost2 = BlogPost::factory()->create();

        $response = $this->getJson('/dashboard/api/blog-posts');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'slug',
                    'title_translation_key_id',
                    'type',
                    'category_id',
                    'cover_picture_id',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function index_returns_empty_array_when_no_blog_posts(): void
    {
        $response = $this->getJson('/dashboard/api/blog-posts');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    #[Test]
    public function store_validates_draft_id_required(): void
    {
        $response = $this->postJson('/dashboard/api/blog-posts', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'draft_id' => ['Le champ draft id est obligatoire.'],
                ],
            ]);
    }

    #[Test]
    public function store_validates_draft_id_is_integer(): void
    {
        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => 'not_an_integer',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'draft_id' => ['Le champ draft id doit être un entier.'],
                ],
            ]);
    }

    #[Test]
    public function store_validates_draft_id_exists(): void
    {
        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => 9999,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'draft_id' => ['La valeur sélectionnée pour draft id est invalide.'],
                ],
            ]);
    }

    #[Test]
    public function store_converts_draft_successfully(): void
    {
        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => $this->draft->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Blog post published successfully',
            ])
            ->assertJsonStructure([
                'message',
                'blog_post' => [
                    'id',
                    'slug',
                    'title_translation_key_id',
                    'type',
                    'category_id',
                    'cover_picture_id',
                    'created_at',
                    'updated_at',
                    'title_translation_key',
                    'category',
                    'cover_picture',
                    'contents',
                ],
            ]);

        $this->assertDatabaseHas('blog_posts', [
            'slug' => $this->draft->slug,
            'title_translation_key_id' => $this->draft->title_translation_key_id,
            'type' => $this->draft->type->value,
            'category_id' => $this->draft->category_id,
            'cover_picture_id' => $this->draft->cover_picture_id,
        ]);
    }

    #[Test]
    public function store_loads_correct_relationships(): void
    {
        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => $this->draft->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'blog_post' => [
                    'title_translation_key' => [
                        'id',
                        'translations' => [
                            '*' => [
                                'id',
                                'locale',
                                'text',
                            ],
                        ],
                    ],
                    'category' => [
                        'id',
                        'slug',
                        'color',
                    ],
                    'cover_picture' => [
                        'id',
                        'filename',
                    ],
                    'contents',
                ],
            ]);
    }

    #[Test]
    public function store_handles_conversion_service_exceptions(): void
    {
        // Mock the service to throw an exception
        $this->mock(DraftToBlogPostConverter::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->once()
                ->andThrow(new \Exception('Conversion failed'));
        });

        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => $this->draft->id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Failed to publish blog post',
                'error' => 'Conversion failed',
            ]);
    }

    #[Test]
    public function store_handles_draft_not_found(): void
    {
        // Create a draft that exists initially but will be deleted
        $anotherDraft = BlogPostDraft::factory()->create();
        $draftId = $anotherDraft->id;

        // Delete the draft after creating it but before the request
        $anotherDraft->delete();

        $response = $this->postJson('/dashboard/api/blog-posts', [
            'draft_id' => $draftId,
        ]);

        // This should now return 422 because validation fails
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'draft_id' => ['La valeur sélectionnée pour draft id est invalide.'],
                ],
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
