<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\CategoryColor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogCategory::class)]
class BlogCategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'slug',
            'name_translation_key_id',
            'color',
            'order',
        ];

        $blogCategory = new BlogCategory();

        $this->assertEquals($fillable, $blogCategory->getFillable());
    }

    #[Test]
    public function it_has_correct_casts(): void
    {
        $blogCategory = new BlogCategory();
        $casts = $blogCategory->getCasts();

        $this->assertEquals('int', $casts['id']);
        $this->assertEquals('string', $casts['slug']);
        $this->assertEquals(CategoryColor::class, $casts['color']);
        $this->assertEquals('integer', $casts['order']);
    }

    #[Test]
    public function it_belongs_to_a_name_translation_key(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $blogCategory = BlogCategory::factory()->create([
            'name_translation_key_id' => $translationKey->id,
        ]);

        $this->assertInstanceOf(TranslationKey::class, $blogCategory->nameTranslationKey);
        $this->assertEquals($translationKey->id, $blogCategory->nameTranslationKey->id);
    }

    #[Test]
    public function it_has_name_translation_key_relationship_defined(): void
    {
        $blogCategory = new BlogCategory();
        $relation = $blogCategory->nameTranslationKey();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('name_translation_key_id', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getOwnerKeyName());
    }

    #[Test]
    public function it_has_many_blog_posts(): void
    {
        $category = BlogCategory::factory()->create();
        $post1 = BlogPost::factory()->create(['category_id' => $category->id]);
        $post2 = BlogPost::factory()->create(['category_id' => $category->id]);
        BlogPost::factory()->create(); // Un autre post avec une catégorie différente

        $posts = $category->blogPosts;

        $this->assertCount(2, $posts);
        $this->assertTrue($posts->contains($post1));
        $this->assertTrue($posts->contains($post2));
    }

    #[Test]
    public function it_has_blog_posts_relationship_defined(): void
    {
        $blogCategory = new BlogCategory();
        $relation = $blogCategory->blogPosts();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('category_id', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getLocalKeyName());
    }

    #[Test]
    public function it_casts_color_attribute_to_category_color_enum(): void
    {
        $category = BlogCategory::factory()->create([
            'color' => CategoryColor::BLUE->value,
        ]);

        $this->assertInstanceOf(CategoryColor::class, $category->color);
        $this->assertEquals(CategoryColor::BLUE, $category->color);
        $this->assertEquals('blue', $category->color->value);
    }

    #[Test]
    public function it_can_set_color_using_enum(): void
    {
        $category = BlogCategory::factory()->create();
        $category->color = CategoryColor::RED;
        $category->save();

        $category->refresh();

        $this->assertEquals(CategoryColor::RED, $category->color);
        $this->assertEquals('red', $category->color->value);
    }

    #[Test]
    public function it_can_set_color_using_string(): void
    {
        $category = BlogCategory::factory()->create();
        $category->color = 'green';
        $category->save();

        $category->refresh();

        $this->assertEquals(CategoryColor::GREEN, $category->color);
        $this->assertEquals('green', $category->color->value);
    }

    #[Test]
    public function it_casts_order_to_integer(): void
    {
        $category = BlogCategory::factory()->create([
            'order' => '42',
        ]);

        $this->assertIsInt($category->order);
        $this->assertEquals(42, $category->order);
    }

    #[Test]
    public function it_casts_slug_to_string(): void
    {
        $category = BlogCategory::factory()->create([
            'slug' => 'test-category',
        ]);

        $this->assertIsString($category->slug);
        $this->assertEquals('test-category', $category->slug);
    }

    #[Test]
    public function it_can_create_category_with_all_attributes(): void
    {
        $translationKey = TranslationKey::factory()->create();

        $category = BlogCategory::create([
            'slug' => 'new-category',
            'name_translation_key_id' => $translationKey->id,
            'color' => CategoryColor::PURPLE->value,
            'order' => 10,
        ]);

        $this->assertInstanceOf(BlogCategory::class, $category);
        $this->assertEquals('new-category', $category->slug);
        $this->assertEquals($translationKey->id, $category->name_translation_key_id);
        $this->assertEquals(CategoryColor::PURPLE, $category->color);
        $this->assertEquals(10, $category->order);
    }

    #[Test]
    public function it_can_update_category_attributes(): void
    {
        $category = BlogCategory::factory()->create([
            'slug' => 'old-slug',
            'color' => CategoryColor::YELLOW->value,
            'order' => 5,
        ]);

        $category->update([
            'slug' => 'new-slug',
            'color' => CategoryColor::PINK->value,
            'order' => 15,
        ]);

        $category->refresh();

        $this->assertEquals('new-slug', $category->slug);
        $this->assertEquals(CategoryColor::PINK, $category->color);
        $this->assertEquals(15, $category->order);
    }

    #[Test]
    public function it_can_count_related_blog_posts(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->count(3)->create(['category_id' => $category->id]);

        $categoryWithCount = BlogCategory::withCount('blogPosts')->find($category->id);

        $this->assertEquals(3, $categoryWithCount->blog_posts_count);
    }

    #[Test]
    public function it_eager_loads_name_translation_key(): void
    {
        $category = BlogCategory::factory()->create();

        $loadedCategory = BlogCategory::with('nameTranslationKey')->find($category->id);

        $this->assertTrue($loadedCategory->relationLoaded('nameTranslationKey'));
        $this->assertInstanceOf(TranslationKey::class, $loadedCategory->nameTranslationKey);
    }

    #[Test]
    public function it_eager_loads_blog_posts(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->count(2)->create(['category_id' => $category->id]);

        $loadedCategory = BlogCategory::with('blogPosts')->find($category->id);

        $this->assertTrue($loadedCategory->relationLoaded('blogPosts'));
        $this->assertCount(2, $loadedCategory->blogPosts);
    }

    #[Test]
    public function it_orders_categories_by_order_field(): void
    {
        $category3 = BlogCategory::factory()->create(['order' => 30]);
        $category1 = BlogCategory::factory()->create(['order' => 10]);
        $category2 = BlogCategory::factory()->create(['order' => 20]);

        $categories = BlogCategory::orderBy('order')->get();

        $this->assertEquals($category1->id, $categories[0]->id);
        $this->assertEquals($category2->id, $categories[1]->id);
        $this->assertEquals($category3->id, $categories[2]->id);
    }

    #[Test]
    public function it_can_delete_category_without_affecting_translation_key(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $category = BlogCategory::factory()->create([
            'name_translation_key_id' => $translationKey->id,
        ]);

        $categoryId = $category->id;
        $category->delete();

        $this->assertDatabaseMissing('blog_categories', ['id' => $categoryId]);
        $this->assertDatabaseHas('translation_keys', ['id' => $translationKey->id]);
    }

    #[Test]
    public function it_uses_correct_table_name(): void
    {
        $category = new BlogCategory();

        $this->assertEquals('blog_categories', $category->getTable());
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $category = BlogCategory::factory()->create();

        $this->assertNotNull($category->created_at);
        $this->assertNotNull($category->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $category->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $category->updated_at);
    }

    #[Test]
    public function it_can_access_all_color_enum_values(): void
    {
        $colors = CategoryColor::cases();

        $this->assertCount(8, $colors);
        $this->assertContains(CategoryColor::RED, $colors);
        $this->assertContains(CategoryColor::BLUE, $colors);
        $this->assertContains(CategoryColor::GREEN, $colors);
        $this->assertContains(CategoryColor::YELLOW, $colors);
        $this->assertContains(CategoryColor::PURPLE, $colors);
        $this->assertContains(CategoryColor::PINK, $colors);
        $this->assertContains(CategoryColor::ORANGE, $colors);
        $this->assertContains(CategoryColor::GRAY, $colors);
    }

    #[Test]
    public function it_can_mass_assign_all_fillable_attributes(): void
    {
        $translationKey = TranslationKey::factory()->create();

        $data = [
            'slug' => 'mass-assigned-slug',
            'name_translation_key_id' => $translationKey->id,
            'color' => CategoryColor::ORANGE->value,
            'order' => 99,
        ];

        $category = BlogCategory::create($data);

        $this->assertEquals('mass-assigned-slug', $category->slug);
        $this->assertEquals($translationKey->id, $category->name_translation_key_id);
        $this->assertEquals(CategoryColor::ORANGE, $category->color);
        $this->assertEquals(99, $category->order);
    }

    #[Test]
    public function it_can_be_converted_to_array(): void
    {
        $category = BlogCategory::factory()->create();
        $array = $category->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('name_translation_key_id', $array);
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('order', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function it_can_be_converted_to_json(): void
    {
        $category = BlogCategory::factory()->create([
            'slug' => 'json-test',
            'color' => CategoryColor::GREEN->value,
            'order' => 42,
        ]);

        $json = $category->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('json-test', $decoded['slug']);
        $this->assertEquals('green', $decoded['color']);
        $this->assertEquals(42, $decoded['order']);
    }

    #[Test]
    public function it_uses_has_factory_trait(): void
    {
        $this->assertContains(
            'Illuminate\Database\Eloquent\Factories\HasFactory',
            class_uses_recursive(BlogCategory::class)
        );

        $factory = BlogCategory::factory();
        $this->assertInstanceOf(\Database\Factories\BlogCategoryFactory::class, $factory);
    }

    #[Test]
    public function it_can_find_category_by_slug(): void
    {
        $category = BlogCategory::factory()->create(['slug' => 'unique-slug']);

        $foundCategory = BlogCategory::where('slug', 'unique-slug')->first();

        $this->assertNotNull($foundCategory);
        $this->assertEquals($category->id, $foundCategory->id);
    }

    #[Test]
    public function it_can_filter_categories_by_color(): void
    {
        BlogCategory::factory()->create(['color' => CategoryColor::RED->value]);
        BlogCategory::factory()->create(['color' => CategoryColor::BLUE->value]);
        $greenCategory = BlogCategory::factory()->create(['color' => CategoryColor::GREEN->value]);

        $greenCategories = BlogCategory::where('color', CategoryColor::GREEN->value)->get();

        $this->assertCount(1, $greenCategories);
        $this->assertEquals($greenCategory->id, $greenCategories->first()->id);
    }
}