<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends PublicController
{
    public function __invoke(Request $request, PublicControllersService $publicService): Response
    {
        return Inertia::render('public/BlogPost', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
                'blog' => __('blog'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
        ]);
    }
}
