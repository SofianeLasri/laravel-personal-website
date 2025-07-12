<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SetLocaleFromAcceptLanguage::class)]
class SetLocaleFromAcceptLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sets_french_locale_when_accept_language_is_french()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_when_french_is_preferred()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr;q=1.0,en;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_for_french_variants()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-CA,en;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_by_default_when_accept_language_is_english()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Now defaults to French even with English Accept-Language
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_by_default_when_accept_language_is_not_french()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'es-ES,es;q=0.9,en;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Now defaults to French for any non-French language
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_by_default_when_no_accept_language_header()
    {
        $request = Request::create('/', 'GET');
        // No Accept-Language header

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Now defaults to French when no header
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_by_default_when_accept_language_header_is_empty()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', '');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Now defaults to French when header is empty
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_respects_quality_values_when_french_has_higher_priority()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en;q=0.8,fr;q=0.9,de;q=0.7');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_by_default_when_english_has_higher_priority()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr;q=0.7,en;q=0.9,de;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Now defaults to French regardless of Accept-Language priority
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_handles_complex_accept_language_header()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7,de;q=0.6');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_handles_malformed_accept_language_header_gracefully()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'invalid-header-format');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Should default to French for any unrecognized format
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_returns_correct_response_after_processing()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-FR');

        $middleware = new SetLocaleFromAcceptLanguage;

        $response = $middleware->handle($request, function ($req) {
            return response('success', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $response->getContent());
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_sets_english_locale_when_language_preference_cookie_is_en()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9');
        $request->cookies->set('language_preference', 'en');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Cookie preference should override Accept-Language
        $this->assertEquals('en', App::getLocale());
    }

    #[Test]
    public function it_sets_french_locale_when_language_preference_cookie_is_fr()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
        $request->cookies->set('language_preference', 'fr');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Cookie preference should override Accept-Language
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_ignores_invalid_language_preference_cookie()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
        $request->cookies->set('language_preference', 'invalid');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            return response('test');
        });

        // Should default to French when cookie is invalid
        $this->assertEquals('fr', App::getLocale());
    }

    #[Test]
    public function it_stores_browser_language_in_request_attributes()
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9,de;q=0.8');

        $middleware = new SetLocaleFromAcceptLanguage;

        $middleware->handle($request, function ($req) {
            // Check that browser language is stored in request attributes
            $this->assertEquals('en', $req->attributes->get('browser_language'));

            return response('test');
        });

        $this->assertEquals('fr', App::getLocale());
    }
}
