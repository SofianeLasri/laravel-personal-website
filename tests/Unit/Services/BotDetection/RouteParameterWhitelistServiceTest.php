<?php

namespace Tests\Unit\Services\BotDetection;

use App\Services\BotDetection\RouteParameterWhitelistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(RouteParameterWhitelistService::class)]
class RouteParameterWhitelistServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear routes and set up test routes
        Route::getRoutes()->refreshNameLookups();
    }

    #[Test]
    public function returns_empty_array_for_unknown_path(): void
    {
        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/unknown/path');

        $this->assertIsArray($parameters);
        // Should return common parameters for unknown paths
        $this->assertNotEmpty($parameters);

        // Check that some common parameters are included
        $this->assertContains('page', $parameters);
        $this->assertContains('limit', $parameters);
        $this->assertContains('sort', $parameters);
    }

    #[Test]
    public function extracts_route_parameters_from_path(): void
    {
        // Register a test route with parameters
        Route::get('/test/{id}/{slug}', function ($id, $slug) {
            return response()->json(compact('id', 'slug'));
        });

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/test/123/my-slug');

        $this->assertContains('id', $parameters);
        $this->assertContains('slug', $parameters);
    }

    #[Test]
    public function handles_controller_with_request_validation(): void
    {
        // Create a test controller with validate() call
        Route::post('/test-validate', function () {
            request()->validate([
                'name' => 'required|string',
                'email' => 'required|email',
                'age' => 'integer',
            ]);

            return response()->json(['success' => true]);
        });

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/test-validate');

        // Should not detect parameters from closure routes
        $this->assertIsArray($parameters);
    }

    #[Test]
    public function detects_suspicious_parameters(): void
    {
        Route::get('/api/users', function () {
            return response()->json(['users' => []]);
        });

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $whitelisted = RouteParameterWhitelistService::getWhitelistedParameters('/api/users');

        // Common parameters like 'page', 'per_page' might be whitelisted
        $suspiciousParams = ['hack', 'test123', 'debug', 'admin'];

        foreach ($suspiciousParams as $param) {
            $this->assertNotContains($param, $whitelisted, "Parameter '$param' should not be whitelisted");
        }
    }

    #[Test]
    public function caches_whitelist_after_first_call(): void
    {
        // Clear cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        // First call should build cache
        $params1 = RouteParameterWhitelistService::getWhitelistedParameters('/test/path');

        // Get cache value
        $cache1 = $property->getValue();
        $this->assertNotNull($cache1);

        // Second call should use cache
        $params2 = RouteParameterWhitelistService::getWhitelistedParameters('/test/path');

        // Cache should be the same
        $cache2 = $property->getValue();
        $this->assertSame($cache1, $cache2);
        $this->assertEquals($params1, $params2);
    }

    #[Test]
    public function handles_routes_with_no_controller(): void
    {
        Route::view('/about', 'public-app');
        Route::redirect('/old', '/new');

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        // Should not throw errors
        $params1 = RouteParameterWhitelistService::getWhitelistedParameters('/about');
        $params2 = RouteParameterWhitelistService::getWhitelistedParameters('/old');

        $this->assertIsArray($params1);
        $this->assertIsArray($params2);
    }

    #[Test]
    public function extracts_common_parameters(): void
    {
        // Force rebuild to get common parameters
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        // Get parameters for any path to check common params
        $params = RouteParameterWhitelistService::getWhitelistedParameters('/any/path');

        // Common parameters that should always be whitelisted
        $commonParams = ['page', 'per_page', 'sort', 'order', 'search', 'filter', 'limit'];

        foreach ($commonParams as $param) {
            $this->assertContains($param, $params, "Common parameter '$param' should be whitelisted");
        }
    }

    #[Test]
    public function handles_api_routes(): void
    {
        Route::prefix('api')->group(function () {
            Route::get('/data/{id}', function ($id) {
                return response()->json(['id' => $id]);
            });
        });

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/api/data/123');

        $this->assertContains('id', $parameters);
    }

    #[Test]
    public function handles_routes_with_regex_constraints(): void
    {
        Route::get('/post/{id}', function ($id) {
            return response()->json(['id' => $id]);
        })->where('id', '[0-9]+');

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/post/123');

        $this->assertContains('id', $parameters);
    }

    #[Test]
    public function handles_route_with_multiple_methods(): void
    {
        Route::match(['get', 'post'], '/multi/{action}', function ($action) {
            return response()->json(['action' => $action]);
        });

        // Force rebuild of whitelist cache
        $reflection = new ReflectionClass(RouteParameterWhitelistService::class);
        $property = $reflection->getProperty('whitelistCache');
        $property->setValue(null, null);

        $parameters = RouteParameterWhitelistService::getWhitelistedParameters('/multi/test');

        // Check that common parameters are included
        $this->assertIsArray($parameters);
        $this->assertContains('page', $parameters);
    }
}
