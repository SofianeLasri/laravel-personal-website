<?php

namespace App\Services;

use App\Enums\CreationType;
use App\Enums\ExperienceType;
use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Feature;
use App\Models\Screenshot;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Support\Carbon;
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

    /**
     * @var array<int, int|null>
     */
    private array $creationCountByTechnology;

    public function __construct()
    {
        $this->locale = app()->getLocale();
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
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: string|null,
     *     coverImage: string|null,
     *     startedAt: Carbon,
     *     endedAt: Carbon|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string,
     *     technologies: Collection<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}>
     * }>
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
            $shortDescription = '';
            if ($creation->shortDescriptionTranslationKey) {
                $shortDescriptionTranslation = $creation->shortDescriptionTranslationKey->translations->where('locale', $this->locale)->first();
                $shortDescription = $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '';
            }

            return [
                'id' => $creation->id,
                'name' => $creation->name,
                'slug' => $creation->slug,
                'logo' => $creation->logo ? $creation->logo->getUrl('medium', 'avif') : null,
                'coverImage' => $creation->coverImage ? $creation->coverImage->getUrl('large', 'avif') : null,
                'startedAt' => $creation->started_at,
                'endedAt' => $creation->ended_at,
                'startedAtFormatted' => $this->formatDate($creation->started_at),
                'endedAtFormatted' => $this->formatDate($creation->ended_at),
                'type' => $creation->type,
                'shortDescription' => $shortDescription,
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
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: string|null,
     *     coverImage: string|null,
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
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: string|null,
     *     coverImage: string|null,
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
        $shortDescription = null;
        if ($creation->shortDescriptionTranslationKey) {
            $shortDescriptionTranslation = $creation->shortDescriptionTranslationKey->translations->where('locale', $this->locale)->first();
            $shortDescription = $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '';
        }

        return [
            'id' => $creation->id,
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo' => $creation->logo ? $creation->logo->getUrl('medium', 'avif') : null,
            'coverImage' => $creation->coverImage ? $creation->coverImage->getUrl('medium', 'avif') : null,
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
     *     logo: string|null,
     *     coverImage: string|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     fullDescription: string|null,
     *     externalUrl: string|null,
     *     sourceCodeUrl: string|null,
     *     features: array<int, array{id: int, title: string, description: string, picture: string}>,
     *     screenshots: array<int, array{id: int, picture: string|null, caption: string}>,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, type: TechnologyType, svgIcon: string}>}
     */
    public function formatCreationForSSRFull(Creation $creation): array
    {
        $response = $this->formatCreationForSSRShort($creation);

        $fullDescription = '';
        if ($creation->fullDescriptionTranslationKey) {
            $fullDescriptionTranslation = $creation->fullDescriptionTranslationKey->translations->where('locale', $this->locale)->first();
            $fullDescription = $fullDescriptionTranslation ? $fullDescriptionTranslation->text : '';
        }

        $response['fullDescription'] = $fullDescription;
        $response['externalUrl'] = $creation->external_url;
        $response['sourceCodeUrl'] = $creation->source_code_url;
        $response['features'] = $creation->features->map(function (Feature $feature) {
            $title = '';
            if ($feature->titleTranslationKey) {
                $titleTranslation = $feature->titleTranslationKey->translations->where('locale', $this->locale)->first();
                $title = $titleTranslation ? $titleTranslation->text : '';
            }

            $description = '';
            if ($feature->descriptionTranslationKey) {
                $descriptionTranslation = $feature->descriptionTranslationKey->translations->where('locale', $this->locale)->first();
                $description = $descriptionTranslation ? $descriptionTranslation->text : '';
            }

            $pictureUrl = $feature->picture ? $feature->picture->getUrl('medium', 'avif') : null;

            return [
                'id' => $feature->id,
                'title' => $title,
                'description' => $description,
                'picture' => $pictureUrl,
            ];
        })->toArray();

        $response['screenshots'] = $creation->screenshots->map(function (Screenshot $screenshot) {
            $caption = '';
            if ($screenshot->captionTranslationKey) {
                $captionTranslation = $screenshot->captionTranslationKey->translations->where('locale', $this->locale)->first();
                $caption = $captionTranslation ? $captionTranslation->text : '';
            }

            return [
                'id' => $screenshot->id,
                'picture' => $screenshot->picture ? $screenshot->picture->getUrl('large', 'avif') : null,
                'caption' => $caption,
            ];
        })->toArray();

        return $response;
    }

    /**
     * Format the Technology model for Server-Side Rendering (SSR).
     * Returns a SSRTechnology TypeScript type compatible array.
     *
     * @return array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, svgIcon: string}
     */
    public function formatTechnologyForSSR(Technology $technology): array
    {
        $description = '';
        if ($technology->descriptionTranslationKey) {
            $descriptionTranslation = $technology->descriptionTranslationKey->translations->where('locale', $this->locale)->first();
            $description = $descriptionTranslation ? $descriptionTranslation->text : '';
        }

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
            $descriptionTranslation = $experience->descriptionTranslationKey->translations->where('locale', $this->locale)->first();
            $description = $descriptionTranslation ? $descriptionTranslation->text : '';

            return [
                'id' => $experience->id,
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
     *     logo: string|null,
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
            $title = '';
            if ($experience->titleTranslationKey) {
                $titleTranslation = $experience->titleTranslationKey->translations->where('locale', $this->locale)->first();
                $title = $titleTranslation ? $titleTranslation->text : '';
            }
            $shortDescription = '';
            if ($experience->shortDescriptionTranslationKey) {
                $shortDescriptionTranslation = $experience->shortDescriptionTranslationKey->translations->where('locale', $this->locale)->first();
                $shortDescription = $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '';
            }
            $fullDescription = '';
            if ($experience->fullDescriptionTranslationKey) {
                $fullDescriptionTranslation = $experience->fullDescriptionTranslationKey->translations->where('locale', $this->locale)->first();
                $fullDescription = $fullDescriptionTranslation ? $fullDescriptionTranslation->text : '';
            }

            return [
                'id' => $experience->id,
                'title' => $title,
                'organizationName' => $experience->organization_name,
                'logo' => $experience->logo ? $experience->logo->getUrl('medium', 'avif') : null,
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
