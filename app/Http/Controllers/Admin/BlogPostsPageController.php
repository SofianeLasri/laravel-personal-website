<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Inertia\Inertia;

class BlogPostsPageController extends Controller
{
    public function listPage()
    {
        $articles = BlogPost::all()->withRelationshipAutoloading();

        return Inertia::render('dashboard/blog-posts/List', [
            'blogPosts' => $articles,
        ]);
    }
}
