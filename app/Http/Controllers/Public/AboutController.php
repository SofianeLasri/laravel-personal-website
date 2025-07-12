<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialMediaLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends PublicController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('public/About', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'about' => __('about'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
        ]);
    }
}
