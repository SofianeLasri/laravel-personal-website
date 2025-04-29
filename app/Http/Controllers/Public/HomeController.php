<?php

namespace App\Http\Controllers\Public;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /** @var string La locale actuelle */
    private string $locale;

    /** @var array Types de création pour le développement */
    private const DEVELOPMENT_TYPES = [
        CreationType::PORTFOLIO,
        CreationType::LIBRARY,
        CreationType::TOOL,
        CreationType::WEBSITE,
    ];

    /** @var array Comptage des créations par technologie */
    private array $creationCountByTechnology = [];

    public function __invoke(): Response
    {
        $this->locale = app()->getLocale();

        $this->preloadCreationCountsByTechnology();

        $developmentStats = $this->getDevelopmentStats();

        return Inertia::render('public/Home', [
            'socialMediaLinks' => $this->getSocialMediaLinks(),
            'yearsOfExperience' => $developmentStats['yearsOfExperience'],
            'developmentCreationsCount' => $developmentStats['count'],
            'technologiesCount' => $this->getTechnologiesCount(),
            'laravelCreations' => $this->getLaravelCreations(),
            'technologyExperiences' => $this->getTechnologyExperiences(),
            'experiences' => $this->getExperiences(),
        ]);
    }

    private function preloadCreationCountsByTechnology(): void
    {
        $counts = DB::table('creation_technology')
            ->select('technology_id', DB::raw('COUNT(creation_id) as count'))
            ->groupBy('technology_id')
            ->get();

        foreach ($counts as $count) {
            $this->creationCountByTechnology[$count->technology_id] = $count->count;
        }
    }

    private function getSocialMediaLinks(): Collection
    {
        return SocialMediaLink::all();
    }

    private function getDevelopmentStats(): array
    {
        $stats = [
            'count' => 0,
            'yearsOfExperience' => 0,
        ];

        $developmentInfo = Creation::whereIn('type', self::DEVELOPMENT_TYPES)
            ->select(DB::raw('COUNT(*) as count, MIN(started_at) as oldest_date'))
            ->first();

        if ($developmentInfo) {
            $stats['count'] = $developmentInfo->count;

            if ($developmentInfo->oldest_date) {
                $stats['yearsOfExperience'] = round(now()->diffInYears(Carbon::parse($developmentInfo->oldest_date), true));
            }
        }

        return $stats;
    }

    private function getTechnologiesCount(): int
    {
        return Technology::where('type', TechnologyType::FRAMEWORK)->count();
    }

    private function getLaravelCreations(): Collection
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
                    return [
                        'name' => $technology->name,
                        'svgIcon' => $technology->svg_icon,
                    ];
                }),
            ];
        });

        return $creations->sortByDesc(function ($creation) {
            return $creation['endedAt'] ?? now();
        })->values();
    }

    private function getTechnologyExperiences(): Collection
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

    private function getExperiences(): Collection
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
    private function formatDate(Carbon|string|null $date): ?string
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
