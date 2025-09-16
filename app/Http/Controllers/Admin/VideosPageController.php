<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;

class VideosPageController extends Controller
{
    public function __invoke()
    {
        $videos = Video::with(['cover_picture'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'name' => $video->name,
                    'file_size' => $video->file_size,
                    'created_at' => $video->created_at,
                    'cover_picture' => $video->cover_picture ? [
                        'id' => $video->cover_picture->id,
                        'path_small' => $video->cover_picture->path_small,
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
                'title' => $creation->title,
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
