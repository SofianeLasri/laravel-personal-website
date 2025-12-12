<?php

namespace App\Services;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Enums\ContentRenderContext;
use App\Enums\CreationType;
use App\Enums\ExperienceType;
use App\Enums\GameReviewRating;
use App\Enums\TechnologyType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\Certification;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\Experience;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\Video;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicControllersService
{
    private string $locale;

    private string $fallbackLocale;

    private GitHubService $gitHubService;

    private PackagistService $packagistService;

    private CustomEmojiResolverService $emojiResolver;

    private const DEVELOPMENT_TYPES = [
        CreationType::PORTFOLIO,
        CreationType::LIBRARY,
        CreationType::TOOL,
        CreationType::WEBSITE,
        CreationType::OTHER,
    ];

    /**
     * @var array<int, int|null>
     */
    private array $creationCountByTechnology;

    public function __construct(CustomEmojiResolverService $emojiResolver)
    {
        $this->locale = app()->getLocale();
        $this->fallbackLocale = config('app.fallback_locale');
        $this->creationCountByTechnology = $this->calcCreationCountByTechnology();
        $this->gitHubService = new GitHubService;
        $this->packagistService = new PackagistService;
        $this->emojiResolver = $emojiResolver;
    }

    /**
     * Return projects count per technology.
     *
     * @return array<int, int|null>
     */
    public function calcCreationCountByTechnology(): array
    {
        $creationCountByTechnology = [];

        $technologies = Technology::withCount('creations')->get();

        foreach ($technologies as $technology) {
            $creationCountByTechnology[$technology->id] = $technology->creations_count;
        }

        return $creationCountByTechnology;
    }

    /**
     * Get the projects count and development years of experience.
     *
     * @return array{yearsOfExperience: 0|float, count: int<0, max>}
     */
    public function getDevelopmentStats(): array
    {
        $stats = [
            'yearsOfExperience' => 0,
        ];

        $baseQuery = Creation::whereIn('type', self::DEVELOPMENT_TYPES)->get();
        $creationCount = $baseQuery->count();

        $stats['count'] = $creationCount;

        // Calculate years of experience based on the earliest professional experience
        $earliestWorkExperience = Experience::where('type', ExperienceType::EMPLOI)
            ->orderBy('started_at')
            ->first();

        if ($earliestWorkExperience) {
            $stats['yearsOfExperience'] = round(now()->diffInYears($earliestWorkExperience->started_at, true));
        }

        return $stats;
    }

    /**
     * Get all the Laravel projects.
     * Returns a SSRSimplifiedCreation TypeScript type compatible object.
     *
     * @return Collection<int, array{
     *      id: int,
     *      name: string,
     *      slug: string,
     *      logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *      coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *      startedAt: string,
     *      endedAt: string|null,
     *      startedAtFormatted: string|null,
     *      endedAtFormatted: string|null,
     *      type: CreationType,
     *      shortDescription: string|null,
     *      technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     *  }>
     */
    public function getLaravelCreations(): Collection
    {
        $laravel = Technology::where('name', 'Laravel')->first();

        if (! $laravel) {
            return collect();
        }

        $developmentCreations = Creation::whereIn('type', self::DEVELOPMENT_TYPES)
            ->whereHas('technologies', function ($query) use ($laravel) {
                $query->where('technologies.id', $laravel->id);
            })
            ->with([
                'logo',
                'coverImage',
                'shortDescriptionTranslationKey.translations',
                'technologies.iconPicture',
                'technologies.descriptionTranslationKey.translations',
            ])
            ->orderByRaw('(ended_at IS NULL) DESC, ended_at DESC')->get();

        return $developmentCreations->map(function (Creation $creation) {
            return $this->formatCreationForSSRShort($creation);
        });
    }

    /**
     * Get all the projects.
     * Returns a SSRSimplifiedCreation TypeScript type compatible object.
     *
     * @return Collection<int, array{
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
     * }>
     */
    public function getCreations(): Collection
    {
        $creations = Creation::with([
            'logo.optimizedPictures',
            'coverImage.optimizedPictures',
            'shortDescriptionTranslationKey.translations',
            'technologies.iconPicture.optimizedPictures',
            'technologies.descriptionTranslationKey.translations',
        ])->orderByRaw('(ended_at IS NULL) DESC, ended_at DESC')->get();

        return $creations->map(function (Creation $creation) {
            return $this->formatCreationForSSRShort($creation);
        });
    }

    /**
     * Format the Creation model for Server-Side Rendering (SSR).
     * Returns a SSRSimplifiedCreation TypeScript type compatible array.
     *
     * @param  Creation  $creation  The creation to format
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
    public function formatCreationForSSRShort(Creation $creation): array
    {
        $shortDescription = $creation->shortDescriptionTranslationKey ?
            $this->getTranslationWithFallback($creation->shortDescriptionTranslationKey->translations) : null;

        return [
            'id' => $creation->id,
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo' => $creation->logo ? $this->formatPictureForSSR($creation->logo) : null,
            'coverImage' => $creation->coverImage ? $this->formatPictureForSSR($creation->coverImage) : null,
            'startedAt' => $creation->started_at,
            'endedAt' => $creation->ended_at,
            'startedAtFormatted' => $this->formatDate($creation->started_at),
            'endedAtFormatted' => $this->formatDate($creation->ended_at),
            'type' => $creation->type,
            'shortDescription' => $shortDescription,
            'technologies' => $creation->technologies->map(function (Technology $technology) {
                return $this->formatTechnologyForSSR($technology);
            })->toArray(),
        ];
    }

    /**
     * Format the Creation model for Server-Side Rendering (SSR) with full description.
     * Returns a SSRFullCreation TypeScript type compatible array.
     *
     * @param  Creation  $creation  The creation to format
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
    public function formatCreationForSSRFull(Creation $creation): array
    {
        $response = $this->formatCreationForSSRShort($creation);

        // Format content blocks
        $contents = $creation->contents->map(fn ($content) => $this->formatContentBlockForSSR($content));

        // Keep fullDescription for backward compatibility (will be null if no content blocks exist yet)
        $fullDescription = $creation->fullDescriptionTranslationKey ?
            $this->getTranslationWithFallback($creation->fullDescriptionTranslationKey->translations) : null;

        $response['fullDescription'] = $fullDescription;
        $response['contents'] = $contents->toArray();
        $response['externalUrl'] = $creation->external_url;
        $response['sourceCodeUrl'] = $creation->source_code_url;
        $response['features'] = $creation->features->map(function (Feature $feature) {
            $title = $this->getTranslationWithFallback($feature->titleTranslationKey->translations);
            $description = $this->getTranslationWithFallback($feature->descriptionTranslationKey->translations);

            $picture = $feature->picture ? $this->formatPictureForSSR($feature->picture) : null;

            return [
                'id' => $feature->id,
                'title' => $title,
                'description' => $description,
                'picture' => $picture,
            ];
        })->toArray();

        $response['screenshots'] = $creation->screenshots->map(function (Screenshot $screenshot) {
            $caption = '';
            if ($screenshot->captionTranslationKey) {
                $caption = $this->getTranslationWithFallback($screenshot->captionTranslationKey->translations);
            }

            return [
                'id' => $screenshot->id,
                'picture' => $this->formatPictureForSSR($screenshot->picture),
                'caption' => $caption,
                'order' => $screenshot->order,
            ];
        })->toArray();

        $response['people'] = $creation->people->map(function (Person $person) {
            $picture = null;

            if ($person->picture) {
                $picture = $this->formatPictureForSSR($person->picture);
            }

            return [
                'id' => $person->id,
                'name' => $person->name,
                'url' => $person->url,
                'picture' => $picture,
            ];
        })->toArray();

        $response['videos'] = $creation->videos
            ->where('visibility', VideoVisibility::PUBLIC)
            ->where('status', VideoStatus::READY)
            ->map(function (Video $video) {
                return $this->formatVideoForSSR($video);
            })->toArray();

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
     * Format the Picture model for Server-Side Rendering (SSR).
     * Returns a SSRPicture TypeScript type compatible array.
     *
     * @param  Picture  $picture  The picture to format
     * @return array{
     *  filename: string,
     *  width: int|null,
     *  height: int|null,
     *  avif: array{
     *      thumbnail: string,
     *      small: string,
     *      medium: string,
     *      large: string,
     *      full: string,},
     *  webp: array{
     *      thumbnail: string,
     *      small: string,
     *      medium: string,
     *      large: string,
     *      full: string,},
     *  jpg: array{
     *      thumbnail: string,
     *      small: string,
     *      medium: string,
     *      large: string,
     *      full: string,},
     * }
     */
    public function formatPictureForSSR(Picture $picture): array
    {
        return [
            'filename' => $picture->filename,
            'width' => $picture->width,
            'height' => $picture->height,
            'avif' => [
                'thumbnail' => $picture->getUrl('thumbnail', 'avif'),
                'small' => $picture->getUrl('small', 'avif'),
                'medium' => $picture->getUrl('medium', 'avif'),
                'large' => $picture->getUrl('large', 'avif'),
                'full' => $picture->getUrl('full', 'avif'),
            ],
            'webp' => [
                'thumbnail' => $picture->getUrl('thumbnail', 'webp'),
                'small' => $picture->getUrl('small', 'webp'),
                'medium' => $picture->getUrl('medium', 'webp'),
                'large' => $picture->getUrl('large', 'webp'),
                'full' => $picture->getUrl('full', 'webp'),
            ],
            'jpg' => [
                'thumbnail' => $picture->getUrl('thumbnail', 'jpg'),
                'small' => $picture->getUrl('small', 'jpg'),
                'medium' => $picture->getUrl('medium', 'jpg'),
                'large' => $picture->getUrl('large', 'jpg'),
                'full' => $picture->getUrl('full', 'jpg'),
            ],
        ];
    }

    /**
     * Format the Video model for Server-Side Rendering (SSR).
     * Returns a SSRVideo TypeScript type compatible array.
     *
     * @param  Video  $video  The video to format
     * @return array{
     *  id: int,
     *  bunnyVideoId: string,
     *  name: string,
     *  coverPicture: array{
     *      filename: string,
     *      width: int|null,
     *      height: int|null,
     *      avif: array{
     *          thumbnail: string,
     *          small: string,
     *          medium: string,
     *          large: string,
     *          full: string,},
     *      webp: array{
     *          thumbnail: string,
     *          small: string,
     *          medium: string,
     *          large: string,
     *          full: string,},
     *     },
     *     libraryId: string,
     * }
     */
    public function formatVideoForSSR(Video $video): array
    {
        /** @var Picture $coverPicture */
        $coverPicture = $video->coverPicture;

        return [
            'id' => $video->id,
            'bunnyVideoId' => $video->bunny_video_id,
            'name' => $video->name,
            'coverPicture' => $this->formatPictureForSSR($coverPicture),
            'libraryId' => config('services.bunny.stream_library_id'),
        ];
    }

    /**
     * Format the Technology model for Server-Side Rendering (SSR).
     * Returns a SSRTechnology TypeScript type compatible array.
     *
     * @return array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}
     */
    public function formatTechnologyForSSR(Technology $technology): array
    {
        $description = $this->getTranslationWithFallback($technology->descriptionTranslationKey->translations);

        return [
            'id' => $technology->id,
            'creationCount' => $this->creationCountByTechnology[$technology->id] ?? 0,
            'name' => $technology->name,
            'description' => $description,
            'type' => $technology->type,
            'iconPicture' => $this->formatPictureForSSR($technology->iconPicture),
        ];
    }

    /**
     * Get all the technology experiences.
     * Returns a SSRTechnologyExperience TypeScript type compatible object.
     *
     * @return Collection<int, array{
     *     id: int,
     *     technologyId: int,
     *     name: string,
     *     description: string,
     *     creationCount: int,
     *     type: TechnologyType,
     *     typeLabel: string,
     *     iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     */
    public function getTechnologyExperiences(): Collection
    {
        $experiences = TechnologyExperience::with([
            'technology.iconPicture',
            'technology.descriptionTranslationKey.translations',
            'descriptionTranslationKey.translations',
        ])->get();

        return $experiences->map(function (TechnologyExperience $experience) {
            $technologyId = $experience->technology->id;
            $description = $this->getTranslationWithFallback($experience->descriptionTranslationKey->translations);

            return [
                'id' => $experience->id,
                'technologyId' => $technologyId,
                'name' => $experience->technology->name,
                'description' => $description,
                'creationCount' => $this->creationCountByTechnology[$technologyId] ?? 0,
                'type' => $experience->technology->type,
                'typeLabel' => $experience->technology->type->label(),
                'iconPicture' => $this->formatPictureForSSR($experience->technology->iconPicture),
            ];
        });
    }

    /**
     * Get all the experiences.
     * Returns a SSRExperience TypeScript type compatible object.
     *
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: Collection<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: Carbon,
     *     endedAt: Carbon|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null}>
     */
    public function getExperiences(): Collection
    {
        $experiences = Experience::with([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
            'logo',
            'technologies.iconPicture',
            'technologies.descriptionTranslationKey.translations',
        ])->get();

        return $experiences->map(function (Experience $experience) {
            $title = $experience->titleTranslationKey ?
                $this->getTranslationWithFallback($experience->titleTranslationKey->translations) : '';
            $shortDescription = $experience->shortDescriptionTranslationKey ?
                $this->getTranslationWithFallback($experience->shortDescriptionTranslationKey->translations) : '';
            $fullDescription = $experience->fullDescriptionTranslationKey ?
                $this->getTranslationWithFallback($experience->fullDescriptionTranslationKey->translations) : '';

            return [
                'id' => $experience->id,
                'title' => $title,
                'organizationName' => $experience->organization_name,
                'slug' => $experience->slug,
                'logo' => $experience->logo ? $this->formatPictureForSSR($experience->logo) : null,
                'location' => $experience->location,
                'websiteUrl' => $experience->website_url,
                'shortDescription' => $shortDescription,
                'fullDescription' => $fullDescription,
                'technologies' => $experience->technologies->map(function (Technology $technology) {
                    return $this->formatTechnologyForSSR($technology);
                }),
                'type' => $experience->type,
                'startedAt' => $experience->started_at,
                'endedAt' => $experience->ended_at,
                'startedAtFormatted' => $this->formatDate($experience->started_at),
                'endedAtFormatted' => $this->formatDate($experience->ended_at),
            ];
        });
    }

    /**
     * Get a translation with fallback to the fallback locale.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Translation>  $translations  Collection of translations
     * @return string The translation text or empty string if not found
     */
    public function getTranslationWithFallback(\Illuminate\Database\Eloquent\Collection $translations): string
    {
        $translation = $translations->where('locale', $this->locale)->first();
        if ($translation && isset($translation->text)) {
            return $translation->text;
        }

        if ($this->locale !== $this->fallbackLocale) {
            $fallbackTranslation = $translations->where('locale', $this->fallbackLocale)->first();
            if ($fallbackTranslation && isset($fallbackTranslation->text)) {
                return $fallbackTranslation->text;
            }
        }

        return '';
    }

    /**
     * Format a date according to the user's preferred locale with month in CamelCase.
     *
     * @param  string|Carbon|null  $date  The date to format
     * @return string|null Formatted date or null
     */
    public function formatDate(Carbon|string|null $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (! $date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $month = Str::ucfirst($date->translatedFormat('F'));

        return $month.' '.$date->format('Y');
    }

    /**
     * Get all certifications for SSR.
     * Returns a SSRCertification TypeScript type compatible array.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     level: string|null,
     *     score: string|null,
     *     date: string,
     *     dateFormatted: string,
     *     link: string|null,
     *     picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null
     * }>
     */
    public function getCertifications(): Collection
    {
        $certifications = Certification::with('picture')->orderBy('date', 'desc')->get();

        return $certifications->map(function (Certification $certification) {
            return $this->formatCertificationForSSR($certification);
        });
    }

    /**
     * Get experiences filtered by type for SSR.
     * Returns a SSRExperience TypeScript type compatible array.
     *
     * @param  ExperienceType  $type  The experience type to filter by
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }>
     */
    public function getExperiencesByType(ExperienceType $type): Collection
    {
        $experiences = Experience::where('type', $type)
            ->with([
                'titleTranslationKey.translations',
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'logo',
                'technologies.iconPicture',
                'technologies.descriptionTranslationKey.translations',
            ])
            ->orderBy('started_at', 'desc')
            ->get();

        return $experiences->map(function (Experience $experience) {
            return $this->formatExperienceForSSR($experience);
        });
    }

    /**
     * Get all data needed for the certifications career page.
     * Returns a SSRCertificationsCareerData TypeScript type compatible array.
     *
     * @return array{
     *     certifications: Collection<int, array{id: int, name: string, level: string|null, score: string|null, date: string, dateFormatted: string, link: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     educationExperiences: Collection<int, array{id: int, title: string, organizationName: string, slug: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>,
     *     workExperiences: Collection<int, array{id: int, title: string, organizationName: string, slug: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>
     * }
     */
    public function getCertificationsCareerData(): array
    {
        return [
            'certifications' => $this->getCertifications(),
            'educationExperiences' => $this->getExperiencesByType(ExperienceType::FORMATION),
            'workExperiences' => $this->getExperiencesByType(ExperienceType::EMPLOI),
        ];
    }

    /**
     * Format the Certification model for Server-Side Rendering (SSR).
     * Returns a SSRCertification TypeScript type compatible array.
     *
     * @param  Certification  $certification  The certification to format
     * @return array{
     *     id: int,
     *     name: string,
     *     level: string|null,
     *     score: string|null,
     *     date: string,
     *     dateFormatted: string,
     *     link: string|null,
     *     picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null
     * }
     */
    public function formatCertificationForSSR(Certification $certification): array
    {
        $date = Carbon::parse($certification->date);
        $dateFormatted = $this->formatDate($date);

        return [
            'id' => $certification->id,
            'name' => $certification->name,
            'level' => $certification->level,
            'score' => $certification->score,
            'date' => $date->format('Y-m-d'),
            'dateFormatted' => $dateFormatted ?? '',
            'link' => $certification->link,
            'picture' => ($certification->picture instanceof Picture) ? $this->formatPictureForSSR($certification->picture) : null,
        ];
    }

    /**
     * Format the Experience model for Server-Side Rendering (SSR).
     * Returns a SSRExperience TypeScript type compatible array.
     *
     * @param  Experience  $experience  The experience to format
     * @return array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }
     */
    public function formatExperienceForSSR(Experience $experience): array
    {
        $title = $experience->titleTranslationKey ?
            $this->getTranslationWithFallback($experience->titleTranslationKey->translations) : '';
        $shortDescription = $experience->shortDescriptionTranslationKey ?
            $this->getTranslationWithFallback($experience->shortDescriptionTranslationKey->translations) : '';
        $fullDescription = $experience->fullDescriptionTranslationKey ?
            $this->getTranslationWithFallback($experience->fullDescriptionTranslationKey->translations) : '';

        $startedAtFormatted = $this->formatDate($experience->started_at);
        $endedAtFormatted = $this->formatDate($experience->ended_at);

        return [
            'id' => $experience->id,
            'title' => $title,
            'organizationName' => $experience->organization_name,
            'slug' => $experience->slug,
            'logo' => $experience->logo ? $this->formatPictureForSSR($experience->logo) : null,
            'location' => $experience->location,
            'websiteUrl' => $experience->website_url,
            'shortDescription' => $shortDescription,
            'fullDescription' => $fullDescription,
            'technologies' => $experience->technologies->map(function (Technology $technology) {
                return $this->formatTechnologyForSSR($technology);
            })->toArray(),
            'type' => $experience->type,
            'startedAt' => $experience->started_at->toDateString(),
            'endedAt' => $experience->ended_at?->toDateString(),
            'startedAtFormatted' => $startedAtFormatted ?? '',
            'endedAtFormatted' => $endedAtFormatted,
        ];
    }

    /**
     * Get blog posts for public home page with latest article first
     *
     * @return Collection<int, BlogPost>
     */
    public function getBlogPostsForPublicHome(): Collection
    {
        return BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->where('content_type', ContentMarkdown::class)->orderBy('order');
            },
            'contents.content.translationKey.translations',
        ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Format the BlogPost model for Server-Side Rendering (SSR) with short excerpt.
     * Returns a SSRBlogPost TypeScript type compatible array.
     *
     * @param  BlogPost  $blogPost  The blog post to format
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatBlogPostForSSRShort(BlogPost $blogPost): array
    {
        /** @phpstan-ignore ternary.alwaysTrue */
        $title = $blogPost->titleTranslationKey
            ? $this->getTranslationWithFallback($blogPost->titleTranslationKey->translations) : '';
        $categoryName = $blogPost->category->nameTranslationKey
            ? $this->getTranslationWithFallback($blogPost->category->nameTranslationKey->translations) : '';
        $excerpt = $this->extractExcerptFromFirstTextBlock($blogPost, 150);

        return [
            'id' => $blogPost->id,
            'title' => $title,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category' => [
                'name' => $categoryName,
                'color' => $blogPost->category->color,
            ],
            'coverImage' => $blogPost->coverPicture ? $this->formatPictureForSSR($blogPost->coverPicture) : null,
            'publishedAt' => $blogPost->created_at,
            'publishedAtFormatted' => $this->formatDate($blogPost->created_at),
            'excerpt' => $excerpt,
        ];
    }

    /**
     * Format the BlogPost model for Server-Side Rendering (SSR) with long excerpt for hero.
     * Returns a SSRBlogPost TypeScript type compatible array.
     *
     * @param  BlogPost  $blogPost  The blog post to format
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatBlogPostForSSRHero(BlogPost $blogPost): array
    {
        /** @phpstan-ignore ternary.alwaysTrue */
        $title = $blogPost->titleTranslationKey
            ? $this->getTranslationWithFallback($blogPost->titleTranslationKey->translations) : '';
        $categoryName = $blogPost->category->nameTranslationKey
            ? $this->getTranslationWithFallback($blogPost->category->nameTranslationKey->translations) : '';
        $excerpt = $this->extractExcerptFromFirstTextBlock($blogPost, 300);

        return [
            'id' => $blogPost->id,
            'title' => $title,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category' => [
                'name' => $categoryName,
                'color' => $blogPost->category->color,
            ],
            'coverImage' => $blogPost->coverPicture ? $this->formatPictureForSSR($blogPost->coverPicture) : null,
            'publishedAt' => $blogPost->created_at,
            'publishedAtFormatted' => $this->formatDate($blogPost->created_at),
            'excerpt' => $excerpt,
        ];
    }

    /**
     * Extract excerpt from the first text block of a blog post
     *
     * @param  BlogPost  $blogPost  The blog post
     * @param  int  $maxLength  Maximum length of excerpt
     */
    private function extractExcerptFromFirstTextBlock(BlogPost $blogPost, int $maxLength = 200): string
    {
        // Get first markdown content ordered by position
        $firstTextContent = $blogPost->contents
            ->where('content_type', ContentMarkdown::class)
            ->sortBy('order')
            ->first();

        if (! $firstTextContent || ! $firstTextContent->content) {
            return '';
        }

        $markdownContent = $firstTextContent->content;

        // Ensure content is BlogContentMarkdown type
        if (! $markdownContent instanceof ContentMarkdown) {
            return '';
        }

        if (! $markdownContent->translationKey) {
            return '';
        }

        // Get text content with fallback
        $text = $this->getTranslationWithFallback($markdownContent->translationKey->translations);

        if (empty($text)) {
            return '';
        }

        // Remove markdown formatting
        $plainText = strip_tags(str_replace(['#', '*', '_', '`'], '', $text));

        // Clean up whitespace
        $plainText = preg_replace('/\s+/', ' ', trim($plainText)) ?? '';

        if (strlen($plainText) <= $maxLength) {
            return $plainText;
        }

        // Truncate at word boundary
        $truncated = substr($plainText, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }

    /**
     * Get blog posts for index page with filters and pagination
     *
     * @param  array{category?: string|array<string>|null, type?: string|null, sort?: string|null, search?: string|null}  $filters
     * @return array{
     *     data: array<int, array{
     *         id: int,
     *         title: string,
     *         slug: string,
     *         type: BlogPostType,
     *         category: array{name: string, color: string},
     *         coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *         publishedAt: string,
     *         publishedAtFormatted: string,
     *         excerpt: string
     *     }>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int,
     *     from: int|null,
     *     to: int|null
     * }
     */
    public function getBlogPostsForIndex(array $filters, int $perPage = 12): array
    {
        $query = BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->where('content_type', ContentMarkdown::class)->orderBy('order');
            },
            'contents.content.translationKey.translations',
        ]);

        // Apply category filter (handle array)
        if (! empty($filters['category'])) {
            $categories = is_array($filters['category']) ? $filters['category'] : [$filters['category']];
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }

        // Apply search filter
        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->whereHas('titleTranslationKey.translations', function (Builder $q) use ($searchTerm) {
                /** @phpstan-ignore argument.type */
                $q->where('text', 'like', '%'.$searchTerm.'%');
            });
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at');
                break;
            case 'alphabetical':
                $query->join('translation_keys', 'blog_posts.title_translation_key_id', '=', 'translation_keys.id')
                    ->join('translations', function ($join) {
                        $join->on('translation_keys.id', '=', 'translations.translation_key_id')
                            ->where('translations.locale', $this->locale);
                    })
                    ->orderBy('translations.text')
                    ->select('blog_posts.*');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate results
        $paginator = $query->paginate($perPage);

        // Format posts for SSR
        $formattedPosts = $paginator->map(fn ($post) => $this->formatBlogPostForSSRShort($post));

        return [
            'data' => $formattedPosts->toArray(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Get all blog categories for filters
     *
     * @return array<int, array{id: int, name: string, slug: string, color: CategoryColor}>
     */
    public function getBlogCategories(): array
    {
        $categories = BlogCategory::with('nameTranslationKey.translations')
            ->orderBy('order')
            ->get();

        return $categories->map(function ($category) {
            $name = $category->nameTranslationKey ?
                $this->getTranslationWithFallback($category->nameTranslationKey->translations) : '';

            return [
                'id' => $category->id,
                'name' => $name,
                'slug' => $category->slug,
                'color' => $category->color,
            ];
        })->toArray();
    }

    /**
     * Get all blog categories with post counts for filters
     *
     * @return array<int, array{id: int, name: string, slug: string, color: CategoryColor, postCount: int}>
     */
    public function getBlogCategoriesWithCounts(): array
    {
        $categories = BlogCategory::with(['nameTranslationKey.translations', 'blogPosts'])
            ->orderBy('order')
            ->get();

        return $categories->map(function ($category) {
            $name = $category->nameTranslationKey ?
                $this->getTranslationWithFallback($category->nameTranslationKey->translations) : '';

            return [
                'id' => $category->id,
                'name' => $name,
                'slug' => $category->slug,
                'color' => $category->color,
                'postCount' => $category->blogPosts->count(),
            ];
        })->toArray();
    }

    /**
     * Get a blog post by slug with all its content
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string,
     *     excerpt: string,
     *     contents: array<int, array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}, caption?: string}>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}>,
     *     gameReview?: array{gameTitle: string, releaseDate: Carbon|null, genre: string|null, developer: string|null, publisher: string|null, platforms: array<string, mixed>|null, rating: GameReviewRating|null, pros: string|null, cons: string|null, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}
     * }|null
     */
    public function getBlogPostBySlug(string $slug): ?array
    {
        $blogPost = BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->orderBy('order');
            },
            'contents.content' => function ($query) {
                // Load different relations based on content type
                $query->morphWith([
                    ContentMarkdown::class => ['translationKey.translations'],
                    ContentGallery::class => ['pictures'],
                    ContentVideo::class => ['video.coverPicture', 'captionTranslationKey.translations'],
                ]);
            },
            'gameReview.coverPicture',
            'gameReview.prosTranslationKey.translations',
            'gameReview.consTranslationKey.translations',
        ])
            ->where('slug', $slug)
            ->first();

        if (! $blogPost) {
            return null;
        }

        /** @phpstan-ignore ternary.alwaysTrue */
        $title = $blogPost->titleTranslationKey
            ? $this->getTranslationWithFallback($blogPost->titleTranslationKey->translations) : '';
        $categoryName = $blogPost->category->nameTranslationKey
            ? $this->getTranslationWithFallback($blogPost->category->nameTranslationKey->translations) : '';

        // Format contents
        $contents = $blogPost->contents->map(fn ($content) => $this->formatContentBlockForSSR($content));

        // Generate excerpt from first markdown content
        $excerpt = '';
        $firstMarkdownContent = $contents->first(function ($content) {
            return $content['content_type'] === ContentMarkdown::class;
        });

        if ($firstMarkdownContent && isset($firstMarkdownContent['markdown'])) {
            $excerpt = Str::limit(strip_tags($firstMarkdownContent['markdown']), 200);
        }

        $result = [
            'id' => $blogPost->id,
            'title' => $title,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category' => [
                'name' => $categoryName,
                'color' => $blogPost->category->color,
            ],
            'coverImage' => $blogPost->coverPicture ? $this->formatPictureForSSR($blogPost->coverPicture) : null,
            'publishedAt' => $blogPost->created_at,
            // @phpstan-ignore-next-line (Carbon type narrowing limitation)
            'publishedAtFormatted' => $blogPost->created_at instanceof Carbon ? $blogPost->created_at->locale($this->locale)->translatedFormat('j F Y') : '',
            'excerpt' => $excerpt,
            'contents' => $contents->toArray(),
        ];

        // Add game review data if it's a game review
        if ($blogPost->type === BlogPostType::GAME_REVIEW && $blogPost->gameReview) {
            $result['gameReview'] = $this->formatGameReviewForSSR($blogPost->gameReview);
        }

        return $result;
    }

    /**
     * Get a blog post draft for preview with all its content
     * Similar to getBlogPostBySlug but works with drafts
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string,
     *     excerpt: string,
     *     contents: array<int, array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}, caption?: string}>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}>,
     *     gameReview?: array{gameTitle: string, releaseDate: Carbon|null, genre: string|null, developer: string|null, publisher: string|null, platforms: array<string, mixed>|null, rating: GameReviewRating|null, pros: string|null, cons: string|null, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null},
     *     isPreview: bool
     * }
     */
    public function getBlogPostDraftForPreview(BlogPostDraft $draft): array
    {
        // Load all necessary relations
        $draft->load([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->orderBy('order');
            },
            'contents.content' => function ($query) {
                // Load different relations based on content type
                $query->morphWith([
                    ContentMarkdown::class => ['translationKey.translations'],
                    ContentGallery::class => ['pictures'],
                    ContentVideo::class => ['video.coverPicture', 'captionTranslationKey.translations'],
                ]);
            },
            'gameReviewDraft.coverPicture',
            'gameReviewDraft.prosTranslationKey.translations',
            'gameReviewDraft.consTranslationKey.translations',
        ]);

        $title = $draft->titleTranslationKey ?
            $this->getTranslationWithFallback($draft->titleTranslationKey->translations) : '';
        $categoryName = $draft->category->nameTranslationKey ?
            $this->getTranslationWithFallback($draft->category->nameTranslationKey->translations) : '';

        // Format contents (PREVIEW context shows all ready videos regardless of visibility)
        $contents = $draft->contents->map(
            fn ($content) => $this->formatContentBlockForSSR($content, ContentRenderContext::PREVIEW),
        );

        // Generate excerpt from first markdown content
        $excerpt = '';
        $firstMarkdownContent = $contents->first(function ($content) {
            return $content['content_type'] === ContentMarkdown::class;
        });

        if ($firstMarkdownContent && isset($firstMarkdownContent['markdown'])) {
            $excerpt = Str::limit(strip_tags($firstMarkdownContent['markdown']), 200);
        }

        $result = [
            'id' => $draft->id,
            'title' => $title,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'category' => [
                'name' => $categoryName,
                'color' => $draft->category->color,
            ],
            'coverImage' => $draft->coverPicture ? $this->formatPictureForSSR($draft->coverPicture) : null,
            'publishedAt' => $draft->created_at,
            // @phpstan-ignore-next-line (Carbon type narrowing limitation)
            'publishedAtFormatted' => $draft->created_at instanceof Carbon ? $draft->created_at->locale($this->locale)->translatedFormat('j F Y') : '',
            'excerpt' => $excerpt,
            'contents' => $contents->toArray(),
            'isPreview' => true, // Flag to indicate this is a preview
        ];

        // Add game review draft data if it's a game review
        if ($draft->type === BlogPostType::GAME_REVIEW && $draft->gameReviewDraft) {
            $result['gameReview'] = $this->formatGameReviewForSSR($draft->gameReviewDraft);
        }

        return $result;
    }

    /**
     * Format a single content block for SSR.
     * Handles ContentMarkdown, ContentGallery, and ContentVideo types.
     *
     * @return array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, mixed>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}
     */
    private function formatContentBlockForSSR(
        CreationContent|BlogPostContent|BlogPostDraftContent $content,
        ContentRenderContext $context = ContentRenderContext::PUBLIC,
    ): array {
        $result = [
            'id' => $content->id,
            'order' => $content->order,
            'content_type' => $content->content_type,
        ];

        $contentType = $content->content_type;
        $contentModel = $content->content;

        // Handle different content types
        if ($contentType === ContentMarkdown::class && $contentModel instanceof ContentMarkdown) {
            $markdownContent = $contentModel->translationKey ?
                $this->getTranslationWithFallback($contentModel->translationKey->translations) : '';
            // Resolve custom emojis (:emoji_name:) to HTML picture tags
            try {
                $result['markdown'] = $this->emojiResolver->resolveEmojisInMarkdown($markdownContent);
            } catch (Exception) {
                // Fallback to original markdown if emoji resolution fails
                $result['markdown'] = $markdownContent;
            }
        } elseif ($contentType === ContentGallery::class && $contentModel instanceof ContentGallery) {
            // Get caption translation keys from the pivot data
            $captionTranslationKeyIds = $contentModel->pictures
                ->pluck('pivot.caption_translation_key_id')
                ->filter()
                ->unique();

            // Load translation keys with their translations if any captions exist
            $captionTranslations = [];
            if ($captionTranslationKeyIds->isNotEmpty()) {
                $translationKeys = TranslationKey::with('translations')
                    ->whereIn('id', $captionTranslationKeyIds)
                    ->get()
                    ->keyBy('id');

                foreach ($translationKeys as $key => $translationKey) {
                    $captionTranslations[$key] = $this->getTranslationWithFallback($translationKey->translations);
                }
            }

            $result['gallery'] = [
                'id' => $contentModel->id,
                'pictures' => $contentModel->pictures->map(function (Picture $picture) use ($captionTranslations) {
                    $formattedPicture = $this->formatPictureForSSR($picture);

                    // Add caption if it exists in the pivot data
                    /** @phpstan-ignore property.notFound */
                    $captionTranslationKeyId = $picture->pivot?->caption_translation_key_id;
                    if ($captionTranslationKeyId && isset($captionTranslations[$captionTranslationKeyId])) {
                        $formattedPicture['caption'] = $captionTranslations[$captionTranslationKeyId];
                    }

                    return $formattedPicture;
                })->toArray(),
            ];
        } elseif ($contentType === ContentVideo::class && $contentModel instanceof ContentVideo) {
            $video = $contentModel->video;

            // For preview context, show all videos regardless of visibility (but still check if ready)
            // For public context, also check that visibility is PUBLIC
            $videoIsReady = $video && $video->status === VideoStatus::READY;
            $videoIsVisible = $context === ContentRenderContext::PREVIEW
                || ($video && $video->visibility === VideoVisibility::PUBLIC);

            if ($videoIsReady && $videoIsVisible) {
                $caption = null;
                if ($contentModel->captionTranslationKey) {
                    $caption = $this->getTranslationWithFallback($contentModel->captionTranslationKey->translations);
                }

                $formattedVideo = $this->formatVideoForSSR($video);
                $formattedVideo['caption'] = $caption;

                $result['video'] = $formattedVideo;
            }
        }

        return $result;
    }

    /**
     * Format a game review for SSR.
     *
     * @return array{gameTitle: string, releaseDate: Carbon|null, genre: string|null, developer: string|null, publisher: string|null, platforms: array<string, mixed>|null, rating: GameReviewRating|null, pros: string|null, cons: string|null, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}
     */
    private function formatGameReviewForSSR(GameReview|GameReviewDraft $gameReview): array
    {
        $pros = $gameReview->prosTranslationKey
            ? $this->getTranslationWithFallback($gameReview->prosTranslationKey->translations) : null;
        $cons = $gameReview->consTranslationKey
            ? $this->getTranslationWithFallback($gameReview->consTranslationKey->translations) : null;

        return [
            'gameTitle' => $gameReview->game_title,
            'releaseDate' => $gameReview->release_date,
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'rating' => $gameReview->rating,
            'pros' => $pros,
            'cons' => $cons,
            'coverPicture' => $gameReview->coverPicture ? $this->formatPictureForSSR($gameReview->coverPicture) : null,
        ];
    }
}
