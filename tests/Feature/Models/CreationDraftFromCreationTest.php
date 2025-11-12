<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreationDraftFromCreationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_copies_markdown_content_blocks_when_creating_draft_from_creation(): void
    {
        $creation = Creation::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        $this->assertCount(1, $draft->contents);

        $draftContent = $draft->contents->first();
        $this->assertEquals(ContentMarkdown::class, $draftContent->content_type);
        $this->assertEquals(1, $draftContent->order);

        // Verify content was duplicated (different IDs)
        $this->assertNotEquals($markdown->id, $draftContent->content_id);

        // Verify translation key was duplicated
        $this->assertInstanceOf(ContentMarkdown::class, $draftContent->content);
        $this->assertNotEquals($translationKey->id, $draftContent->content->translation_key_id);
        $this->assertNotNull($draftContent->content->translationKey);
    }

    #[Test]
    public function it_copies_gallery_content_blocks_when_creating_draft_from_creation(): void
    {
        $creation = Creation::factory()->create();
        $gallery = ContentGallery::factory()->create(['layout' => 'grid', 'columns' => 3]);
        $pictures = Picture::factory()->count(2)->create();

        $pictureData = [];
        foreach ($pictures as $index => $picture) {
            $pictureData[$picture->id] = [
                'order' => $index + 1,
                'caption_translation_key_id' => TranslationKey::factory()->create()->id,
            ];
        }
        $gallery->pictures()->attach($pictureData);

        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        $this->assertCount(1, $draft->contents);

        $draftContent = $draft->contents->first();
        $this->assertEquals(ContentGallery::class, $draftContent->content_type);
        $this->assertEquals(1, $draftContent->order);

        // Verify gallery was duplicated
        $this->assertNotEquals($gallery->id, $draftContent->content_id);

        // Verify gallery properties were copied
        $this->assertInstanceOf(ContentGallery::class, $draftContent->content);
        $this->assertEquals('grid', $draftContent->content->layout);
        $this->assertEquals(3, $draftContent->content->columns);
        $this->assertCount(2, $draftContent->content->pictures);
    }

    #[Test]
    public function it_copies_video_content_blocks_when_creating_draft_from_creation(): void
    {
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

        $draft = CreationDraft::fromCreation($creation);

        $this->assertCount(1, $draft->contents);

        $draftContent = $draft->contents->first();
        $this->assertEquals(ContentVideo::class, $draftContent->content_type);
        $this->assertEquals(1, $draftContent->order);

        // Verify video content was duplicated
        $this->assertNotEquals($videoContent->id, $draftContent->content_id);

        // Verify video content properties
        $this->assertInstanceOf(ContentVideo::class, $draftContent->content);
        $this->assertEquals($video->id, $draftContent->content->video_id);

        // Caption translation key should be duplicated
        $this->assertNotEquals($captionKey->id, $draftContent->content->caption_translation_key_id);
        $this->assertNotNull($draftContent->content->captionTranslationKey);
    }

    #[Test]
    public function it_copies_multiple_content_blocks_in_correct_order(): void
    {
        $creation = Creation::factory()->create();

        // Create markdown content
        $markdown = ContentMarkdown::factory()->create();
        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        // Create gallery content
        $gallery = ContentGallery::factory()->create();
        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        // Create video content
        $videoContent = ContentVideo::factory()->create();
        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 3,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        $this->assertCount(3, $draft->contents);

        $contents = $draft->contents->sortBy('order')->values();

        // Verify order and types
        $this->assertEquals(ContentMarkdown::class, $contents[0]->content_type);
        $this->assertEquals(1, $contents[0]->order);

        $this->assertEquals(ContentGallery::class, $contents[1]->content_type);
        $this->assertEquals(2, $contents[1]->order);

        $this->assertEquals(ContentVideo::class, $contents[2]->content_type);
        $this->assertEquals(3, $contents[2]->order);

        // Verify all were duplicated (different IDs)
        $this->assertNotEquals($markdown->id, $contents[0]->content_id);
        $this->assertNotEquals($gallery->id, $contents[1]->content_id);
        $this->assertNotEquals($videoContent->id, $contents[2]->content_id);
    }

    #[Test]
    public function it_handles_creation_with_no_content_blocks(): void
    {
        $creation = Creation::factory()->create();
        // No content blocks added

        $draft = CreationDraft::fromCreation($creation);

        $this->assertInstanceOf(CreationDraft::class, $draft);
        $this->assertCount(0, $draft->contents);
    }

    #[Test]
    public function it_creates_independent_content_blocks_not_shared_references(): void
    {
        $creation = Creation::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creationContent = $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        $draftContent = $draft->contents->first();

        // Verify content blocks are independent (different models/tables)
        $this->assertInstanceOf(\App\Models\CreationContent::class, $creationContent);
        $this->assertInstanceOf(\App\Models\CreationDraftContent::class, $draftContent);
        $this->assertNotEquals($markdown->id, $draftContent->content_id);

        // Verify translation keys are independent
        $this->assertNotEquals($translationKey->id, $draftContent->content->translation_key_id);

        // Verify original content still exists in database
        $this->assertDatabaseHas('creation_contents', [
            'id' => $creationContent->id,
            'creation_id' => $creation->id,
        ]);

        // Verify new draft content exists
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $draftContent->id,
            'creation_draft_id' => $draft->id,
        ]);
    }

    #[Test]
    public function it_copies_complex_creation_with_all_content_types(): void
    {
        $creation = Creation::factory()->create();

        // Markdown content
        $markdown1 = ContentMarkdown::factory()->create();
        $markdown2 = ContentMarkdown::factory()->create();

        // Gallery with pictures
        $gallery = ContentGallery::factory()->create(['layout' => 'carousel']);
        $pictures = Picture::factory()->count(3)->create();
        $gallery->pictures()->attach($pictures->pluck('id')->toArray());

        // Video with caption
        $video = Video::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => TranslationKey::factory()->create()->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);

        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 3,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 4,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        $this->assertCount(4, $draft->contents);

        $contents = $draft->contents->sortBy('order')->values();

        // Verify all content types and order
        $this->assertEquals(ContentMarkdown::class, $contents[0]->content_type);
        $this->assertEquals(1, $contents[0]->order);
        $this->assertNotEquals($markdown1->id, $contents[0]->content_id);

        $this->assertEquals(ContentGallery::class, $contents[1]->content_type);
        $this->assertEquals(2, $contents[1]->order);
        $this->assertEquals('carousel', $contents[1]->content->layout);
        $this->assertCount(3, $contents[1]->content->pictures);

        $this->assertEquals(ContentMarkdown::class, $contents[2]->content_type);
        $this->assertEquals(3, $contents[2]->order);
        $this->assertNotEquals($markdown2->id, $contents[2]->content_id);

        $this->assertEquals(ContentVideo::class, $contents[3]->content_type);
        $this->assertEquals(4, $contents[3]->order);
        $this->assertEquals($video->id, $contents[3]->content->video_id);
    }

    #[Test]
    public function it_copies_content_blocks_along_with_features_and_screenshots(): void
    {
        $creation = Creation::factory()
            ->withFeatures(2)
            ->withScreenshots(2)
            ->create();

        $markdown = ContentMarkdown::factory()->create();
        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);

        // Verify features were copied
        $this->assertCount(2, $draft->features);

        // Verify screenshots were copied
        $this->assertCount(2, $draft->screenshots);

        // Verify content blocks were also copied
        $this->assertCount(1, $draft->contents);
        $this->assertEquals(ContentMarkdown::class, $draft->contents->first()->content_type);
    }
}
