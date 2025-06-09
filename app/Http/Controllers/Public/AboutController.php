<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('public/About', [
            'locale' => app()->getLocale(),
            'translations' => [
                'about' => __('about'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
        ]);
    }
}
