<?php

namespace App\Services\Formatters;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Creation;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Screenshot;
use App\Models\Technology;
use App\Models\Video;
use App\Services\GitHubService;
use App\Services\PackagistService;

class CreationFormatter
{
    /**
     * @var array<int, int|null>|null
     */
    private ?array $creationCountByTechnology = null;

    public function __construct(
        private readonly MediaFormatter $mediaFormatter,
        private readonly TranslationHelper $translationHelper,
        private readonly ContentBlockFormatter $contentBlockFormatter,
        private readonly GitHubService $gitHubService,
        private readonly PackagistService $packagistService,
    ) {}

    /**
     * Format the Creation model for SSR short view (index cards).
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     * }
     */
    public function formatShort(Creation $creation): array
    {
        $shortDescription = $creation->shortDescriptionTranslationKey
            ? $this->translationHelper->getWithFallback($creation->shortDescriptionTranslationKey->translations)
            : null;

        return [
            'id' => $creation->id,
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo' => $creation->logo ? $this->mediaFormatter->formatPicture($creation->logo) : null,
            'coverImage' => $creation->coverImage ? $this->mediaFormatter->formatPicture($creation->coverImage) : null,
            'startedAt' => $creation->started_at,
            'endedAt' => $creation->ended_at,
            'startedAtFormatted' => $this->translationHelper->formatDate($creation->started_at),
            'endedAtFormatted' => $this->translationHelper->formatDate($creation->ended_at),
            'type' => $creation->type,
            'shortDescription' => $shortDescription,
            'technologies' => $creation->technologies->map(function (Technology $technology) {
                return $this->formatTechnology($technology);
            })->toArray(),
        ];
    }

    /**
     * Format the Creation model for SSR full view (detail page).
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     fullDescription: string|null,
     *     contents: array<int, array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, mixed>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}>,
     *     externalUrl: string|null,
     *     sourceCodeUrl: string|null,
     *     features: array<int, array{id: int, title: string, description: string, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     screenshots: array<int, array{id: int, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, caption: string, order: int}>,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     people: array<int, array{id: int, name: string, url: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     videos: array<int, array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string}>,
     *     githubData: array{name: string, description: string|null, stars: int, forks: int, watchers: int, language: string|null, topics: array<string>, license: string|null, updated_at: string, created_at: string, open_issues: int, default_branch: string, size: int, url: string, homepage: string|null}|null,
     *     githubLanguages: array<string, float>|null,
     *     packagistData: array{name: string, description: string|null, downloads: int, daily_downloads: int, monthly_downloads: int, stars: int, dependents: int, suggesters: int, type: string|null, repository: string|null, github_stars: int|null, github_watchers: int|null, github_forks: int|null, github_open_issues: int|null, language: string|null, license: array<string>|null, latest_version: string|null, latest_stable_version: string|null, created_at: string|null, updated_at: string|null, url: string, maintainers: array<array{name: string, avatar_url: string|null}>, php_version: string|null, laravel_version: string|null}|null
     * }
     */
    public function formatFull(Creation $creation): array
    {
        $response = $this->formatShort($creation);

        // Format content blocks
        $contents = $creation->contents->map(
            fn ($content) => $this->contentBlockFormatter->format($content)
        );

        // Keep fullDescription for backward compatibility
        $fullDescription = $creation->fullDescriptionTranslationKey
            ? $this->translationHelper->getWithFallback($creation->fullDescriptionTranslationKey->translations)
            : null;

        $response['fullDescription'] = $fullDescription;
        $response['contents'] = $contents->toArray();
        $response['externalUrl'] = $creation->external_url;
        $response['sourceCodeUrl'] = $creation->source_code_url;
        $response['features'] = $this->formatFeatures($creation->features);
        $response['screenshots'] = $this->formatScreenshots($creation->screenshots);
        $response['people'] = $this->formatPeople($creation->people);
        $response['videos'] = $this->formatVideos($creation->videos);

        // Add GitHub repository data if source code URL exists
        $response['githubData'] = null;
        $response['githubLanguages'] = null;

        if ($creation->source_code_url && str_contains($creation->source_code_url, 'github.com')) {
            $response['githubData'] = $this->gitHubService->getRepositoryData($creation->source_code_url);
            if ($response['githubData']) {
                $response['githubLanguages'] = $this->gitHubService->getRepositoryLanguages($creation->source_code_url);
            }
        }

        // Add Packagist package data if external URL is a Packagist URL
        $response['packagistData'] = null;

        if ($creation->external_url && str_contains($creation->external_url, 'packagist.org')) {
            $response['packagistData'] = $this->packagistService->getPackageData($creation->external_url);
        }

        return $response;
    }

