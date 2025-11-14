<?php

declare(strict_types=1);

namespace Tests\Browser\Dashboard;

use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

class CreationDraftVideoContentBlockTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_copies_video_content_blocks_when_creating_draft_from_creation(): void
    {
        // Create a creation with a video content block
        $creation = Creation::factory()->create([
            'name' => 'Creation With Video',
        ]);

        $video = Video::factory()->create([
            'name' => 'Test Video',
            'bunny_video_id' => 'test-video-123',
        ]);

        $captionKey = TranslationKey::factory()->withTranslations([
            'en' => 'This is the video caption in English.',
            'fr' => 'Ceci est la légende de la vidéo en français.',
        ])->create();

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($creation, $video) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?creation-id='.$creation->id)
                ->waitForText('Vidéo') // Wait for the Video content block label
                ->pause(3000); // Wait for content to load

            // Verify in database that a draft was created with video content blocks
            $draft = CreationDraft::where('original_creation_id', $creation->id)->first();
            $this->assertNotNull($draft);
            $this->assertCount(1, $draft->contents);

            $draftContent = $draft->contents->first();
            $this->assertEquals(ContentVideo::class, $draftContent->content_type);

            // Verify the video is preserved
            $this->assertInstanceOf(ContentVideo::class, $draftContent->content);
            $this->assertEquals($video->id, $draftContent->content->video_id);

            // Load the video relation and verify it's correct
            $draftContent->content->load('video');
            $this->assertNotNull($draftContent->content->video);
            $this->assertEquals('test-video-123', $draftContent->content->video->bunny_video_id);
        });
    }

    #[Test]
    public function it_preserves_video_content_blocks_when_loading_existing_draft(): void
    {
        // Create a draft directly with a video content block
        $video = Video::factory()->create([
            'name' => 'Existing Video',
            'bunny_video_id' => 'existing-video-456',
        ]);

        $captionKey = TranslationKey::factory()->withTranslations([
            'en' => 'Existing video caption.',
            'fr' => 'Légende vidéo existante.',
        ])->create();

        $draft = CreationDraft::factory()->create([
            'name' => 'Draft With Existing Video',
        ]);

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $this->assertCount(1, $draft->contents);
        $originalContentId = $draft->contents->first()->content_id;

        $this->browse(function (Browser $browser) use ($draft, $originalContentId, $video) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?draft-id='.$draft->id)
                ->waitForText('Vidéo') // Wait for the Video content block label
                ->pause(3000); // Wait for content to load

            $draft->refresh();

            // Should not have created additional content blocks
            $this->assertCount(1, $draft->contents);
            $this->assertEquals($originalContentId, $draft->contents->first()->content_id);

            // Verify the video is still correctly referenced
            $this->assertEquals($video->id, $draft->contents->first()->content->video_id);
        });
    }

    #[Test]
    public function it_displays_video_content_along_with_other_content_types(): void
    {
        // Create a creation with multiple content types including video
        $creation = Creation::factory()->create([
            'name' => 'Creation With Mixed Content',
        ]);

        // Create markdown content
        $markdownKey = TranslationKey::factory()->withTranslations([
            'en' => 'Introduction markdown content.',
            'fr' => 'Contenu markdown d\'introduction.',
        ])->create();

        $markdown = \App\Models\ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => \App\Models\ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        // Create video content
        $video = Video::factory()->create([
            'name' => 'Tutorial Video',
            'bunny_video_id' => 'tutorial-789',
        ]);

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 2,
        ]);

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->loginAs($this->user)
                ->visit('/dashboard/creations/edit?creation-id='.$creation->id)
                ->waitForText('Markdown') // Wait for markdown block
                ->waitForText('Vidéo') // Wait for video block
                ->pause(3000);

            // Verify in database that draft has both content blocks
            $draft = CreationDraft::where('original_creation_id', $creation->id)->first();
            $this->assertNotNull($draft);
            $this->assertCount(2, $draft->contents);

            $contents = $draft->contents->sortBy('order')->values();
            $this->assertEquals(\App\Models\ContentMarkdown::class, $contents[0]->content_type);
            $this->assertEquals(1, $contents[0]->order);

            $this->assertEquals(ContentVideo::class, $contents[1]->content_type);
            $this->assertEquals(2, $contents[1]->order);
        });
    }
}
