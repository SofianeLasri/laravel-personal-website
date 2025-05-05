<?php

namespace App\Services;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicControllersService
{
    private string $locale;

    private const DEVELOPMENT_TYPES = [
        CreationType::PORTFOLIO,
        CreationType::LIBRARY,
        CreationType::TOOL,
        CreationType::WEBSITE,
    ];

    private array $creationCountByTechnology;

    public function __construct()
    {
        $this->locale = app()->getLocale();
        $this->creationCountByTechnology = $this->getCreationCountByTechnology();
    }

    /**
     * Return projects count per technology.
     *
     * @return array{int, int}
     */
    public function getCreationCountByTechnology(): array
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
     * @return array{'yearsOfExperience': int, 'count': int}
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
     */
    public function getLaravelCreations(): Collection
    {
        $laravel = Technology::where('name', 'Laravel')->first();

        if (! $laravel) {
            return collect();
        }

        $developmentCreations = Creation::with([
            'technologies',
            'logo',
            'coverImage',
            'shortDescriptionTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
        ])
            ->whereIn('type', self::DEVELOPMENT_TYPES)
            ->whereHas('technologies', function ($query) use ($laravel) {
                $query->where('technologies.id', $laravel->id);
            })
            ->get();

        $creations = $developmentCreations->map(function (Creation $creation) {
            $shortDescriptionTranslation = $creation->shortDescriptionTranslationKey->translations->first();

            return [
                'id' => $creation->id,
                'name' => $creation->name,
                'slug' => $creation->slug,
                'logo' => $creation->logo->getUrl('medium', 'avif'),
                'coverImage' => $creation->coverImage->getUrl('medium', 'avif'),
                'startedAt' => $creation->started_at,
                'endedAt' => $creation->ended_at,
                'startedAtFormatted' => $this->formatDate($creation->started_at),
                'endedAtFormatted' => $this->formatDate($creation->ended_at),
                'type' => $creation->type->label(),
                'shortDescription' => $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '',
                'technologies' => $creation->technologies->map(function ($technology) {
                    return $this->formatTechnologyForSSR($technology);
                }),
            ];
        });

        return $creations->sortByDesc(function ($creation) {
            return $creation['endedAt'] ?? now();
        })->values();
    }

    /**
     * Get all the projects.
     * Returns a SSRSimplifiedCreation TypeScript type compatible object.
     */
    public function getCreations(): Collection
    {
        $creations = Creation::all()->withRelationshipAutoloading();

        return $creations->map(function (Creation $creation) {
            return $this->formatCreationForSSRShort($creation);
        });
    }

    /**
     * Format the Creation model for Server-Side Rendering (SSR).
     * Returns a SSRSimplifiedCreation TypeScript type compatible array.
     *
     * @param  Creation  $creation  The creation to format
     * @return array{id: int, name: string, slug: string, logo: string, coverImage: string, startedAt: string, endedAt: string|null, startedAtFormatted: string|null, endedAtFormatted: string|null, type: CreationType, shortDescription: string|null, technologies: array<int, array{id: int, creationCount: mixed, name: string, type: TechnologyType, svgIcon: string}>}
     */
    public function formatCreationForSSRShort(Creation $creation): array
    {
        $shortDescriptionTranslation = $creation->shortDescriptionTranslationKey->translations->where('locale', $this->locale)->first();

        return [
            'id' => $creation->id,
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo' => $creation->logo->getUrl('medium', 'avif'),
            'coverImage' => $creation->coverImage->getUrl('medium', 'avif'),
            'startedAt' => $creation->started_at,
            'endedAt' => $creation->ended_at,
            'startedAtFormatted' => $this->formatDate($creation->started_at),
            'endedAtFormatted' => $this->formatDate($creation->ended_at),
            'type' => $creation->type,
            'shortDescription' => $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '',
            'technologies' => $creation->technologies->map(function ($technology) {
                return $this->formatTechnologyForSSR($technology);
            }),
        ];
    }

