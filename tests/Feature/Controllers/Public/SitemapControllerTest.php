<?php

namespace Tests\Feature\Controllers\Public;

use App\Models\BlogPost;
use App\Models\Creation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SitemapControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sitemap_is_accessible()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        // Use case-insensitive comparison as charset case varies by environment
        $contentType = strtolower($response->headers->get('content-type'));
        $this->assertEquals('text/xml; charset=utf-8', $contentType);
    }

    #[Test]
    public function sitemap_contains_public_routes()
    {
        $response = $this->get('/sitemap.xml');

        $content = $response->getContent();

        // Check that main public routes are included
        $this->assertStringContainsString(route('public.home'), $content);
        $this->assertStringContainsString(route('public.projects'), $content);
        $this->assertStringContainsString(route('public.certifications-career'), $content);
    }

    #[Test]
    public function sitemap_includes_project_pages()
    {
        $creation = Creation::factory()->create(['slug' => 'test-project']);

        $response = $this->get('/sitemap.xml');

        $content = $response->getContent();
        $this->assertStringContainsString(route('public.projects.show', 'test-project'), $content);
    }

    #[Test]
    public function sitemap_does_not_include_dashboard_routes()
    {
        $response = $this->get('/sitemap.xml');

        $content = $response->getContent();

        // Ensure dashboard routes are not included
        $this->assertStringNotContainsString('/dashboard', $content);
        $this->assertStringNotContainsString('/login', $content);
        $this->assertStringNotContainsString('/register', $content);
    }

    #[Test]
    public function sitemap_has_proper_xml_structure()
    {
        $response = $this->get('/sitemap.xml');

        $content = $response->getContent();

        // Check XML structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $content);
        $this->assertStringContainsString('<url>', $content);
        $this->assertStringContainsString('<loc>', $content);
        $this->assertStringContainsString('<lastmod>', $content);
        $this->assertStringContainsString('<changefreq>', $content);
        $this->assertStringContainsString('<priority>', $content);
        $this->assertStringContainsString('</urlset>', $content);
    }

    #[Test]
    public function sitemap_includes_blog_routes_when_blog_posts_exist()
    {
        // Create a blog post
        $blogPost = BlogPost::factory()->create(['slug' => 'test-blog-post']);

        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        // Check that blog routes are included
        $this->assertStringContainsString(route('public.blog.home'), $content);
        $this->assertStringContainsString(route('public.blog.index'), $content);
        $this->assertStringContainsString(route('public.blog.post', 'test-blog-post'), $content);
    }

    #[Test]
    public function sitemap_excludes_blog_routes_when_no_blog_posts_exist()
    {
        // Ensure no blog posts exist
        BlogPost::query()->delete();

        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        // Check that blog routes are NOT included
        $this->assertStringNotContainsString('/blog', $content);
    }

    #[Test]
    public function sitemap_includes_multiple_blog_posts()
    {
        // Create multiple blog posts
        $blogPost1 = BlogPost::factory()->create(['slug' => 'first-blog-post']);
        $blogPost2 = BlogPost::factory()->create(['slug' => 'second-blog-post']);
        $blogPost3 = BlogPost::factory()->create(['slug' => 'third-blog-post']);

        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        // Check that all blog posts are included
        $this->assertStringContainsString(route('public.blog.post', 'first-blog-post'), $content);
        $this->assertStringContainsString(route('public.blog.post', 'second-blog-post'), $content);
        $this->assertStringContainsString(route('public.blog.post', 'third-blog-post'), $content);
    }
}
