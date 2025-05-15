<?php

namespace App\Http\Controllers;

use App\Models\Creation;

class TestController extends Controller
{
    public function __invoke()
    {
        $creations = Creation::where('type', 'website')
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('test', [
            'creations' => $creations,
        ]);
    }
}
