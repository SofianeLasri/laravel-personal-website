<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogMigrationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_blog_categories_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_categories'));

        $columns = Schema::getColumnListing('blog_categories');

        $this->assertContains('id', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('icon', $columns);
        $this->assertContains('color', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_posts_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_posts'));

        $columns = Schema::getColumnListing('blog_posts');

        $this->assertContains('id', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('category_id', $columns);
        $this->assertContains('cover_picture_id', $columns);
        $this->assertContains('published_at', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_post_drafts_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_post_drafts'));

        $columns = Schema::getColumnListing('blog_post_drafts');

        $this->assertContains('id', $columns);
        $this->assertContains('blog_post_id', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('category_id', $columns);
        $this->assertContains('cover_picture_id', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_post_contents_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_post_contents'));

        $columns = Schema::getColumnListing('blog_post_contents');

        $this->assertContains('id', $columns);
        $this->assertContains('blog_post_id', $columns);
        $this->assertContains('content_type', $columns);
        $this->assertContains('content_id', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_post_draft_contents_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_post_draft_contents'));

        $columns = Schema::getColumnListing('blog_post_draft_contents');

        $this->assertContains('id', $columns);
        $this->assertContains('blog_post_draft_id', $columns);
        $this->assertContains('content_type', $columns);
        $this->assertContains('content_id', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_content_markdown_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_content_markdown'));

        $columns = Schema::getColumnListing('blog_content_markdown');

        $this->assertContains('id', $columns);
        $this->assertContains('translation_key_id', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_content_galleries_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_content_galleries'));

        $columns = Schema::getColumnListing('blog_content_galleries');

        $this->assertContains('id', $columns);
        $this->assertContains('layout', $columns);
        $this->assertContains('columns', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_blog_content_gallery_pictures_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_content_gallery_pictures'));

        $columns = Schema::getColumnListing('blog_content_gallery_pictures');

        $this->assertContains('gallery_id', $columns);
        $this->assertContains('picture_id', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('caption_translation_key_id', $columns);
    }

    #[Test]
    public function it_creates_blog_content_videos_table(): void
    {
        $this->assertTrue(Schema::hasTable('blog_content_videos'));

        $columns = Schema::getColumnListing('blog_content_videos');

        $this->assertContains('id', $columns);
        $this->assertContains('video_id', $columns);
        $this->assertContains('caption_translation_key_id', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_game_reviews_table(): void
    {
        $this->assertTrue(Schema::hasTable('game_reviews'));

        $columns = Schema::getColumnListing('game_reviews');

        $this->assertContains('id', $columns);
        $this->assertContains('blog_post_id', $columns);
        $this->assertContains('game_title', $columns);
        $this->assertContains('release_date', $columns);
        $this->assertContains('genre', $columns);
        $this->assertContains('developer', $columns);
        $this->assertContains('publisher', $columns);
        $this->assertContains('platforms', $columns);
        $this->assertContains('cover_picture_id', $columns);
        $this->assertContains('pros_translation_key_id', $columns);
        $this->assertContains('cons_translation_key_id', $columns);
        $this->assertContains('score', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_game_review_drafts_table(): void
    {
        $this->assertTrue(Schema::hasTable('game_review_drafts'));

        $columns = Schema::getColumnListing('game_review_drafts');

        $this->assertContains('id', $columns);
        $this->assertContains('blog_post_draft_id', $columns);
        $this->assertContains('game_title', $columns);
        $this->assertContains('release_date', $columns);
        $this->assertContains('genre', $columns);
        $this->assertContains('developer', $columns);
        $this->assertContains('publisher', $columns);
        $this->assertContains('platforms', $columns);
        $this->assertContains('cover_picture_id', $columns);
        $this->assertContains('pros_translation_key_id', $columns);
        $this->assertContains('cons_translation_key_id', $columns);
        $this->assertContains('score', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_game_review_links_table(): void
    {
        $this->assertTrue(Schema::hasTable('game_review_links'));

        $columns = Schema::getColumnListing('game_review_links');

        $this->assertContains('id', $columns);
        $this->assertContains('game_review_id', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('url', $columns);
        $this->assertContains('label_translation_key_id', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    #[Test]
    public function it_creates_game_review_draft_links_table(): void
    {
        $this->assertTrue(Schema::hasTable('game_review_draft_links'));

        $columns = Schema::getColumnListing('game_review_draft_links');

        $this->assertContains('id', $columns);
        $this->assertContains('game_review_draft_id', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('url', $columns);
        $this->assertContains('label_translation_key_id', $columns);
        $this->assertContains('order', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }
}
