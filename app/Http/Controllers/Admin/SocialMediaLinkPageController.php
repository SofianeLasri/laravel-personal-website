<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;

class SocialMediaLinkPageController extends Controller
{
    public function __invoke()
    {
        $socialMediaLinks = SocialMediaLink::all();

        return Inertia::render('dashboard/social-links/List', [
            'socialMediaLinks' => $socialMediaLinks,
        ]);
    }
}
