<?php

namespace App\Http\Controllers\Public;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends PublicController
{
    public function __construct(protected PublicControllersService $service) {}

    public function __invoke(Request $request, string $slug): InertiaResponse|RedirectResponse
    {
        $legacyMappings = config('legacy-projects.mappings', []);
        if (isset($legacyMappings[$slug])) {
            return redirect()->route('public.projects.show', ['slug' => $legacyMappings[$slug]], 301);
        }

        $creation = Creation::with([
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
            'logo',
            'coverImage',
            'technologies.iconPicture',
            'technologies.descriptionTranslationKey.translations',
            'features.picture',
            'features.titleTranslationKey.translations',
            'features.descriptionTranslationKey.translations',
            'screenshots.picture',
            'screenshots.captionTranslationKey.translations',
            'people.picture',
            'videos.coverPicture',
            'contents' => function ($query) {
                $query->orderBy('order');
            },
            'contents.content' => function ($query) {
                // Load different relations based on content type
                $query->morphWith([
                    ContentMarkdown::class => ['translationKey.translations'],
                    ContentGallery::class => ['pictures'],
                    ContentVideo::class => ['video.coverPicture', 'captionTranslationKey.translations'],
                ]);
            },
        ])->where('slug', $slug)->firstOrFail();

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
