<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __invoke(string $slug)
    {
        $project = SocialMediaLink::where('slug', $slug)->firstOrFail();

        return Inertia::render('public/Project', [
            'locale' => app()->getLocale(),
            'socialMediaLinks' => SocialMediaLink::all(),
        ]);
    }
}
