<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Inertia\Response;

class VideosPageController extends Controller
{
    public function __invoke(): Response
    {
        $videos = Video::with(['coverPicture'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'name' => $video->name,
                    'created_at' => $video->created_at,
                    'cover_picture' => $video->coverPicture ? [
                        'id' => $video->coverPicture->id,
                        'path_original' => $video->coverPicture->path_original,
                    ] : null,
                    'usages' => $this->getVideoUsages($video),
                ];
            });

        return inertia('dashboard/videos/Index', [
            'videos' => $videos,
        ]);
    }

    private function getVideoUsages(Video $video): array
    {
        $usages = [];

        // Check usage in creations
        $creationUsages = $video->creations()->get();
        foreach ($creationUsages as $creation) {
            $usages[] = [
                'id' => $creation->id,
                'type' => 'creation',
                'title' => $creation->name,
                'slug' => $creation->slug,
                'url' => route('dashboard.creations.edit').'?id='.$creation->id,
            ];
        }

        // Check usage in blog posts
        $blogPostUsages = $video->blogContentVideos()
            ->with(['blogContent.blogPostDraft.titleTranslationKey.translations'])
            ->get();

        foreach ($blogPostUsages as $blogContentVideo) {
            $blogContent = $blogContentVideo->blogContent;
            if ($blogContent && $blogContent->blogPostDraft) {
                $blogPost = $blogContent->blogPostDraft;

                // Extract title from translation (prefer French, fallback to first available)
                $translationKey = $blogPost->titleTranslationKey;
                $title = 'Sans titre';

                if ($translationKey && $translationKey->translations) {
                    $frenchTranslation = $translationKey->translations->firstWhere('locale', 'fr');
                    if ($frenchTranslation) {
                        $title = $frenchTranslation->text;
                    } else {
                        $firstTranslation = $translationKey->translations->first();
                        if ($firstTranslation) {
                            $title = $firstTranslation->text;
                        }
                    }
                }

                $usages[] = [
                    'id' => $blogPost->id,
                    'type' => 'blog_post',
                    'title' => $title,
                    'slug' => $blogPost->slug ?? '',
                    'url' => route('dashboard.blog-posts.edit').'?id='.$blogPost->id,
                ];
            }
        }

        return $usages;
    }
}
