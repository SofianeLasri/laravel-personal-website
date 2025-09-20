<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostsController extends PublicController
{
    public function __invoke(Request $request, PublicControllersService $publicService): Response
    {
        $perPage = 12;

        // Get filters from request (handle comma-separated values)
        $categoryFilter = $request->get('category');

        $filters = [
            'category' => $categoryFilter ? explode(',', $categoryFilter) : [],
            'sort' => $request->get('sort', 'newest'),
        ];

        // Get paginated blog posts with filters
        $posts = $publicService->getBlogPostsForIndex($filters, $perPage);

        // Get all categories with post counts
        $categories = $publicService->getBlogCategoriesWithCounts();

        return Inertia::render('public/BlogPosts', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
                'blog' => __('blog'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'posts' => $posts,
            'categories' => $categories,
            'currentFilters' => [
                'category' => $categoryFilter ? explode(',', $categoryFilter) : [],
                'sort' => $request->get('sort', 'newest'),
            ],
        ]);
    }
}
