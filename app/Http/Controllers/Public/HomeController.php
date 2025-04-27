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
use App\Models\Translation;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
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

    public function __invoke(): Response
    {
        $socialMediaLinks = SocialMediaLink::all();

        $creations = Creation::all();
        $developmentCreations = $creations->where('type', CreationType::PORTFOLIO)
            ->concat($creations->where('type', CreationType::LIBRARY))
            ->concat($creations->where('type', CreationType::TOOL))
            ->concat($creations->where('type', CreationType::WEBSITE));

        $yearsOfExperience = round(now()->diffInYears($developmentCreations->min('started_at'), true));
        $developmentCreationsCount = $developmentCreations->count();

        $technologiesCount = Technology::where('type', TechnologyType::FRAMEWORK)->count();

        // Creations where the Technology name equals 'Laravel'
        $laravelCreations = $developmentCreations->filter(function (Creation $creation) {
            return $creation->technologies->contains('name', 'Laravel');
        });

        $laravelCreationsJson = $laravelCreations->map(function (Creation $creation) {
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
                'shortDescription' => Translation::findByKeyAndLocale($creation->shortDescriptionTranslationKey->key, app()->getLocale())->text,
                'technologies' => $creation->technologies->map(function (Technology $technology) {
                    return [
                        'name' => $technology->name,
                        'svgIcon' => $technology->svg_icon,
                    ];
                }),
            ];
        });

        $laravelCreationsJson = $laravelCreationsJson->sortByDesc(function ($creation) {
            return $creation['endedAt'] ?? now();
        })->values();

        $technologyExperience = TechnologyExperience::all()->load([
            'technology', 'descriptionTranslationKey', 'descriptionTranslationKey.translations',
        ]);

        $technologyExperienceJson = $technologyExperience->map(function (TechnologyExperience $experience) {
            return [
                'id' => $experience->id,
                'name' => $experience->technology->name,
                'description' => Translation::findByKeyAndLocale($experience->descriptionTranslationKey->key, app()->getLocale())->text,
                'creationCount' => $experience->technology->creations()->count(),
                'type' => $experience->technology->type,
                'typeLabel' => $experience->technology->type->label(),
                'svgIcon' => $experience->technology->svg_icon,
            ];
        });

        $experiences = Experience::all()->withRelationshipAutoloading();

        $experiencesJson = $experiences->map(function (Experience $experience) {
            return [
                'id' => $experience->id,
                'title' => Translation::findByKeyAndLocale($experience->titleTranslationKey->key, app()->getLocale())->text,
                'organizationName' => $experience->organization_name,
                'logo' => $experience->logo->getUrl('medium', 'webp'),
                'location' => $experience->location,
                'websiteUrl' => $experience->website_url,
                'shortDescription' => Translation::findByKeyAndLocale($experience->shortDescriptionTranslationKey->key, app()->getLocale())->text,
                'fullDescription' => Translation::findByKeyAndLocale($experience->fullDescriptionTranslationKey->key, app()->getLocale())->text,
                'technologies' => $experience->technologies->map(function (Technology $technology) {
                    return [
                        'name' => $technology->name,
                        'svgIcon' => $technology->svg_icon,
                        'description' => Translation::findByKeyAndLocale($technology->descriptionTranslationKey->key, app()->getLocale())->text,
                    ];
                }),
                'type' => $experience->type,
                'startedAt' => $experience->started_at,
                'endedAt' => $experience->ended_at,
                'startedAtFormatted' => $this->formatDate($experience->started_at),
                'endedAtFormatted' => $this->formatDate($experience->ended_at),
            ];
        });

        return Inertia::render('public/Home', [
            'socialMediaLinks' => $socialMediaLinks,
            'yearsOfExperience' => $yearsOfExperience,
            'developmentCreationsCount' => $developmentCreationsCount,
            'technologiesCount' => $technologiesCount,
            'laravelCreations' => $laravelCreationsJson,
            'technologyExperiences' => $technologyExperienceJson,
            'experiences' => $experiencesJson,
        ]);
    }
}
