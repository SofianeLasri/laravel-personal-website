<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Public\PublicController;
use App\Models\SocialMediaLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            ],
            'socialMediaLinks' => $socialMediaLinks,
        ]);
    }
}
