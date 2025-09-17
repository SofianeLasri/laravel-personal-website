<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_title_translation_key(): void
    {
        $blogPost = BlogPost::factory()->create();

        $this->assertNotNull($blogPost->titleTranslationKey);
        $this->assertEquals($blogPost->title_translation_key_id, $blogPost->titleTranslationKey->id);
    }

    #[Test]
    public function it_belongs_to_a_category(): void
    {
        $category = BlogCategory::factory()->create();
        $blogPost = BlogPost::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(BlogCategory::class, $blogPost->category);
        $this->assertEquals($category->id, $blogPost->category->id);
    }

    #[Test]
    public function it_belongs_to_a_cover_picture(): void
    {
        $picture = Picture::factory()->create();
        $blogPost = BlogPost::factory()->create(['cover_picture_id' => $picture->id]);

        $this->assertInstanceOf(Picture::class, $blogPost->coverPicture);
        $this->assertEquals($picture->id, $blogPost->coverPicture->id);
    }

    #[Test]
    public function it_has_many_contents(): void
    {
        $blogPost = BlogPost::factory()->create();
        $content1 = BlogPostContent::factory()->create(['blog_post_id' => $blogPost->id, 'order' => 1]);
        $content2 = BlogPostContent::factory()->create(['blog_post_id' => $blogPost->id, 'order' => 2]);

        $this->assertCount(2, $blogPost->contents);
        $this->assertEquals($content1->id, $blogPost->contents->first()->id);
        $this->assertEquals($content2->id, $blogPost->contents->last()->id);
    }

    #[Test]
    public function it_has_one_draft(): void
    {
        $blogPost = BlogPost::factory()->create();
        $draft = BlogPostDraft::factory()->create(['original_blog_post_id' => $blogPost->id]);

        $this->assertInstanceOf(BlogPostDraft::class, $blogPost->draft);
        $this->assertEquals($draft->id, $blogPost->draft->id);
    }

    #[Test]
    public function it_has_one_game_review_when_type_is_game_review(): void
    {
        $blogPost = BlogPost::factory()->create(['type' => 'game_review']);
        $gameReview = GameReview::factory()->create(['blog_post_id' => $blogPost->id]);

        $this->assertInstanceOf(GameReview::class, $blogPost->gameReview);
        $this->assertEquals($gameReview->id, $blogPost->gameReview->id);
    }

    #[Test]
    public function it_scopes_by_category(): void
    {
        $category1 = BlogCategory::factory()->create();
        $category2 = BlogCategory::factory()->create();

        $post1 = BlogPost::factory()->create(['category_id' => $category1->id]);
        BlogPost::factory()->create(['category_id' => $category2->id]);

        $results = BlogPost::byCategory($category1->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($post1->id, $results->first()->id);
    }

    #[Test]
    public function it_scopes_by_type(): void
    {
        $article = BlogPost::factory()->create(['type' => 'article']);
        BlogPost::factory()->create(['type' => 'game_review']);

        $results = BlogPost::byType('article')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($article->id, $results->first()->id);
    }

    #[Test]
    public function it_orders_contents_by_order_field(): void
    {
        $blogPost = BlogPost::factory()->create();
        $content3 = BlogPostContent::factory()->create(['blog_post_id' => $blogPost->id, 'order' => 3]);
        $content1 = BlogPostContent::factory()->create(['blog_post_id' => $blogPost->id, 'order' => 1]);
        $content2 = BlogPostContent::factory()->create(['blog_post_id' => $blogPost->id, 'order' => 2]);

        $contents = $blogPost->contents;

        $this->assertEquals($content1->id, $contents[0]->id);
        $this->assertEquals($content2->id, $contents[1]->id);
        $this->assertEquals($content3->id, $contents[2]->id);
    }
}
