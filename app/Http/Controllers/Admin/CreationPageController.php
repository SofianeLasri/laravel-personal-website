<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CreationPageController extends Controller
{
    public function listPage(): Response
    {
        return Inertia::render('dashboard/creations/List');
    }
}
