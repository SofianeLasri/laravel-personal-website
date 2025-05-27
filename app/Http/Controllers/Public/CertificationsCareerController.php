<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Inertia\Inertia;

class CertificationsCareerController extends Controller
{
    public function __invoke(PublicControllersService $publicControllersService)
    {
        $careerData = $publicControllersService->getCertificationsCareerData();

        return Inertia::render('public/CertificationsCareer', [
            'socialMediaLinks' => SocialMediaLink::all(),
            'locale' => app()->getLocale(),
            'translations' => [
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'career' => __('career'),
                'search' => __('search'),
            ],
            'certifications' => $careerData['certifications'],
            'educationExperiences' => $careerData['educationExperiences'],
            'workExperiences' => $careerData['workExperiences'],
        ]);
    }
}
