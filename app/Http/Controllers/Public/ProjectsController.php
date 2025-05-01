<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;

class ProjectsController extends Controller
{
    public function __invoke()
    {
        return inertia('public/Projects', [
            'locale' => app()->getLocale(),
            'translations' => [
                'projects' => __('projects'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
        ]);
    }
}
