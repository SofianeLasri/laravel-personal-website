<?php

namespace App\Http\Controllers\Public;

use App\Models\Creation;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends PublicController
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $legacyMappings = config('legacy-projects.mappings', []);
        if (isset($legacyMappings[$slug])) {
            $slug = $legacyMappings[$slug];
        }

        $creation = Creation::where('slug', $slug)->firstOrFail()
            ->withRelationshipAutoloading();

        $formattedCreation = $this->service->formatCreationForSSRFull($creation);

        return Inertia::render('public/Project', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
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
