<?php

namespace Tests\Feature\Controllers\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function set_language_accepts_valid_language()
    {
        $response = $this->post('/set-language', ['language' => 'en']);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertCookie('language_preference', 'en');
    }

    #[Test]
    public function set_language_accepts_french_language()
    {
        $response = $this->post('/set-language', ['language' => 'fr']);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertCookie('language_preference', 'fr');
    }

    #[Test]
    public function set_language_rejects_invalid_language()
    {
        $response = $this->post('/set-language', ['language' => 'invalid']);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid language']);
    }

    #[Test]
    public function set_language_requires_language_parameter()
    {
        $response = $this->post('/set-language', []);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid language']);
    }

    #[Test]
    public function set_language_cookie_has_correct_value()
    {
        $response = $this->post('/set-language', ['language' => 'en']);

        $response->assertStatus(200)
            ->assertCookie('language_preference', 'en');

        // Test that subsequent requests use the cookie value
        $followUpResponse = $this->withCookies([
            'language_preference' => 'en',
        ])->get('/');

        $followUpResponse->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }
}
