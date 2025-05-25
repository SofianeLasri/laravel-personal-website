<?php

namespace App\Http\Controllers\Public;

use App\Enums\TechnologyType;
use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Services\PublicControllersService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(): Response
    {
        Log::debug('Locale: '.app()->getLocale());
        $developmentStats = $this->service->getDevelopmentStats();

        return Inertia::render('public/Home', [
            'locale' => app()->getLocale(),
            'translations' => [
                'home' => __('home'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'career' => __('career'),
                'search' => __('search'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'yearsOfExperience' => $developmentStats['yearsOfExperience'],
            'developmentCreationsCount' => $developmentStats['count'],
            'masteredFrameworksCount' => Technology::where('type', TechnologyType::FRAMEWORK)->count(),
            'laravelCreations' => $this->service->getLaravelCreations(),
            'technologyExperiences' => $this->service->getTechnologyExperiences(),
            'experiences' => $this->service->getExperiences(),
        ]);
    }
}
