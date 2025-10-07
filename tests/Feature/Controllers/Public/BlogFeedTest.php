<?php

namespace Tests\Feature\Controllers\Public;

use App\Models\BlogCategory;
use App\Models\BlogContentMarkdown;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogFeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the Atom feed is accessible and returns valid XML
     */
    public function test_feed_is_accessible_and_returns_valid_xml(): void
    {
        // Create a blog post with all necessary relationships
        $blogPost = $this->createBlogPost();

        $response = $this->get('/feed');

        $response->assertStatus(200);
        // Accept both application/atom+xml and application/xml as valid content types
        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'application/xml') || str_contains($contentType, 'application/atom+xml'),
            "Expected Atom XML content type, got: {$contentType}"
        );

        // Verify that the response is valid XML
        $xml = simplexml_load_string($response->content());
        $this->assertNotFalse($xml, 'Response is not valid XML');
    }

    /**
     * Test that the feed contains blog posts in the correct order
     */
    public function test_feed_contains_blog_posts_in_correct_order(): void
    {
        // Create multiple blog posts with different dates
        $oldPost = $this->createBlogPost('old-post', 'Old Post');
        $oldPost->created_at = now()->subDays(7);
        $oldPost->save();

        $newPost = $this->createBlogPost('new-post', 'New Post');
        $newPost->created_at = now()->subDay();
        $newPost->save();

        $latestPost = $this->createBlogPost('latest-post', 'Latest Post');
        $latestPost->created_at = now();
        $latestPost->save();

        $response = $this->get('/feed');

        $response->assertStatus(200);

        $xml = simplexml_load_string($response->content());
        $this->assertNotFalse($xml);

        // Get all entries from the Atom feed
        $entries = $xml->entry;
        $this->assertCount(3, $entries);

        // Verify order (newest first)
        $this->assertStringContainsString('Latest Post', (string) $entries[0]->title);
        $this->assertStringContainsString('New Post', (string) $entries[1]->title);
        $this->assertStringContainsString('Old Post', (string) $entries[2]->title);
    }

    /**
     * Test that the feed contains correct metadata
     */
    public function test_feed_contains_correct_metadata(): void
    {
        $blogPost = $this->createBlogPost();

        $response = $this->get('/feed');

        $xml = simplexml_load_string($response->content());

        // Verify feed metadata (Atom uses <title> and <subtitle> instead of channel)
        $this->assertStringContainsString('Blog', (string) $xml->title);
        $this->assertStringContainsString('articles de blog', (string) $xml->subtitle);
        $this->assertEquals('fr-FR', (string) $xml->attributes('xml', true)->lang);
    }

    /**
     * Test that feed items contain correct information
     */
    public function test_feed_items_contain_correct_information(): void
    {
        $blogPost = $this->createBlogPost('test-post', 'Test Post Title');

        $response = $this->get('/feed');

        $xml = simplexml_load_string($response->content());
        $entry = $xml->entry[0];

        // Verify entry contains required fields
        $this->assertNotEmpty((string) $entry->title);
        $this->assertStringContainsString('Test Post Title', (string) $entry->title);

        // In Atom, link is an attribute
        $link = $entry->link[0];
        $this->assertNotEmpty((string) $link['href']);
        $this->assertStringContainsString('/blog/articles/test-post', (string) $link['href']);

        $this->assertNotEmpty((string) $entry->summary);
        $this->assertNotEmpty((string) $entry->updated);
    }

    /**
     * Test that feed works with posts without cover images
     */
    public function test_feed_works_with_posts_without_cover_images(): void
    {
        $blogPost = $this->createBlogPost('post-no-image', 'Post Without Image', false);

        $response = $this->get('/feed');

        $response->assertStatus(200);

        $xml = simplexml_load_string($response->content());
        $this->assertNotFalse($xml);
        $this->assertCount(1, $xml->entry);
    }

    /**
     * Test that feed works when there are no blog posts
     */
    public function test_feed_works_with_no_posts(): void
    {
        $response = $this->get('/feed');

        $response->assertStatus(200);

        $xml = simplexml_load_string($response->content());
        $this->assertNotFalse($xml);
        $this->assertCount(0, $xml->entry);
    }

    /**
     * Test that feed limits to 50 most recent posts
     */
    public function test_feed_limits_to_50_posts(): void
    {
        // Create 60 blog posts
        for ($i = 1; $i <= 60; $i++) {
            $post = $this->createBlogPost("post-{$i}", "Post {$i}");
            $post->created_at = now()->subDays($i);
            $post->save();
        }

        $response = $this->get('/feed');

        $response->assertStatus(200);

        $xml = simplexml_load_string($response->content());
        $this->assertNotFalse($xml);
        $this->assertCount(50, $xml->entry);
    }

    /**
     * Helper method to create a blog post with all necessary relationships
     */
    private function createBlogPost(string $slug = 'test-post', string $title = 'Test Post', bool $withCoverImage = true): BlogPost
    {
        // Create category
        $categoryNameKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'fr',
            'text' => 'Test Category',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'en',
            'text' => 'Test Category',
        ]);

        $category = BlogCategory::factory()->create([
            'name_translation_key_id' => $categoryNameKey->id,
        ]);

        // Create title translation
        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => $title,
        ]);
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => $title,
        ]);

        // Create cover picture if needed
        $coverPicture = null;
        if ($withCoverImage) {
            $coverPicture = Picture::factory()->create();
        }

        // Create blog post
        $blogPost = BlogPost::factory()->create([
            'slug' => $slug,
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture?->id,
        ]);

        // Create content
        $contentKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $contentKey->id,
            'locale' => 'fr',
            'text' => 'Ceci est le contenu du test. Il contient suffisamment de texte pour tester l\'extraction d\'extrait du flux RSS.',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $contentKey->id,
            'locale' => 'en',
            'text' => 'This is the test content. It contains enough text to test the excerpt extraction for the RSS feed.',
        ]);

        $markdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $contentKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        return $blogPost->fresh([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents.content.translationKey.translations',
        ]);
    }
}
