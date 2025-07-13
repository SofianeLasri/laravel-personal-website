<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Inertia404Test extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_renders_correctly(): void
    {
        $response = $this->get('/non-existent-page');

        $response->assertStatus(404);
        $response->assertInertia(function ($page) {
            $page->component('public/Error404')
                ->has('locale')
                ->has('translations.errors')
                ->has('socialMediaLinks');
        });
    }

    public function test_404_page_has_correct_translations(): void
    {
        $response = $this->get('/non-existent-page');

        $response->assertStatus(404);
        $response->assertInertia(function ($page) {
            $page->component('public/Error404')
                ->where('translations.errors.404.page_name', 'Page introuvable')
                ->where('translations.errors.404.home_page_link', 'Page d\'accueil');
        });
    }

    public function test_dashboard_404_does_not_use_custom_handler(): void
    {
        $response = $this->get('/dashboard/non-existent');

        // Should get default Laravel 404, not our custom page
        $response->assertStatus(404);
        // This should not be an Inertia response since we exclude dashboard routes
        $this->assertNotInstanceOf(\Inertia\Response::class, $response->baseResponse);
    }
}
