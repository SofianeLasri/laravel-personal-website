<?php

namespace App\Http\Controllers\Public;

use App\Enums\TechnologyType;
use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use App\Models\TechnologyExperience;
use App\Services\PublicControllersService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(): Response
    {
        $developmentStats = $this->service->getDevelopmentStats();
        // Laravel + tech with experience texts
        $masteredFrameworksCount = 1 + TechnologyExperience::join('technologies', 'technologies.id', '=', 'technology_experiences.technology_id')->where('technologies.type', TechnologyType::FRAMEWORK)->count();

        return Inertia::render('public/Home', [
            'locale' => app()->getLocale(),
            'translations' => [
                'home' => __('home'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'career' => __('career'),
                'search' => __('search'),
                'projects' => [
                    'types' => __('projects.types'),
                ],
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'yearsOfExperience' => $developmentStats['yearsOfExperience'],
            'developmentCreationsCount' => $developmentStats['count'],
            'masteredFrameworksCount' => $masteredFrameworksCount,
            'laravelCreations' => $this->service->getLaravelCreations(),
            'technologyExperiences' => $this->service->getTechnologyExperiences(),
            'experiences' => $this->service->getExperiences(),
        ]);
    }
}
