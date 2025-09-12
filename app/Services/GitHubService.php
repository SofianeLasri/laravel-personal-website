<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const API_BASE_URL = 'https://api.github.com';

    /**
     * Extract owner and repo from GitHub URL
     *
     * @return array{owner: string, repo: string}|null
     */
    private function parseGitHubUrl(string $url): ?array
    {
        // Remove .git extension if present
        $cleanUrl = preg_replace('/\.git$/', '', $url);
        if ($cleanUrl === null) {
            return null;
        }

        // Match GitHub URLs (both HTTPS and SSH format)
        $pattern = '/github\.com[\/:]([^\/]+)\/([^\/\?#]+)/';
        if (preg_match($pattern, $cleanUrl, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
            ];
        }

        return null;
    }

    /**
     * Get repository data from GitHub API
     *
     * @return array{
     *     name: string,
     *     description: string|null,
     *     stars: int,
     *     forks: int,
     *     watchers: int,
     *     language: string|null,
     *     topics: array<string>,
     *     license: string|null,
     *     updated_at: string,
     *     created_at: string,
     *     open_issues: int,
     *     default_branch: string,
     *     size: int,
     *     url: string,
     *     homepage: string|null
     * }|null
     */
    public function getRepositoryData(string $githubUrl): ?array
    {
        $parsed = $this->parseGitHubUrl($githubUrl);
        if (! $parsed) {
            return null;
        }

        $cacheKey = "github_repo_{$parsed['owner']}_{$parsed['repo']}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($parsed) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                ])->get(self::API_BASE_URL."/repos/{$parsed['owner']}/{$parsed['repo']}");

                if (! $response->successful()) {
                    Log::warning('GitHub API request failed', [
                        'status' => $response->status(),
                        'owner' => $parsed['owner'],
                        'repo' => $parsed['repo'],
                    ]);

                    return null;
                }

                $data = $response->json();

                return [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'stars' => $data['stargazers_count'],
                    'forks' => $data['forks_count'],
                    'watchers' => $data['watchers_count'],
                    'language' => $data['language'],
                    'topics' => $data['topics'] ?? [],
                    'license' => $data['license']['name'] ?? null,
                    'updated_at' => $data['updated_at'],
                    'created_at' => $data['created_at'],
                    'open_issues' => $data['open_issues_count'],
                    'default_branch' => $data['default_branch'],
                    'size' => $data['size'],
                    'url' => $data['html_url'],
                    'homepage' => $data['homepage'],
                ];
            } catch (\Exception $e) {
                Log::error('Failed to fetch GitHub repository data', [
                    'error' => $e->getMessage(),
                    'owner' => $parsed['owner'],
                    'repo' => $parsed['repo'],
                ]);

                return null;
            }
        });
    }

    /**
     * Get repository languages with percentages
     *
     * @return array<string, float>|null
     */
    public function getRepositoryLanguages(string $githubUrl): ?array
    {
        $parsed = $this->parseGitHubUrl($githubUrl);
        if (! $parsed) {
            return null;
        }

        $cacheKey = "github_languages_{$parsed['owner']}_{$parsed['repo']}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($parsed) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                ])->get(self::API_BASE_URL."/repos/{$parsed['owner']}/{$parsed['repo']}/languages");

                if (! $response->successful()) {
                    return null;
                }

                $languages = $response->json();
                $total = array_sum($languages);

                if ($total === 0) {
                    return [];
                }

                $percentages = [];
                foreach ($languages as $language => $bytes) {
                    $percentages[$language] = round(($bytes / $total) * 100, 1);
                }

                arsort($percentages);

                return $percentages;
            } catch (\Exception $e) {
                Log::error('Failed to fetch GitHub repository languages', [
                    'error' => $e->getMessage(),
                    'owner' => $parsed['owner'],
                    'repo' => $parsed['repo'],
                ]);

                return null;
            }
        });
    }

    /**
     * Clear cache for a specific repository
     */
    public function clearCache(string $githubUrl): void
    {
        $parsed = $this->parseGitHubUrl($githubUrl);
        if (! $parsed) {
            return;
        }

        Cache::forget("github_repo_{$parsed['owner']}_{$parsed['repo']}");
        Cache::forget("github_languages_{$parsed['owner']}_{$parsed['repo']}");
    }
}
