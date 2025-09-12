<?php

namespace Tests\Unit\Services;

use App\Services\PackagistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PackagistServiceTest extends TestCase
{
    private PackagistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PackagistService;
        Cache::flush();
    }

    public function test_get_package_data_returns_correct_data(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'description' => 'Package description',
                'downloads' => [
                    'total' => 1000000,
                    'monthly' => 50000,
                    'daily' => 2000,
                ],
                'favers' => 150,
                'dependents' => 25,
                'suggesters' => 5,
                'type' => 'library',
                'repository' => 'https://github.com/vendor/package',
                'github_stars' => 500,
                'github_watchers' => 50,
                'github_forks' => 100,
                'github_open_issues' => 10,
                'language' => 'PHP',
                'versions' => [
                    'v1.0.0' => [
                        'license' => ['MIT'],
                        'time' => '2023-01-01T00:00:00+00:00',
                    ],
                    'dev-master' => [
                        'license' => ['MIT'],
                        'time' => '2024-01-01T00:00:00+00:00',
                    ],
                ],
                'time' => '2023-01-01T00:00:00+00:00',
                'maintainers' => [
                    [
                        'name' => 'maintainer1',
                        'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://packagist.org/packages/vendor/package.json' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('vendor/package', $result['name']);
        $this->assertEquals('Package description', $result['description']);
        $this->assertEquals(1000000, $result['downloads']);
        $this->assertEquals(50000, $result['monthly_downloads']);
        $this->assertEquals(2000, $result['daily_downloads']);
        $this->assertEquals(150, $result['stars']);
        $this->assertEquals(25, $result['dependents']);
        $this->assertEquals(5, $result['suggesters']);
        $this->assertEquals('library', $result['type']);
        $this->assertEquals('https://github.com/vendor/package', $result['repository']);
        $this->assertEquals(500, $result['github_stars']);
        $this->assertEquals(100, $result['github_forks']);
        $this->assertEquals(['MIT'], $result['license']);
        $this->assertEquals('v1.0.0', $result['latest_stable_version']);
        $this->assertEquals('2023-01-01T00:00:00+00:00', $result['created_at']);
        $this->assertEquals('2023-01-01T00:00:00+00:00', $result['updated_at']);
        $this->assertEquals($packagistUrl, $result['url']);
        $this->assertCount(1, $result['maintainers']);
        $this->assertEquals('maintainer1', $result['maintainers'][0]['name']);
    }

    public function test_get_package_data_extracts_vendor_and_package_name(): void
    {
        $packagistUrl = 'https://packagist.org/packages/symfony/console';

        Http::fake([
            'https://packagist.org/packages/symfony/console.json' => Http::response([
                'package' => [
                    'name' => 'symfony/console',
                    'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                    'favers' => 0,
                    'dependents' => 0,
                    'suggesters' => 0,
                ],
            ]),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('symfony/console', $result['name']);
    }

    public function test_get_package_data_handles_invalid_url(): void
    {
        $invalidUrl = 'https://example.com/not-a-packagist-url';

        $result = $this->service->getPackageData($invalidUrl);

        $this->assertNull($result);
    }

    public function test_get_package_data_handles_api_error(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch Packagist data', \Mockery::type('array'));
    }

    public function test_get_package_data_handles404_error(): void
    {
        $packagistUrl = 'https://packagist.org/packages/nonexistent/package';

        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Packagist package not found', \Mockery::type('array'));
    }

    public function test_get_package_data_handles_malformed_json(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response('not valid json', 200),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Invalid JSON response from Packagist', \Mockery::type('array'));
    }

    public function test_get_package_data_caches_result(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 50,
                'dependents' => 10,
                'suggesters' => 2,
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        // First call
        $result1 = $this->service->getPackageData($packagistUrl);

        // Second call should use cache
        Http::fake([
            '*' => Http::response(null, 500), // Would fail if called
        ]);

        $result2 = $this->service->getPackageData($packagistUrl);

        $this->assertEquals($result1, $result2);
        $this->assertNotNull($result2);
    }

    public function test_get_package_data_handles_rate_limiting(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(null, 429),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Packagist API rate limit exceeded', \Mockery::type('array'));
    }

    public function test_get_package_data_handles_empty_package_data(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(['package' => []]),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('vendor/package', $result['name']);
        $this->assertEquals(0, $result['downloads']);
        $this->assertEquals(0, $result['monthly_downloads']);
        $this->assertEquals(0, $result['daily_downloads']);
        $this->assertEquals(0, $result['stars']);
        $this->assertEquals(0, $result['dependents']);
        $this->assertEquals(0, $result['suggesters']);
        $this->assertNull($result['description']);
        $this->assertNull($result['type']);
        $this->assertNull($result['repository']);
        $this->assertNull($result['license']);
        $this->assertEmpty($result['maintainers']);
    }

    public function test_get_package_data_extracts_latest_stable_version(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
                'versions' => [
                    'dev-master' => ['time' => '2024-01-01T00:00:00+00:00'],
                    'v2.0.0-beta' => ['time' => '2023-12-01T00:00:00+00:00'],
                    'v1.5.0' => ['time' => '2023-11-01T00:00:00+00:00'],
                    'v1.4.0' => ['time' => '2023-10-01T00:00:00+00:00'],
                    'v1.0.0-alpha' => ['time' => '2023-01-01T00:00:00+00:00'],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertEquals('v1.5.0', $result['latest_stable_version']);
        $this->assertEquals('dev-master', $result['latest_version']);
    }

    public function test_get_package_data_handles_no_stable_version(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
                'versions' => [
                    'dev-master' => ['time' => '2024-01-01T00:00:00+00:00'],
                    'dev-feature' => ['time' => '2023-12-01T00:00:00+00:00'],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result['latest_stable_version']);
        $this->assertEquals('dev-master', $result['latest_version']);
    }
}
