<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $socialMediaLinks = SocialMediaLink::all();

        return Inertia::render('public/Home', [
            'socialMediaLinks' => $socialMediaLinks,
        ]);
    }
}
