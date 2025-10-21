<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Inertia\Inertia;
use Inertia\Response;

class BlogCategoriesPageController extends Controller
{
    public function index(): Response
    {
        $categories = BlogCategory::with([
            'nameTranslationKey.translations',
        ])
            ->withCount(['blogPosts', 'blogPostDrafts'])
            ->orderBy('order')
            ->get();

        return Inertia::render('dashboard/blog-categories/List', [
            'blogCategories' => $categories,
        ]);
    }
}