    /**
     * Format the Technology model for SSR.
     *
     * @return array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}
     */
    public function formatTechnology(Technology $technology): array
    {
        $description = $this->translationHelper->getWithFallback($technology->descriptionTranslationKey->translations);

        return [
            'id' => $technology->id,
            'creationCount' => $this->getCreationCountByTechnology()[$technology->id] ?? 0,
            'name' => $technology->name,
            'description' => $description,
            'type' => $technology->type,
            'iconPicture' => $this->mediaFormatter->formatPicture($technology->iconPicture),
        ];
    }

    /**
     * Get the creation count by technology (lazy loaded).
     *
     * @return array<int, int|null>
     */
    private function getCreationCountByTechnology(): array
    {
        if ($this->creationCountByTechnology === null) {
            $this->creationCountByTechnology = $this->calculateCreationCountByTechnology();
        }

        return $this->creationCountByTechnology;
    }

    /**
     * Set the creation count by technology (useful for dependency injection in tests).
     *
     * @param  array<int, int|null>  $counts
     */
    public function setCreationCountByTechnology(array $counts): void
    {
        $this->creationCountByTechnology = $counts;
    }

    /**
     * Calculate projects count per technology.
     *
     * @return array<int, int|null>
     */
    private function calculateCreationCountByTechnology(): array
    {
        $creationCountByTechnology = [];

        $technologies = Technology::withCount('creations')->get();

        foreach ($technologies as $technology) {
            $creationCountByTechnology[$technology->id] = $technology->creations_count;
        }

        return $creationCountByTechnology;
    }

    /**
     * Format features for SSR.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Feature>  $features
     * @return array<int, array{id: int, title: string, description: string, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>
     */
    private function formatFeatures(\Illuminate\Database\Eloquent\Collection $features): array
    {
        return $features->map(function (Feature $feature) {
            $title = $this->translationHelper->getWithFallback($feature->titleTranslationKey->translations);
            $description = $this->translationHelper->getWithFallback($feature->descriptionTranslationKey->translations);
            $picture = $feature->picture ? $this->mediaFormatter->formatPicture($feature->picture) : null;

            return [
                'id' => $feature->id,
                'title' => $title,
                'description' => $description,
                'picture' => $picture,
            ];
        })->toArray();
    }

    /**
     * Format screenshots for SSR.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Screenshot>  $screenshots
     * @return array<int, array{id: int, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, caption: string, order: int}>
     */
    private function formatScreenshots(\Illuminate\Database\Eloquent\Collection $screenshots): array
    {
        return $screenshots->map(function (Screenshot $screenshot) {
            $caption = '';
            if ($screenshot->captionTranslationKey) {
                $caption = $this->translationHelper->getWithFallback($screenshot->captionTranslationKey->translations);
            }

            return [
                'id' => $screenshot->id,
                'picture' => $this->mediaFormatter->formatPicture($screenshot->picture),
                'caption' => $caption,
                'order' => $screenshot->order,
            ];
        })->toArray();
    }

    /**
     * Format people for SSR.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Person>  $people
     * @return array<int, array{id: int, name: string, url: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>
     */
    private function formatPeople(\Illuminate\Database\Eloquent\Collection $people): array
    {
        return $people->map(function (Person $person) {
            $picture = $person->picture ? $this->mediaFormatter->formatPicture($person->picture) : null;

            return [
                'id' => $person->id,
                'name' => $person->name,
                'url' => $person->url,
                'picture' => $picture,
            ];
        })->toArray();
    }

    /**
     * Format videos for SSR.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Video>  $videos
     * @return array<int, array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string}>
     */
    private function formatVideos(\Illuminate\Database\Eloquent\Collection $videos): array
    {
        return $videos
            ->where('visibility', VideoVisibility::PUBLIC)
            ->where('status', VideoStatus::READY)
            ->map(function (Video $video) {
                return $this->mediaFormatter->formatVideo($video);
            })->toArray();
    }
}
