<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Picture;
use Inertia\Inertia;
use Inertia\Response;

class CertificationPageController extends Controller
{
    public function listPage(): Response
    {
        $certifications = Certification::with('picture')
            ->orderBy('date', 'desc')
            ->get();

        return Inertia::render('dashboard/certifications/List', [
            'certifications' => $certifications,
        ]);
    }

    public function editPage(?int $id = null): Response
    {
        $certification = null;

        if ($id) {
            $certification = Certification::with('picture')->find($id);
        }

        $pictures = Picture::all();

        return Inertia::render('dashboard/certifications/Edit', [
            'certification' => $certification,
            'pictures' => $pictures,
        ]);
    }
}
