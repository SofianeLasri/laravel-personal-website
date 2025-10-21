<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostDraftsPageController extends Controller
{
    public function listPage(): Response
    {
        $drafts = BlogPostDraft::with([
            'titleTranslationKey.translations',
            'originalBlogPost',
            'category',
            'coverPicture',
        ])->get();

        return Inertia::render('dashboard/blog-posts/ListDrafts', [
            'blogPostDrafts' => $drafts,
        ]);
    }
}
