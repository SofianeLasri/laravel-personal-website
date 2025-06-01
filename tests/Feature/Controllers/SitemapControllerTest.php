<?php

namespace Tests\Feature\Controllers;

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
        $response->assertHeader('content-type', 'text/xml; charset=UTF-8');
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
}
