<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Services\PublicControllersService;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(): Response
    {
        $locale = app()->getLocale();

        $technologies = Technology::all();

        return inertia('public/Projects', [
            'locale' => $locale,
            'translations' => [
                'projects' => __('projects'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'creations' => $this->service->getCreations(),
            'technologies' => $technologies->map(function ($technology) {
                return $this->service->formatTechnologyForSSR($technology);
            }),
        ]);
    }
}
