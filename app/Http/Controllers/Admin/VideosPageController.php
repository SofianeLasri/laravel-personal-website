<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;

class VideosPageController extends Controller
{
    public function __invoke()
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
                        'path_small' => $video->coverPicture->path_small,
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
            ->with(['blogContent.blogPostDraft'])
            ->get();

        foreach ($blogPostUsages as $blogContentVideo) {
            $blogContent = $blogContentVideo->blogContent;
            if ($blogContent && $blogContent->blogPostDraft) {
                $blogPost = $blogContent->blogPostDraft;
                $usages[] = [
                    'id' => $blogPost->id,
                    'type' => 'blog_post',
                    'title' => $blogPost->title,
                    'slug' => $blogPost->slug ?? '',
                    'url' => route('dashboard.blog-posts.edit').'?id='.$blogPost->id,
                ];
            }
        }

        return $usages;
    }
}
