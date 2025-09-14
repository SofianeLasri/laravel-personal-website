<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Inertia\Inertia;

class ArticlesPageController extends Controller
{
    public function listPage()
    {
        $articles = BlogPost::all()->load('titleTranslationKey.translations');

        return Inertia::render('dashboard/blog-posts/List', [
            'blogPosts' => $articles,
        ]);
    }
}
