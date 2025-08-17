<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Public\PublicController;
use App\Models\SocialMediaLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tighten\Ziggy\Ziggy;

class ErrorController extends PublicController
{
    /**
     * Display the 404 error page.
     */
    public function show404(Request $request): Response
    {
        $socialMediaLinks = SocialMediaLink::all();

        return Inertia::render('public/Error404', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'errors' => __('errors'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
            ],
            'socialMediaLinks' => $socialMediaLinks,

            // We add this because the 404 page doesn't seem to use the Inertia middleware.
            // So its created SSR errors with the Ziggy location and routes.
            'name' => config('app.name'),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }
}
