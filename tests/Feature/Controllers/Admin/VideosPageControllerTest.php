<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\VideosPageController;
use App\Models\BlogContentVideo;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\Creation;
use App\Models\Picture;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(VideosPageController::class)]
class VideosPageControllerTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    public function test_invoke_returns_videos_index_view()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index'));
    }

    public function test_invoke_requires_authentication()
    {
        $response = $this->get('/dashboard/videos');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_invoke_returns_empty_videos_list_when_no_videos_exist()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 0)
        );
    }

    public function test_invoke_returns_videos_with_cover_pictures()
    {
        $this->loginAsAdmin();

        $coverPicture = Picture::factory()->create();
        $video = Video::factory()->create([
            'name' => 'Test Video',
            'cover_picture_id' => $coverPicture->id,
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0', fn ($videoData) => $videoData->where('id', $video->id)
                ->where('name', 'Test Video')
                ->has('cover_picture', fn ($cover) => $cover->where('id', $coverPicture->id)
                    ->has('path_small')
                )
                ->has('created_at')
                ->has('usages')
            )
        );
    }

    public function test_invoke_returns_videos_without_cover_pictures()
    {
        $this->loginAsAdmin();

        $video = Video::factory()->create([
            'name' => 'Video Without Cover',
            'cover_picture_id' => null,
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0', fn ($videoData) => $videoData->where('id', $video->id)
                ->where('name', 'Video Without Cover')
                ->where('cover_picture', null)
                ->has('created_at')
                ->has('usages')
            )
        );
    }

    public function test_invoke_orders_videos_by_created_at_desc()
    {
        $this->loginAsAdmin();

        $olderVideo = Video::factory()->create([
            'name' => 'Older Video',
            'created_at' => now()->subDays(2),
        ]);

        $newerVideo = Video::factory()->create([
            'name' => 'Newer Video',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 2)
            ->has('videos.0', fn ($videoData) => $videoData->where('name', 'Newer Video')
                ->has('id')
                ->has('created_at')
                ->has('cover_picture')
                ->has('usages')
            )
            ->has('videos.1', fn ($videoData) => $videoData->where('name', 'Older Video')
                ->has('id')
                ->has('created_at')
                ->has('cover_picture')
                ->has('usages')
            )
        );
    }

    public function test_get_video_usages_returns_creation_usages()
    {
        $this->loginAsAdmin();

        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'slug' => 'test-creation',
        ]);

        $video = Video::factory()->create();
        $video->creations()->attach($creation);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 1)
            ->has('videos.0.usages.0', fn ($usage) => $usage->where('id', $creation->id)
                ->where('type', 'creation')
                ->where('title', 'Test Creation')
                ->where('slug', 'test-creation')
                ->has('url')
            )
        );
    }

    public function test_get_video_usages_returns_blog_post_usages()
    {
        $this->loginAsAdmin();

        $blogPostDraft = BlogPostDraft::factory()->create([
            'slug' => 'test-blog-post',
        ]);

        $blogContentVideo = BlogContentVideo::factory()->create();
        $video = $blogContentVideo->video;

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $blogPostDraft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $blogContentVideo->id,
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 1)
            ->has('videos.0.usages.0', fn ($usage) => $usage->where('id', $blogPostDraft->id)
                ->where('type', 'blog_post')
                ->has('title')
                ->where('slug', 'test-blog-post')
                ->has('url')
            )
        );
    }

    public function test_get_video_usages_returns_multiple_usages()
    {
        $this->loginAsAdmin();

        // Create creation usage
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'slug' => 'test-creation',
        ]);

        // Create blog post usage
        $blogPostDraft = BlogPostDraft::factory()->create([
            'slug' => 'test-blog-post',
        ]);

        $blogContentVideo = BlogContentVideo::factory()->create();
        $video = $blogContentVideo->video;

        // Link video to creation
        $video->creations()->attach($creation);

        // Link video to blog post
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $blogPostDraft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $blogContentVideo->id,
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 2)
        );
    }

    public function test_get_video_usages_returns_empty_array_when_no_usages()
    {
        $this->loginAsAdmin();

        $video = Video::factory()->create();

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 0)
        );
    }

    public function test_get_video_usages_generates_correct_creation_url()
    {
        $this->loginAsAdmin();

        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'slug' => 'test-creation',
        ]);

        $video = Video::factory()->create();
        $video->creations()->attach($creation);

        $expectedUrl = route('dashboard.creations.edit').'?id='.$creation->id;

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 1)
            ->has('videos.0.usages.0', fn ($usage) => $usage->where('url', $expectedUrl)
                ->where('type', 'creation')
                ->has('id')
                ->has('title')
                ->has('slug')
            )
        );
    }

    public function test_get_video_usages_generates_correct_blog_post_url()
    {
        $this->loginAsAdmin();

        $blogPostDraft = BlogPostDraft::factory()->create([
            'slug' => 'test-blog-post',
        ]);

        $blogContentVideo = BlogContentVideo::factory()->create();
        $video = $blogContentVideo->video;

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $blogPostDraft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $blogContentVideo->id,
        ]);

        $expectedUrl = route('dashboard.blog-posts.edit').'?id='.$blogPostDraft->id;

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 1)
            ->has('videos.0.usages.0', fn ($usage) => $usage->where('url', $expectedUrl)
                ->where('type', 'blog_post')
                ->has('id')
                ->has('title')
                ->has('slug')
            )
        );
    }

    public function test_get_video_usages_handles_blog_post_with_empty_slug()
    {
        $this->loginAsAdmin();

        $blogPostDraft = BlogPostDraft::factory()->create([
            'slug' => '',
        ]);

        $blogContentVideo = BlogContentVideo::factory()->create();
        $video = $blogContentVideo->video;

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $blogPostDraft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $blogContentVideo->id,
        ]);

        $response = $this->get('/dashboard/videos');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/videos/Index')
            ->has('videos', 1)
            ->has('videos.0.usages', 1)
            ->has('videos.0.usages.0', fn ($usage) => $usage->where('slug', '')
                ->has('id')
                ->has('type')
                ->has('title')
                ->has('url')
            )
        );
    }
}
