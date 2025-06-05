<?php

namespace App\Services;

use App\Enums\CreationType;
use App\Enums\ExperienceType;
use App\Enums\TechnologyType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Certification;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicControllersService
{
    private string $locale;

    private string $fallbackLocale;

    private const DEVELOPMENT_TYPES = [
        CreationType::PORTFOLIO,
        CreationType::LIBRARY,
        CreationType::TOOL,
        CreationType::WEBSITE,
    ];

    /**
     * @var array<int, int|null>
     */
    private array $creationCountByTechnology;

    public function __construct()
    {
        $this->locale = app()->getLocale();
        $this->fallbackLocale = config('app.fallback_locale');
        $this->creationCountByTechnology = $this->calcCreationCountByTechnology();
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
        $oldestDate = $baseQuery->min('started_at');

        $stats['count'] = $creationCount;
        if ($oldestDate) {
            $stats['yearsOfExperience'] = round(now()->diffInYears(Carbon::parse($oldestDate), true));
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
     *      logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *      coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *      startedAt: string,
     *      endedAt: string|null,
     *      startedAtFormatted: string|null,
     *      endedAtFormatted: string|null,
     *      type: CreationType,
     *      shortDescription: string|null,
     *      technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>
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
            })->get()->withRelationshipAutoloading();

        $creations = $developmentCreations->map(function (Creation $creation) {
            return $this->formatCreationForSSRShort($creation);
        });

        return $creations->sortByDesc(function ($creation) {
            return $creation['endedAt'] ?? now();
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
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>
     * }>
     */
    public function getCreations(): Collection
    {
        $creations = Creation::all()->withRelationshipAutoloading()->sortByDesc('ended_at');

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
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>
     * }
     */
    public function formatCreationForSSRShort(Creation $creation): array
    {
        $shortDescription = $this->getTranslationWithFallback($creation->shortDescriptionTranslationKey->translations);

        return [
            'id' => $creation->id,
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo' => $this->formatPictureForSSR($creation->logo),
            'coverImage' => $this->formatPictureForSSR($creation->coverImage),
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
     * @return array{id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     fullDescription: string|null,
     *     externalUrl: string|null,
     *     sourceCodeUrl: string|null,
     *     features: array<int, array{id: int, title: string, description: string, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     screenshots: array<int, array{id: int, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, caption: string}>,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, type: TechnologyType, svgIcon: string}>,
     *     people: array<int, array{id: int, name: string, url: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     videos: array<int, array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>}
     */
    public function formatCreationForSSRFull(Creation $creation): array
    {
        $response = $this->formatCreationForSSRShort($creation);

        $fullDescription = $this->getTranslationWithFallback($creation->fullDescriptionTranslationKey->translations);

        $response['fullDescription'] = $fullDescription;
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
     * @return array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}
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
            'svgIcon' => $technology->svg_icon,
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
     *     svgIcon: string}>
     */
    public function getTechnologyExperiences(): Collection
    {
        $experiences = TechnologyExperience::all()->withRelationshipAutoloading();

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
                'svgIcon' => $experience->technology->svg_icon,
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
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}},
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: Collection<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>,
     *     type: ExperienceType,
     *     startedAt: Carbon,
     *     endedAt: Carbon|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null}>
     */
    public function getExperiences(): Collection
    {
        $experiences = Experience::all()->withRelationshipAutoloading();

        return $experiences->map(function (Experience $experience) {
            $title = $this->getTranslationWithFallback($experience->titleTranslationKey->translations);
            $shortDescription = $this->getTranslationWithFallback($experience->shortDescriptionTranslationKey->translations);
            $fullDescription = $this->getTranslationWithFallback($experience->fullDescriptionTranslationKey->translations);

            return [
                'id' => $experience->id,
                'title' => $title,
                'organizationName' => $experience->organization_name,
                'logo' => $this->formatPictureForSSR($experience->logo),
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
    private function getTranslationWithFallback(\Illuminate\Database\Eloquent\Collection $translations): string
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
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>,
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
     *     educationExperiences: Collection<int, array{id: int, title: string, organizationName: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>,
     *     workExperiences: Collection<int, array{id: int, title: string, organizationName: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>
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
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }
     */
    public function formatExperienceForSSR(Experience $experience): array
    {
        $title = $this->getTranslationWithFallback($experience->titleTranslationKey->translations);
        $shortDescription = $this->getTranslationWithFallback($experience->shortDescriptionTranslationKey->translations);
        $fullDescription = $this->getTranslationWithFallback($experience->fullDescriptionTranslationKey->translations);

        $startedAtFormatted = $this->formatDate($experience->started_at);
        $endedAtFormatted = $this->formatDate($experience->ended_at);

        return [
            'id' => $experience->id,
            'title' => $title,
            'organizationName' => $experience->organization_name,
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
}
