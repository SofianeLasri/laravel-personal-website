<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(string $slug): Response
    {
        $creation = Creation::where('slug', $slug)->firstOrFail()
            ->withRelationshipAutoloading();

        $formattedCreation = $this->service->formatCreationForSSRFull($creation);

        return Inertia::render('public/Project', [
            'locale' => app()->getLocale(),
            'translations' => [
                'project' => __('project'),
                'projects' => __('projects'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'creation' => $formattedCreation,
        ]);
    }
}
