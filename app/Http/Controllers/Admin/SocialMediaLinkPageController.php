<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;
use Inertia\Response;

class SocialMediaLinkPageController extends Controller
{
    public function __invoke(): Response
    {
        $socialMediaLinks = SocialMediaLink::all();

        return Inertia::render('dashboard/social-links/List', [
            'socialMediaLinks' => $socialMediaLinks,
        ]);
    }
}
