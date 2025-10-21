<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostsPageController extends Controller
{
    public function listPage(): Response
    {
        $articles = BlogPost::with([
            'titleTranslationKey.translations',
            'category',
            'coverPicture',
            'drafts',
        ])->get();

        return Inertia::render('dashboard/blog-posts/List', [
            'blogPosts' => $articles,
        ]);
    }
}
