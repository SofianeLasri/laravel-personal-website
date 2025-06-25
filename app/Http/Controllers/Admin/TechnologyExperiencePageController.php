<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Inertia\Inertia;
use Inertia\Response;

class TechnologyExperiencePageController extends Controller
{
    public function __invoke(): Response
    {
        $technologies = Technology::with(['descriptionTranslationKey.translations', 'iconPicture'])
            ->withCount('creations')
            ->get();

        $technologyExperiences = TechnologyExperience::with(['technology', 'descriptionTranslationKey.translations'])
            ->get();

        return Inertia::render('dashboard/technology-experiences/List', [
            'technologies' => $technologies,
            'technologyExperiences' => $technologyExperiences,
        ]);
    }
}
