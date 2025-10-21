<?php

namespace App\Http\Controllers\Public;

use App\Models\BlogPostPreviewToken;
use App\Models\SocialMediaLink;
use App\Services\PublicControllersService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostPreviewController extends PublicController
{
    /**
     * Display a blog post draft preview using a temporary token
     */
    public function __invoke(Request $request, string $token, PublicControllersService $publicService): Response
    {
        // Find the preview token
        $previewToken = BlogPostPreviewToken::where('token', $token)
            ->with('blogPostDraft')
            ->first();

        // Check if token exists
        if (! $previewToken) {
            abort(404, 'Lien de prévisualisation non trouvé');
        }

        // Check if token is expired
        if ($previewToken->isExpired()) {
            abort(404, 'Ce lien de prévisualisation a expiré');
        }

        // Get the draft and format it for preview
        $draft = $previewToken->blogPostDraft;
        $blogPost = $publicService->getBlogPostDraftForPreview($draft);

        return Inertia::render('public/BlogPost', [
            'locale' => app()->getLocale(),
            'browserLanguage' => $this->getBrowserLanguage($request),
            'translations' => [
                'navigation' => __('navigation'),
                'footer' => __('footer'),
                'search' => __('search'),
                'blog' => __('blog'),
            ],
            'socialMediaLinks' => SocialMediaLink::all(),
            'blogPost' => $blogPost,
        ]);
    }
}
