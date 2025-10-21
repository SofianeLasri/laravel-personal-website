<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\PreventIndexing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PreventIndexing::class)]
class PreventIndexingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_adds_x_robots_tag_header(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('Test content');
        });

        $this->assertTrue($response->headers->has('X-Robots-Tag'));
        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function it_does_not_modify_response_content(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');
        $expectedContent = 'Original content';

        $response = $middleware->handle($request, function ($req) use ($expectedContent) {
            return new Response($expectedContent);
        });

        $this->assertEquals($expectedContent, $response->getContent());
    }

    #[Test]
    public function it_preserves_response_status_code(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('Test content', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_works_with_different_http_methods(): void
    {
        $middleware = new PreventIndexing;

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/test', $method);

            $response = $middleware->handle($request, function ($req) {
                return new Response('Test content');
            });

            $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        }
    }

    #[Test]
    public function it_preserves_existing_headers(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            $response = new Response('Test content');
            $response->headers->set('Content-Type', 'text/html');
            $response->headers->set('X-Custom-Header', 'custom-value');

            return $response;
        });

        $this->assertEquals('text/html', $response->headers->get('Content-Type'));
        $this->assertEquals('custom-value', $response->headers->get('X-Custom-Header'));
        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function it_works_with_json_response(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'Test JSON']);
        });

        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        $this->assertJson($response->getContent());
    }

    #[Test]
    public function it_works_with_redirect_response(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return redirect('/redirect-target');
        });

        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    #[Test]
    public function it_works_with_404_response(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('Not found', 404);
        });

        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function it_works_with_500_response(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('Server error', 500);
        });

        $this->assertEquals('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals(500, $response->getStatusCode());
    }

    #[Test]
    public function it_sets_all_three_directives(): void
    {
        $middleware = new PreventIndexing;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('Test content');
        });

        $header = $response->headers->get('X-Robots-Tag');

        $this->assertStringContainsString('noindex', $header);
        $this->assertStringContainsString('nofollow', $header);
        $this->assertStringContainsString('noarchive', $header);
    }

    #[Test]
    public function it_does_not_modify_request(): void
    {
        $middleware = new PreventIndexing;
        $originalUri = '/test-uri';
        $request = Request::create($originalUri, 'GET');

        $response = $middleware->handle($request, function ($req) use ($originalUri) {
            // Verify request wasn't modified
            TestCase::assertEquals($originalUri, $req->getRequestUri());

            return new Response('Test content');
        });

        $this->assertInstanceOf(Response::class, $response);
    }
}
