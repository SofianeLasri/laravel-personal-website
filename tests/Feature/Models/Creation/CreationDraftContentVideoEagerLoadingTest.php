<?php

declare(strict_types=1);

namespace Tests\Feature\Models\Creation;

use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreationDraftContentVideoEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_loads_video_relation_when_creating_draft_from_creation_with_video_content(): void
    {
        $user = User::factory()->create();
        $creation = Creation::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        // Visit the edit page with creation-id parameter to create draft
        $response = $this->actingAs($user)
            ->get(route('dashboard.creations.edit', ['creation-id' => $creation->id]));

        $response->assertStatus(200);

        // Verify that the video relation is loaded in the Inertia props
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/creations/EditPage')
            ->has('creationDraft.contents', 1)
            ->where('creationDraft.contents.0.content_type', ContentVideo::class)
            ->has('creationDraft.contents.0.content.video', fn (Assert $video) => $video
                ->has('id')
                ->has('bunny_video_id')
                ->etc()
            )
        );
    }

    #[Test]
    public function it_loads_video_relation_when_loading_existing_draft_with_video_content(): void
    {
        $user = User::factory()->create();
        $creation = Creation::factory()->create();
        $video = Video::factory()->create();

        // Create a draft from a creation with video content
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        // Now visit the edit page with the draft-id
        $response = $this->actingAs($user)
            ->get(route('dashboard.creations.edit', ['draft-id' => $draft->id]));

        $response->assertStatus(200);

        // Verify that the video relation is loaded in the Inertia props
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/creations/EditPage')
            ->has('creationDraft.contents', 1)
            ->where('creationDraft.contents.0.content_type', ContentVideo::class)
            ->has('creationDraft.contents.0.content.video', fn (Assert $video) => $video
                ->has('id')
                ->has('bunny_video_id')
                ->etc()
            )
        );
    }

    #[Test]
    public function it_preserves_video_reference_when_copying_video_content_blocks(): void
    {
        $creation = Creation::factory()->create();
        $video = Video::factory()->create([
            'bunny_video_id' => 'test-video-123',
        ]);

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);
        $draft->load('contents.content.video');

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();

        $this->assertInstanceOf(ContentVideo::class, $draftContent->content);
        $this->assertEquals($video->id, $draftContent->content->video_id);

        // Verify the video relation is loaded and correct
        $this->assertNotNull($draftContent->content->video);
        $this->assertEquals('test-video-123', $draftContent->content->video->bunny_video_id);
    }
}
