<?php

namespace App\Http\Controllers\Public;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\SocialMediaLink;
use App\Models\Technology;
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

        return Inertia::render('public/Home', [
            'socialMediaLinks' => $socialMediaLinks,
            'yearsOfExperience' => $yearsOfExperience,
            'developmentCreationsCount' => $developmentCreationsCount,
            'technologiesCount' => $technologiesCount,
        ]);
    }
}
