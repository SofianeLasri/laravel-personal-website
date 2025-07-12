<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_route_uses_french_locale_when_accept_language_is_french()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ])->get('/');

        $response->assertStatus(200);

        // Check that the locale was set to French in the response
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function home_route_uses_french_locale_by_default_when_accept_language_is_english()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get('/');

        $response->assertStatus(200);

        // Check that the locale defaults to French even with English Accept-Language
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function home_route_uses_french_locale_by_default()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check that the locale defaults to French
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function projects_route_uses_correct_locale_from_accept_language()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'fr-CA',
        ])->get('/projects');

        $response->assertStatus(200);

        // Check that the locale was set to French
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function home_route_uses_english_locale_when_language_preference_cookie_is_set()
    {
        $response = $this->withCookies([
            'language_preference' => 'en',
        ])->get('/');

        $response->assertStatus(200);

        // Check that the locale was set to English from cookie
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'en')
        );
    }

    #[Test]
    public function home_route_uses_french_locale_when_language_preference_cookie_is_set()
    {
        $response = $this->withCookies([
            'language_preference' => 'fr',
        ])->get('/');

        $response->assertStatus(200);

        // Check that the locale was set to French from cookie
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function cookie_preference_overrides_accept_language_header()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->withCookies([
            'language_preference' => 'fr',
        ])->get('/');

        $response->assertStatus(200);

        // Check that the cookie preference overrides Accept-Language header
        $response->assertInertia(fn ($page) => $page->has('locale')
            ->where('locale', 'fr')
        );
    }

    #[Test]
    public function browser_language_is_passed_to_view_when_no_cookie_is_set()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9,de;q=0.8',
        ])->get('/');

        $response->assertStatus(200);

        // Check that browserLanguage is passed to the view
        $response->assertInertia(fn ($page) => $page->has('browserLanguage')
            ->where('browserLanguage', 'en')
        );
    }
}
