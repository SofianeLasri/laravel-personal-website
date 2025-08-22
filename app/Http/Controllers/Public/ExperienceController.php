<?php

namespace App\Http\Controllers\Public;

use App\Models\Experience;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ExperienceController extends PublicController
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(Request $request, string $slug): InertiaResponse
    {
        $experience = Experience::where('slug', $slug)
            ->firstOrFail()->load([
                'titleTranslationKey.translations',
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'logo',
                'technologies.iconPicture',
            ]);

        $formattedExperience = $this->service->formatExperienceForSSR($experience);

        return Inertia::render('public/Experience', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'experience' => __('experience'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
                'projects' => [
                    'types' => __('projects.types'),
                ],
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'experience' => $formattedExperience,
        ]);
    }
}
