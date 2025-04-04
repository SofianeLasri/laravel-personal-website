<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creation;
use Inertia\Inertia;
use Inertia\Response;

class CreationPageController extends Controller
{
    public function listPage(): Response
    {
        $creations = Creation::all()->load(['shortDescriptionTranslationKey.translations', 'fullDescriptionTranslationKey.translations']);

        return Inertia::render('dashboard/creations/List', [
            'creations' => $creations,
        ]);
    }
}
