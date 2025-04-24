<?php

namespace App\Http\Controllers\Public;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Models\Translation;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
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
                'name' => $creation->name,
                'slug' => $creation->slug,
                'logo' => $creation->logo->getUrl('medium', 'avif'),
                'coverImage' => $creation->coverImage->getUrl('medium', 'avif'),
                'startedAt' => $creation->started_at,
                'endedAt' => $creation->ended_at,
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

        return Inertia::render('public/Home', [
            'socialMediaLinks' => $socialMediaLinks,
            'yearsOfExperience' => $yearsOfExperience,
            'developmentCreationsCount' => $developmentCreationsCount,
            'technologiesCount' => $technologiesCount,
            'laravelCreations' => $laravelCreationsJson,
        ]);
    }
}
