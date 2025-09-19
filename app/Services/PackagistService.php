<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackagistService
{
    private const API_BASE_URL = 'https://packagist.org';

    private int $cacheTtl;

    public function __construct()
    {
        $this->cacheTtl = config('services.packagist.cache_ttl', 7200); // 2 hours default
    }

    /**
     * Extract vendor and package name from Packagist URL
     *
     * @return array{vendor: string, package: string}|null
     */
    private function parsePackagistUrl(string $url): ?array
    {
        // Match packagist.org URLs
        $pattern = '/packagist\.org\/packages\/([^\/]+)\/([^\/\?#]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return [
                'vendor' => $matches[1],
                'package' => $matches[2],
            ];
        }

        return null;
    }

    /**
     * Get package data from Packagist API
     *
     * @return array{
     *     name: string,
     *     description: string|null,
     *     downloads: int,
     *     daily_downloads: int,
     *     monthly_downloads: int,
     *     stars: int,
     *     dependents: int,
     *     suggesters: int,
     *     type: string|null,
     *     repository: string|null,
     *     github_stars: int|null,
     *     github_watchers: int|null,
     *     github_forks: int|null,
     *     github_open_issues: int|null,
     *     language: string|null,
     *     license: array<string>|null,
     *     latest_version: string|null,
     *     latest_stable_version: string|null,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     url: string,
     *     maintainers: array<array{name: string, avatar_url: string|null}>,
     *     php_version: string|null,
     *     laravel_version: string|null
     * }|null
     */
    public function getPackageData(string $packagistUrl): ?array
    {
        $parsed = $this->parsePackagistUrl($packagistUrl);
        if (! $parsed) {
            return null;
        }

        $packageName = "{$parsed['vendor']}/{$parsed['package']}";
        $cacheKey = 'packagist_package_'.str_replace('/', '_', $packageName);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($packageName, $packagistUrl) {
            try {
                // Fetch main package data
                $response = Http::get(self::API_BASE_URL."/packages/{$packageName}.json");

                if (! $response->successful()) {
                    $this->logApiError($response->status(), $packageName);

                    return null;
                }

                $data = $response->json();

                if (! $data || ! isset($data['package'])) {
                    Log::error('Invalid JSON response from Packagist', [
                        'package' => $packageName,
                        'response' => $data,
                    ]);

                    return null;
                }

                $package = $data['package'];

                // Get latest versions
                $versions = $package['versions'] ?? [];
                $latestVersion = null;
                $latestStableVersion = null;

                // Find latest version (including dev versions)
                if (! empty($versions)) {
                    // First key is typically the latest version
                    $latestVersion = array_key_first($versions);
                }

                // Find latest stable version (exclude dev and pre-release versions)
                foreach ($versions as $version => $versionData) {
                    // Skip dev branches
                    if (str_starts_with($version, 'dev-')) {
                        continue;
                    }

                    // Check if this is a stable version (no pre-release indicators)
                    if (! preg_match('/-(?:alpha|beta|rc|dev)/i', $version)) {
                        $latestStableVersion = $version;
                        break; // First stable version found is the latest
                    }
                }

                // Get download statistics
                $downloads = $package['downloads'] ?? [];

                // Get GitHub data if available
                $githubData = [
                    'github_stars' => $package['github_stars'] ?? null,
                    'github_watchers' => $package['github_watchers'] ?? null,
                    'github_forks' => $package['github_forks'] ?? null,
                    'github_open_issues' => $package['github_open_issues'] ?? null,
                ];

                // Get maintainers
                $maintainers = [];
                if (isset($package['maintainers'])) {
                    foreach ($package['maintainers'] as $maintainer) {
                        $maintainers[] = [
                            'name' => $maintainer['name'] ?? '',
                            'avatar_url' => $maintainer['avatar_url'] ?? null,
                        ];
                    }
                }

                // Get license and requirements from latest version or latest stable version
                $license = null;
                $phpVersion = null;
                $laravelVersion = null;

                // Determine which version to use for requirements
                $versionToCheck = $latestStableVersion ?? $latestVersion;

                if ($versionToCheck && isset($versions[$versionToCheck])) {
                    $versionData = $versions[$versionToCheck];

                    // Get license
                    if (isset($versionData['license'])) {
                        $license = $versionData['license'];
                    }

                    // Get PHP version requirement
                    if (isset($versionData['require']) && isset($versionData['require']['php'])) {
                        $phpVersion = $versionData['require']['php'];
                    }

                    // Get Laravel version requirement
                    if (isset($versionData['require']) && isset($versionData['require']['laravel/framework'])) {
                        $laravelVersion = $versionData['require']['laravel/framework'];
                    }
                }

                // Get times
                $createdAt = $package['time'] ?? null;
                $updatedAt = null;

                // Use the latest version time for updated_at, or the first version time if available
                if ($latestVersion && isset($versions[$latestVersion]['time'])) {
                    $updatedAt = $versions[$latestVersion]['time'];
                } elseif (! empty($versions)) {
                    $firstVersion = array_key_first($versions);
                    $updatedAt = $versions[$firstVersion]['time'] ?? null;
                }

                return [
                    'name' => $package['name'] ?? $packageName,
                    'description' => $package['description'] ?? null,
                    'downloads' => $downloads['total'] ?? 0,
                    'daily_downloads' => $downloads['daily'] ?? 0,
                    'monthly_downloads' => $downloads['monthly'] ?? 0,
                    'stars' => $package['favers'] ?? 0,
                    'dependents' => $package['dependents'] ?? 0,
                    'suggesters' => $package['suggesters'] ?? 0,
                    'type' => $package['type'] ?? null,
                    'repository' => $package['repository'] ?? null,
                    ...$githubData,
                    'language' => $package['language'] ?? null,
                    'license' => $license,
                    'latest_version' => $latestVersion,
                    'latest_stable_version' => $latestStableVersion,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    'url' => $packagistUrl,
                    'maintainers' => $maintainers,
                    'php_version' => $phpVersion,
                    'laravel_version' => $laravelVersion,
                ];
            } catch (Exception $e) {
                Log::error('Failed to fetch Packagist package data', [
                    'error' => $e->getMessage(),
                    'package' => $packageName,
                ]);

                return null;
            }
        });
    }

    /**
     * Get package statistics (downloads over time)
     *
     * @return array{
     *     daily: array<string, int>,
     *     monthly: array<string, int>,
     *     total: int
     * }|null
     */
    public function getPackageStatistics(string $packagistUrl): ?array
    {
        $parsed = $this->parsePackagistUrl($packagistUrl);
        if (! $parsed) {
            return null;
        }

        $packageName = "{$parsed['vendor']}/{$parsed['package']}";
        $cacheKey = 'packagist_stats_'.str_replace('/', '_', $packageName);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($packageName) {
            try {
                $response = Http::get(self::API_BASE_URL."/packages/{$packageName}/stats.json");

                if (! $response->successful()) {
                    $this->logApiError($response->status(), $packageName, 'statistics');

                    return null;
                }

                $data = $response->json();

                return [
                    'daily' => $data['values']['daily'] ?? [],
                    'monthly' => $data['values']['monthly'] ?? [],
                    'total' => array_sum($data['values']['monthly'] ?? []),
                ];
            } catch (Exception $e) {
                Log::error('Failed to fetch Packagist package statistics', [
                    'error' => $e->getMessage(),
                    'package' => $packageName,
                ]);

                return null;
            }
        });
    }

    /**
     * Log API errors with appropriate severity
     */
    private function logApiError(int $status, string $package, string $endpoint = 'package'): void
    {
        $context = [
            'status' => $status,
            'package' => $package,
            'endpoint' => $endpoint,
        ];

        switch ($status) {
            case 404:
                Log::info('Packagist package not found', $context);
                break;
            case 429:
                Log::warning('Packagist API rate limit exceeded', $context);
                break;
            case 500:
            case 502:
            case 503:
                Log::error('Failed to fetch Packagist data', $context);
                break;
            default:
                Log::warning('Packagist API request failed', $context);
        }
    }

    /**
     * Clear cache for a specific package
     */
    public function clearCache(string $packagistUrl): void
    {
        $parsed = $this->parsePackagistUrl($packagistUrl);
        if (! $parsed) {
            return;
        }

        $packageName = "{$parsed['vendor']}/{$parsed['package']}";
        $cacheKeyBase = str_replace('/', '_', $packageName);

        Cache::forget("packagist_package_{$cacheKeyBase}");
        Cache::forget("packagist_stats_{$cacheKeyBase}");
    }
}