    /**
     * Format the Creation model for Server-Side Rendering (SSR) with full description.
     * Returns a SSRFullCreation TypeScript type compatible array.
     *
     * @param  Creation  $creation  The creation to format
     * @return array{id: int, name: string, slug: string, logo: string, coverImage: string, startedAt: string, endedAt: string|null, startedAtFormatted: string|null, endedAtFormatted: string|null, type: CreationType, shortDescription: string|null, fullDescription: string|null, features: array<int, array{id: int, title: string, description: string, picture: string}>}
     */
    public function formatCreationForSSRFull(Creation $creation): array
    {
        $shortCreation = $this->formatCreationForSSRShort($creation);
        $fullDescriptionTranslation = $creation->fullDescriptionTranslationKey->translations->where('locale', $this->locale)->first();

        $shortCreation['fullDescription'] = $fullDescriptionTranslation ?? '';
        $shortCreation['features'] = $creation->features->map(function ($feature) {
            $titleTranslation = $feature->titleTranslationKey->translations->where('locale', $this->locale)->first();
            $descriptionTranslation = $feature->descriptionTranslationKey->translations->where('locale', $this->locale)->first();
            $pictureUrl = $feature->picture ? $feature->picture->getUrl('medium', 'avif') : null;

            return [
                'id' => $feature->id,
                'title' => $titleTranslation ? $titleTranslation->text : '',
                'description' => $descriptionTranslation ? $descriptionTranslation->text : '',
                'picture' => $pictureUrl,
            ];
        });

        return $shortCreation;
    }

    /**
     * Format the Technology model for Server-Side Rendering (SSR).
     * Returns a SSRTechnology TypeScript type compatible array.
     *
     * @return array{id: int, creationCount: mixed, name: string, type: TechnologyType, svgIcon: string}
     */
    public function formatTechnologyForSSR(Technology $technology): array
    {
        return [
            'id' => $technology->id,
            'creationCount' => $this->creationCountByTechnology[$technology->id] ?? 0,
            'name' => $technology->name,
            'type' => $technology->type,
            'svgIcon' => $technology->svg_icon,
        ];
    }

    /**
     * Get all the technology experiences.
     * Returns a SSRTechnologyExperience TypeScript type compatible object.
     */
    public function getTechnologyExperiences(): Collection
    {
        $experiences = TechnologyExperience::with([
            'technology',
            'descriptionTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
        ])->get();

        return $experiences->map(function (TechnologyExperience $experience) {
            $description = $experience->descriptionTranslationKey->translations->first();
            $technologyId = $experience->technology->id;

            return [
                'id' => $experience->id,
                'name' => $experience->technology->name,
                'description' => $description ? $description->text : '',
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
     */
    public function getExperiences(): Collection
    {
        $experiences = Experience::with([
            'technologies' => function ($query) {
                $query->with([
                    'descriptionTranslationKey.translations' => function ($query) {
                        $query->where('locale', $this->locale);
                    },
                ]);
            },
            'logo',
            'titleTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
            'shortDescriptionTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
            'fullDescriptionTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
        ])->get();

        return $experiences->map(function (Experience $experience) {
            $title = $experience->titleTranslationKey->translations->first();
            $shortDescription = $experience->shortDescriptionTranslationKey->translations->first();
            $fullDescription = $experience->fullDescriptionTranslationKey->translations->first();

            return [
                'id' => $experience->id,
                'title' => $title ? $title->text : '',
                'organizationName' => $experience->organization_name,
                'logo' => $experience->logo->getUrl('medium', 'webp'),
                'location' => $experience->location,
                'websiteUrl' => $experience->website_url,
                'shortDescription' => $shortDescription ? $shortDescription->text : '',
                'fullDescription' => $fullDescription ? $fullDescription->text : '',
                'technologies' => $experience->technologies->map(function ($technology) {
                    $description = $technology->descriptionTranslationKey->translations->first();

                    return [
                        'name' => $technology->name,
                        'svgIcon' => $technology->svg_icon,
                        'description' => $description ? $description->text : '',
                    ];
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
}
