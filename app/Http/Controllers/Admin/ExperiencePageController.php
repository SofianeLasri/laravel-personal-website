<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Technology;
use Inertia\Inertia;
use Inertia\Response;

class ExperiencePageController extends Controller
{
    public function listPage(): Response
    {
        $experiences = Experience::with([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
            'logo',
            'technologies',
        ])->orderBy('started_at', 'desc')->get();

        return Inertia::render('dashboard/experiences/List', [
            'experiences' => $experiences,
        ]);
    }

    public function editPage(?int $id = null): Response
    {
        $experience = null;

        if ($id) {
            $experience = Experience::with([
                'titleTranslationKey.translations',
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'logo',
                'technologies',
            ])->find($id);
        }

        $technologies = Technology::with(['descriptionTranslationKey.translations'])
            ->withCount('creations')
            ->get();

        return Inertia::render('dashboard/experiences/Edit', [
            'experience' => $experience,
            'technologies' => $technologies,
        ]);
    }
}
