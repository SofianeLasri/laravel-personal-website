<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;

class CertificationsCareerController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('public/CertificationsCareer', [
            'socialMediaLinks' => SocialMediaLink::all(),
            'locale' => app()->getLocale(),
            'translations' => [
                'navigation' => __('navigation'),
                'footer' => __('footer'),
            ],
        ]);
    }
}
