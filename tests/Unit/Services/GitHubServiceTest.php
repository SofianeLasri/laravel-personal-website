<?php

namespace Tests\Unit\Services;

use App\Services\GitHubService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GitHubService::class)]
class GitHubServiceTest extends TestCase
{
    private GitHubService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GitHubService;
        Cache::flush();
    }

    #[Test]
    public function it_parses_github_urls_correctly(): void
    {
        $testCases = [
            'https://github.com/owner/repo' => ['owner' => 'owner', 'repo' => 'repo'],
            'https://github.com/owner/repo.git' => ['owner' => 'owner', 'repo' => 'repo'],
            'git@github.com:owner/repo.git' => ['owner' => 'owner', 'repo' => 'repo'],
            'https://github.com/owner-name/repo-name' => ['owner' => 'owner-name', 'repo' => 'repo-name'],
            'https://github.com/owner.name/repo.name' => ['owner' => 'owner.name', 'repo' => 'repo.name'],
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('parseGitHubUrl');
        $method->setAccessible(true);

        foreach ($testCases as $url => $expected) {
            $result = $method->invoke($this->service, $url);
            $this->assertEquals($expected, $result, "Failed to parse: $url");
        }
    }

    #[Test]
    public function it_returns_null_for_invalid_github_urls(): void
    {
        $invalidUrls = [
            'https://gitlab.com/owner/repo',
            'https://bitbucket.org/owner/repo',
            'not-a-url',
            '',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('parseGitHubUrl');
        $method->setAccessible(true);

        foreach ($invalidUrls as $url) {
            $result = $method->invoke($this->service, $url);
            $this->assertNull($result, "Should return null for: $url");
        }
    }

    #[Test]
    public function it_fetches_repository_data_successfully(): void
    {
        $githubUrl = 'https://github.com/owner/repo';
        $mockResponse = [
            'name' => 'repo',
            'description' => 'Test repository',
            'stargazers_count' => 100,
            'forks_count' => 50,
            'watchers_count' => 75,
            'language' => 'PHP',
            'topics' => ['laravel', 'php'],
            'license' => ['name' => 'MIT'],
            'updated_at' => '2024-01-01T00:00:00Z',
            'created_at' => '2023-01-01T00:00:00Z',
            'open_issues_count' => 10,
            'default_branch' => 'main',
            'size' => 1024,
            'html_url' => $githubUrl,
            'homepage' => 'https://example.com',
        ];

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->getRepositoryData($githubUrl);

        $this->assertNotNull($result);
        $this->assertEquals('repo', $result['name']);
        $this->assertEquals('Test repository', $result['description']);
        $this->assertEquals(100, $result['stars']);
        $this->assertEquals(50, $result['forks']);
        $this->assertEquals(75, $result['watchers']);
        $this->assertEquals('PHP', $result['language']);
        $this->assertEquals(['laravel', 'php'], $result['topics']);
        $this->assertEquals('MIT', $result['license']);
        $this->assertEquals('2024-01-01T00:00:00Z', $result['updated_at']);
        $this->assertEquals('2023-01-01T00:00:00Z', $result['created_at']);
        $this->assertEquals(10, $result['open_issues']);
        $this->assertEquals('main', $result['default_branch']);
        $this->assertEquals(1024, $result['size']);
        $this->assertEquals($githubUrl, $result['url']);
        $this->assertEquals('https://example.com', $result['homepage']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Accept', 'application/vnd.github.v3+json') &&
                   $request->url() === 'https://api.github.com/repos/owner/repo';
        });
    }

    #[Test]
    public function it_handles_repository_without_license(): void
    {
        $githubUrl = 'https://github.com/owner/repo';
        $mockResponse = [
            'name' => 'repo',
            'description' => null,
            'stargazers_count' => 0,
            'forks_count' => 0,
            'watchers_count' => 0,
            'language' => null,
            'topics' => [],
            'license' => null,
            'updated_at' => '2024-01-01T00:00:00Z',
            'created_at' => '2023-01-01T00:00:00Z',
            'open_issues_count' => 0,
            'default_branch' => 'main',
            'size' => 0,
            'html_url' => $githubUrl,
            'homepage' => null,
        ];

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->getRepositoryData($githubUrl);

        $this->assertNotNull($result);
        $this->assertNull($result['description']);
        $this->assertNull($result['language']);
        $this->assertNull($result['license']);
        $this->assertNull($result['homepage']);
        $this->assertEquals([], $result['topics']);
    }

    #[Test]
    public function it_returns_null_for_non_existent_repository(): void
    {
        $githubUrl = 'https://github.com/owner/nonexistent';

        Http::fake([
            'api.github.com/repos/owner/nonexistent' => Http::response(null, 404),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('GitHub API request failed', [
                'status' => 404,
                'owner' => 'owner',
                'repo' => 'nonexistent',
            ])
            ->andReturnNull();

        $result = $this->service->getRepositoryData($githubUrl);

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response(null, 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('GitHub API request failed', [
                'status' => 500,
                'owner' => 'owner',
                'repo' => 'repo',
            ])
            ->andReturnNull();

        $result = $this->service->getRepositoryData($githubUrl);

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_network_exceptions(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to fetch GitHub repository data', [
                'error' => 'Network error',
                'owner' => 'owner',
                'repo' => 'repo',
            ])
            ->andReturnNull();

        $result = $this->service->getRepositoryData($githubUrl);

        $this->assertNull($result);
    }

    #[Test]
    public function it_caches_repository_data(): void
    {
        $githubUrl = 'https://github.com/owner/repo';
        $mockResponse = [
            'name' => 'repo',
            'description' => 'Test repository',
            'stargazers_count' => 100,
            'forks_count' => 50,
            'watchers_count' => 75,
            'language' => 'PHP',
            'topics' => [],
            'license' => null,
            'updated_at' => '2024-01-01T00:00:00Z',
            'created_at' => '2023-01-01T00:00:00Z',
            'open_issues_count' => 10,
            'default_branch' => 'main',
            'size' => 1024,
            'html_url' => $githubUrl,
            'homepage' => null,
        ];

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response($mockResponse, 200),
        ]);

        // First call - should hit the API
        $result1 = $this->service->getRepositoryData($githubUrl);
        $this->assertNotNull($result1);

        // Second call - should use cache
        $result2 = $this->service->getRepositoryData($githubUrl);
        $this->assertNotNull($result2);
        $this->assertEquals($result1, $result2);

        // API should only be called once
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_fetches_repository_languages_successfully(): void
    {
        $githubUrl = 'https://github.com/owner/repo';
        $mockResponse = [
            'PHP' => 50000,
            'JavaScript' => 30000,
            'CSS' => 15000,
            'HTML' => 5000,
        ];

        Http::fake([
            'api.github.com/repos/owner/repo/languages' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->getRepositoryLanguages($githubUrl);

        $this->assertNotNull($result);
        $this->assertEquals(50.0, $result['PHP']);
        $this->assertEquals(30.0, $result['JavaScript']);
        $this->assertEquals(15.0, $result['CSS']);
        $this->assertEquals(5.0, $result['HTML']);

        // Check that languages are sorted by percentage
        $keys = array_keys($result);
        $this->assertEquals(['PHP', 'JavaScript', 'CSS', 'HTML'], $keys);
    }

    #[Test]
    public function it_returns_empty_array_for_repository_without_languages(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        Http::fake([
            'api.github.com/repos/owner/repo/languages' => Http::response([], 200),
        ]);

        $result = $this->service->getRepositoryLanguages($githubUrl);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_null_for_languages_api_error(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        Http::fake([
            'api.github.com/repos/owner/repo/languages' => Http::response(null, 404),
        ]);

        $result = $this->service->getRepositoryLanguages($githubUrl);

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_languages_network_exceptions(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to fetch GitHub repository languages', [
                'error' => 'Network error',
                'owner' => 'owner',
                'repo' => 'repo',
            ])
            ->andReturnNull();

        $result = $this->service->getRepositoryLanguages($githubUrl);

        $this->assertNull($result);
    }

    #[Test]
    public function it_caches_repository_languages(): void
    {
        $githubUrl = 'https://github.com/owner/repo';
        $mockResponse = [
            'PHP' => 70000,
            'JavaScript' => 30000,
        ];

        Http::fake([
            'api.github.com/repos/owner/repo/languages' => Http::response($mockResponse, 200),
        ]);

        // First call - should hit the API
        $result1 = $this->service->getRepositoryLanguages($githubUrl);
        $this->assertNotNull($result1);

        // Second call - should use cache
        $result2 = $this->service->getRepositoryLanguages($githubUrl);
        $this->assertNotNull($result2);
        $this->assertEquals($result1, $result2);

        // API should only be called once
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_clears_cache_for_repository(): void
    {
        $githubUrl = 'https://github.com/owner/repo';

        // Set some cache values
        Cache::put('github_repo_owner_repo', ['test' => 'data'], 3600);
        Cache::put('github_languages_owner_repo', ['PHP' => 100], 3600);

        // Verify cache exists
        $this->assertTrue(Cache::has('github_repo_owner_repo'));
        $this->assertTrue(Cache::has('github_languages_owner_repo'));

        // Clear cache
        $this->service->clearCache($githubUrl);

        // Verify cache is cleared
        $this->assertFalse(Cache::has('github_repo_owner_repo'));
        $this->assertFalse(Cache::has('github_languages_owner_repo'));
    }

    #[Test]
    public function it_handles_invalid_url_for_cache_clearing(): void
    {
        $invalidUrl = 'not-a-github-url';

        // Should not throw exception
        $this->service->clearCache($invalidUrl);

        $this->assertTrue(true); // Test passes if no exception
    }

    #[Test]
    #[DataProvider('githubUrlProvider')]
    public function it_handles_various_github_url_formats(string $url, bool $shouldWork): void
    {
        if ($shouldWork) {
            $mockResponse = [
                'name' => 'repo',
                'description' => 'Test repository',
                'stargazers_count' => 100,
                'forks_count' => 50,
                'watchers_count' => 75,
                'language' => 'PHP',
                'topics' => [],
                'license' => null,
                'updated_at' => '2024-01-01T00:00:00Z',
                'created_at' => '2023-01-01T00:00:00Z',
                'open_issues_count' => 10,
                'default_branch' => 'main',
                'size' => 1024,
                'html_url' => $url,
                'homepage' => null,
            ];

            Http::fake([
                'api.github.com/repos/owner/repo' => Http::response($mockResponse, 200),
            ]);

            $result = $this->service->getRepositoryData($url);
            $this->assertNotNull($result);
        } else {
            $result = $this->service->getRepositoryData($url);
            $this->assertNull($result);
        }
    }

    public static function githubUrlProvider(): array
    {
        return [
            'https URL' => ['https://github.com/owner/repo', true],
            'https URL with .git' => ['https://github.com/owner/repo.git', true],
            'SSH URL' => ['git@github.com:owner/repo.git', true],
            'GitLab URL' => ['https://gitlab.com/owner/repo', false],
            'Invalid URL' => ['not-a-url', false],
            'Empty string' => ['', false],
        ];
    }
}
