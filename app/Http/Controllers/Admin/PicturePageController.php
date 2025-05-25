<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Picture;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PicturePageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = Picture::query()->with(['optimizedPictures']);

        if ($request->filled('search')) {
            $query->where('filename', 'like', '%'.$request->input('search').'%');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['filename', 'created_at', 'size', 'width', 'height'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $pictures = $query->withCount('optimizedPictures')->paginate(24);

        return Inertia::render('dashboard/pictures/List', [
            'pictures' => $pictures,
            'filters' => [
                'search' => $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }
}
