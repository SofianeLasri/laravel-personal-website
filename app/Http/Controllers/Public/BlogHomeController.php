<?php

namespace App\Http\Controllers\Public;

use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogHomeController extends PublicController
{
    public function __invoke(Request $request, PublicControllersService $publicService): Response
    {
        $blogPosts = $publicService->getBlogPostsForPublicHome();

        // Return 404 if no blog posts exist
        if ($blogPosts->isEmpty()) {
            abort(404, 'No blog posts found');
        }

        $heroPost = $blogPosts->first();
        $recentPosts = $blogPosts->skip(1)->take(4);
        $hasMultiplePosts = $blogPosts->count() > 1;

        return Inertia::render('public/BlogHome', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'about' => __('about'),
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
                'projects' => [
                    'types' => __('projects.types'),
                ],
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'heroPost' => $publicService->formatBlogPostForSSRHero($heroPost),
            'recentPosts' => $recentPosts->map(fn ($post) => $publicService->formatBlogPostForSSRShort($post))->values()->toArray(),
            'hasMultiplePosts' => $hasMultiplePosts,
        ]);
    }
}
