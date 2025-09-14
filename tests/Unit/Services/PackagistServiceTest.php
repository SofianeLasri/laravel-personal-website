<?php

namespace Tests\Unit\Services;

use App\Services\PackagistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PackagistService::class)]
class PackagistServiceTest extends TestCase
{
    private PackagistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PackagistService;
        Cache::flush();
    }

    #[Test]
    public function get_package_data_returns_correct_data_with_all_fields(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'description' => 'Package description',
                'time' => '2023-01-01T00:00:00+00:00',
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
                        'require' => [
                            'php' => '^8.1',
                            'laravel/framework' => '^10.0',
                        ],
                    ],
                    'dev-master' => [
                        'license' => ['MIT'],
                        'time' => '2024-01-01T00:00:00+00:00',
                    ],
                ],
                'maintainers' => [
                    [
                        'name' => 'maintainer1',
                        'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
                    ],
                    [
                        'name' => 'maintainer2',
                        'avatar_url' => null,
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
        $this->assertEquals('v1.0.0', $result['latest_version']); // First key in versions is v1.0.0
        $this->assertEquals('2023-01-01T00:00:00+00:00', $result['created_at']);
        $this->assertEquals('2023-01-01T00:00:00+00:00', $result['updated_at']);
        $this->assertEquals($packagistUrl, $result['url']);
        $this->assertEquals('^8.1', $result['php_version']);
        $this->assertEquals('^10.0', $result['laravel_version']);
        $this->assertCount(2, $result['maintainers']);
        $this->assertEquals('maintainer1', $result['maintainers'][0]['name']);
        $this->assertEquals('maintainer2', $result['maintainers'][1]['name']);
    }

    #[Test]
    public function get_package_data_handles_missing_require_fields(): void
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
                    'v1.0.0' => [
                        'license' => ['MIT'],
                        'require' => [
                            'php' => '^7.4',
                            // No laravel/framework
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('^7.4', $result['php_version']);
        $this->assertNull($result['laravel_version']);
    }

    #[Test]
    public function get_package_data_handles_no_require_section(): void
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
                    'v1.0.0' => [
                        'license' => ['MIT'],
                        // No require section at all
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertNull($result['php_version']);
        $this->assertNull($result['laravel_version']);
    }

    #[Test]
    #[DataProvider('invalidUrlProvider')]
    public function get_package_data_handles_invalid_urls(string $invalidUrl): void
    {
        $result = $this->service->getPackageData($invalidUrl);
        $this->assertNull($result);
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'not a packagist url' => ['https://example.com/not-a-packagist-url'],
            'github url' => ['https://github.com/vendor/package'],
            'malformed packagist url' => ['https://packagist.org/invalid'],
            'missing package name' => ['https://packagist.org/packages/vendor/'],
            'empty url' => [''],
        ];
    }

    #[Test]
    #[DataProvider('httpErrorProvider')]
    public function get_package_data_handles_http_errors(int $statusCode, string $expectedLogLevel, string $expectedMessage): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(null, $statusCode),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived($expectedLogLevel)
            ->once()
            ->with($expectedMessage, \Mockery::type('array'));
    }

    public static function httpErrorProvider(): array
    {
        return [
            '404 not found' => [404, 'info', 'Packagist package not found'],
            '429 rate limit' => [429, 'warning', 'Packagist API rate limit exceeded'],
            '500 server error' => [500, 'error', 'Failed to fetch Packagist data'],
            '502 bad gateway' => [502, 'error', 'Failed to fetch Packagist data'],
            '503 service unavailable' => [503, 'error', 'Failed to fetch Packagist data'],
            '403 forbidden' => [403, 'warning', 'Packagist API request failed'],
        ];
    }

    #[Test]
    public function get_package_data_handles_malformed_json(): void
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

    #[Test]
    public function get_package_data_handles_missing_package_key(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(['error' => 'Something went wrong'], 200),
        ]);

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Invalid JSON response from Packagist', \Mockery::type('array'));
    }

    #[Test]
    public function get_package_data_caches_result(): void
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

    #[Test]
    public function get_package_data_uses_configured_cache_ttl(): void
    {
        Config::set('services.packagist.cache_ttl', 3600);

        $service = new PackagistService;

        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $key === 'packagist_package_vendor_package' && $ttl === 3600;
            })
            ->andReturn([
                'name' => 'vendor/package',
                'downloads' => 1000,
                'monthly_downloads' => 100,
                'daily_downloads' => 10,
                'stars' => 0,
                'dependents' => 0,
                'suggesters' => 0,
                'description' => null,
                'type' => null,
                'repository' => null,
                'github_stars' => null,
                'github_watchers' => null,
                'github_forks' => null,
                'github_open_issues' => null,
                'language' => null,
                'license' => null,
                'latest_version' => null,
                'latest_stable_version' => null,
                'created_at' => null,
                'updated_at' => null,
                'url' => $packagistUrl,
                'maintainers' => [],
                'php_version' => null,
                'laravel_version' => null,
            ]);

        $result = $service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
    }

    #[Test]
    public function get_package_data_handles_empty_package_data(): void
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
        $this->assertNull($result['php_version']);
        $this->assertNull($result['laravel_version']);
        $this->assertEmpty($result['maintainers']);
    }

    #[Test]
    public function get_package_data_extracts_latest_stable_version(): void
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

    #[Test]
    public function get_package_data_handles_no_stable_version(): void
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
                    'v1.0.0-alpha' => ['time' => '2023-01-01T00:00:00+00:00'],
                    'v2.0.0-beta' => ['time' => '2023-02-01T00:00:00+00:00'],
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

    #[Test]
    public function get_package_data_handles_exception(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::spy();

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch Packagist package data', \Mockery::type('array'));
    }

    #[Test]
    public function get_package_statistics_returns_correct_data(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'values' => [
                'daily' => [
                    '2024-01-01' => 100,
                    '2024-01-02' => 150,
                ],
                'monthly' => [
                    '2024-01' => 3000,
                    '2024-02' => 3500,
                ],
            ],
        ];

        Http::fake([
            'https://packagist.org/packages/vendor/package/stats.json' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageStatistics($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals(['2024-01-01' => 100, '2024-01-02' => 150], $result['daily']);
        $this->assertEquals(['2024-01' => 3000, '2024-02' => 3500], $result['monthly']);
        $this->assertEquals(6500, $result['total']);
    }

    #[Test]
    public function get_package_statistics_handles_invalid_url(): void
    {
        $result = $this->service->getPackageStatistics('https://example.com/invalid');
        $this->assertNull($result);
    }

    #[Test]
    public function get_package_statistics_handles_api_error(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        Log::spy();

        $result = $this->service->getPackageStatistics($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Packagist package not found', \Mockery::type('array'));
    }

    #[Test]
    public function get_package_statistics_caches_result(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'values' => [
                'daily' => ['2024-01-01' => 100],
                'monthly' => ['2024-01' => 3000],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        // First call
        $result1 = $this->service->getPackageStatistics($packagistUrl);

        // Second call should use cache
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $result2 = $this->service->getPackageStatistics($packagistUrl);

        $this->assertEquals($result1, $result2);
        $this->assertNotNull($result2);
    }

    #[Test]
    public function get_package_statistics_handles_exception(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::spy();

        $result = $this->service->getPackageStatistics($packagistUrl);

        $this->assertNull($result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch Packagist package statistics', \Mockery::type('array'));
    }

    #[Test]
    public function clear_cache_removes_cached_data(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';

        // Mock cache operations
        Cache::shouldReceive('remember')
            ->once()
            ->with('packagist_package_vendor_package', \Mockery::any(), \Mockery::any())
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Cache::shouldReceive('forget')
            ->once()
            ->with('packagist_package_vendor_package');

        Cache::shouldReceive('forget')
            ->once()
            ->with('packagist_stats_vendor_package');

        // Setup API response
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);
        $this->assertEquals(1000, $result['downloads']);

        // Clear cache - this should call Cache::forget
        $this->service->clearCache($packagistUrl);

        // Assert that cache forget was called (handled by Mockery above)
        $this->assertTrue(true);
    }

    #[Test]
    public function clear_cache_handles_invalid_url(): void
    {
        // Should not throw exception
        $this->service->clearCache('https://example.com/invalid');
        $this->assertTrue(true);
    }

    #[Test]
    public function get_package_data_with_special_characters_in_name(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor-name/package-name';
        $apiResponse = [
            'package' => [
                'name' => 'vendor-name/package-name',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
            ],
        ];

        Http::fake([
            'https://packagist.org/packages/vendor-name/package-name.json' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('vendor-name/package-name', $result['name']);
    }

    #[Test]
    public function get_package_data_handles_null_github_data(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
                'github_stars' => null,
                'github_watchers' => null,
                'github_forks' => null,
                'github_open_issues' => null,
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertNull($result['github_stars']);
        $this->assertNull($result['github_watchers']);
        $this->assertNull($result['github_forks']);
        $this->assertNull($result['github_open_issues']);
    }

    #[Test]
    public function get_package_data_handles_empty_versions(): void
    {
        $packagistUrl = 'https://packagist.org/packages/vendor/package';
        $apiResponse = [
            'package' => [
                'name' => 'vendor/package',
                'downloads' => ['total' => 1000, 'monthly' => 100, 'daily' => 10],
                'favers' => 0,
                'dependents' => 0,
                'suggesters' => 0,
                'versions' => [],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertNull($result['latest_version']);
        $this->assertNull($result['latest_stable_version']);
        $this->assertNull($result['license']);
        $this->assertNull($result['php_version']);
        $this->assertNull($result['laravel_version']);
    }

    #[Test]
    public function get_package_data_handles_multiple_licenses(): void
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
                    'v1.0.0' => [
                        'license' => ['MIT', 'Apache-2.0'],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals(['MIT', 'Apache-2.0'], $result['license']);
    }

    #[Test]
    public function get_package_data_extracts_requirements_from_dev_version_when_no_stable(): void
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
                    'dev-master' => [
                        'require' => [
                            'php' => '>=8.0',
                            'laravel/framework' => '^9.0|^10.0',
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse),
        ]);

        $result = $this->service->getPackageData($packagistUrl);

        $this->assertNotNull($result);
        $this->assertEquals('>=8.0', $result['php_version']);
        $this->assertEquals('^9.0|^10.0', $result['laravel_version']);
    }
}
