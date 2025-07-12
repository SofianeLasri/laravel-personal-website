<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificationsCareerController extends PublicController
{
    public function __invoke(Request $request, PublicControllersService $publicControllersService): Response
    {
        $careerData = $publicControllersService->getCertificationsCareerData();

        return Inertia::render('public/CertificationsCareer', [
            'socialMediaLinks' => SocialMediaLink::all(),
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
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
